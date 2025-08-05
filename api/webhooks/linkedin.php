<?php
/**
 * LinkedIn Webhook Handler
 * 
 * Receives real-time updates from LinkedIn
 * about posts, comments, reactions, etc.
 */

require_once '../../config.php';
require_once '../../includes/Database.php';

// Verify webhook challenge (for initial setup)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['challengeCode'])) {
    $challengeCode = $_GET['challengeCode'];
    
    // LinkedIn expects the challenge code to be returned
    http_response_code(200);
    echo $challengeCode;
    exit;
}

// Handle webhook POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = file_get_contents('php://input');
    $headers = getallheaders();
    $signature = $headers['X-LI-Signature'] ?? $headers['x-li-signature'] ?? '';
    
    // Verify webhook signature
    $expectedSignature = base64_encode(
        hash_hmac('sha256', $body, LINKEDIN_WEBHOOK_SECRET, true)
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
             VALUES ('linkedin', ?, ?, NOW())"
        );
        $eventType = $data['eventType'] ?? 'unknown';
        $stmt->execute([$eventType, $body]);
        
        // Process different event types
        switch ($eventType) {
            case 'ORGANIZATION_SOCIAL_ACTION':
                processOrganizationSocialAction($data);
                break;
            case 'MEMBER_SOCIAL_ACTION':
                processMemberSocialAction($data);
                break;
            case 'COMMENT':
                processComment($data);
                break;
            case 'SHARE':
                processShare($data);
                break;
        }
        
        // Always respond 200 OK
        http_response_code(200);
        
    } catch (Exception $e) {
        // Log error but still respond 200 to prevent retries
        error_log('LinkedIn webhook error: ' . $e->getMessage());
        http_response_code(200);
    }
    exit;
}

// Invalid request method
http_response_code(405);
exit;

/**
 * Process organization social actions (company page events)
 */
function processOrganizationSocialAction($data) {
    global $db;
    
    $organizationUrn = $data['organizationUrn'] ?? null;
    $actorUrn = $data['actorUrn'] ?? null;
    $actionType = $data['socialActionType'] ?? null;
    $objectUrn = $data['objectUrn'] ?? null;
    
    if (!$organizationUrn || !$objectUrn) {
        return;
    }
    
    // Find account by organization URN
    $stmt = $db->prepare(
        "SELECT * FROM accounts 
         WHERE platform = 'linkedin' 
         AND JSON_EXTRACT(account_data, '$.organization_urn') = ? 
         AND is_active = 1"
    );
    $stmt->execute([$organizationUrn]);
    $account = $stmt->fetch();
    
    if (!$account) {
        return;
    }
    
    // Handle different action types
    switch ($actionType) {
        case 'LIKE':
            handleLike($account, $objectUrn, $actorUrn, 'organization');
            break;
        case 'COMMENT':
            handleCommentNotification($account, $objectUrn, $actorUrn, 'organization');
            break;
        case 'SHARE':
            handleShareNotification($account, $objectUrn, $actorUrn, 'organization');
            break;
    }
}

/**
 * Process member social actions (personal profile events)
 */
function processMemberSocialAction($data) {
    global $db;
    
    $memberUrn = $data['memberUrn'] ?? null;
    $actorUrn = $data['actorUrn'] ?? null;
    $actionType = $data['socialActionType'] ?? null;
    $objectUrn = $data['objectUrn'] ?? null;
    
    if (!$memberUrn || !$objectUrn) {
        return;
    }
    
    // Find account by member URN
    $stmt = $db->prepare(
        "SELECT * FROM accounts 
         WHERE platform = 'linkedin' 
         AND JSON_EXTRACT(account_data, '$.user_urn') = ? 
         AND is_active = 1"
    );
    $stmt->execute([$memberUrn]);
    $account = $stmt->fetch();
    
    if (!$account) {
        return;
    }
    
    // Handle different action types
    switch ($actionType) {
        case 'LIKE':
            handleLike($account, $objectUrn, $actorUrn, 'member');
            break;
        case 'COMMENT':
            handleCommentNotification($account, $objectUrn, $actorUrn, 'member');
            break;
        case 'SHARE':
            handleShareNotification($account, $objectUrn, $actorUrn, 'member');
            break;
    }
}

/**
 * Process comment events
 */
function processComment($data) {
    global $db;
    
    $parentUrn = $data['parentUrn'] ?? null;
    $commentUrn = $data['commentUrn'] ?? null;
    $authorUrn = $data['authorUrn'] ?? null;
    $text = $data['text'] ?? '';
    
    if (!$parentUrn) {
        return;
    }
    
    // Find the post this comment belongs to
    $stmt = $db->prepare(
        "SELECT p.*, a.client_id 
         FROM posts p
         JOIN accounts a ON p.client_id = a.client_id
         WHERE JSON_EXTRACT(p.platform_posts_json, '$.linkedin') = ?
         AND a.platform = 'linkedin' AND a.is_active = 1
         LIMIT 1"
    );
    $stmt->execute([$parentUrn]);
    $post = $stmt->fetch();
    
    if ($post) {
        // Update comment count
        $stmt = $db->prepare(
            "INSERT INTO post_metrics (post_id, platform, metric_name, metric_value, updated_at)
             VALUES (?, 'linkedin', 'comments', 1, NOW())
             ON DUPLICATE KEY UPDATE 
             metric_value = metric_value + 1,
             updated_at = NOW()"
        );
        $stmt->execute([$post['id']]);
        
        // Create notification
        $stmt = $db->prepare(
            "INSERT INTO notifications (client_id, type, platform, title, message, data, created_at)
             VALUES (?, 'comment', 'linkedin', ?, ?, ?, NOW())"
        );
        
        $title = "New LinkedIn Comment";
        $message = substr($text, 0, 100) . (strlen($text) > 100 ? '...' : '');
        
        $stmt->execute([
            $post['client_id'],
            $title,
            $message,
            json_encode($data)
        ]);
    }
}

/**
 * Process share events
 */
function processShare($data) {
    global $db;
    
    $originalShareUrn = $data['originalShareUrn'] ?? null;
    $shareUrn = $data['shareUrn'] ?? null;
    $actorUrn = $data['actorUrn'] ?? null;
    
    if (!$originalShareUrn) {
        return;
    }
    
    // Find the original post
    $stmt = $db->prepare(
        "SELECT p.*, a.client_id 
         FROM posts p
         JOIN accounts a ON p.client_id = a.client_id
         WHERE JSON_EXTRACT(p.platform_posts_json, '$.linkedin') = ?
         AND a.platform = 'linkedin' AND a.is_active = 1
         LIMIT 1"
    );
    $stmt->execute([$originalShareUrn]);
    $post = $stmt->fetch();
    
    if ($post) {
        // Update share count
        $stmt = $db->prepare(
            "INSERT INTO post_metrics (post_id, platform, metric_name, metric_value, updated_at)
             VALUES (?, 'linkedin', 'shares', 1, NOW())
             ON DUPLICATE KEY UPDATE 
             metric_value = metric_value + 1,
             updated_at = NOW()"
        );
        $stmt->execute([$post['id']]);
        
        // Create notification
        $stmt = $db->prepare(
            "INSERT INTO notifications (client_id, type, platform, title, message, data, created_at)
             VALUES (?, 'share', 'linkedin', ?, ?, ?, NOW())"
        );
        
        $title = "LinkedIn Post Shared";
        $message = "Your LinkedIn post was shared";
        
        $stmt->execute([
            $post['client_id'],
            $title,
            $message,
            json_encode($data)
        ]);
    }
}

/**
 * Handle like notifications
 */
function handleLike($account, $objectUrn, $actorUrn, $type) {
    global $db;
    
    // Find the post
    $stmt = $db->prepare(
        "SELECT p.* 
         FROM posts p
         WHERE JSON_EXTRACT(p.platform_posts_json, '$.linkedin') = ?
         AND p.client_id = ?
         LIMIT 1"
    );
    $stmt->execute([$objectUrn, $account['client_id']]);
    $post = $stmt->fetch();
    
    if ($post) {
        // Update like count
        $stmt = $db->prepare(
            "INSERT INTO post_metrics (post_id, platform, metric_name, metric_value, updated_at)
             VALUES (?, 'linkedin', 'likes', 1, NOW())
             ON DUPLICATE KEY UPDATE 
             metric_value = metric_value + 1,
             updated_at = NOW()"
        );
        $stmt->execute([$post['id']]);
        
        // Create notification
        $stmt = $db->prepare(
            "INSERT INTO notifications (client_id, type, platform, title, message, data, created_at)
             VALUES (?, 'like', 'linkedin', ?, ?, ?, NOW())"
        );
        
        $title = "New LinkedIn Like";
        $message = "Someone liked your LinkedIn post";
        
        $notificationData = [
            'objectUrn' => $objectUrn,
            'actorUrn' => $actorUrn,
            'type' => $type
        ];
        
        $stmt->execute([
            $account['client_id'],
            $title,
            $message,
            json_encode($notificationData)
        ]);
    }
}

/**
 * Handle comment notifications
 */
function handleCommentNotification($account, $objectUrn, $actorUrn, $type) {
    global $db;
    
    // Create notification (detailed comment data comes from comment event)
    $stmt = $db->prepare(
        "INSERT INTO notifications (client_id, type, platform, title, message, data, created_at)
         VALUES (?, 'comment', 'linkedin', ?, ?, ?, NOW())"
    );
    
    $title = "New LinkedIn Comment";
    $message = "Someone commented on your LinkedIn post";
    
    $notificationData = [
        'objectUrn' => $objectUrn,
        'actorUrn' => $actorUrn,
        'type' => $type
    ];
    
    $stmt->execute([
        $account['client_id'],
        $title,
        $message,
        json_encode($notificationData)
    ]);
}

/**
 * Handle share notifications
 */
function handleShareNotification($account, $objectUrn, $actorUrn, $type) {
    global $db;
    
    // Create notification (detailed share data comes from share event)
    $stmt = $db->prepare(
        "INSERT INTO notifications (client_id, type, platform, title, message, data, created_at)
         VALUES (?, 'share', 'linkedin', ?, ?, ?, NOW())"
    );
    
    $title = "LinkedIn Post Shared";
    $message = "Someone shared your LinkedIn post";
    
    $notificationData = [
        'objectUrn' => $objectUrn,
        'actorUrn' => $actorUrn,
        'type' => $type
    ];
    
    $stmt->execute([
        $account['client_id'],
        $title,
        $message,
        json_encode($notificationData)
    ]);
}