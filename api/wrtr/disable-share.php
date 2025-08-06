<?php
require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Auth.php';
require_once '../../includes/functions.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$auth = new Auth();
$auth->requireLogin();
requireClient();

$client = $auth->getCurrentClient();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !$auth->validateCSRFToken($input['csrf_token'] ?? '')) {
    jsonResponse(['error' => 'Invalid request'], 400);
}

$linkId = intval($input['link_id'] ?? 0);
if (!$linkId) {
    jsonResponse(['error' => 'Link ID required'], 400);
}

$db = Database::getInstance();

try {
    // Verify share link belongs to client's campaign
    $stmt = $db->prepare("
        SELECT csl.id, sc.client_id
        FROM campaign_share_links csl
        JOIN strategy_campaigns sc ON csl.campaign_id = sc.id
        WHERE csl.id = ? AND sc.client_id = ?
    ");
    $stmt->execute([$linkId, $client['id']]);
    $shareLink = $stmt->fetch();
    
    if (!$shareLink) {
        jsonResponse(['error' => 'Share link not found'], 404);
    }
    
    // Disable the share link
    $stmt = $db->prepare("
        UPDATE campaign_share_links 
        SET is_active = 0, updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$linkId]);
    
    // Log the access attempt as blocked
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
    
    $stmt = $db->prepare("
        INSERT INTO campaign_share_access_logs (
            share_link_id, access_type, ip_address, user_agent,
            success, failure_reason, created_at
        ) VALUES (?, 'disable', ?, ?, 1, 'Link disabled by owner', NOW())
    ");
    $stmt->execute([$linkId, $clientIP, $userAgent]);
    
    jsonResponse([
        'success' => true,
        'message' => 'Share link disabled successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Disable share link error: " . $e->getMessage());
    jsonResponse([
        'error' => 'Failed to disable share link',
        'details' => $e->getMessage()
    ], 500);
}