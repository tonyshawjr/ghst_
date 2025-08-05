<?php
/**
 * Twitter/X Webhook Handler
 * 
 * Receives real-time updates from Twitter
 * about tweets, mentions, likes, retweets, etc.
 */

require_once '../../config.php';
require_once '../../includes/Database.php';

// Handle CRC (Challenge Response Check) for webhook registration
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['crc_token'])) {
    $crcToken = $_GET['crc_token'];
    
    // Create response token using HMAC-SHA256
    $hash = hash_hmac('sha256', $crcToken, TWITTER_WEBHOOK_SECRET, true);
    $responseToken = base64_encode($hash);
    
    // Send response
    header('Content-Type: application/json');
    echo json_encode(['response_token' => 'sha256=' . $responseToken]);
    exit;
}

// Handle webhook POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = file_get_contents('php://input');
    $signature = $_SERVER['HTTP_X_TWITTER_WEBHOOKS_SIGNATURE'] ?? '';
    
    // Verify webhook signature
    $expectedSignature = 'sha256=' . base64_encode(
        hash_hmac('sha256', $body, TWITTER_WEBHOOK_SECRET, true)
    );
    
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
             VALUES ('twitter', ?, ?, NOW())"
        );
        $eventType = array_keys($data)[0] ?? 'unknown';
        $stmt->execute([$eventType, $body]);
        
        // Process different event types
        if (isset($data['tweet_create_events'])) {
            processTweetCreateEvents($data['tweet_create_events']);
        } elseif (isset($data['favorite_events'])) {
            processFavoriteEvents($data['favorite_events']);
        } elseif (isset($data['retweet_events'])) {
            processRetweetEvents($data['retweet_events']);
        } elseif (isset($data['follow_events'])) {
            processFollowEvents($data['follow_events']);
        } elseif (isset($data['direct_message_events'])) {
            processDirectMessageEvents($data['direct_message_events']);
        }
        
        // Always respond 200 OK
        http_response_code(200);
        
    } catch (Exception $e) {
        // Log error but still respond 200 to prevent retries
        error_log('Twitter webhook error: ' . $e->getMessage());
        http_response_code(200);
    }
    exit;
}

// Invalid request method
http_response_code(405);
exit;

/**
 * Process tweet create events (mentions, replies)
 */
function processTweetCreateEvents($events) {
    global $db;
    
    foreach ($events as $tweet) {
        // Skip if this is our own tweet
        if (isset($tweet['user']['id_str'])) {
            $stmt = $db->prepare(
                "SELECT * FROM accounts 
                 WHERE platform = 'twitter' 
                 AND platform_user_id = ? 
                 AND is_active = 1"
            );
            $stmt->execute([$tweet['user']['id_str']]);
            $account = $stmt->fetch();
            
            if ($account) {
                // This is our own tweet, skip
                continue;
            }
        }
        
        // Check if this is a reply to our tweet
        if (isset($tweet['in_reply_to_status_id_str'])) {
            handleReply($tweet);
        }
        
        // Check for mentions
        if (isset($tweet['entities']['user_mentions'])) {
            foreach ($tweet['entities']['user_mentions'] as $mention) {
                handleMention($tweet, $mention);
            }
        }
    }
}

/**
 * Process favorite (like) events
 */
function processFavoriteEvents($events) {
    global $db;
    
    foreach ($events as $event) {
        $tweetId = $event['favorited_status']['id_str'];
        $userId = $event['user']['id_str'];
        
        // Find the post
        $stmt = $db->prepare(
            "SELECT p.*, a.client_id 
             FROM posts p
             JOIN accounts a ON JSON_EXTRACT(p.platform_posts_json, '$.twitter') = ?
             WHERE a.platform = 'twitter' AND a.is_active = 1"
        );
        $stmt->execute([$tweetId]);
        $post = $stmt->fetch();
        
        if ($post) {
            // Update metrics
            $stmt = $db->prepare(
                "INSERT INTO post_metrics (post_id, platform, metric_name, metric_value, updated_at)
                 VALUES (?, 'twitter', 'likes', 1, NOW())
                 ON DUPLICATE KEY UPDATE 
                 metric_value = metric_value + 1,
                 updated_at = NOW()"
            );
            $stmt->execute([$post['id']]);
            
            // Create notification
            $stmt = $db->prepare(
                "INSERT INTO notifications (client_id, type, platform, title, message, data, created_at)
                 VALUES (?, 'like', 'twitter', ?, ?, ?, NOW())"
            );
            
            $title = "New Twitter Like";
            $message = "@{$event['user']['screen_name']} liked your tweet";
            
            $stmt->execute([
                $post['client_id'],
                $title,
                $message,
                json_encode($event)
            ]);
        }
    }
}

/**
 * Process retweet events
 */
function processRetweetEvents($events) {
    global $db;
    
    foreach ($events as $event) {
        $originalTweetId = $event['retweeted_status']['id_str'];
        $userId = $event['user']['id_str'];
        
        // Find the post
        $stmt = $db->prepare(
            "SELECT p.*, a.client_id 
             FROM posts p
             JOIN accounts a ON JSON_EXTRACT(p.platform_posts_json, '$.twitter') = ?
             WHERE a.platform = 'twitter' AND a.is_active = 1"
        );
        $stmt->execute([$originalTweetId]);
        $post = $stmt->fetch();
        
        if ($post) {
            // Update metrics
            $stmt = $db->prepare(
                "INSERT INTO post_metrics (post_id, platform, metric_name, metric_value, updated_at)
                 VALUES (?, 'twitter', 'retweets', 1, NOW())
                 ON DUPLICATE KEY UPDATE 
                 metric_value = metric_value + 1,
                 updated_at = NOW()"
            );
            $stmt->execute([$post['id']]);
            
            // Create notification
            $stmt = $db->prepare(
                "INSERT INTO notifications (client_id, type, platform, title, message, data, created_at)
                 VALUES (?, 'retweet', 'twitter', ?, ?, ?, NOW())"
            );
            
            $title = "New Retweet";
            $message = "@{$event['user']['screen_name']} retweeted your tweet";
            
            $stmt->execute([
                $post['client_id'],
                $title,
                $message,
                json_encode($event)
            ]);
        }
    }
}

/**
 * Process follow events
 */
function processFollowEvents($events) {
    global $db;
    
    foreach ($events as $event) {
        if ($event['type'] !== 'follow') {
            continue;
        }
        
        // Find account being followed
        $stmt = $db->prepare(
            "SELECT * FROM accounts 
             WHERE platform = 'twitter' 
             AND platform_user_id = ? 
             AND is_active = 1"
        );
        $stmt->execute([$event['target']['id_str']]);
        $account = $stmt->fetch();
        
        if ($account) {
            // Create notification
            $stmt = $db->prepare(
                "INSERT INTO notifications (client_id, type, platform, title, message, data, created_at)
                 VALUES (?, 'follow', 'twitter', ?, ?, ?, NOW())"
            );
            
            $title = "New Twitter Follower";
            $message = "@{$event['source']['screen_name']} started following you";
            
            $stmt->execute([
                $account['client_id'],
                $title,
                $message,
                json_encode($event)
            ]);
            
            // Update follower count
            $stmt = $db->prepare(
                "UPDATE accounts 
                 SET JSON_SET(account_data, '$.followers_count', 
                     COALESCE(JSON_EXTRACT(account_data, '$.followers_count'), 0) + 1)
                 WHERE id = ?"
            );
            $stmt->execute([$account['id']]);
        }
    }
}

/**
 * Process direct message events
 */
function processDirectMessageEvents($events) {
    global $db;
    
    foreach ($events as $event) {
        if ($event['type'] !== 'message_create') {
            continue;
        }
        
        $senderId = $event['message_create']['sender_id'];
        $recipientId = $event['message_create']['target']['recipient_id'];
        
        // Find recipient account
        $stmt = $db->prepare(
            "SELECT * FROM accounts 
             WHERE platform = 'twitter' 
             AND platform_user_id = ? 
             AND is_active = 1"
        );
        $stmt->execute([$recipientId]);
        $account = $stmt->fetch();
        
        if ($account && $senderId !== $recipientId) {
            // Create notification for received DM
            $stmt = $db->prepare(
                "INSERT INTO notifications (client_id, type, platform, title, message, data, created_at)
                 VALUES (?, 'dm', 'twitter', ?, ?, ?, NOW())"
            );
            
            $title = "New Twitter DM";
            $message = "You have a new direct message";
            
            $stmt->execute([
                $account['client_id'],
                $title,
                $message,
                json_encode($event)
            ]);
        }
    }
}

/**
 * Handle replies to our tweets
 */
function handleReply($tweet) {
    global $db;
    
    $replyToId = $tweet['in_reply_to_status_id_str'];
    
    // Find the original post
    $stmt = $db->prepare(
        "SELECT p.*, a.client_id 
         FROM posts p
         JOIN accounts a ON JSON_EXTRACT(p.platform_posts_json, '$.twitter') = ?
         WHERE a.platform = 'twitter' AND a.is_active = 1"
    );
    $stmt->execute([$replyToId]);
    $post = $stmt->fetch();
    
    if ($post) {
        // Create notification
        $stmt = $db->prepare(
            "INSERT INTO notifications (client_id, type, platform, title, message, data, created_at)
             VALUES (?, 'reply', 'twitter', ?, ?, ?, NOW())"
        );
        
        $title = "New Twitter Reply";
        $message = "@{$tweet['user']['screen_name']} replied to your tweet";
        
        $stmt->execute([
            $post['client_id'],
            $title,
            $message,
            json_encode($tweet)
        ]);
        
        // Update reply count
        $stmt = $db->prepare(
            "INSERT INTO post_metrics (post_id, platform, metric_name, metric_value, updated_at)
             VALUES (?, 'twitter', 'replies', 1, NOW())
             ON DUPLICATE KEY UPDATE 
             metric_value = metric_value + 1,
             updated_at = NOW()"
        );
        $stmt->execute([$post['id']]);
    }
}

/**
 * Handle mentions
 */
function handleMention($tweet, $mention) {
    global $db;
    
    // Find account by user ID
    $stmt = $db->prepare(
        "SELECT * FROM accounts 
         WHERE platform = 'twitter' 
         AND platform_user_id = ? 
         AND is_active = 1"
    );
    $stmt->execute([$mention['id_str']]);
    $account = $stmt->fetch();
    
    if ($account) {
        // Create notification
        $stmt = $db->prepare(
            "INSERT INTO notifications (client_id, type, platform, title, message, data, created_at)
             VALUES (?, 'mention', 'twitter', ?, ?, ?, NOW())"
        );
        
        $title = "Twitter Mention";
        $message = "@{$tweet['user']['screen_name']} mentioned you in a tweet";
        
        $stmt->execute([
            $account['client_id'],
            $title,
            $message,
            json_encode($tweet)
        ]);
    }
}