<?php
require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/functions.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$shareToken = $_GET['token'] ?? '';
$weekNumber = intval($_GET['week_number'] ?? 0);

if (empty($shareToken) || !$weekNumber || $weekNumber < 1 || $weekNumber > 24) {
    jsonResponse(['error' => 'Invalid parameters'], 400);
}

$db = Database::getInstance();

// Get and validate share link
$stmt = $db->prepare("
    SELECT csl.*, sc.id as campaign_id
    FROM campaign_share_links csl
    JOIN strategy_campaigns sc ON csl.campaign_id = sc.id
    WHERE csl.share_token = ? AND csl.is_active = 1
");
$stmt->execute([$shareToken]);
$shareLink = $stmt->fetch();

if (!$shareLink) {
    jsonResponse(['error' => 'Invalid or expired share link'], 404);
}

// Check permissions
if (!$shareLink['allow_week_expansion']) {
    jsonResponse(['error' => 'Week expansion not allowed'], 403);
}

// Check if link has expired
if ($shareLink['expires_at'] && strtotime($shareLink['expires_at']) < time()) {
    jsonResponse(['error' => 'Share link has expired'], 410);
}

try {
    // Get week data
    $stmt = $db->prepare("
        SELECT cw.*, cwp.id as post_id, cwp.platform, cwp.post_type, 
               cwp.content, cwp.hashtags, cwp.call_to_action, 
               cwp.content_pillar, cwp.status as post_status,
               cwp.post_order
        FROM campaign_weeks cw
        LEFT JOIN campaign_week_posts cwp ON cw.id = cwp.campaign_week_id
        WHERE cw.campaign_id = ? AND cw.week_number = ?
        ORDER BY cwp.post_order ASC
    ");
    
    $stmt->execute([$shareLink['campaign_id'], $weekNumber]);
    $results = $stmt->fetchAll();
    
    if (empty($results)) {
        jsonResponse(['error' => 'Week not found'], 404);
    }
    
    // Structure the week data
    $week = [
        'id' => $results[0]['id'],
        'week_number' => $results[0]['week_number'],
        'week_start_date' => formatDate($results[0]['week_start_date']),
        'week_end_date' => formatDate($results[0]['week_end_date']),
        'week_theme' => $results[0]['week_theme'],
        'objectives' => json_decode($results[0]['objectives'], true) ?? [],
        'key_messages' => json_decode($results[0]['key_messages'], true) ?? [],
        'status' => $results[0]['status'],
        'posts' => []
    ];
    
    // Add posts (filter sensitive data if needed)
    foreach ($results as $row) {
        if ($row['post_id']) {
            $post = [
                'id' => $row['post_id'],
                'platform' => $row['platform'],
                'post_type' => $row['post_type'],
                'content' => $row['content'],
                'hashtags' => $row['hashtags'],
                'content_pillar' => $row['content_pillar'],
                'status' => $row['post_status']
            ];
            
            // Only include CTA if sensitive data is allowed
            if ($shareLink['show_sensitive_data']) {
                $post['call_to_action'] = $row['call_to_action'];
            }
            
            $week['posts'][] = $post;
        }
    }
    
    // Log the access
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
    
    $stmt = $db->prepare("
        INSERT INTO campaign_share_access_logs (
            share_link_id, access_type, ip_address, user_agent, 
            success, created_at
        ) VALUES (?, 'view', ?, ?, 1, NOW())
    ");
    $stmt->execute([$shareLink['id'], $clientIP, $userAgent]);
    
    jsonResponse([
        'success' => true,
        'week' => $week
    ]);
    
} catch (Exception $e) {
    error_log("Get shared week error: " . $e->getMessage());
    jsonResponse([
        'error' => 'Failed to load week data',
        'details' => $e->getMessage()
    ], 500);
}