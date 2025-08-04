<?php
/**
 * Cron job for publishing scheduled posts
 * Run every 5 minutes: */5 * * * * /usr/bin/php /home/username/public_html/cron.php
 */

// Prevent web access
if (php_sapi_name() !== 'cli' && (!isset($_GET['secret']) || $_GET['secret'] !== CRON_SECRET)) {
    die('Unauthorized access');
}

require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/functions.php';

// Set time limit for long-running processes
set_time_limit(300); // 5 minutes

$db = Database::getInstance();
$processedCount = 0;
$failedCount = 0;

// Log cron start
logCron('info', 'Cron job started');

try {
    // Get posts ready to be published
    $stmt = $db->prepare("
        SELECT p.*, c.timezone 
        FROM posts p
        JOIN clients c ON p.client_id = c.id
        WHERE p.scheduled_at <= NOW() 
        AND p.status = 'scheduled'
        ORDER BY p.scheduled_at ASC
        LIMIT ?
    ");
    $stmt->execute([POST_BATCH_SIZE]);
    $posts = $stmt->fetchAll();
    
    foreach ($posts as $post) {
        // Update status to publishing
        $updateStmt = $db->prepare("UPDATE posts SET status = 'publishing' WHERE id = ?");
        $updateStmt->execute([$post['id']]);
        
        // Get platforms from JSON
        $platforms = json_decode($post['platforms_json'], true);
        $allSuccess = true;
        $errors = [];
        
        foreach ($platforms as $platform) {
            // Get account credentials
            $accountStmt = $db->prepare("
                SELECT * FROM accounts 
                WHERE client_id = ? 
                AND platform = ? 
                AND is_active = 1
                LIMIT 1
            ");
            $accountStmt->execute([$post['client_id'], $platform]);
            $account = $accountStmt->fetch();
            
            if (!$account) {
                $errors[] = "No active {$platform} account found";
                $allSuccess = false;
                continue;
            }
            
            // Check if token is expired
            if ($account['expires_at'] && strtotime($account['expires_at']) < time()) {
                $errors[] = "{$platform} token expired";
                $allSuccess = false;
                
                // Add to retry queue
                addToRetryQueue($post['id'], $platform, 'Token expired');
                continue;
            }
            
            // Publish to platform
            $result = publishToPlatform($platform, $account, $post);
            
            if (!$result['success']) {
                $errors[] = "{$platform}: " . $result['error'];
                $allSuccess = false;
                
                // Add to retry queue if temporary error
                if ($result['retry']) {
                    addToRetryQueue($post['id'], $platform, $result['error']);
                }
            }
            
            // Log the attempt
            logPost($post['id'], $post['client_id'], $platform, $result['success'], $result['message'] ?? $result['error'] ?? 'Unknown');
        }
        
        // Update post status
        if ($allSuccess) {
            $finalStmt = $db->prepare("UPDATE posts SET status = 'published', published_at = NOW() WHERE id = ?");
            $finalStmt->execute([$post['id']]);
            $processedCount++;
        } else {
            $errorMessage = implode('; ', $errors);
            $finalStmt = $db->prepare("UPDATE posts SET status = 'failed', last_error = ? WHERE id = ?");
            $finalStmt->execute([$errorMessage, $post['id']]);
            $failedCount++;
        }
    }
    
    // Process retry queue
    processRetryQueue();
    
} catch (Exception $e) {
    logCron('error', 'Cron job failed: ' . $e->getMessage());
    die('Cron job failed: ' . $e->getMessage());
}

// Log cron completion
logCron('info', "Cron job completed. Processed: {$processedCount}, Failed: {$failedCount}");

/**
 * Publish post to specific platform
 */
function publishToPlatform($platform, $account, $post) {
    switch ($platform) {
        case 'instagram':
            return publishToInstagram($account, $post);
        case 'facebook':
            return publishToFacebook($account, $post);
        case 'linkedin':
            return publishToLinkedIn($account, $post);
        case 'twitter':
            return publishToTwitter($account, $post);
        case 'threads':
            return publishToThreads($account, $post);
        default:
            return ['success' => false, 'error' => 'Unsupported platform', 'retry' => false];
    }
}

/**
 * Instagram Publishing (via Facebook Graph API)
 */
function publishToInstagram($account, $post) {
    // Instagram requires media, check if provided
    if (empty($post['media_url'])) {
        return ['success' => false, 'error' => 'Instagram requires media', 'retry' => false];
    }
    
    // This is a placeholder - actual implementation would use Facebook Graph API
    // https://developers.facebook.com/docs/instagram-api/guides/content-publishing
    
    return [
        'success' => false, 
        'error' => 'Instagram API integration not yet implemented', 
        'retry' => false
    ];
}

/**
 * Facebook Publishing
 */
function publishToFacebook($account, $post) {
    // This is a placeholder - actual implementation would use Facebook Graph API
    // https://developers.facebook.com/docs/graph-api/reference/page/feed
    
    return [
        'success' => false, 
        'error' => 'Facebook API integration not yet implemented', 
        'retry' => false
    ];
}

/**
 * LinkedIn Publishing
 */
function publishToLinkedIn($account, $post) {
    // This is a placeholder - actual implementation would use LinkedIn API
    // https://docs.microsoft.com/en-us/linkedin/marketing/integrations/community-management/shares/share-api
    
    return [
        'success' => false, 
        'error' => 'LinkedIn API integration not yet implemented', 
        'retry' => false
    ];
}

/**
 * Twitter/X Publishing
 */
function publishToTwitter($account, $post) {
    // This is a placeholder - actual implementation would use Twitter API v2
    // https://developer.twitter.com/en/docs/twitter-api/tweets/manage-tweets/api-reference/post-tweets
    
    return [
        'success' => false, 
        'error' => 'Twitter API integration not yet implemented', 
        'retry' => false
    ];
}

/**
 * Threads Publishing
 */
function publishToThreads($account, $post) {
    // This is a placeholder - Threads API is still in development
    
    return [
        'success' => false, 
        'error' => 'Threads API integration not yet implemented', 
        'retry' => false
    ];
}

/**
 * Add failed post to retry queue
 */
function addToRetryQueue($postId, $platform, $error) {
    global $db;
    
    $retryAfter = date('Y-m-d H:i:s', strtotime('+' . RETRY_DELAY_MINUTES . ' minutes'));
    
    $stmt = $db->prepare("
        INSERT INTO retry_queue (post_id, platform, retry_after, last_error)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        attempts = attempts + 1,
        retry_after = VALUES(retry_after),
        last_error = VALUES(last_error)
    ");
    
    $stmt->execute([$postId, $platform, $retryAfter, $error]);
}

/**
 * Process retry queue
 */
function processRetryQueue() {
    global $db;
    
    $stmt = $db->prepare("
        SELECT rq.*, p.*, c.timezone
        FROM retry_queue rq
        JOIN posts p ON rq.post_id = p.id
        JOIN clients c ON p.client_id = c.id
        WHERE rq.retry_after <= NOW()
        AND rq.attempts < rq.max_attempts
        LIMIT 5
    ");
    $stmt->execute();
    $retries = $stmt->fetchAll();
    
    foreach ($retries as $retry) {
        // Get account
        $accountStmt = $db->prepare("
            SELECT * FROM accounts 
            WHERE client_id = ? 
            AND platform = ? 
            AND is_active = 1
            LIMIT 1
        ");
        $accountStmt->execute([$retry['client_id'], $retry['platform']]);
        $account = $accountStmt->fetch();
        
        if ($account) {
            $result = publishToPlatform($retry['platform'], $account, $retry);
            
            if ($result['success']) {
                // Remove from retry queue
                $deleteStmt = $db->prepare("DELETE FROM retry_queue WHERE id = ?");
                $deleteStmt->execute([$retry['id']]);
                
                // Update post status if all platforms succeeded
                checkAndUpdatePostStatus($retry['post_id']);
            } else {
                // Update retry queue
                $updateStmt = $db->prepare("
                    UPDATE retry_queue 
                    SET attempts = attempts + 1, 
                        retry_after = DATE_ADD(NOW(), INTERVAL ? MINUTE),
                        last_error = ?
                    WHERE id = ?
                ");
                $updateStmt->execute([RETRY_DELAY_MINUTES * ($retry['attempts'] + 1), $result['error'], $retry['id']]);
            }
            
            logPost($retry['post_id'], $retry['client_id'], $retry['platform'], $result['success'], $result['message'] ?? $result['error'] ?? 'Retry attempt');
        }
    }
}

/**
 * Check if all platforms succeeded and update post status
 */
function checkAndUpdatePostStatus($postId) {
    global $db;
    
    // Check if any retries remain
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM retry_queue WHERE post_id = ?");
    $stmt->execute([$postId]);
    $retryCount = $stmt->fetch()['count'];
    
    if ($retryCount == 0) {
        $updateStmt = $db->prepare("UPDATE posts SET status = 'published', published_at = NOW() WHERE id = ? AND status = 'failed'");
        $updateStmt->execute([$postId]);
    }
}

/**
 * Log post publishing attempt
 */
function logPost($postId, $clientId, $platform, $success, $message) {
    global $db;
    
    $stmt = $db->prepare("
        INSERT INTO logs (client_id, post_id, action, status, message, platform)
        VALUES (?, ?, 'publish', ?, ?, ?)
    ");
    
    $status = $success ? 'success' : 'error';
    $stmt->execute([$clientId, $postId, $status, $message, $platform]);
}

/**
 * Log cron activity
 */
function logCron($level, $message) {
    global $db;
    
    $stmt = $db->prepare("
        INSERT INTO logs (action, status, message)
        VALUES ('cron', ?, ?)
    ");
    
    $stmt->execute([$level, $message]);
}