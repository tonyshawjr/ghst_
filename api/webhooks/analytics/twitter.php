<?php
/**
 * Twitter Analytics Webhook Handler
 * 
 * Dedicated webhook for Twitter analytics data collection
 * Processes real-time engagement and performance metrics
 */

require_once '../../../config.php';
require_once '../../../includes/Database.php';
require_once '../../../includes/AnalyticsCollector.php';

// Twitter webhooks use different verification
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Twitter Account Activity API verification
    $crcToken = $_GET['crc_token'] ?? '';
    
    if ($crcToken) {
        $hash = hash_hmac('sha256', $crcToken, TWITTER_CONSUMER_SECRET, true);
        $response = [
            'response_token' => 'sha256=' . base64_encode($hash)
        ];
        
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
    
    http_response_code(400);
    exit;
}

// Handle webhook POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = file_get_contents('php://input');
    $signature = $_SERVER['HTTP_X_TWITTER_WEBHOOKS_SIGNATURE'] ?? '';
    
    // Verify webhook signature
    $expectedSignature = 'sha256=' . base64_encode(hash_hmac('sha256', $body, TWITTER_CONSUMER_SECRET, true));
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
             VALUES ('twitter_analytics', ?, ?, NOW())"
        );
        $stmt->execute([getTwitterEventType($data), $body]);
        
        // Process different webhook types
        processTwitterAnalyticsWebhook($data);
        
        // Always respond 200 OK
        http_response_code(200);
        echo 'OK';
        
    } catch (Exception $e) {
        // Log error but still respond 200 to prevent retries
        error_log('Twitter analytics webhook error: ' . $e->getMessage());
        http_response_code(200);
        echo 'OK';
    }
    exit;
}

// Invalid request method
http_response_code(405);
exit;

/**
 * Get Twitter event type from webhook data
 */
function getTwitterEventType($data) {
    if (isset($data['tweet_create_events'])) return 'tweet_create';
    if (isset($data['tweet_delete_events'])) return 'tweet_delete';
    if (isset($data['favorite_events'])) return 'favorite';
    if (isset($data['follow_events'])) return 'follow';
    if (isset($data['tweet_insights'])) return 'tweet_insights';
    if (isset($data['user_insights'])) return 'user_insights';
    
    return 'unknown';
}

/**
 * Process Twitter analytics webhooks
 */
function processTwitterAnalyticsWebhook($data) {
    global $db;
    
    // Handle tweet insights (analytics data)
    if (isset($data['tweet_insights'])) {
        foreach ($data['tweet_insights'] as $insight) {
            handleTwitterTweetInsights($insight);
        }
    }
    
    // Handle user insights (follower analytics)
    if (isset($data['user_insights'])) {
        foreach ($data['user_insights'] as $insight) {
            handleTwitterUserInsights($insight);
        }
    }
    
    // Handle engagement events
    if (isset($data['favorite_events'])) {
        foreach ($data['favorite_events'] as $event) {
            handleTwitterEngagementEvent($event, 'like');
        }
    }
    
    if (isset($data['retweet_events'])) {
        foreach ($data['retweet_events'] as $event) {
            handleTwitterEngagementEvent($event, 'retweet');
        }
    }
    
    if (isset($data['reply_events'])) {
        foreach ($data['reply_events'] as $event) {
            handleTwitterEngagementEvent($event, 'reply');
        }
    }
    
    // Handle follower events
    if (isset($data['follow_events'])) {
        foreach ($data['follow_events'] as $event) {
            handleTwitterFollowEvent($event);
        }
    }
}

/**
 * Handle Twitter tweet insights
 */
function handleTwitterTweetInsights($insight) {
    global $db;
    
    try {
        $tweetId = $insight['tweet_id'] ?? null;
        if (!$tweetId) return;
        
        // Find account by tweet author
        $userId = $insight['user_id'] ?? null;
        if (!$userId) return;
        
        $stmt = $db->prepare(
            "SELECT * FROM accounts 
             WHERE platform = 'twitter' 
             AND platform_user_id = ? 
             AND is_active = 1"
        );
        $stmt->execute([$userId]);
        $account = $stmt->fetch();
        
        if (!$account) return;
        
        // Find corresponding post
        $stmt = $db->prepare(
            "SELECT id FROM posts 
             WHERE JSON_EXTRACT(platform_posts_json, '$.twitter') = ?
             AND client_id = ?"
        );
        $stmt->execute([$tweetId, $account['client_id']]);
        $post = $stmt->fetch();
        
        if (!$post) return;
        
        // Process tweet metrics
        $metrics = $insight['metrics'] ?? [];
        $analyticsData = [
            'impressions' => $metrics['impressions'] ?? 0,
            'engagement_rate' => 0,
            'clicks' => ($metrics['url_link_clicks'] ?? 0) + ($metrics['user_profile_clicks'] ?? 0),
            'shares' => $metrics['retweet_count'] ?? 0,
            'comments' => $metrics['reply_count'] ?? 0,
            'likes' => $metrics['like_count'] ?? 0,
            'saves' => $metrics['bookmark_count'] ?? 0,
            'video_views' => $metrics['video_view_count'] ?? 0
        ];
        
        // Calculate engagement rate
        if ($analyticsData['impressions'] > 0) {
            $totalEngagement = $analyticsData['likes'] + $analyticsData['comments'] + 
                             $analyticsData['shares'] + $analyticsData['saves'];
            $analyticsData['engagement_rate'] = round(($totalEngagement / $analyticsData['impressions']) * 100, 2);
        }
        
        // Store analytics
        storePostAnalytics($post['id'], $account['id'], $tweetId, $analyticsData);
        
    } catch (Exception $e) {
        error_log('Twitter tweet insights error: ' . $e->getMessage());
    }
}

/**
 * Handle Twitter user insights
 */
function handleTwitterUserInsights($insight) {
    global $db;
    
    try {
        $userId = $insight['user_id'] ?? null;
        if (!$userId) return;
        
        // Find account
        $stmt = $db->prepare(
            "SELECT * FROM accounts 
             WHERE platform = 'twitter' 
             AND platform_user_id = ? 
             AND is_active = 1"
        );
        $stmt->execute([$userId]);
        $account = $stmt->fetch();
        
        if (!$account) return;
        
        // Process user metrics
        $metrics = $insight['metrics'] ?? [];
        $followerData = [
            'follower_count' => $metrics['followers_count'] ?? 0,
            'following_count' => $metrics['following_count'] ?? 0
        ];
        
        // Store follower analytics
        storeFollowerAnalytics($account['id'], $followerData);
        
    } catch (Exception $e) {
        error_log('Twitter user insights error: ' . $e->getMessage());
    }
}

/**
 * Handle Twitter engagement events
 */
function handleTwitterEngagementEvent($event, $eventType) {
    global $db;
    
    try {
        $tweetId = $event['favorited_status']['id_str'] ?? 
                   $event['retweeted_status']['id_str'] ?? 
                   $event['in_reply_to_status_id_str'] ?? null;
        
        if (!$tweetId) return;
        
        // Find the post and update real-time metrics
        $stmt = $db->prepare(
            "SELECT p.id, a.id as account_id FROM posts p
             JOIN accounts a ON p.client_id = a.client_id
             WHERE JSON_EXTRACT(p.platform_posts_json, '$.twitter') = ?
             AND a.platform = 'twitter' AND a.is_active = 1"
        );
        $stmt->execute([$tweetId]);
        $result = $stmt->fetch();
        
        if (!$result) return;
        
        // Update the specific metric
        $columnMap = [
            'like' => 'likes',
            'retweet' => 'shares',
            'reply' => 'comments'
        ];
        
        $column = $columnMap[$eventType] ?? null;
        if (!$column) return;
        
        $stmt = $db->prepare("
            INSERT INTO post_analytics (post_id, account_id, platform_post_id, {$column}, last_updated)
            VALUES (?, ?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE
                {$column} = {$column} + 1,
                last_updated = NOW()
        ");
        
        $stmt->execute([$result['id'], $result['account_id'], $tweetId]);
        
        // Recalculate engagement rate
        updateEngagementRate($result['id'], $result['account_id']);
        
    } catch (Exception $e) {
        error_log('Twitter engagement event error: ' . $e->getMessage());
    }
}

/**
 * Handle Twitter follow events
 */
function handleTwitterFollowEvent($event) {
    global $db;
    
    try {
        $userId = $event['target']['id_str'] ?? null;
        if (!$userId) return;
        
        // Check if this is our account being followed/unfollowed
        $stmt = $db->prepare(
            "SELECT * FROM accounts 
             WHERE platform = 'twitter' 
             AND platform_user_id = ? 
             AND is_active = 1"
        );
        $stmt->execute([$userId]);
        $account = $stmt->fetch();
        
        if (!$account) return;
        
        $eventType = $event['type'] ?? '';
        $increment = ($eventType === 'follow') ? 1 : -1;
        
        // Update today's follower analytics
        $stmt = $db->prepare("
            INSERT INTO follower_analytics (account_id, date, follower_count, daily_growth)
            SELECT ?, CURDATE(), 
                   COALESCE((SELECT follower_count FROM follower_analytics 
                            WHERE account_id = ? AND date < CURDATE() 
                            ORDER BY date DESC LIMIT 1), 0) + ?,
                   ?
            ON DUPLICATE KEY UPDATE
                follower_count = follower_count + VALUES(daily_growth),
                daily_growth = daily_growth + VALUES(daily_growth)
        ");
        
        $stmt->execute([$account['id'], $account['id'], $increment, $increment]);
        
    } catch (Exception $e) {
        error_log('Twitter follow event error: ' . $e->getMessage());
    }
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
                video_views, last_updated
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                impressions = GREATEST(impressions, VALUES(impressions)),
                reach = GREATEST(reach, VALUES(reach)),
                engagement_rate = VALUES(engagement_rate),
                clicks = GREATEST(clicks, VALUES(clicks)),
                shares = GREATEST(shares, VALUES(shares)),
                saves = GREATEST(saves, VALUES(saves)),
                comments = GREATEST(comments, VALUES(comments)),
                likes = GREATEST(likes, VALUES(likes)),
                video_views = GREATEST(video_views, VALUES(video_views)),
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
            $analytics['video_views'] ?? 0
        ]);
        
    } catch (Exception $e) {
        error_log('Failed to store Twitter post analytics: ' . $e->getMessage());
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
        error_log('Failed to store Twitter follower analytics: ' . $e->getMessage());
    }
}

/**
 * Update engagement rate for a post
 */
function updateEngagementRate($postId, $accountId) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            UPDATE post_analytics 
            SET engagement_rate = CASE 
                WHEN impressions > 0 THEN ROUND(((likes + comments + shares + saves) / impressions) * 100, 2)
                WHEN reach > 0 THEN ROUND(((likes + comments + shares + saves) / reach) * 100, 2)
                ELSE 0 
            END
            WHERE post_id = ? AND account_id = ?
        ");
        
        $stmt->execute([$postId, $accountId]);
        
    } catch (Exception $e) {
        error_log('Failed to update engagement rate: ' . $e->getMessage());
    }
}