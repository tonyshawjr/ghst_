<?php
/**
 * Analytics Collection Cron Job
 * 
 * Periodically collects analytics data from all social media platforms
 * Run every hour: 0 * * * * /usr/bin/php /path/to/your/ghst/cron/collect-analytics.php
 */

// Prevent web access
if (php_sapi_name() !== 'cli' && (!isset($_GET['secret']) || $_GET['secret'] !== CRON_SECRET)) {
    die('Unauthorized access');
}

require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/AnalyticsCollector.php';
require_once '../includes/functions.php';

// Set time limit for long-running processes
set_time_limit(1800); // 30 minutes

$db = Database::getInstance();
$collector = new AnalyticsCollector();

// Configuration
$batchSize = defined('ANALYTICS_BATCH_SIZE') ? ANALYTICS_BATCH_SIZE : 20;
$platforms = ['facebook', 'instagram', 'twitter', 'linkedin'];

// Log cron start
logAnalyticsCron('info', 'Analytics collection cron started');

try {
    $totalResults = [
        'success' => true,
        'collected' => 0,
        'failed' => 0,
        'errors' => [],
        'platforms' => [],
        'duration' => 0
    ];
    
    $startTime = microtime(true);
    
    // Check if we should run full collection or incremental
    $runType = determineRunType();
    
    logAnalyticsCron('info', "Running {$runType} analytics collection");
    
    foreach ($platforms as $platform) {
        try {
            logAnalyticsCron('info', "Starting {$platform} analytics collection");
            
            // Collect analytics for this platform
            $options = [
                'batch_size' => $batchSize,
                'run_type' => $runType,
                'include_historical' => ($runType === 'full')
            ];
            
            $results = $collector->collectAnalytics($platform, null, $options);
            
            // Merge results
            $totalResults['collected'] += $results['collected'];
            $totalResults['failed'] += $results['failed'];
            $totalResults['errors'] = array_merge($totalResults['errors'], $results['errors']);
            $totalResults['platforms'][$platform] = $results['collected'];
            
            logAnalyticsCron('info', "Completed {$platform} analytics collection", [
                'collected' => $results['collected'],
                'failed' => $results['failed'],
                'duration' => $results['duration']
            ]);
            
            // Small delay between platforms
            sleep(2);
            
        } catch (Exception $e) {
            $totalResults['failed']++;
            $totalResults['errors'][] = [
                'platform' => $platform,
                'error' => $e->getMessage()
            ];
            
            logAnalyticsCron('error', "Failed to collect {$platform} analytics", [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    // Calculate derived metrics and insights
    if ($totalResults['collected'] > 0) {
        logAnalyticsCron('info', 'Calculating derived metrics');
        calculateDerivedMetrics();
        
        // Update best posting times
        calculateBestPostingTimes();
        
        // Update content type performance
        calculateContentTypePerformance();
        
        // Update hashtag performance
        calculateHashtagPerformance();
        
        // Update platform comparison metrics
        calculatePlatformComparisons();
    }
    
    // Clean up old data if this is a full run
    if ($runType === 'full') {
        logAnalyticsCron('info', 'Cleaning up old analytics data');
        cleanupOldAnalyticsData();
    }
    
    // Generate summary stats
    generateAnalyticsSummary();
    
    $totalResults['duration'] = round(microtime(true) - $startTime, 2);
    
    // Update last collection timestamp
    updateLastCollectionTime();
    
    logAnalyticsCron('info', 'Analytics collection completed successfully', $totalResults);
    
} catch (Exception $e) {
    logAnalyticsCron('error', 'Analytics collection cron failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    die('Analytics collection failed: ' . $e->getMessage());
}

/**
 * Determine if we should run full or incremental collection
 */
function determineRunType() {
    global $db;
    
    // Check when we last ran a full collection
    $stmt = $db->prepare(
        "SELECT created_at FROM logs 
         WHERE action = 'analytics_collection' 
         AND message LIKE '%full%' 
         ORDER BY created_at DESC 
         LIMIT 1"
    );
    $stmt->execute();
    $lastFull = $stmt->fetch();
    
    // Run full collection daily, incremental hourly
    if (!$lastFull || strtotime($lastFull['created_at']) < strtotime('-1 day')) {
        return 'full';
    }
    
    return 'incremental';
}

/**
 * Calculate derived metrics
 */
function calculateDerivedMetrics() {
    global $db;
    
    // Update engagement rates where missing
    $db->query("
        UPDATE post_analytics 
        SET engagement_rate = CASE 
            WHEN reach > 0 THEN ROUND(((likes + comments + shares + saves) / reach) * 100, 2)
            WHEN impressions > 0 THEN ROUND(((likes + comments + shares + saves) / impressions) * 100, 2)
            ELSE 0 
        END
        WHERE engagement_rate = 0 
        AND (likes > 0 OR comments > 0 OR shares > 0 OR saves > 0)
    ");
    
    // Update peak performance times
    $db->query("
        UPDATE post_analytics pa
        JOIN posts p ON pa.post_id = p.id
        SET pa.peak_performance_time = p.published_at
        WHERE pa.peak_performance_time IS NULL
        AND p.published_at IS NOT NULL
    ");
}

/**
 * Calculate best posting times
 */
function calculateBestPostingTimes() {
    global $db;
    
    // This replicates the logic from AnalyticsCollector but as a batch operation
    $db->query("
        INSERT INTO posting_time_analytics (
            account_id, day_of_week, hour_of_day, avg_engagement_rate,
            avg_reach, avg_impressions, post_count, total_engagement,
            last_calculated, analysis_period_days
        )
        SELECT 
            pa.account_id,
            DAYOFWEEK(p.published_at) - 1 as day_of_week,
            HOUR(p.published_at) as hour_of_day,
            AVG(pa.engagement_rate) as avg_engagement_rate,
            AVG(pa.reach) as avg_reach,
            AVG(pa.impressions) as avg_impressions,
            COUNT(*) as post_count,
            SUM(pa.likes + pa.comments + pa.shares) as total_engagement,
            NOW() as last_calculated,
            90 as analysis_period_days
        FROM post_analytics pa
        JOIN posts p ON pa.post_id = p.id
        WHERE p.published_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        AND p.status = 'published'
        GROUP BY pa.account_id, day_of_week, hour_of_day
        HAVING post_count >= 3
        ON DUPLICATE KEY UPDATE
            avg_engagement_rate = VALUES(avg_engagement_rate),
            avg_reach = VALUES(avg_reach),
            avg_impressions = VALUES(avg_impressions),
            post_count = VALUES(post_count),
            total_engagement = VALUES(total_engagement),
            last_calculated = VALUES(last_calculated)
    ");
}

/**
 * Calculate content type performance
 */
function calculateContentTypePerformance() {
    global $db;
    
    // Analyze content types based on media attachments
    $db->query("
        INSERT INTO content_type_analytics (
            account_id, content_type, post_count, avg_engagement_rate,
            avg_reach, avg_impressions, avg_likes, avg_comments, avg_shares, 
            avg_saves, last_calculated, analysis_period_days
        )
        SELECT 
            pa.account_id,
            CASE 
                WHEN p.media_json IS NULL OR p.media_json = '[]' THEN 'text'
                WHEN JSON_LENGTH(p.media_json) = 1 THEN 'image'
                WHEN JSON_LENGTH(p.media_json) > 1 THEN 'carousel'
                ELSE 'text'
            END as content_type,
            COUNT(*) as post_count,
            AVG(pa.engagement_rate) as avg_engagement_rate,
            AVG(pa.reach) as avg_reach,
            AVG(pa.impressions) as avg_impressions,
            AVG(pa.likes) as avg_likes,
            AVG(pa.comments) as avg_comments,
            AVG(pa.shares) as avg_shares,
            AVG(pa.saves) as avg_saves,
            NOW() as last_calculated,
            90 as analysis_period_days
        FROM post_analytics pa
        JOIN posts p ON pa.post_id = p.id
        WHERE p.published_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        AND p.status = 'published'
        GROUP BY pa.account_id, content_type
        HAVING post_count >= 2
        ON DUPLICATE KEY UPDATE
            post_count = VALUES(post_count),
            avg_engagement_rate = VALUES(avg_engagement_rate),
            avg_reach = VALUES(avg_reach),
            avg_impressions = VALUES(avg_impressions),
            avg_likes = VALUES(avg_likes),
            avg_comments = VALUES(avg_comments),
            avg_shares = VALUES(avg_shares),
            avg_saves = VALUES(avg_saves),
            last_calculated = VALUES(last_calculated),
            analysis_period_days = VALUES(analysis_period_days)
    ");
}

/**
 * Calculate hashtag performance
 */
function calculateHashtagPerformance() {
    global $db;
    
    try {
        // Extract hashtags from post content and analyze performance
        $stmt = $db->prepare("
            SELECT p.id, p.client_id, p.content, a.platform,
                   AVG(pa.engagement_rate) as avg_engagement_rate,
                   AVG(pa.reach) as avg_reach,
                   AVG(pa.impressions) as avg_impressions,
                   SUM(pa.likes + pa.comments + pa.shares) as total_engagement
            FROM posts p
            JOIN post_analytics pa ON p.id = pa.post_id
            JOIN accounts a ON pa.account_id = a.id
            WHERE p.content LIKE '%#%'
            AND p.published_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            AND p.status = 'published'
            GROUP BY p.id, p.client_id, p.content, a.platform
        ");
        
        $stmt->execute();
        $posts = $stmt->fetchAll();
        
        foreach ($posts as $post) {
            // Extract hashtags from content
            preg_match_all('/#([a-zA-Z0-9_]+)/', $post['content'], $matches);
            $hashtags = $matches[1];
            
            foreach ($hashtags as $hashtag) {
                // Update hashtag analytics
                $updateStmt = $db->prepare("
                    INSERT INTO hashtag_analytics (
                        client_id, hashtag, platform, usage_count, total_reach,
                        total_impressions, total_engagement, avg_engagement_rate,
                        last_used, first_used, updated_at
                    ) VALUES (?, ?, ?, 1, ?, ?, ?, ?, NOW(), NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        usage_count = usage_count + 1,
                        total_reach = total_reach + VALUES(total_reach),
                        total_impressions = total_impressions + VALUES(total_impressions),
                        total_engagement = total_engagement + VALUES(total_engagement),
                        avg_engagement_rate = (avg_engagement_rate + VALUES(avg_engagement_rate)) / 2,
                        last_used = NOW(),
                        updated_at = NOW()
                ");
                
                $updateStmt->execute([
                    $post['client_id'],
                    strtolower($hashtag),
                    $post['platform'],
                    $post['avg_reach'] ?? 0,
                    $post['avg_impressions'] ?? 0,
                    $post['total_engagement'] ?? 0,
                    $post['avg_engagement_rate'] ?? 0
                ]);
            }
        }
        
    } catch (Exception $e) {
        logAnalyticsCron('error', 'Hashtag performance calculation failed', [
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Calculate platform comparison metrics
 */
function calculatePlatformComparisons() {
    global $db;
    
    $db->query("
        INSERT INTO platform_comparison (
            client_id, date, platform, total_posts, total_reach,
            total_impressions, total_engagement, avg_engagement_rate,
            follower_count, follower_growth
        )
        SELECT 
            c.id as client_id,
            CURDATE() as date,
            a.platform,
            COUNT(DISTINCT pa.post_id) as total_posts,
            SUM(pa.reach) as total_reach,
            SUM(pa.impressions) as total_impressions,
            SUM(pa.likes + pa.comments + pa.shares) as total_engagement,
            AVG(pa.engagement_rate) as avg_engagement_rate,
            MAX(fa.follower_count) as follower_count,
            SUM(fa.daily_growth) as follower_growth
        FROM clients c
        JOIN accounts a ON c.id = a.client_id
        JOIN post_analytics pa ON a.id = pa.account_id
        LEFT JOIN follower_analytics fa ON a.id = fa.account_id 
            AND fa.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        WHERE pa.last_updated >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        AND a.is_active = 1
        GROUP BY c.id, a.platform
        ON DUPLICATE KEY UPDATE
            total_posts = VALUES(total_posts),
            total_reach = VALUES(total_reach),
            total_impressions = VALUES(total_impressions),
            total_engagement = VALUES(total_engagement),
            avg_engagement_rate = VALUES(avg_engagement_rate),
            follower_count = VALUES(follower_count),
            follower_growth = VALUES(follower_growth)
    ");
}

/**
 * Clean up old analytics data
 */
function cleanupOldAnalyticsData() {
    global $db;
    
    $retentionDays = defined('ANALYTICS_RETENTION_DAYS') ? ANALYTICS_RETENTION_DAYS : 365;
    
    $tables = [
        'post_analytics' => 'created_at',
        'follower_analytics' => 'created_at',
        'posting_time_analytics' => 'last_calculated',
        'content_type_analytics' => 'last_calculated',
        'platform_comparison' => 'created_at'
    ];
    
    foreach ($tables as $table => $dateColumn) {
        try {
            $stmt = $db->prepare("
                DELETE FROM {$table} 
                WHERE {$dateColumn} < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$retentionDays]);
            
            $deleted = $stmt->rowCount();
            if ($deleted > 0) {
                logAnalyticsCron('info', "Cleaned up {$table}", ['deleted' => $deleted]);
            }
            
        } catch (Exception $e) {
            logAnalyticsCron('error', "Failed to cleanup {$table}", [
                'error' => $e->getMessage()
            ]);
        }
    }
}

/**
 * Generate analytics summary for reporting
 */
function generateAnalyticsSummary() {
    global $db;
    
    try {
        // Calculate daily summary stats
        $stmt = $db->prepare("
            INSERT INTO daily_analytics_summary (
                date, total_posts, total_reach, total_impressions,
                total_engagement, avg_engagement_rate, active_accounts
            )
            SELECT 
                CURDATE() as date,
                COUNT(DISTINCT pa.post_id) as total_posts,
                SUM(pa.reach) as total_reach,
                SUM(pa.impressions) as total_impressions,
                SUM(pa.likes + pa.comments + pa.shares) as total_engagement,
                AVG(pa.engagement_rate) as avg_engagement_rate,
                COUNT(DISTINCT pa.account_id) as active_accounts
            FROM post_analytics pa
            JOIN posts p ON pa.post_id = p.id
            WHERE pa.last_updated >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            AND p.status = 'published'
            ON DUPLICATE KEY UPDATE
                total_posts = VALUES(total_posts),
                total_reach = VALUES(total_reach),
                total_impressions = VALUES(total_impressions),
                total_engagement = VALUES(total_engagement),
                avg_engagement_rate = VALUES(avg_engagement_rate),
                active_accounts = VALUES(active_accounts)
        ");
        
        $stmt->execute();
        
    } catch (Exception $e) {
        // Table might not exist yet, that's okay
        logAnalyticsCron('warning', 'Could not generate analytics summary', [
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Update last collection timestamp
 */
function updateLastCollectionTime() {
    global $db;
    
    try {
        // Update or create a system setting for last collection time
        $stmt = $db->prepare("
            INSERT INTO system_settings (setting_key, setting_value, updated_at)
            VALUES ('analytics_last_collection', NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                setting_value = NOW(),
                updated_at = NOW()
        ");
        
        $stmt->execute();
        
    } catch (Exception $e) {
        // Table might not exist, that's okay
        logAnalyticsCron('info', 'Analytics collection completed at ' . date('Y-m-d H:i:s'));
    }
}

/**
 * Log analytics cron activity
 */
function logAnalyticsCron($level, $message, $data = []) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            INSERT INTO logs (action, level, message, details, created_at)
            VALUES ('analytics_cron', ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $level,
            $message,
            json_encode($data)
        ]);
        
        // Also log to file for debugging
        $logMessage = "[" . date('Y-m-d H:i:s') . "] [{$level}] {$message}";
        if (!empty($data)) {
            $logMessage .= " " . json_encode($data);
        }
        $logMessage .= PHP_EOL;
        
        $logFile = __DIR__ . '/../logs/analytics-cron.log';
        if (is_dir(dirname($logFile))) {
            file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
        }
        
    } catch (Exception $e) {
        // Silently fail - don't break cron due to logging errors
        error_log("Analytics cron logging failed: " . $e->getMessage());
    }
}