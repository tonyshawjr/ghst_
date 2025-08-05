<?php
/**
 * Analytics Data Retrieval API Endpoint
 * 
 * Provides analytics data for dashboards and reporting
 * GET /api/analytics/get.php?type=posts&period=30d&platform=all
 */

require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Auth.php';

// Set content type
header('Content-Type: application/json');

// Check authentication
$auth = new Auth();
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$user = $auth->getUser();

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Parse query parameters
$type = $_GET['type'] ?? 'overview';
$period = $_GET['period'] ?? '30d';
$platform = $_GET['platform'] ?? 'all';
$clientId = $_GET['client_id'] ?? $user['client_id'];

// Validate client access
if ($user['role'] !== 'admin' && $clientId != $user['client_id']) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Parse period into date range
    $dateRange = parsePeriod($period);
    
    // Route to appropriate analytics function
    switch ($type) {
        case 'overview':
            $data = getOverviewAnalytics($db, $clientId, $platform, $dateRange);
            break;
        case 'posts':
            $data = getPostAnalytics($db, $clientId, $platform, $dateRange);
            break;
        case 'followers':
            $data = getFollowerAnalytics($db, $clientId, $platform, $dateRange);
            break;
        case 'engagement':
            $data = getEngagementAnalytics($db, $clientId, $platform, $dateRange);
            break;
        case 'best-times':
            $data = getBestPostingTimes($db, $clientId, $platform);
            break;
        case 'content-types':
            $data = getContentTypeAnalytics($db, $clientId, $platform, $dateRange);
            break;
        case 'hashtags':
            $data = getHashtagAnalytics($db, $clientId, $platform, $dateRange);
            break;
        case 'platform-comparison':
            $data = getPlatformComparison($db, $clientId, $dateRange);
            break;
        case 'activity':
            $data = getRecentActivity($db, $clientId);
            break;
        case 'demographics':
            $data = getDemographicData($db, $clientId, $dateRange);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid analytics type']);
            exit;
    }
    
    echo json_encode([
        'success' => true,
        'data' => $data,
        'meta' => [
            'type' => $type,
            'period' => $period,
            'platform' => $platform,
            'client_id' => $clientId,
            'date_range' => $dateRange,
            'generated_at' => date('c')
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Parse period string into date range
 */
function parsePeriod($period) {
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
            // Try to parse custom format: YYYY-MM-DD:YYYY-MM-DD
            if (preg_match('/^(\d{4}-\d{2}-\d{2}):(\d{4}-\d{2}-\d{2})$/', $period, $matches)) {
                $startDate = $matches[1];
                $endDate = $matches[2];
            } else {
                $startDate = date('Y-m-d', strtotime('-30 days'));
            }
    }
    
    return ['start' => $startDate, 'end' => $endDate];
}

/**
 * Get overview analytics
 */
function getOverviewAnalytics($db, $clientId, $platform, $dateRange) {
    $platformCondition = $platform !== 'all' ? "AND a.platform = ?" : "";
    $params = [$clientId, $dateRange['start'], $dateRange['end']];
    if ($platform !== 'all') {
        $params[] = $platform;
    }
    
    $sql = "
        SELECT 
            COUNT(DISTINCT pa.post_id) as total_posts,
            COALESCE(SUM(pa.impressions), 0) as total_impressions,
            COALESCE(SUM(pa.reach), 0) as total_reach,
            COALESCE(SUM(pa.likes + pa.comments + pa.shares + pa.saves), 0) as total_engagement,
            COALESCE(AVG(pa.engagement_rate), 0) as avg_engagement_rate,
            COALESCE(SUM(pa.clicks), 0) as total_clicks,
            COALESCE(SUM(pa.video_views), 0) as total_video_views
        FROM post_analytics pa
        JOIN posts p ON pa.post_id = p.id
        JOIN accounts a ON pa.account_id = a.id
        WHERE p.client_id = ?
        AND p.published_at >= ?
        AND p.published_at <= ?
        AND p.status = 'published'
        {$platformCondition}
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $overview = $stmt->fetch();
    
    // Get follower growth
    $followerSql = "
        SELECT 
            SUM(fa.daily_growth) as follower_growth,
            COUNT(DISTINCT fa.account_id) as active_accounts
        FROM follower_analytics fa
        JOIN accounts a ON fa.account_id = a.id
        WHERE a.client_id = ?
        AND fa.date >= ?
        AND fa.date <= ?
        {$platformCondition}
    ";
    
    $stmt = $db->prepare($followerSql);
    $stmt->execute($params);
    $followerData = $stmt->fetch();
    
    return array_merge($overview, $followerData);
}

/**
 * Get post analytics
 */
function getPostAnalytics($db, $clientId, $platform, $dateRange) {
    $platformCondition = $platform !== 'all' ? "AND a.platform = ?" : "";
    $params = [$clientId, $dateRange['start'], $dateRange['end']];
    if ($platform !== 'all') {
        $params[] = $platform;
    }
    
    $sql = "
        SELECT 
            p.id,
            p.content,
            p.published_at,
            a.platform,
            pa.impressions,
            pa.reach,
            pa.engagement_rate,
            pa.likes,
            pa.comments,
            pa.shares,
            pa.saves,
            pa.clicks,
            pa.video_views,
            (pa.likes + pa.comments + pa.shares + pa.saves) as total_engagement
        FROM posts p
        JOIN post_analytics pa ON p.id = pa.post_id
        JOIN accounts a ON pa.account_id = a.id
        WHERE p.client_id = ?
        AND p.published_at >= ?
        AND p.published_at <= ?
        AND p.status = 'published'
        {$platformCondition}
        ORDER BY pa.engagement_rate DESC, p.published_at DESC
        LIMIT 100
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

/**
 * Get follower analytics
 */
function getFollowerAnalytics($db, $clientId, $platform, $dateRange) {
    $platformCondition = $platform !== 'all' ? "AND a.platform = ?" : "";
    $params = [$clientId, $dateRange['start'], $dateRange['end']];
    if ($platform !== 'all') {
        $params[] = $platform;
    }
    
    $sql = "
        SELECT 
            fa.date,
            a.platform,
            SUM(fa.follower_count) as follower_count,
            SUM(fa.daily_growth) as daily_growth,
            SUM(fa.new_followers) as new_followers,
            SUM(fa.unfollows) as unfollows
        FROM follower_analytics fa
        JOIN accounts a ON fa.account_id = a.id
        WHERE a.client_id = ?
        AND fa.date >= ?
        AND fa.date <= ?
        {$platformCondition}
        GROUP BY fa.date, a.platform
        ORDER BY fa.date DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

/**
 * Get engagement analytics
 */
function getEngagementAnalytics($db, $clientId, $platform, $dateRange) {
    $platformCondition = $platform !== 'all' ? "AND a.platform = ?" : "";
    $params = [$clientId, $dateRange['start'], $dateRange['end']];
    if ($platform !== 'all') {
        $params[] = $platform;
    }
    
    $sql = "
        SELECT 
            DATE(p.published_at) as date,
            a.platform,
            COUNT(*) as post_count,
            AVG(pa.engagement_rate) as avg_engagement_rate,
            SUM(pa.likes) as total_likes,
            SUM(pa.comments) as total_comments,
            SUM(pa.shares) as total_shares,
            SUM(pa.saves) as total_saves,
            SUM(pa.likes + pa.comments + pa.shares + pa.saves) as total_engagement
        FROM posts p
        JOIN post_analytics pa ON p.id = pa.post_id
        JOIN accounts a ON pa.account_id = a.id
        WHERE p.client_id = ?
        AND p.published_at >= ?
        AND p.published_at <= ?
        AND p.status = 'published'
        {$platformCondition}
        GROUP BY DATE(p.published_at), a.platform
        ORDER BY date DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

/**
 * Get best posting times
 */
function getBestPostingTimes($db, $clientId, $platform) {
    $platformCondition = $platform !== 'all' ? "AND a.platform = ?" : "";
    $params = [$clientId];
    if ($platform !== 'all') {
        $params[] = $platform;
    }
    
    $sql = "
        SELECT 
            pta.day_of_week,
            pta.hour_of_day,
            a.platform,
            pta.avg_engagement_rate,
            pta.avg_reach,
            pta.avg_impressions,
            pta.post_count,
            pta.total_engagement
        FROM posting_time_analytics pta
        JOIN accounts a ON pta.account_id = a.id
        WHERE a.client_id = ?
        {$platformCondition}
        AND pta.last_calculated >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY pta.avg_engagement_rate DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

/**
 * Get content type analytics
 */
function getContentTypeAnalytics($db, $clientId, $platform, $dateRange) {
    $platformCondition = $platform !== 'all' ? "AND a.platform = ?" : "";
    $params = [$clientId];
    if ($platform !== 'all') {
        $params[] = $platform;
    }
    
    $sql = "
        SELECT 
            cta.content_type,
            a.platform,
            cta.post_count,
            cta.avg_engagement_rate,
            cta.avg_reach,
            cta.avg_impressions,
            cta.avg_likes,
            cta.avg_comments,
            cta.avg_shares,
            cta.avg_saves
        FROM content_type_analytics cta
        JOIN accounts a ON cta.account_id = a.id
        WHERE a.client_id = ?
        {$platformCondition}
        AND cta.last_calculated >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY cta.avg_engagement_rate DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

/**
 * Get hashtag analytics
 */
function getHashtagAnalytics($db, $clientId, $platform, $dateRange) {
    $platformCondition = $platform !== 'all' ? "AND platform = ?" : "";
    $params = [$clientId];
    if ($platform !== 'all') {
        $params[] = $platform;
    }
    
    $sql = "
        SELECT 
            hashtag,
            platform,
            usage_count,
            total_reach,
            total_impressions,
            total_engagement,
            avg_engagement_rate,
            trending_score,
            last_used,
            first_used
        FROM hashtag_analytics
        WHERE client_id = ?
        {$platformCondition}
        AND updated_at >= ?
        ORDER BY avg_engagement_rate DESC, usage_count DESC
        LIMIT 50
    ";
    
    $params[] = $dateRange['start'];
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

/**
 * Get platform comparison data
 */
function getPlatformComparison($db, $clientId, $dateRange) {
    $sql = "
        SELECT 
            platform,
            SUM(total_posts) as total_posts,
            SUM(total_reach) as total_reach,
            SUM(total_impressions) as total_impressions,
            SUM(total_engagement) as total_engagement,
            AVG(avg_engagement_rate) as avg_engagement_rate,
            MAX(follower_count) as current_followers,
            SUM(follower_growth) as follower_growth
        FROM platform_comparison
        WHERE client_id = ?
        AND date >= ?
        AND date <= ?
        GROUP BY platform
        ORDER BY avg_engagement_rate DESC
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$clientId, $dateRange['start'], $dateRange['end']]);
    
    return $stmt->fetchAll();
}

/**
 * Get recent activity data
 */
function getRecentActivity($db, $clientId) {
    $sql = "
        SELECT 
            'post_published' as type,
            p.id as item_id,
            SUBSTRING(p.content, 1, 100) as message,
            p.published_at as timestamp,
            'multi' as platform
        FROM posts p
        WHERE p.client_id = ?
        AND p.status = 'published'
        AND p.published_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY p.published_at DESC
        LIMIT 20
    ";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$clientId]);
    
    return $stmt->fetchAll();
}

/**
 * Get demographic data
 */
function getDemographicData($db, $clientId, $dateRange) {
    // This would typically come from platform APIs
    // For now, return mock data structure that matches what the frontend expects
    
    return [
        'age_groups' => [
            ['range' => '18-24', 'percentage' => 25],
            ['range' => '25-34', 'percentage' => 35],
            ['range' => '35-44', 'percentage' => 20],
            ['range' => '45-54', 'percentage' => 15],
            ['range' => '55+', 'percentage' => 5]
        ],
        'gender' => [
            ['type' => 'Female', 'percentage' => 52],
            ['type' => 'Male', 'percentage' => 46],
            ['type' => 'Other', 'percentage' => 2]
        ],
        'locations' => [
            ['name' => 'United States', 'percentage' => 35],
            ['name' => 'United Kingdom', 'percentage' => 15],
            ['name' => 'Canada', 'percentage' => 12],
            ['name' => 'Australia', 'percentage' => 8],
            ['name' => 'Germany', 'percentage' => 6],
            ['name' => 'France', 'percentage' => 5],
            ['name' => 'Spain', 'percentage' => 4],
            ['name' => 'Italy', 'percentage' => 3],
            ['name' => 'Netherlands', 'percentage' => 3],
            ['name' => 'Other', 'percentage' => 9]
        ]
    ];
}