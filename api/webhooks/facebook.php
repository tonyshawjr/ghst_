<?php
/**
 * Facebook/Instagram Webhook Handler
 * 
 * Receives real-time updates from Facebook/Instagram
 * about posts, comments, mentions, etc.
 */

require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/AnalyticsCollector.php';

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
             VALUES ('facebook', ?, ?, NOW())"
        );
        $stmt->execute([$data['object'] ?? 'unknown', $body]);
        
        // Process different webhook types
        if ($data['object'] === 'instagram') {
            processInstagramWebhook($data);
        } elseif ($data['object'] === 'page') {
            processFacebookWebhook($data);
        }
        
        // Always respond 200 OK
        http_response_code(200);
        echo 'EVENT_RECEIVED';
        
    } catch (Exception $e) {
        // Log error but still respond 200 to prevent retries
        error_log('Facebook webhook error: ' . $e->getMessage());
        http_response_code(200);
        echo 'EVENT_RECEIVED';
    }
    exit;
}

// Invalid request method
http_response_code(405);
exit;

/**
 * Process Instagram webhooks
 */
function processInstagramWebhook($data) {
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
                case 'comments':
                    handleInstagramComment($account, $value);
                    break;
                case 'mentions':
                    handleInstagramMention($account, $value);
                    break;
                case 'story_insights':
                    handleInstagramStoryInsights($account, $value);
                    break;
            }
        }
    }
}

/**
 * Process Facebook page webhooks
 */
function processFacebookWebhook($data) {
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
                case 'feed':
                    handleFacebookFeed($account, $value);
                    break;
                case 'reactions':
                    handleFacebookReactions($account, $value);
                    break;
                case 'comments':
                    handleFacebookComment($account, $value);
                    break;
            }
        }
    }
}

/**
 * Handle Instagram comments
 */
function handleInstagramComment($account, $data) {
    global $db;
    
    // Store comment notification
    $stmt = $db->prepare(
        "INSERT INTO notifications (client_id, type, platform, title, message, data, created_at)
         VALUES (?, 'comment', 'instagram', ?, ?, ?, NOW())"
    );
    
    $title = "New Instagram Comment";
    $message = "New comment on your Instagram post";
    
    $stmt->execute([
        $account['client_id'],
        $title,
        $message,
        json_encode($data)
    ]);
}

/**
 * Handle Instagram mentions
 */
function handleInstagramMention($account, $data) {
    global $db;
    
    // Store mention notification
    $stmt = $db->prepare(
        "INSERT INTO notifications (client_id, type, platform, title, message, data, created_at)
         VALUES (?, 'mention', 'instagram', ?, ?, ?, NOW())"
    );
    
    $title = "Instagram Mention";
    $message = "You were mentioned in an Instagram post";
    
    $stmt->execute([
        $account['client_id'],
        $title,
        $message,
        json_encode($data)
    ]);
}

/**
 * Handle Instagram story insights
 */
function handleInstagramStoryInsights($account, $data) {
    global $db;
    
    // Store real-time analytics data
    storeRealTimeAnalytics($account, 'instagram', $data);
    
    // Update analytics data (legacy format)
    $metrics = $data['metrics'] ?? [];
    
    foreach ($metrics as $metric) {
        $stmt = $db->prepare(
            "INSERT INTO analytics (client_id, platform, post_id, metric_name, metric_value, recorded_at)
             VALUES (?, 'instagram', ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE 
             metric_value = VALUES(metric_value),
             recorded_at = NOW()"
        );
        
        $stmt->execute([
            $account['client_id'],
            $data['media_id'] ?? null,
            $metric['name'],
            $metric['values'][0]['value'] ?? 0
        ]);
    }
}

/**
 * Handle Facebook feed updates
 */
function handleFacebookFeed($account, $data) {
    global $db;
    
    // Check if this is about a post we created
    if (isset($data['post_id'])) {
        $stmt = $db->prepare(
            "SELECT id FROM posts 
             WHERE JSON_EXTRACT(platform_posts_json, '$.facebook') = ?"
        );
        $stmt->execute([$data['post_id']]);
        $post = $stmt->fetch();
        
        if ($post) {
            // Update post metrics
            updatePostMetrics('facebook', $post['id'], $data);
        }
    }
}

/**
 * Handle Facebook reactions
 */
function handleFacebookReactions($account, $data) {
    global $db;
    
    // Store reaction data
    if (isset($data['post_id']) && isset($data['reaction_type'])) {
        $stmt = $db->prepare(
            "INSERT INTO post_reactions (platform, post_id, reaction_type, user_id, created_at)
             VALUES ('facebook', ?, ?, ?, NOW())"
        );
        
        $stmt->execute([
            $data['post_id'],
            $data['reaction_type'],
            $data['user_id'] ?? null
        ]);
    }
}

/**
 * Handle Facebook comments
 */
function handleFacebookComment($account, $data) {
    global $db;
    
    // Store comment notification
    $stmt = $db->prepare(
        "INSERT INTO notifications (client_id, type, platform, title, message, data, created_at)
         VALUES (?, 'comment', 'facebook', ?, ?, ?, NOW())"
    );
    
    $title = "New Facebook Comment";
    $message = $data['message'] ?? "New comment on your Facebook post";
    
    $stmt->execute([
        $account['client_id'],
        $title,
        $message,
        json_encode($data)
    ]);
}

/**
 * Update post metrics from webhook data
 */
function updatePostMetrics($platform, $postId, $data) {
    global $db;
    
    $metrics = [
        'likes' => $data['likes']['count'] ?? null,
        'comments' => $data['comments']['count'] ?? null,
        'shares' => $data['shares']['count'] ?? null,
        'views' => $data['views'] ?? null,
    ];
    
    // Update legacy post_metrics table
    foreach ($metrics as $name => $value) {
        if ($value !== null) {
            $stmt = $db->prepare(
                "INSERT INTO post_metrics (post_id, platform, metric_name, metric_value, updated_at)
                 VALUES (?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE 
                 metric_value = VALUES(metric_value),
                 updated_at = NOW()"
            );
            
            $stmt->execute([$postId, $platform, $name, $value]);
        }
    }
    
    // Update post_analytics table with real-time data
    updatePostAnalyticsFromWebhook($postId, $platform, $data);
}

/**
 * Store real-time analytics data from webhooks
 */
function storeRealTimeAnalytics($account, $platform, $data) {
    global $db;
    
    try {
        // Find the post this analytics data belongs to
        $mediaId = $data['media_id'] ?? $data['post_id'] ?? null;
        if (!$mediaId) return;
        
        // Find post by platform post ID
        $stmt = $db->prepare(
            "SELECT p.id FROM posts p 
             WHERE JSON_EXTRACT(p.platform_posts_json, ?) = ?
             AND p.client_id = ?"
        );
        
        $platformPath = '$."' . $platform . '"';
        $stmt->execute([$platformPath, $mediaId, $account['client_id']]);
        $post = $stmt->fetch();
        
        if (!$post) return;
        
        // Extract metrics from webhook data
        $analytics = extractAnalyticsFromWebhook($platform, $data);
        
        if ($analytics) {
            // Store in post_analytics table
            $stmt = $db->prepare(
                "INSERT INTO post_analytics (
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
                    last_updated = NOW()"
            );
            
            $stmt->execute([
                $post['id'],
                $account['id'],
                $mediaId,
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
        }
        
    } catch (Exception $e) {
        error_log('Failed to store real-time analytics: ' . $e->getMessage());
    }
}

/**
 * Extract analytics data from webhook payload
 */
function extractAnalyticsFromWebhook($platform, $data) {
    $analytics = [];
    
    switch ($platform) {
        case 'instagram':
            if (isset($data['metrics'])) {
                foreach ($data['metrics'] as $metric) {
                    $name = $metric['name'];
                    $value = $metric['values'][0]['value'] ?? 0;
                    
                    switch ($name) {
                        case 'impressions':
                            $analytics['impressions'] = $value;
                            break;
                        case 'reach':
                            $analytics['reach'] = $value;
                            break;
                        case 'video_views':
                            $analytics['video_views'] = $value;
                            break;
                    }
                }
            }
            break;
            
        case 'facebook':
            // Extract Facebook-specific metrics
            $analytics['likes'] = $data['likes']['count'] ?? 0;
            $analytics['comments'] = $data['comments']['count'] ?? 0;
            $analytics['shares'] = $data['shares']['count'] ?? 0;
            break;
    }
    
    return $analytics;
}

/**
 * Update post analytics from webhook data
 */
function updatePostAnalyticsFromWebhook($postId, $platform, $data) {
    global $db;
    
    // Get account for this post and platform
    $stmt = $db->prepare(
        "SELECT a.id FROM accounts a
         JOIN posts p ON a.client_id = p.client_id
         WHERE p.id = ? AND a.platform = ? AND a.is_active = 1"
    );
    $stmt->execute([$postId, $platform]);
    $account = $stmt->fetch();
    
    if (!$account) return;
    
    // Extract platform post ID
    $stmt = $db->prepare("SELECT platform_posts_json FROM posts WHERE id = ?");
    $stmt->execute([$postId]);
    $post = $stmt->fetch();
    
    if (!$post) return;
    
    $platformPosts = json_decode($post['platform_posts_json'], true) ?: [];
    $platformPostId = $platformPosts[$platform] ?? null;
    
    if (!$platformPostId) return;
    
    // Extract and store analytics
    $analytics = extractAnalyticsFromWebhook($platform, $data);
    
    if ($analytics) {
        $stmt = $db->prepare(
            "INSERT INTO post_analytics (
                post_id, account_id, platform_post_id, likes, comments, shares, last_updated
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                likes = GREATEST(likes, VALUES(likes)),
                comments = GREATEST(comments, VALUES(comments)),
                shares = GREATEST(shares, VALUES(shares)),
                last_updated = NOW()"
        );
        
        $stmt->execute([
            $postId,
            $account['id'],
            $platformPostId,
            $analytics['likes'] ?? 0,
            $analytics['comments'] ?? 0,
            $analytics['shares'] ?? 0
        ]);
    }
}