<?php
/**
 * Facebook Analytics Webhook Handler
 * 
 * Dedicated webhook for Facebook/Instagram analytics data collection
 * Processes real-time engagement and performance metrics
 */

require_once '../../../config.php';
require_once '../../../includes/Database.php';
require_once '../../../includes/AnalyticsCollector.php';

// Verify webhook (for initial setup)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $mode = $_GET['hub_mode'] ?? '';
    $token = $_GET['hub_verify_token'] ?? '';
    $challenge = $_GET['hub_challenge'] ?? '';
    
    // Check if this is a valid verification request
    if ($mode === 'subscribe' && $token === WEBHOOK_VERIFY_TOKEN) {
        // Respond with challenge to verify webhook
        http_response_code(200);
        echo $challenge;
        exit;
    } else {
        // Invalid verification token
        http_response_code(403);
        exit;
    }
}

// Handle webhook POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = file_get_contents('php://input');
    $signature = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
    
    // Verify webhook signature
    $expectedSignature = 'sha256=' . hash_hmac('sha256', $body, FB_APP_SECRET);
    if (!hash_equals($expectedSignature, $signature)) {
        http_response_code(403);
        exit;
    }
    
    // Parse webhook data
    $data = json_decode($body, true);
    
    // Process webhook
    try {
        $db = Database::getInstance();
        
        // Log webhook for debugging
        $stmt = $db->prepare(
            "INSERT INTO webhook_logs (platform, event_type, payload, created_at) 
             VALUES ('facebook_analytics', ?, ?, NOW())"
        );
        $stmt->execute([$data['object'] ?? 'unknown', $body]);
        
        // Process different webhook types
        if ($data['object'] === 'instagram') {
            processInstagramAnalyticsWebhook($data);
        } elseif ($data['object'] === 'page') {
            processFacebookAnalyticsWebhook($data);
        }
        
        // Always respond 200 OK
        http_response_code(200);
        echo 'EVENT_RECEIVED';
        
    } catch (Exception $e) {
        // Log error but still respond 200 to prevent retries
        error_log('Facebook analytics webhook error: ' . $e->getMessage());
        http_response_code(200);
        echo 'EVENT_RECEIVED';
    }
    exit;
}

// Invalid request method
http_response_code(405);
exit;

/**
 * Process Instagram analytics webhooks
 */
function processInstagramAnalyticsWebhook($data) {
    global $db;
    
    foreach ($data['entry'] as $entry) {
        $instagramId = $entry['id'];
        
        // Find account by Instagram ID
        $stmt = $db->prepare(
            "SELECT * FROM accounts 
             WHERE platform = 'instagram' 
             AND platform_user_id = ? 
             AND is_active = 1"
        );
        $stmt->execute([$instagramId]);
        $account = $stmt->fetch();
        
        if (!$account) {
            continue;
        }
        
        // Process changes
        foreach ($entry['changes'] as $change) {
            $field = $change['field'];
            $value = $change['value'];
            
            switch ($field) {
                case 'story_insights':
                    handleInstagramStoryAnalytics($account, $value);
                    break;
                case 'media_insights':
                    handleInstagramMediaAnalytics($account, $value);
                    break;
                case 'account_insights':
                    handleInstagramAccountAnalytics($account, $value);
                    break;
            }
        }
    }
}

/**
 * Process Facebook analytics webhooks
 */
function processFacebookAnalyticsWebhook($data) {
    global $db;
    
    foreach ($data['entry'] as $entry) {
        $pageId = $entry['id'];
        
        // Find account by page ID
        $stmt = $db->prepare(
            "SELECT * FROM accounts 
             WHERE platform = 'facebook' 
             AND JSON_EXTRACT(account_data, '$.page_id') = ? 
             AND is_active = 1"
        );
        $stmt->execute([$pageId]);
        $account = $stmt->fetch();
        
        if (!$account) {
            continue;
        }
        
        // Process changes
        foreach ($entry['changes'] as $change) {
            $field = $change['field'];
            $value = $change['value'];
            
            switch ($field) {
                case 'page_insights':
                    handleFacebookPageAnalytics($account, $value);
                    break;
                case 'post_insights':
                    handleFacebookPostAnalytics($account, $value);
                    break;
                case 'video_insights':
                    handleFacebookVideoAnalytics($account, $value);
                    break;
            }
        }
    }
}

/**
 * Handle Instagram story analytics
 */
function handleInstagramStoryAnalytics($account, $data) {
    global $db;
    
    try {
        $mediaId = $data['media_id'] ?? null;
        if (!$mediaId) return;
        
        // Find corresponding post
        $stmt = $db->prepare(
            "SELECT id FROM posts 
             WHERE JSON_EXTRACT(platform_posts_json, '$.instagram') = ?
             AND client_id = ?"
        );
        $stmt->execute([$mediaId, $account['client_id']]);
        $post = $stmt->fetch();
        
        if (!$post) return;
        
        // Process story metrics
        $metrics = $data['metrics'] ?? [];
        $analyticsData = [
            'impressions' => 0,
            'reach' => 0,
            'story_exits' => 0,
            'story_taps_forward' => 0,
            'story_taps_back' => 0
        ];
        
        foreach ($metrics as $metric) {
            $name = $metric['name'];
            $value = $metric['values'][0]['value'] ?? 0;
            
            switch ($name) {
                case 'impressions':
                    $analyticsData['impressions'] = $value;
                    break;
                case 'reach':
                    $analyticsData['reach'] = $value;
                    break;
                case 'exits':
                    $analyticsData['story_exits'] = $value;
                    break;
                case 'taps_forward':
                    $analyticsData['story_taps_forward'] = $value;
                    break;
                case 'taps_back':
                    $analyticsData['story_taps_back'] = $value;
                    break;
            }
        }
        
        // Store analytics
        storePostAnalytics($post['id'], $account['id'], $mediaId, $analyticsData);
        
    } catch (Exception $e) {
        error_log('Instagram story analytics error: ' . $e->getMessage());
    }
}

/**
 * Handle Instagram media analytics
 */
function handleInstagramMediaAnalytics($account, $data) {
    global $db;
    
    try {
        $mediaId = $data['media_id'] ?? null;
        if (!$mediaId) return;
        
        // Find corresponding post
        $stmt = $db->prepare(
            "SELECT id FROM posts 
             WHERE JSON_EXTRACT(platform_posts_json, '$.instagram') = ?
             AND client_id = ?"
        );
        $stmt->execute([$mediaId, $account['client_id']]);
        $post = $stmt->fetch();
        
        if (!$post) return;
        
        // Process media metrics
        $metrics = $data['metrics'] ?? [];
        $analyticsData = [
            'impressions' => 0,
            'reach' => 0,
            'engagement_rate' => 0,
            'likes' => 0,
            'comments' => 0,
            'shares' => 0,
            'saves' => 0,
            'video_views' => 0,
            'profile_visits' => 0,
            'website_clicks' => 0
        ];
        
        foreach ($metrics as $metric) {
            $name = $metric['name'];
            $value = $metric['values'][0]['value'] ?? 0;
            
            if (array_key_exists($name, $analyticsData)) {
                $analyticsData[$name] = $value;
            }
        }
        
        // Calculate engagement rate if we have reach
        if ($analyticsData['reach'] > 0) {
            $totalEngagement = $analyticsData['likes'] + $analyticsData['comments'] + 
                             $analyticsData['shares'] + $analyticsData['saves'];
            $analyticsData['engagement_rate'] = round(($totalEngagement / $analyticsData['reach']) * 100, 2);
        }
        
        // Store analytics
        storePostAnalytics($post['id'], $account['id'], $mediaId, $analyticsData);
        
    } catch (Exception $e) {
        error_log('Instagram media analytics error: ' . $e->getMessage());
    }
}

/**
 * Handle Instagram account analytics
 */
function handleInstagramAccountAnalytics($account, $data) {
    global $db;
    
    try {
        $metrics = $data['metrics'] ?? [];
        $followerData = [
            'follower_count' => 0,
            'following_count' => 0
        ];
        
        foreach ($metrics as $metric) {
            $name = $metric['name'];
            $value = $metric['values'][0]['value'] ?? 0;
            
            switch ($name) {
                case 'follower_count':
                    $followerData['follower_count'] = $value;
                    break;
                case 'following_count':
                    $followerData['following_count'] = $value;
                    break;
            }
        }
        
        // Store follower analytics
        storeFollowerAnalytics($account['id'], $followerData);
        
    } catch (Exception $e) {
        error_log('Instagram account analytics error: ' . $e->getMessage());
    }
}

/**
 * Handle Facebook page analytics
 */
function handleFacebookPageAnalytics($account, $data) {
    global $db;
    
    try {
        $metrics = $data['metrics'] ?? [];
        $followerData = [
            'follower_count' => 0,
            'following_count' => 0
        ];
        
        foreach ($metrics as $metric) {
            $name = $metric['name'];
            $value = $metric['values'][0]['value'] ?? 0;
            
            switch ($name) {
                case 'page_fans':
                    $followerData['follower_count'] = $value;
                    break;
                case 'page_follows':
                    $followerData['following_count'] = $value;
                    break;
            }
        }
        
        // Store follower analytics
        storeFollowerAnalytics($account['id'], $followerData);
        
    } catch (Exception $e) {
        error_log('Facebook page analytics error: ' . $e->getMessage());
    }
}

/**
 * Handle Facebook post analytics
 */
function handleFacebookPostAnalytics($account, $data) {
    global $db;
    
    try {
        $postId = $data['post_id'] ?? null;
        if (!$postId) return;
        
        // Find corresponding post
        $stmt = $db->prepare(
            "SELECT id FROM posts 
             WHERE JSON_EXTRACT(platform_posts_json, '$.facebook') = ?
             AND client_id = ?"
        );
        $stmt->execute([$postId, $account['client_id']]);
        $post = $stmt->fetch();
        
        if (!$post) return;
        
        // Process post metrics
        $metrics = $data['metrics'] ?? [];
        $analyticsData = [
            'impressions' => 0,
            'reach' => 0,
            'engagement_rate' => 0,
            'clicks' => 0,
            'shares' => 0,
            'comments' => 0,
            'likes' => 0,
            'reactions' => [],
            'video_views' => 0
        ];
        
        foreach ($metrics as $metric) {
            $name = $metric['name'];
            $value = $metric['values'][0]['value'] ?? 0;
            
            switch ($name) {
                case 'post_impressions':
                    $analyticsData['impressions'] = $value;
                    break;
                case 'post_impressions_unique':
                    $analyticsData['reach'] = $value;
                    break;
                case 'post_clicks':
                    $analyticsData['clicks'] = $value;
                    break;
                case 'post_video_views':
                    $analyticsData['video_views'] = $value;
                    break;
                default:
                    if (strpos($name, 'post_reactions_') === 0) {
                        $reactionType = str_replace(['post_reactions_', '_total'], '', $name);
                        $analyticsData['reactions'][$reactionType] = $value;
                        $analyticsData['likes'] += $value;
                    }
            }
        }
        
        // Get additional data from post object
        if (isset($data['post_data'])) {
            $postData = $data['post_data'];
            $analyticsData['comments'] = $postData['comments']['summary']['total_count'] ?? 0;
            $analyticsData['shares'] = $postData['shares']['count'] ?? 0;
        }
        
        // Calculate engagement rate
        if ($analyticsData['reach'] > 0) {
            $totalEngagement = $analyticsData['likes'] + $analyticsData['comments'] + $analyticsData['shares'];
            $analyticsData['engagement_rate'] = round(($totalEngagement / $analyticsData['reach']) * 100, 2);
        }
        
        // Store analytics
        storePostAnalytics($post['id'], $account['id'], $postId, $analyticsData);
        
    } catch (Exception $e) {
        error_log('Facebook post analytics error: ' . $e->getMessage());
    }
}

/**
 * Handle Facebook video analytics
 */
function handleFacebookVideoAnalytics($account, $data) {
    // Similar to post analytics but focused on video metrics
    handleFacebookPostAnalytics($account, $data);
}

/**
 * Store post analytics in database
 */
function storePostAnalytics($postId, $accountId, $platformPostId, $analytics) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            INSERT INTO post_analytics (
                post_id, account_id, platform_post_id, impressions, reach, 
                engagement_rate, clicks, shares, saves, comments, likes, 
                reactions, video_views, story_exits, story_taps_forward, 
                story_taps_back, profile_visits, website_clicks, last_updated
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                impressions = GREATEST(impressions, VALUES(impressions)),
                reach = GREATEST(reach, VALUES(reach)),
                engagement_rate = VALUES(engagement_rate),
                clicks = GREATEST(clicks, VALUES(clicks)),
                shares = GREATEST(shares, VALUES(shares)),
                saves = GREATEST(saves, VALUES(saves)),
                comments = GREATEST(comments, VALUES(comments)),
                likes = GREATEST(likes, VALUES(likes)),
                reactions = VALUES(reactions),
                video_views = GREATEST(video_views, VALUES(video_views)),
                story_exits = GREATEST(story_exits, VALUES(story_exits)),
                story_taps_forward = GREATEST(story_taps_forward, VALUES(story_taps_forward)),
                story_taps_back = GREATEST(story_taps_back, VALUES(story_taps_back)),
                profile_visits = GREATEST(profile_visits, VALUES(profile_visits)),
                website_clicks = GREATEST(website_clicks, VALUES(website_clicks)),
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
            $analytics['story_exits'] ?? 0,
            $analytics['story_taps_forward'] ?? 0,
            $analytics['story_taps_back'] ?? 0,
            $analytics['profile_visits'] ?? 0,
            $analytics['website_clicks'] ?? 0
        ]);
        
    } catch (Exception $e) {
        error_log('Failed to store post analytics: ' . $e->getMessage());
    }
}

/**
 * Store follower analytics in database
 */
function storeFollowerAnalytics($accountId, $data) {
    global $db;
    
    try {
        // Get previous day's data to calculate growth
        $stmt = $db->prepare(
            "SELECT follower_count FROM follower_analytics 
             WHERE account_id = ? 
             ORDER BY date DESC 
             LIMIT 1"
        );
        $stmt->execute([$accountId]);
        $previous = $stmt->fetch();
        
        $previousCount = $previous['follower_count'] ?? 0;
        $currentCount = $data['follower_count'] ?? 0;
        $dailyGrowth = $currentCount - $previousCount;
        
        $stmt = $db->prepare("
            INSERT INTO follower_analytics (
                account_id, date, follower_count, following_count, daily_growth
            ) VALUES (?, CURDATE(), ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                follower_count = VALUES(follower_count),
                following_count = VALUES(following_count),
                daily_growth = VALUES(daily_growth)
        ");
        
        $stmt->execute([
            $accountId,
            $data['follower_count'] ?? 0,
            $data['following_count'] ?? 0,
            $dailyGrowth
        ]);
        
    } catch (Exception $e) {
        error_log('Failed to store follower analytics: ' . $e->getMessage());
    }
}