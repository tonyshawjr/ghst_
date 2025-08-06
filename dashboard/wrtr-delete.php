<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';

$auth = new Auth();
$auth->requireLogin();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate CSRF token
$headers = getallheaders();
$csrfToken = $headers['X-CSRF-Token'] ?? '';

if (!$auth->validateCSRFToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$campaignId = intval($input['id'] ?? 0);

if (!$campaignId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid campaign ID']);
    exit;
}

try {
    $db = Database::getInstance();
    $client = $auth->getCurrentClient();
    
    // Verify campaign belongs to current client
    $stmt = $db->prepare("SELECT id FROM ai_campaigns WHERE id = ? AND client_id = ?");
    $stmt->execute([$campaignId, $client['id']]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Campaign not found']);
        exit;
    }
    
    // Delete campaign (cascades to related tables)
    $stmt = $db->prepare("DELETE FROM ai_campaigns WHERE id = ? AND client_id = ?");
    $stmt->execute([$campaignId, $client['id']]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to delete campaign']);
}
?>