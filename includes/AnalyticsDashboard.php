<?php
/**
 * Analytics Dashboard Helper Class
 * 
 * Provides utility methods for generating analytics reports and dashboard data
 * Used by frontend components to format and aggregate analytics data
 */

require_once __DIR__ . '/Database.php';

class AnalyticsDashboard {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Generate comprehensive dashboard data for a client
     */
    public function getDashboardData($clientId, $period = '30d', $platform = 'all') {
        $dateRange = $this->parsePeriod($period);
        
        return [
            'overview' => $this->getOverviewMetrics($clientId, $platform, $dateRange),
            'growth_trend' => $this->getGrowthTrend($clientId, $platform, $dateRange),
            'top_posts' => $this->getTopPosts($clientId, $platform, $dateRange, 5),
            'platform_breakdown' => $this->getPlatformBreakdown($clientId, $dateRange),
            'engagement_timeline' => $this->getEngagementTimeline($clientId, $platform, $dateRange),
            'best_posting_times' => $this->getBestPostingTimes($clientId, $platform),
            'content_performance' => $this->getContentPerformance($clientId, $platform, $dateRange),
            'recent_activity' => $this->getRecentActivity($clientId, 10)
        ];
    }
    
    /**
     * Get overview metrics with comparison to previous period
     */
    public function getOverviewMetrics($clientId, $platform, $dateRange) {
        // Current period metrics
        $current = $this->getMetricsForPeriod($clientId, $platform, $dateRange);
        
        // Previous period for comparison
        $periodDays = (strtotime($dateRange['end']) - strtotime($dateRange['start'])) / 86400;
        $previousRange = [
            'start' => date('Y-m-d', strtotime($dateRange['start']) - $periodDays * 86400),
            'end' => date('Y-m-d', strtotime($dateRange['end']) - $periodDays * 86400)
        ];
        $previous = $this->getMetricsForPeriod($clientId, $platform, $previousRange);
        
        // Calculate changes
        $metrics = [];
        foreach ($current as $key => $value) {
            $previousValue = $previous[$key] ?? 0;
            $change = $previousValue > 0 ? (($value - $previousValue) / $previousValue) * 100 : 0;
            
            $metrics[$key] = [
                'current' => $value,
                'previous' => $previousValue,
                'change' => round($change, 1),
                'trend' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'flat')
            ];
        }
        
        return $metrics;
    }
    
    /**
     * Get metrics for a specific period
     */
    private function getMetricsForPeriod($clientId, $platform, $dateRange) {
        $platformCondition = $platform !== 'all' ? "AND a.platform = ?" : "";
        $params = [$clientId, $dateRange['start'], $dateRange['end']];
        if ($platform !== 'all') {
            $params[] = $platform;
        }
        
        // Post metrics
        $sql = "
            SELECT 
                COUNT(DISTINCT pa.post_id) as posts,
                COALESCE(SUM(pa.impressions), 0) as impressions,
                COALESCE(SUM(pa.reach), 0) as reach,
                COALESCE(SUM(pa.likes + pa.comments + pa.shares + pa.saves), 0) as engagement,
                COALESCE(AVG(pa.engagement_rate), 0) as engagement_rate,
                COALESCE(SUM(pa.clicks), 0) as clicks,
                COALESCE(SUM(pa.video_views), 0) as video_views
            FROM post_analytics pa
            JOIN posts p ON pa.post_id = p.id
            WHERE p.client_id = ?
            AND p.published_at >= ?
            AND p.published_at <= ?
            AND p.status = 'published'
            {$platformCondition}
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $postMetrics = $stmt->fetch();
        
        // Follower metrics
        $followerSql = "
            SELECT 
                COALESCE(SUM(fa.daily_growth), 0) as follower_growth,
                COALESCE(SUM(fa.daily_growth), 0) as new_followers,
                0 as unfollows
            FROM follower_analytics fa
            JOIN accounts a ON fa.account_id = a.id
            WHERE a.client_id = ?
            AND fa.recorded_date >= ?
            AND fa.recorded_date <= ?
            {$platformCondition}
        ";
        
        $stmt = $this->db->prepare($followerSql);
        $stmt->execute($params);
        $followerMetrics = $stmt->fetch();
        
        // Ensure we have arrays to merge
        $postMetrics = $postMetrics ?: [];
        $followerMetrics = $followerMetrics ?: [];
        
        return array_merge($postMetrics, $followerMetrics);
    }
    
    /**
     * Get growth trend data for charts
     */
    public function getGrowthTrend($clientId, $platform, $dateRange) {
        $platformCondition = $platform !== 'all' ? "AND a.platform = ?" : "";
        $params = [$clientId, $dateRange['start'], $dateRange['end']];
        if ($platform !== 'all') {
            $params[] = $platform;
        }
        
        $sql = "
            SELECT 
                fa.recorded_date as date,
                SUM(fa.follower_count) as total_followers,
                SUM(fa.daily_growth) as daily_growth
            FROM follower_analytics fa
            JOIN accounts a ON fa.account_id = a.id
            WHERE a.client_id = ?
            AND fa.recorded_date >= ?
            AND fa.recorded_date <= ?
            {$platformCondition}
            GROUP BY fa.recorded_date
            ORDER BY fa.recorded_date ASC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get top performing posts
     */
    public function getTopPosts($clientId, $platform, $dateRange, $limit = 10) {
        $platformCondition = $platform !== 'all' ? "AND pa.platform = ?" : "";
        $params = [$clientId, $dateRange['start'], $dateRange['end']];
        if ($platform !== 'all') {
            $params[] = $platform;
        }
        $params[] = $limit;
        
        $sql = "
            SELECT 
                p.id,
                p.content,
                p.published_at,
                pa.platform,
                pa.engagement_rate,
                pa.reach,
                pa.impressions,
                pa.likes,
                pa.comments,
                pa.shares,
                pa.saves,
                (pa.likes + pa.comments + pa.shares + pa.saves) as total_engagement,
                SUBSTRING(p.content, 1, 100) as preview
            FROM posts p
            JOIN post_analytics pa ON p.id = pa.post_id
            WHERE p.client_id = ?
            AND p.published_at >= ?
            AND p.published_at <= ?
            AND p.status = 'published'
            {$platformCondition}
            ORDER BY pa.engagement_rate DESC, total_engagement DESC
            LIMIT ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get platform breakdown
     */
    public function getPlatformBreakdown($clientId, $dateRange) {
        $sql = "
            SELECT 
                pa.platform,
                COUNT(DISTINCT pa.post_id) as posts,
                COALESCE(SUM(pa.reach), 0) as reach,
                COALESCE(SUM(pa.impressions), 0) as impressions,
                COALESCE(SUM(pa.likes + pa.comments + pa.shares + pa.saves), 0) as engagement,
                COALESCE(AVG(pa.engagement_rate), 0) as avg_engagement_rate,
                0 as current_followers
            FROM post_analytics pa
            JOIN posts p ON pa.post_id = p.id
            WHERE p.client_id = ?
            AND p.published_at >= ?
            AND p.published_at <= ?
            AND p.status = 'published'
            GROUP BY pa.platform
            ORDER BY avg_engagement_rate DESC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$clientId, $dateRange['start'], $dateRange['end']]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get engagement timeline for charts
     */
    public function getEngagementTimeline($clientId, $platform, $dateRange) {
        $platformCondition = $platform !== 'all' ? "AND pa.platform = ?" : "";
        $params = [$clientId, $dateRange['start'], $dateRange['end']];
        if ($platform !== 'all') {
            $params[] = $platform;
        }
        
        $sql = "
            SELECT 
                DATE(p.published_at) as date,
                COUNT(*) as posts,
                COALESCE(SUM(pa.reach), 0) as reach,
                COALESCE(SUM(pa.impressions), 0) as impressions,
                COALESCE(SUM(pa.likes + pa.comments + pa.shares + pa.saves), 0) as engagement,
                COALESCE(AVG(pa.engagement_rate), 0) as avg_engagement_rate
            FROM posts p
            LEFT JOIN post_analytics pa ON p.id = pa.post_id
            WHERE p.client_id = ?
            AND p.published_at >= ?
            AND p.published_at <= ?
            AND p.status = 'published'
            {$platformCondition}
            GROUP BY DATE(p.published_at)
            ORDER BY date ASC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get best posting times heatmap data
     */
    public function getBestPostingTimes($clientId, $platform) {
        $platformCondition = $platform !== 'all' ? "AND pta.platform = ?" : "";
        $params = [$clientId];
        if ($platform !== 'all') {
            $params[] = $platform;
        }
        
        $sql = "
            SELECT 
                pta.day_of_week,
                pta.hour as hour_of_day,
                pta.avg_engagement_rate,
                pta.post_count
            FROM posting_time_analytics pta
            WHERE pta.client_id = ?
            {$platformCondition}
            AND pta.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND pta.post_count >= 2
            ORDER BY pta.day_of_week, pta.hour
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        $data = $stmt->fetchAll();
        
        // Format as heatmap matrix
        $heatmap = [];
        for ($day = 0; $day < 7; $day++) {
            for ($hour = 0; $hour < 24; $hour++) {
                $heatmap[$day][$hour] = [
                    'engagement_rate' => 0,
                    'post_count' => 0
                ];
            }
        }
        
        foreach ($data as $point) {
            $heatmap[$point['day_of_week']][$point['hour_of_day']] = [
                'engagement_rate' => $point['avg_engagement_rate'],
                'post_count' => $point['post_count']
            ];
        }
        
        return $heatmap;
    }
    
    /**
     * Get content type performance
     */
    public function getContentPerformance($clientId, $platform, $dateRange) {
        $platformCondition = $platform !== 'all' ? "AND cta.platform = ?" : "";
        $params = [$clientId];
        if ($platform !== 'all') {
            $params[] = $platform;
        }
        
        $sql = "
            SELECT 
                cta.content_type,
                cta.post_count,
                cta.avg_engagement_rate,
                cta.avg_reach,
                cta.avg_impressions
            FROM content_type_analytics cta
            WHERE cta.client_id = ?
            {$platformCondition}
            AND cta.updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ORDER BY cta.avg_engagement_rate DESC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get recent activity/notifications
     */
    public function getRecentActivity($clientId, $limit = 10) {
        $sql = "
            SELECT 
                'post_published' as type,
                p.id as item_id,
                p.content as message,
                p.published_at as timestamp,
                a.platform
            FROM posts p
            JOIN accounts a ON JSON_EXTRACT(p.platforms_json, CONCAT('$.', a.platform)) IS NOT NULL
            WHERE p.client_id = ?
            AND p.status = 'published'
            AND p.published_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            
            UNION ALL
            
            SELECT 
                'analytics_milestone' as type,
                pa.post_id as item_id,
                CONCAT('Post reached ', FORMAT(pa.reach, 0), ' people') as message,
                pa.updated_at as timestamp,
                pa.platform
            FROM post_analytics pa
            JOIN posts p ON pa.post_id = p.id
            WHERE p.client_id = ?
            AND pa.reach >= 1000
            AND pa.updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            
            ORDER BY timestamp DESC
            LIMIT ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$clientId, $clientId, $limit]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Parse period string into date range
     */
    private function parsePeriod($period) {
        // Check if it's a custom date range
        if (strpos($period, ':') !== false) {
            list($startDate, $endDate) = explode(':', $period);
            return ['start' => $startDate, 'end' => $endDate];
        }
        
        $endDate = date('Y-m-d');
        
        switch ($period) {
            case '7d':
                $startDate = date('Y-m-d', strtotime('-7 days'));
                break;
            case '30d':
                $startDate = date('Y-m-d', strtotime('-30 days'));
                break;
            case '90d':
                $startDate = date('Y-m-d', strtotime('-90 days'));
                break;
            case '1y':
                $startDate = date('Y-m-d', strtotime('-1 year'));
                break;
            default:
                $startDate = date('Y-m-d', strtotime('-30 days'));
        }
        
        return ['start' => $startDate, 'end' => $endDate];
    }
    
    /**
     * Get analytics summary stats for admin dashboard
     */
    public function getSystemStats() {
        // Total system metrics
        $overview = $this->db->query("
            SELECT 
                COUNT(DISTINCT c.id) as total_clients,
                COUNT(DISTINCT a.id) as total_accounts,
                COUNT(DISTINCT p.id) as total_posts,
                COUNT(DISTINCT pa.id) as total_analytics_records
            FROM clients c
            LEFT JOIN accounts a ON c.id = a.client_id AND a.is_active = 1
            LEFT JOIN posts p ON c.id = p.client_id AND p.status = 'published'
            LEFT JOIN post_analytics pa ON p.id = pa.post_id
        ")->fetch();
        
        // Recent activity stats
        $recent = $this->db->query("
            SELECT 
                COUNT(DISTINCT p.id) as posts_last_24h,
                COUNT(DISTINCT pa.id) as analytics_collected_24h
            FROM posts p
            LEFT JOIN post_analytics pa ON p.id = pa.post_id AND pa.last_updated >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            WHERE p.published_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ")->fetch();
        
        // Platform breakdown
        $platforms = $this->db->query("
            SELECT 
                a.platform,
                COUNT(*) as account_count,
                COUNT(DISTINCT a.client_id) as client_count
            FROM accounts a
            WHERE a.is_active = 1
            GROUP BY a.platform
            ORDER BY account_count DESC
        ")->fetchAll();
        
        return [
            'overview' => $overview,
            'recent' => $recent,
            'platforms' => $platforms,
            'last_updated' => date('c')
        ];
    }
}