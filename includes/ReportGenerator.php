<?php
/**
 * Report Generator Class
 * Generates professional branded reports for SMM clients
 */

require_once 'Database.php';
require_once 'BrandingHelper.php';
require_once 'PDFGenerator.php';

class ReportGenerator {
    private $db;
    private $brandingHelper;
    private $reportTypes = [
        'executive_summary' => 'Executive Summary',
        'detailed_analytics' => 'Detailed Analytics',
        'social_performance' => 'Social Media Performance',
        'custom' => 'Custom Report'
    ];
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->brandingHelper = BrandingHelper::getInstance();
    }
    
    /**
     * Generate a new report
     */
    public function generateReport($clientId, $options = []) {
        try {
            // Validate required parameters
            if (!$clientId) {
                throw new Exception('Client ID is required');
            }
            
            // Set default options
            $options = array_merge([
                'report_type' => 'detailed_analytics',
                'date_from' => date('Y-m-01', strtotime('-1 month')),
                'date_to' => date('Y-m-t', strtotime('-1 month')),
                'template_id' => null,
                'report_name' => null,
                'sections' => null,
                'format' => 'pdf',
                'include_branding' => true
            ], $options);
            
            // Generate report name if not provided
            if (!$options['report_name']) {
                $options['report_name'] = $this->generateReportName($clientId, $options);
            }
            
            // Create report record
            $reportId = $this->createReportRecord($clientId, $options);
            
            // Generate report data
            $reportData = $this->collectReportData($clientId, $options);
            
            // Apply template if specified
            if ($options['template_id']) {
                $template = $this->getReportTemplate($options['template_id']);
                $reportData = $this->applyTemplate($reportData, $template);
            }
            
            // Generate report file
            $filePath = $this->generateReportFile($reportId, $reportData, $options);
            
            // Update report record with file info
            $this->updateReportRecord($reportId, [
                'file_path' => $filePath,
                'file_size' => filesize($filePath),
                'status' => 'completed',
                'data_points' => $this->countDataPoints($reportData)
            ]);
            
            return [
                'success' => true,
                'report_id' => $reportId,
                'file_path' => $filePath,
                'report_name' => $options['report_name']
            ];
            
        } catch (Exception $e) {
            // Update report record with error
            if (isset($reportId)) {
                $this->updateReportRecord($reportId, [
                    'status' => 'failed',
                    'error_message' => $e->getMessage()
                ]);
            }
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Collect analytics data for the report
     */
    private function collectReportData($clientId, $options) {
        $data = [
            'client_info' => $this->getClientInfo($clientId),
            'branding' => $this->brandingHelper->getBranding($clientId),
            'date_range' => [
                'from' => $options['date_from'],
                'to' => $options['date_to'],
                'formatted' => date('M j, Y', strtotime($options['date_from'])) . ' - ' . date('M j, Y', strtotime($options['date_to']))
            ],
            'overview' => $this->getOverviewMetrics($clientId, $options['date_from'], $options['date_to']),
            'platform_performance' => $this->getPlatformPerformance($clientId, $options['date_from'], $options['date_to']),
            'top_posts' => $this->getTopPosts($clientId, $options['date_from'], $options['date_to']),
            'audience_growth' => $this->getAudienceGrowth($clientId, $options['date_from'], $options['date_to']),
            'engagement_trends' => $this->getEngagementTrends($clientId, $options['date_from'], $options['date_to']),
            'content_analysis' => $this->getContentAnalysis($clientId, $options['date_from'], $options['date_to']),
            'hashtag_performance' => $this->getHashtagPerformance($clientId, $options['date_from'], $options['date_to']),
            'optimal_timing' => $this->getOptimalTiming($clientId, $options['date_from'], $options['date_to']),
            'competitor_comparison' => $this->getCompetitorComparison($clientId, $options['date_from'], $options['date_to']),
            'recommendations' => $this->generateRecommendations($clientId, $options['date_from'], $options['date_to'])
        ];
        
        return $data;
    }
    
    /**
     * Get client information
     */
    private function getClientInfo($clientId) {
        $stmt = $this->db->prepare("
            SELECT c.*, cb.business_name, cb.logo_url 
            FROM clients c 
            LEFT JOIN client_branding cb ON c.id = cb.client_id 
            WHERE c.id = ?
        ");
        $stmt->execute([$clientId]);
        return $stmt->fetch();
    }
    
    /**
     * Get overview metrics
     */
    private function getOverviewMetrics($clientId, $dateFrom, $dateTo) {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(DISTINCT pa.post_id) as total_posts,
                COALESCE(AVG(pa.engagement_rate), 0) as avg_engagement_rate,
                COALESCE(SUM(pa.reach), 0) as total_reach,
                COALESCE(SUM(pa.impressions), 0) as total_impressions,
                COALESCE(SUM(pa.likes + pa.comments + pa.shares + pa.saves), 0) as total_engagement,
                COALESCE(SUM(pa.clicks), 0) as total_clicks,
                COALESCE(SUM(pa.profile_visits), 0) as total_profile_visits
            FROM post_analytics pa
            JOIN posts p ON pa.post_id = p.id
            WHERE p.client_id = ? 
                AND p.published_at BETWEEN ? AND ?
                AND p.status = 'published'
        ");
        $stmt->execute([$clientId, $dateFrom, $dateTo]);
        $overview = $stmt->fetch();
        
        // Get previous period for comparison
        $previousPeriod = $this->getPreviousPeriodMetrics($clientId, $dateFrom, $dateTo);
        
        // Calculate growth rates
        foreach ($overview as $key => $value) {
            if (isset($previousPeriod[$key]) && $previousPeriod[$key] > 0) {
                $growth = (($value - $previousPeriod[$key]) / $previousPeriod[$key]) * 100;
                $overview[$key . '_growth'] = round($growth, 2);
            } else {
                $overview[$key . '_growth'] = 0;
            }
        }
        
        return $overview;
    }
    
    /**
     * Get platform performance breakdown
     */
    private function getPlatformPerformance($clientId, $dateFrom, $dateTo) {
        $stmt = $this->db->prepare("
            SELECT 
                a.platform,
                COUNT(DISTINCT pa.post_id) as post_count,
                COALESCE(AVG(pa.engagement_rate), 0) as avg_engagement_rate,
                COALESCE(SUM(pa.reach), 0) as total_reach,
                COALESCE(SUM(pa.impressions), 0) as total_impressions,
                COALESCE(SUM(pa.likes + pa.comments + pa.shares), 0) as total_engagement,
                COALESCE(MAX(fa.follower_count), 0) as current_followers
            FROM accounts a
            LEFT JOIN post_analytics pa ON a.id = pa.account_id
            LEFT JOIN posts p ON pa.post_id = p.id AND p.published_at BETWEEN ? AND ?
            LEFT JOIN follower_analytics fa ON a.id = fa.account_id 
                AND fa.date = (SELECT MAX(date) FROM follower_analytics WHERE account_id = a.id)
            WHERE a.client_id = ? AND a.is_active = 1
            GROUP BY a.platform
            ORDER BY avg_engagement_rate DESC
        ");
        $stmt->execute([$dateFrom, $dateTo, $clientId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get top performing posts
     */
    private function getTopPosts($clientId, $dateFrom, $dateTo, $limit = 10) {
        $stmt = $this->db->prepare("
            SELECT 
                p.id,
                p.content,
                p.published_at,
                a.platform,
                pa.engagement_rate,
                pa.reach,
                pa.impressions,
                (pa.likes + pa.comments + pa.shares + pa.saves) as total_engagement,
                pa.likes,
                pa.comments,
                pa.shares,
                pa.saves
            FROM posts p
            JOIN post_analytics pa ON p.id = pa.post_id
            JOIN accounts a ON pa.account_id = a.id
            WHERE p.client_id = ? 
                AND p.published_at BETWEEN ? AND ?
                AND p.status = 'published'
            ORDER BY pa.engagement_rate DESC
            LIMIT ?
        ");
        $stmt->execute([$clientId, $dateFrom, $dateTo, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get audience growth data
     */
    private function getAudienceGrowth($clientId, $dateFrom, $dateTo) {
        $stmt = $this->db->prepare("
            SELECT 
                a.platform,
                fa.date,
                fa.follower_count,
                fa.daily_growth,
                fa.new_followers,
                fa.unfollows
            FROM follower_analytics fa
            JOIN accounts a ON fa.account_id = a.id
            WHERE a.client_id = ? 
                AND fa.date BETWEEN ? AND ?
            ORDER BY fa.date ASC, a.platform
        ");
        $stmt->execute([$clientId, $dateFrom, $dateTo]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get engagement trends over time
     */
    private function getEngagementTrends($clientId, $dateFrom, $dateTo) {
        $stmt = $this->db->prepare("
            SELECT 
                DATE(p.published_at) as date,
                AVG(pa.engagement_rate) as avg_engagement_rate,
                SUM(pa.reach) as daily_reach,
                SUM(pa.likes + pa.comments + pa.shares) as daily_engagement,
                COUNT(pa.post_id) as posts_count
            FROM posts p
            JOIN post_analytics pa ON p.id = pa.post_id
            WHERE p.client_id = ? 
                AND p.published_at BETWEEN ? AND ?
                AND p.status = 'published'
            GROUP BY DATE(p.published_at)
            ORDER BY date ASC
        ");
        $stmt->execute([$clientId, $dateFrom, $dateTo]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get content type analysis
     */
    private function getContentAnalysis($clientId, $dateFrom, $dateTo) {
        $stmt = $this->db->prepare("
            SELECT 
                cta.content_type,
                cta.post_count,
                cta.avg_engagement_rate,
                cta.avg_reach,
                cta.avg_impressions
            FROM content_type_analytics cta
            JOIN accounts a ON cta.account_id = a.id
            WHERE a.client_id = ?
                AND cta.last_calculated >= ?
            ORDER BY cta.avg_engagement_rate DESC
        ");
        $stmt->execute([$clientId, $dateFrom]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get hashtag performance
     */
    private function getHashtagPerformance($clientId, $dateFrom, $dateTo, $limit = 20) {
        $stmt = $this->db->prepare("
            SELECT 
                hashtag,
                platform,
                usage_count,
                avg_engagement_rate,
                total_reach,
                total_impressions,
                trending_score
            FROM hashtag_analytics
            WHERE client_id = ?
                AND last_used BETWEEN ? AND ?
                AND usage_count > 0
            ORDER BY avg_engagement_rate DESC, usage_count DESC
            LIMIT ?
        ");
        $stmt->execute([$clientId, $dateFrom, $dateTo, $limit]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get optimal posting times
     */
    private function getOptimalTiming($clientId, $dateFrom, $dateTo) {
        $stmt = $this->db->prepare("
            SELECT 
                pta.day_of_week,
                pta.hour_of_day,
                pta.avg_engagement_rate,
                pta.avg_reach,
                pta.post_count,
                a.platform
            FROM posting_time_analytics pta
            JOIN accounts a ON pta.account_id = a.id
            WHERE a.client_id = ?
                AND pta.last_calculated >= ?
                AND pta.post_count >= 3
            ORDER BY pta.avg_engagement_rate DESC
        ");
        $stmt->execute([$clientId, $dateFrom]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get competitor comparison data
     */
    private function getCompetitorComparison($clientId, $dateFrom, $dateTo) {
        $stmt = $this->db->prepare("
            SELECT 
                ct.competitor_name,
                ct.platform,
                ct.follower_count,
                ct.avg_engagement_rate,
                ct.avg_posts_per_week,
                cs.follower_growth
            FROM competitor_tracking ct
            LEFT JOIN competitor_snapshots cs ON ct.id = cs.competitor_id 
                AND cs.snapshot_date = (SELECT MAX(snapshot_date) FROM competitor_snapshots WHERE competitor_id = ct.id)
            WHERE ct.client_id = ? AND ct.tracking_enabled = 1
            ORDER BY ct.avg_engagement_rate DESC
        ");
        $stmt->execute([$clientId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Generate AI-powered recommendations
     */
    private function generateRecommendations($clientId, $dateFrom, $dateTo) {
        $recommendations = [];
        
        // Analyze performance trends
        $trends = $this->getEngagementTrends($clientId, $dateFrom, $dateTo);
        $platforms = $this->getPlatformPerformance($clientId, $dateFrom, $dateTo);
        $timing = $this->getOptimalTiming($clientId, $dateFrom, $dateTo);
        $contentTypes = $this->getContentAnalysis($clientId, $dateFrom, $dateTo);
        
        // Content recommendations
        if (!empty($contentTypes)) {
            $bestContentType = $contentTypes[0];
            $recommendations[] = [
                'type' => 'content',
                'priority' => 'high',
                'title' => 'Optimize Content Types',
                'description' => "Your {$bestContentType['content_type']} content performs best with {$bestContentType['avg_engagement_rate']}% engagement rate. Consider creating more of this content type.",
                'action' => 'Create more ' . $bestContentType['content_type'] . ' content'
            ];
        }
        
        // Timing recommendations
        if (!empty($timing)) {
            $bestTime = $timing[0];
            $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            $recommendations[] = [
                'type' => 'timing',
                'priority' => 'medium',
                'title' => 'Optimize Posting Schedule',
                'description' => "Your best performing time is {$dayNames[$bestTime['day_of_week']]} at {$bestTime['hour_of_day']}:00 with {$bestTime['avg_engagement_rate']}% engagement rate.",
                'action' => "Schedule more posts for {$dayNames[$bestTime['day_of_week']]}s at {$bestTime['hour_of_day']}:00"
            ];
        }
        
        // Platform recommendations
        if (!empty($platforms)) {
            $platforms = array_filter($platforms, function($p) { return $p['post_count'] > 0; });
            if (count($platforms) > 1) {
                usort($platforms, function($a, $b) {
                    return $b['avg_engagement_rate'] <=> $a['avg_engagement_rate'];
                });
                
                $bestPlatform = $platforms[0];
                $recommendations[] = [
                    'type' => 'platform',
                    'priority' => 'medium',
                    'title' => 'Focus on High-Performing Platforms',
                    'description' => "{$bestPlatform['platform']} is your top performer with {$bestPlatform['avg_engagement_rate']}% engagement rate. Consider allocating more resources here.",
                    'action' => "Increase posting frequency on " . ucfirst($bestPlatform['platform'])
                ];
            }
        }
        
        // Engagement recommendations
        $overview = $this->getOverviewMetrics($clientId, $dateFrom, $dateTo);
        if ($overview['avg_engagement_rate'] < 2.0) {
            $recommendations[] = [
                'type' => 'engagement',
                'priority' => 'high',
                'title' => 'Improve Engagement Rate',
                'description' => "Your current engagement rate is {$overview['avg_engagement_rate']}%. Industry average is 2-3%. Focus on creating more interactive content.",
                'action' => 'Add more questions, polls, and calls-to-action in your posts'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Get previous period metrics for comparison
     */
    private function getPreviousPeriodMetrics($clientId, $dateFrom, $dateTo) {
        $period = strtotime($dateTo) - strtotime($dateFrom);
        $prevDateTo = date('Y-m-d', strtotime($dateFrom) - 86400);
        $prevDateFrom = date('Y-m-d', strtotime($prevDateTo) - $period);
        
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(DISTINCT pa.post_id) as total_posts,
                COALESCE(AVG(pa.engagement_rate), 0) as avg_engagement_rate,
                COALESCE(SUM(pa.reach), 0) as total_reach,
                COALESCE(SUM(pa.impressions), 0) as total_impressions,
                COALESCE(SUM(pa.likes + pa.comments + pa.shares + pa.saves), 0) as total_engagement,
                COALESCE(SUM(pa.clicks), 0) as total_clicks,
                COALESCE(SUM(pa.profile_visits), 0) as total_profile_visits
            FROM post_analytics pa
            JOIN posts p ON pa.post_id = p.id
            WHERE p.client_id = ? 
                AND p.published_at BETWEEN ? AND ?
                AND p.status = 'published'
        ");
        $stmt->execute([$clientId, $prevDateFrom, $prevDateTo]);
        return $stmt->fetch() ?: [];
    }
    
    /**
     * Create report record in database
     */
    private function createReportRecord($clientId, $options) {
        $stmt = $this->db->prepare("
            INSERT INTO generated_reports (
                client_id, template_id, report_name, report_type, 
                date_from, date_to, status, generated_by
            ) VALUES (?, ?, ?, ?, ?, ?, 'generating', ?)
        ");
        
        $stmt->execute([
            $clientId,
            $options['template_id'],
            $options['report_name'],
            $options['report_type'],
            $options['date_from'],
            $options['date_to'],
            $_SESSION['user_id'] ?? null
        ]);
        
        return $this->db->lastInsertId();
    }
    
    /**
     * Update report record
     */
    private function updateReportRecord($reportId, $data) {
        $fields = [];
        $values = [];
        
        foreach ($data as $field => $value) {
            $fields[] = "{$field} = ?";
            $values[] = $value;
        }
        
        $values[] = $reportId;
        
        $stmt = $this->db->prepare("
            UPDATE generated_reports 
            SET " . implode(', ', $fields) . "
            WHERE id = ?
        ");
        
        return $stmt->execute($values);
    }
    
    /**
     * Generate report file
     */
    private function generateReportFile($reportId, $reportData, $options) {
        $reportsDir = $_SERVER['DOCUMENT_ROOT'] . '/reports/';
        if (!is_dir($reportsDir)) {
            mkdir($reportsDir, 0755, true);
        }
        
        $fileName = 'report_' . $reportId . '_' . date('Y-m-d_H-i-s') . '.html';
        $filePath = $reportsDir . $fileName;
        
        // Generate HTML report
        $html = $this->generateHTMLReport($reportData, $options);
        
        // Save HTML file
        file_put_contents($filePath, $html);
        
        // Generate PDF if requested
        if ($options['format'] === 'pdf') {
            $pdfPath = str_replace('.html', '.pdf', $filePath);
            $this->convertToPDF($filePath, $pdfPath, $reportData);
            return $pdfPath;
        }
        
        return $filePath;
    }
    
    /**
     * Generate HTML report
     */
    private function generateHTMLReport($data, $options) {
        $branding = $data['branding'];
        $clientInfo = $data['client_info'];
        
        ob_start();
        include $_SERVER['DOCUMENT_ROOT'] . '/includes/report-templates/' . $options['report_type'] . '.php';
        return ob_get_clean();
    }
    
    /**
     * Convert HTML to PDF using PDFGenerator
     */
    private function convertToPDF($htmlPath, $pdfPath, $reportData = null) {
        try {
            // Read HTML content
            $htmlContent = file_get_contents($htmlPath);
            if ($htmlContent === false) {
                throw new Exception('Failed to read HTML file');
            }
            
            // Get branding info
            $branding = $reportData['branding'] ?? [];
            
            // Initialize PDF generator
            $pdfGenerator = new PDFGenerator();
            
            // Generate PDF
            $success = $pdfGenerator->generatePDF($htmlContent, $pdfPath, $branding);
            
            if (!$success) {
                throw new Exception('PDF generation failed');
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log('PDF conversion error: ' . $e->getMessage());
            // Fallback: copy HTML file with PDF extension
            copy($htmlPath, $pdfPath);
            return false;
        }
    }
    
    /**
     * Generate report name
     */
    private function generateReportName($clientId, $options) {
        $clientInfo = $this->getClientInfo($clientId);
        $clientName = $clientInfo['business_name'] ?: $clientInfo['name'];
        
        $dateRange = date('M Y', strtotime($options['date_from']));
        if ($options['date_from'] !== $options['date_to']) {
            $dateRange = date('M j', strtotime($options['date_from'])) . ' - ' . date('M j, Y', strtotime($options['date_to']));
        }
        
        return $this->reportTypes[$options['report_type']] . ' - ' . $clientName . ' - ' . $dateRange;
    }
    
    /**
     * Count data points in report
     */
    private function countDataPoints($reportData) {
        $count = 0;
        
        if (isset($reportData['top_posts'])) {
            $count += count($reportData['top_posts']);
        }
        
        if (isset($reportData['platform_performance'])) {
            $count += count($reportData['platform_performance']);
        }
        
        if (isset($reportData['engagement_trends'])) {
            $count += count($reportData['engagement_trends']);
        }
        
        return $count;
    }
    
    /**
     * Get report template
     */
    private function getReportTemplate($templateId) {
        $stmt = $this->db->prepare("SELECT * FROM report_templates WHERE id = ?");
        $stmt->execute([$templateId]);
        return $stmt->fetch();
    }
    
    /**
     * Apply template configuration to report data
     */
    private function applyTemplate($reportData, $template) {
        if ($template) {
            $sections = json_decode($template['sections'], true);
            $metrics = json_decode($template['metrics'], true);
            
            // Filter data based on template sections
            $filteredData = [];
            foreach ($sections as $section) {
                if (isset($reportData[$section])) {
                    $filteredData[$section] = $reportData[$section];
                }
            }
            
            // Merge with required base data
            $filteredData['client_info'] = $reportData['client_info'];
            $filteredData['branding'] = $reportData['branding'];
            $filteredData['date_range'] = $reportData['date_range'];
            
            return $filteredData;
        }
        
        return $reportData;
    }
    
    /**
     * Get list of generated reports
     */
    public function getReportsList($clientId = null, $filters = []) {
        $where = [];
        $params = [];
        
        if ($clientId) {
            $where[] = "gr.client_id = ?";
            $params[] = $clientId;
        }
        
        if (!empty($filters['status'])) {
            $where[] = "gr.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['report_type'])) {
            $where[] = "gr.report_type = ?";
            $params[] = $filters['report_type'];
        }
        
        if (!empty($filters['date_from'])) {
            $where[] = "gr.created_at >= ?";
            $params[] = $filters['date_from'];
        }
        
        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $stmt = $this->db->prepare("
            SELECT 
                gr.*,
                c.name as client_name,
                cb.business_name,
                u.name as generated_by_name
            FROM generated_reports gr
            JOIN clients c ON gr.client_id = c.id
            LEFT JOIN client_branding cb ON c.id = cb.client_id
            LEFT JOIN users u ON gr.generated_by = u.id
            {$whereClause}
            ORDER BY gr.created_at DESC
        ");
        
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get single report details
     */
    public function getReport($reportId) {
        $stmt = $this->db->prepare("
            SELECT 
                gr.*,
                c.name as client_name,
                cb.business_name,
                u.name as generated_by_name
            FROM generated_reports gr
            JOIN clients c ON gr.client_id = c.id
            LEFT JOIN client_branding cb ON c.id = cb.client_id
            LEFT JOIN users u ON gr.generated_by = u.id
            WHERE gr.id = ?
        ");
        
        $stmt->execute([$reportId]);
        return $stmt->fetch();
    }
    
    /**
     * Delete report
     */
    public function deleteReport($reportId) {
        $report = $this->getReport($reportId);
        
        if ($report && $report['file_path'] && file_exists($report['file_path'])) {
            unlink($report['file_path']);
        }
        
        $stmt = $this->db->prepare("DELETE FROM generated_reports WHERE id = ?");
        return $stmt->execute([$reportId]);
    }
    
    /**
     * Create shareable link for report
     */
    public function createShareableLink($reportId, $options = []) {
        $token = bin2hex(random_bytes(32));
        
        $stmt = $this->db->prepare("
            INSERT INTO shareable_reports (
                report_id, share_token, password_hash, allowed_downloads, 
                expires_at, created_by
            ) VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        return $stmt->execute([
            $reportId,
            $token,
            isset($options['password']) ? password_hash($options['password'], PASSWORD_DEFAULT) : null,
            $options['allowed_downloads'] ?? null,
            $options['expires_at'] ?? null,
            $_SESSION['user_id'] ?? null
        ]);
    }
    
    /**
     * Get available report types
     */
    public function getReportTypes() {
        return $this->reportTypes;
    }
    
    /**
     * Get available report templates
     */
    public function getReportTemplates($clientId = null) {
        $where = $clientId ? "WHERE client_id IS NULL OR client_id = ?" : "WHERE client_id IS NULL";
        $params = $clientId ? [$clientId] : [];
        
        $stmt = $this->db->prepare("
            SELECT * FROM report_templates 
            {$where} AND is_active = 1
            ORDER BY template_type, name
        ");
        
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}