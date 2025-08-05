<?php
/**
 * Analytics Collector Class
 * 
 * Main orchestrator for collecting analytics data from all social media platforms
 * Handles both real-time webhook data and batch collection with rate limiting
 */

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/platforms/Platform.php';
require_once __DIR__ . '/RateLimiter.php';
require_once __DIR__ . '/exceptions/PlatformExceptions.php';

class AnalyticsCollector {
    private $db;
    private $rateLimiter;
    private $platforms = ['facebook', 'instagram', 'twitter', 'linkedin'];
    
    // Analytics collection settings
    private $batchSize = 50;
    private $maxRetries = 3;
    private $retryDelay = 30; // seconds
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->rateLimiter = new RateLimiter();
    }
    
    /**
     * Collect analytics for all active accounts
     * 
     * @param string|null $platform Specific platform or null for all
     * @param int|null $clientId Specific client or null for all
     * @param array $options Collection options
     * @return array Results summary
     */
    public function collectAnalytics($platform = null, $clientId = null, $options = []) {
        $startTime = microtime(true);
        $results = [
            'success' => true,
            'collected' => 0,
            'failed' => 0,
            'errors' => [],
            'platforms' => [],
            'duration' => 0
        ];
        
        try {
            // Get active accounts to process
            $accounts = $this->getActiveAccounts($platform, $clientId);
            
            $this->logActivity('info', 'Starting analytics collection', [
                'account_count' => count($accounts),
                'platform' => $platform,
                'client_id' => $clientId,
                'options' => $options
            ]);
            
            foreach ($accounts as $account) {
                try {
                    // Check rate limits before processing
                    $this->checkRateLimit($account['platform'], $account['client_id']);
                    
                    // Collect platform-specific analytics
                    $platformResult = $this->collectPlatformAnalytics($account, $options);
                    
                    if ($platformResult['success']) {
                        $results['collected']++;
                        $results['platforms'][$account['platform']] = 
                            ($results['platforms'][$account['platform']] ?? 0) + 1;
                    } else {
                        $results['failed']++;
                        $results['errors'][] = [
                            'account_id' => $account['id'],
                            'platform' => $account['platform'],
                            'error' => $platformResult['error']
                        ];
                    }
                    
                    // Record API action for rate limiting
                    $this->rateLimiter->recordAction(
                        $account['platform'],
                        $account['client_id'],
                        'analytics_collection'
                    );
                    
                } catch (PlatformRateLimitException $e) {
                    $this->handleRateLimit($account, $e);
                    $results['failed']++;
                    $results['errors'][] = [
                        'account_id' => $account['id'],
                        'platform' => $account['platform'],
                        'error' => 'Rate limit exceeded: ' . $e->getMessage()
                    ];
                } catch (Exception $e) {
                    $results['failed']++;
                    $results['errors'][] = [
                        'account_id' => $account['id'],
                        'platform' => $account['platform'],
                        'error' => $e->getMessage()
                    ];
                    
                    $this->logActivity('error', 'Failed to collect analytics for account', [
                        'account_id' => $account['id'],
                        'platform' => $account['platform'],
                        'error' => $e->getMessage()
                    ]);
                }
                
                // Small delay between accounts to be respectful
                usleep(250000); // 250ms
            }
            
            // Calculate derived metrics after all data is collected
            $this->calculateDerivedMetrics($clientId);
            
            // Clean up old analytics data
            if (!isset($options['skip_cleanup'])) {
                $this->cleanupOldData();
            }
            
        } catch (Exception $e) {
            $results['success'] = false;
            $results['errors'][] = ['general' => $e->getMessage()];
            
            $this->logActivity('error', 'Analytics collection failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
        
        $results['duration'] = round(microtime(true) - $startTime, 2);
        
        $this->logActivity('info', 'Analytics collection completed', $results);
        
        return $results;
    }
    
    /**
     * Collect analytics for a specific platform account
     */
    private function collectPlatformAnalytics($account, $options = []) {
        try {
            $platformClass = ucfirst($account['platform']) . 'Platform';
            
            if (!class_exists($platformClass)) {
                throw new Exception("Platform class {$platformClass} not found");
            }
            
            $platform = new $platformClass($account['id']);
            
            // Check if token needs refresh
            if ($platform->isTokenExpired() && method_exists($platform, 'refreshToken')) {
                $platform->refreshToken();
            }
            
            $collected = 0;
            
            // Collect post analytics
            $collected += $this->collectPostAnalytics($platform, $account, $options);
            
            // Collect follower analytics
            $collected += $this->collectFollowerAnalytics($platform, $account, $options);
            
            // Collect content type analytics
            $collected += $this->collectContentTypeAnalytics($platform, $account, $options);
            
            // Collect hashtag analytics
            $collected += $this->collectHashtagAnalytics($platform, $account, $options);
            
            // Update account last collected timestamp
            $this->updateAccountLastCollected($account['id']);
            
            return [
                'success' => true,
                'collected' => $collected,
                'account_id' => $account['id']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'account_id' => $account['id']
            ];
        }
    }
    
    /**
     * Collect post analytics from platform
     */
    private function collectPostAnalytics($platform, $account, $options = []) {
        $collected = 0;
        
        try {
            // Get posts that need analytics updates
            $posts = $this->getPostsForAnalytics($account, $options);
            
            foreach ($posts as $post) {
                try {
                    // Get platform-specific post ID
                    $platformPosts = json_decode($post['platform_posts_json'], true) ?: [];
                    $platformPostId = $platformPosts[$account['platform']] ?? null;
                    
                    if (!$platformPostId) {
                        continue;
                    }
                    
                    // Fetch analytics from platform
                    $analytics = $this->fetchPostAnalytics($platform, $platformPostId, $account['platform']);
                    
                    if ($analytics) {
                        // Store analytics data
                        $this->storePostAnalytics($post['id'], $account['id'], $platformPostId, $analytics);
                        $collected++;
                    }
                    
                } catch (Exception $e) {
                    $this->logActivity('warning', 'Failed to collect post analytics', [
                        'post_id' => $post['id'],
                        'platform_post_id' => $platformPostId ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
        } catch (Exception $e) {
            $this->logActivity('error', 'Post analytics collection failed', [
                'account_id' => $account['id'],
                'platform' => $account['platform'],
                'error' => $e->getMessage()
            ]);
        }
        
        return $collected;
    }
    
    /**
     * Collect follower analytics from platform
     */
    private function collectFollowerAnalytics($platform, $account, $options = []) {
        try {
            $followerData = $this->fetchFollowerAnalytics($platform, $account['platform']);
            
            if ($followerData) {
                $this->storeFollowerAnalytics($account['id'], $followerData);
                return 1;
            }
            
        } catch (Exception $e) {
            $this->logActivity('warning', 'Failed to collect follower analytics', [
                'account_id' => $account['id'],
                'platform' => $account['platform'],
                'error' => $e->getMessage()
            ]);
        }
        
        return 0;
    }
    
    /**
     * Collect content type performance analytics
     */
    private function collectContentTypeAnalytics($platform, $account, $options = []) {
        try {
            // This is calculated from existing post data
            $this->calculateContentTypeAnalytics($account['id']);
            return 1;
            
        } catch (Exception $e) {
            $this->logActivity('warning', 'Failed to collect content type analytics', [
                'account_id' => $account['id'],
                'platform' => $account['platform'],
                'error' => $e->getMessage()
            ]);
        }
        
        return 0;
    }
    
    /**
     * Collect hashtag performance analytics
     */
    private function collectHashtagAnalytics($platform, $account, $options = []) {
        try {
            // This is calculated from existing post data
            $this->calculateHashtagAnalytics($account['client_id'], $account['platform']);
            return 1;
            
        } catch (Exception $e) {
            $this->logActivity('warning', 'Failed to collect hashtag analytics', [
                'account_id' => $account['id'],
                'platform' => $account['platform'],
                'error' => $e->getMessage()
            ]);
        }
        
        return 0;
    }
    
    /**
     * Fetch post analytics from platform API
     */
    private function fetchPostAnalytics($platform, $platformPostId, $platformName) {
        $analytics = null;
        
        switch ($platformName) {
            case 'facebook':
                $analytics = $this->fetchFacebookPostAnalytics($platform, $platformPostId);
                break;
            case 'instagram':
                $analytics = $this->fetchInstagramPostAnalytics($platform, $platformPostId);
                break;
            case 'twitter':
                $analytics = $this->fetchTwitterPostAnalytics($platform, $platformPostId);
                break;
            case 'linkedin':
                $analytics = $this->fetchLinkedInPostAnalytics($platform, $platformPostId);
                break;
        }
        
        return $analytics;
    }
    
    /**
     * Fetch Facebook post analytics
     */
    private function fetchFacebookPostAnalytics($platform, $postId) {
        try {
            $metrics = [
                'post_impressions',
                'post_impressions_unique',
                'post_engaged_users',
                'post_clicks',
                'post_reactions_like_total',
                'post_reactions_love_total',
                'post_reactions_wow_total',
                'post_reactions_haha_total',
                'post_reactions_sorry_total',
                'post_reactions_anger_total',
                'post_video_views',
                'post_video_complete_views_30s'
            ];
            
            $url = "https://graph.facebook.com/v18.0/{$postId}/insights";
            $params = [
                'metric' => implode(',', $metrics),
                'access_token' => $platform->account['access_token']
            ];
            
            $response = $platform->makeApiRequest($url . '?' . http_build_query($params));
            
            $analytics = [
                'impressions' => 0,
                'reach' => 0,
                'engagement_rate' => 0,
                'clicks' => 0,
                'shares' => 0,
                'saves' => 0,
                'comments' => 0,
                'likes' => 0,
                'reactions' => [],
                'video_views' => 0,
                'video_completion_rate' => 0
            ];
            
            if (isset($response['data'])) {
                foreach ($response['data'] as $metric) {
                    $name = $metric['name'];
                    $value = $metric['values'][0]['value'] ?? 0;
                    
                    switch ($name) {
                        case 'post_impressions':
                            $analytics['impressions'] = $value;
                            break;
                        case 'post_impressions_unique':
                            $analytics['reach'] = $value;
                            break;
                        case 'post_engaged_users':
                            $analytics['engagement_rate'] = $analytics['reach'] > 0 
                                ? round(($value / $analytics['reach']) * 100, 2) 
                                : 0;
                            break;
                        case 'post_clicks':
                            $analytics['clicks'] = $value;
                            break;
                        case 'post_video_views':
                            $analytics['video_views'] = $value;
                            break;
                        default:
                            if (strpos($name, 'post_reactions_') === 0) {
                                $reactionType = str_replace(['post_reactions_', '_total'], '', $name);
                                $analytics['reactions'][$reactionType] = $value;
                                $analytics['likes'] += $value;
                            }
                    }
                }
            }
            
            // Get basic post data for comments and shares
            $postData = $platform->makeApiRequest(
                "https://graph.facebook.com/v18.0/{$postId}?fields=comments.summary(true),shares&access_token=" . 
                $platform->account['access_token']
            );
            
            if (isset($postData['comments']['summary']['total_count'])) {
                $analytics['comments'] = $postData['comments']['summary']['total_count'];
            }
            
            if (isset($postData['shares']['count'])) {
                $analytics['shares'] = $postData['shares']['count'];
            }
            
            return $analytics;
            
        } catch (Exception $e) {
            $this->logActivity('error', 'Facebook analytics fetch failed', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Fetch Instagram post analytics
     */
    private function fetchInstagramPostAnalytics($platform, $postId) {
        try {
            $metrics = [
                'impressions',
                'reach',
                'engagement',
                'likes',
                'comments',
                'shares',
                'saves',
                'video_views'
            ];
            
            $url = "https://graph.facebook.com/v18.0/{$postId}/insights";
            $params = [
                'metric' => implode(',', $metrics),
                'access_token' => $platform->account['access_token']
            ];
            
            $response = $platform->makeApiRequest($url . '?' . http_build_query($params));
            
            $analytics = [
                'impressions' => 0,
                'reach' => 0,
                'engagement_rate' => 0,
                'clicks' => 0,
                'shares' => 0,
                'saves' => 0,
                'comments' => 0,
                'likes' => 0,
                'video_views' => 0
            ];
            
            if (isset($response['data'])) {
                foreach ($response['data'] as $metric) {
                    $name = $metric['name'];
                    $value = $metric['values'][0]['value'] ?? 0;
                    
                    if (isset($analytics[$name])) {
                        $analytics[$name] = $value;
                    }
                }
                
                // Calculate engagement rate
                if ($analytics['reach'] > 0) {
                    $totalEngagement = $analytics['likes'] + $analytics['comments'] + $analytics['shares'] + $analytics['saves'];
                    $analytics['engagement_rate'] = round(($totalEngagement / $analytics['reach']) * 100, 2);
                }
            }
            
            return $analytics;
            
        } catch (Exception $e) {
            $this->logActivity('error', 'Instagram analytics fetch failed', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Fetch Twitter post analytics
     */
    private function fetchTwitterPostAnalytics($platform, $postId) {
        try {
            // Twitter API v2 tweet metrics
            $url = "https://api.twitter.com/2/tweets/{$postId}";
            $params = [
                'tweet.fields' => 'public_metrics,non_public_metrics,promoted_metrics',
                'expansions' => 'author_id'
            ];
            
            $headers = [
                'Authorization: Bearer ' . $platform->account['access_token'],
                'Content-Type: application/json'
            ];
            
            $response = $platform->makeApiRequest($url . '?' . http_build_query($params), 'GET', null, $headers);
            
            $analytics = [
                'impressions' => 0,
                'reach' => 0,
                'engagement_rate' => 0,
                'clicks' => 0,
                'shares' => 0,
                'saves' => 0,
                'comments' => 0,
                'likes' => 0,
                'video_views' => 0
            ];
            
            if (isset($response['data']['public_metrics'])) {
                $metrics = $response['data']['public_metrics'];
                
                $analytics['likes'] = $metrics['like_count'] ?? 0;
                $analytics['shares'] = $metrics['retweet_count'] ?? 0;
                $analytics['comments'] = $metrics['reply_count'] ?? 0;
                $analytics['saves'] = $metrics['bookmark_count'] ?? 0;
            }
            
            if (isset($response['data']['non_public_metrics'])) {
                $metrics = $response['data']['non_public_metrics'];
                
                $analytics['impressions'] = $metrics['impression_count'] ?? 0;
                $analytics['clicks'] = ($metrics['url_link_clicks'] ?? 0) + ($metrics['user_profile_clicks'] ?? 0);
            }
            
            // Calculate engagement rate
            if ($analytics['impressions'] > 0) {
                $totalEngagement = $analytics['likes'] + $analytics['comments'] + $analytics['shares'] + $analytics['saves'];
                $analytics['engagement_rate'] = round(($totalEngagement / $analytics['impressions']) * 100, 2);
            }
            
            return $analytics;
            
        } catch (Exception $e) {
            $this->logActivity('error', 'Twitter analytics fetch failed', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Fetch LinkedIn post analytics
     */
    private function fetchLinkedInPostAnalytics($platform, $postId) {
        try {
            $url = "https://api.linkedin.com/v2/socialActions/{$postId}/statistics";
            $headers = [
                'Authorization: Bearer ' . $platform->account['access_token'],
                'Content-Type: application/json'
            ];
            
            $response = $platform->makeApiRequest($url, 'GET', null, $headers);
            
            $analytics = [
                'impressions' => $response['impressionCount'] ?? 0,
                'reach' => $response['uniqueImpressionsCount'] ?? 0,
                'engagement_rate' => 0,
                'clicks' => $response['clickCount'] ?? 0,
                'shares' => $response['shareCount'] ?? 0,
                'saves' => 0,
                'comments' => $response['commentCount'] ?? 0,
                'likes' => $response['likeCount'] ?? 0,
                'video_views' => 0
            ];
            
            // Calculate engagement rate
            if ($analytics['impressions'] > 0) {
                $totalEngagement = $analytics['likes'] + $analytics['comments'] + $analytics['shares'] + $analytics['clicks'];
                $analytics['engagement_rate'] = round(($totalEngagement / $analytics['impressions']) * 100, 2);
            }
            
            return $analytics;
            
        } catch (Exception $e) {
            $this->logActivity('error', 'LinkedIn analytics fetch failed', [
                'post_id' => $postId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Fetch follower analytics from platform
     */
    private function fetchFollowerAnalytics($platform, $platformName) {
        switch ($platformName) {
            case 'facebook':
                return $this->fetchFacebookFollowerAnalytics($platform);
            case 'instagram':
                return $this->fetchInstagramFollowerAnalytics($platform);
            case 'twitter':
                return $this->fetchTwitterFollowerAnalytics($platform);
            case 'linkedin':
                return $this->fetchLinkedInFollowerAnalytics($platform);
        }
        
        return null;
    }
    
    /**
     * Store post analytics in database
     */
    private function storePostAnalytics($postId, $accountId, $platformPostId, $analytics) {
        $stmt = $this->db->prepare("
            INSERT INTO post_analytics (
                post_id, account_id, platform_post_id, impressions, reach, 
                engagement_rate, clicks, shares, saves, comments, likes, 
                reactions, video_views, video_completion_rate, last_updated
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                impressions = VALUES(impressions),
                reach = VALUES(reach),
                engagement_rate = VALUES(engagement_rate),
                clicks = VALUES(clicks),
                shares = VALUES(shares),
                saves = VALUES(saves),
                comments = VALUES(comments),
                likes = VALUES(likes),
                reactions = VALUES(reactions),
                video_views = VALUES(video_views),
                video_completion_rate = VALUES(video_completion_rate),
                last_updated = NOW()
        ");
        
        $stmt->execute([
            $postId,
            $accountId,
            $platformPostId,
            $analytics['impressions'] ?? 0,
            $analytics['reach'] ?? 0,
            $analytics['engagement_rate'] ?? 0,
            $analytics['clicks'] ?? 0,
            $analytics['shares'] ?? 0,
            $analytics['saves'] ?? 0,
            $analytics['comments'] ?? 0,
            $analytics['likes'] ?? 0,
            json_encode($analytics['reactions'] ?? []),
            $analytics['video_views'] ?? 0,
            $analytics['video_completion_rate'] ?? 0
        ]);
    }
    
    /**
     * Store follower analytics in database
     */
    private function storeFollowerAnalytics($accountId, $data) {
        $stmt = $this->db->prepare("
            INSERT INTO follower_analytics (
                account_id, date, follower_count, following_count, 
                daily_growth, new_followers, unfollows
            ) VALUES (?, CURDATE(), ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                follower_count = VALUES(follower_count),
                following_count = VALUES(following_count),
                daily_growth = VALUES(daily_growth),
                new_followers = VALUES(new_followers),
                unfollows = VALUES(unfollows)
        ");
        
        $stmt->execute([
            $accountId,
            $data['follower_count'] ?? 0,
            $data['following_count'] ?? 0,
            $data['daily_growth'] ?? 0,
            $data['new_followers'] ?? 0,
            $data['unfollows'] ?? 0
        ]);
    }
    
    /**
     * Get active accounts for analytics collection
     */
    private function getActiveAccounts($platform = null, $clientId = null) {
        $sql = "SELECT * FROM accounts WHERE is_active = 1";
        $params = [];
        
        if ($platform) {
            $sql .= " AND platform = ?";
            $params[] = $platform;
        }
        
        if ($clientId) {
            $sql .= " AND client_id = ?";
            $params[] = $clientId;
        }
        
        // Only collect for accounts that haven't been collected recently
        $sql .= " AND (analytics_last_collected IS NULL OR analytics_last_collected < DATE_SUB(NOW(), INTERVAL 1 HOUR))";
        
        $sql .= " ORDER BY analytics_last_collected ASC LIMIT " . $this->batchSize;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get posts that need analytics updates
     */
    private function getPostsForAnalytics($account, $options = []) {
        $sql = "
            SELECT p.* FROM posts p
            LEFT JOIN post_analytics pa ON p.id = pa.post_id AND pa.account_id = ?
            WHERE p.client_id = ? 
            AND p.status = 'published'
            AND JSON_EXTRACT(p.platform_posts_json, ?) IS NOT NULL
            AND (
                pa.last_updated IS NULL 
                OR pa.last_updated < DATE_SUB(NOW(), INTERVAL 6 HOUR)
                OR p.published_at > DATE_SUB(NOW(), INTERVAL 48 HOUR)
            )
            ORDER BY p.published_at DESC
            LIMIT 20
        ";
        
        $platformPath = '$.\"' . $account['platform'] . '\"';
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $account['id'],
            $account['client_id'],
            $platformPath
        ]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Check rate limits before making API calls
     */
    private function checkRateLimit($platform, $clientId) {
        $result = $this->rateLimiter->checkLimit($platform, $clientId, 'analytics_collection');
        
        if (!$result['allowed']) {
            throw new PlatformRateLimitException(
                "Rate limit exceeded for {$platform}. Retry after {$result['retry_after']} seconds."
            );
        }
    }
    
    /**
     * Handle rate limit exceptions
     */
    private function handleRateLimit($account, $exception) {
        // Schedule for retry later
        $retryAfter = $exception->getRetryAfter() ?? 3600; // Default 1 hour
        
        $stmt = $this->db->prepare("
            UPDATE accounts 
            SET analytics_retry_after = DATE_ADD(NOW(), INTERVAL ? SECOND)
            WHERE id = ?
        ");
        
        $stmt->execute([$retryAfter, $account['id']]);
    }
    
    /**
     * Update account last collected timestamp
     */
    private function updateAccountLastCollected($accountId) {
        $stmt = $this->db->prepare("
            UPDATE accounts 
            SET analytics_last_collected = NOW() 
            WHERE id = ?
        ");
        
        $stmt->execute([$accountId]);
    }
    
    /**
     * Calculate derived metrics
     */
    private function calculateDerivedMetrics($clientId = null) {
        // Calculate best posting times
        $this->calculateBestPostingTimes($clientId);
        
        // Calculate content type performance
        $this->calculateContentTypePerformance($clientId);
        
        // Calculate platform comparison metrics
        $this->calculatePlatformComparison($clientId);
    }
    
    /**
     * Calculate best posting times
     */
    private function calculateBestPostingTimes($clientId = null) {
        $sql = "
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
            " . ($clientId ? "AND p.client_id = {$clientId}" : "") . "
            GROUP BY pa.account_id, day_of_week, hour_of_day
            HAVING post_count >= 3
            ON DUPLICATE KEY UPDATE
                avg_engagement_rate = VALUES(avg_engagement_rate),
                avg_reach = VALUES(avg_reach),
                avg_impressions = VALUES(avg_impressions),
                post_count = VALUES(post_count),
                total_engagement = VALUES(total_engagement),
                last_calculated = VALUES(last_calculated)
        ";
        
        $this->db->query($sql);
    }
    
    /**
     * Calculate content type analytics
     */
    private function calculateContentTypeAnalytics($accountId) {
        // This would analyze media types and calculate performance metrics
        // Implementation depends on how media types are stored in your system
    }
    
    /**
     * Calculate hashtag analytics
     */
    private function calculateHashtagAnalytics($clientId, $platform) {
        // Extract hashtags from post content and calculate performance
        // This is a complex operation that would need to parse hashtags from posts
    }
    
    /**
     * Clean up old analytics data
     */
    private function cleanupOldData() {
        // Remove analytics data older than configured retention period
        $retentionDays = defined('ANALYTICS_RETENTION_DAYS') ? ANALYTICS_RETENTION_DAYS : 365;
        
        $tables = [
            'post_analytics' => 'created_at',
            'follower_analytics' => 'created_at',
            'posting_time_analytics' => 'last_calculated'
        ];
        
        foreach ($tables as $table => $dateColumn) {
            $stmt = $this->db->prepare("
                DELETE FROM {$table} 
                WHERE {$dateColumn} < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$retentionDays]);
        }
    }
    
    /**
     * Log analytics collection activity
     */
    private function logActivity($level, $message, $data = []) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO logs (action, level, message, details, created_at)
                VALUES ('analytics_collection', ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $level,
                $message,
                json_encode($data)
            ]);
            
        } catch (Exception $e) {
            // Silently fail - don't break analytics collection due to logging errors
            error_log("Analytics logging failed: " . $e->getMessage());
        }
    }
    
    // Placeholder methods for platform-specific follower analytics
    private function fetchFacebookFollowerAnalytics($platform) { return null; }
    private function fetchInstagramFollowerAnalytics($platform) { return null; }
    private function fetchTwitterFollowerAnalytics($platform) { return null; }
    private function fetchLinkedInFollowerAnalytics($platform) { return null; }
    
    private function calculateContentTypePerformance($clientId) {}
    private function calculatePlatformComparison($clientId) {}
}