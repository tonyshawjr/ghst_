<?php
/**
 * Facebook/Instagram Webhook Handler
 * 
 * Receives real-time updates from Facebook/Instagram
 * about posts, comments, mentions, etc.
 */

require_once '../../config.php';
require_once '../../includes/Database.php';

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
    
    // Update analytics data
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
}