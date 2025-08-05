<?php
/**
 * API Endpoint: Revoke Shareable Report Link
 * 
 * POST /api/reports/revoke-share.php
 * Revokes access to a shared report link
 */

// CORS headers for API
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Include core files
require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Auth.php';
require_once '../../includes/ReportSharingService.php';

// Start session for authentication
session_start();

// Initialize services
$auth = new Auth();
$sharingService = new ReportSharingService();

try {
    // Check authentication
    if (!$auth->isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        exit();
    }
    
    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON data']);
        exit();
    }
    
    // Validate required fields
    if (isset($input['share_id'])) {
        if (!is_numeric($input['share_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Valid share_id is required']);
            exit();
        }
        $shareId = (int)$input['share_id'];
    } elseif (isset($input['share_token'])) {
        if (!preg_match('/^[a-f0-9]{64}$/', $input['share_token'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Valid share_token is required']);
            exit();
        }
        
        // Get share ID from token
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT id FROM shareable_reports WHERE share_token = ? AND is_active = 1");
        $stmt->execute([$input['share_token']]);
        $share = $stmt->fetch();
        
        if (!$share) {
            http_response_code(404);
            echo json_encode(['error' => 'Share not found']);
            exit();
        }
        
        $shareId = $share['id'];
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Either share_id or share_token is required']);
        exit();
    }
    
    // Validate CSRF token if provided
    if (isset($input['csrf_token'])) {
        if (!$auth->validateCSRFToken($input['csrf_token'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
            exit();
        }
    }
    
    // Verify user has permission to revoke this share
    $db = Database::getInstance();
    $stmt = $db->prepare("
        SELECT sr.id, sr.created_by, gr.client_id 
        FROM shareable_reports sr
        JOIN generated_reports gr ON sr.report_id = gr.id
        JOIN user_clients uc ON gr.client_id = uc.client_id
        WHERE sr.id = ? AND uc.user_id = ? AND sr.is_active = 1
    ");
    
    $stmt->execute([$shareId, $_SESSION['user_id']]);
    $sharePermission = $stmt->fetch();
    
    if (!$sharePermission) {
        http_response_code(404);
        echo json_encode(['error' => 'Share not found or access denied']);
        exit();
    }
    
    // Revoke the share
    $result = $sharingService->revokeShare($shareId);
    
    if ($result) {
        // Get share analytics before responding
        $analytics = $sharingService->getShareAnalytics($shareId);
        
        $response = [
            'success' => true,
            'message' => 'Share link revoked successfully',
            'data' => [
                'share_id' => $shareId,
                'revoked_at' => date('Y-m-d H:i:s'),
                'total_views' => $analytics['total_views'] ?? 0,
                'total_downloads' => $analytics['total_downloads'] ?? 0
            ]
        ];
        
        // Log successful revocation
        error_log("Share link revoked - Share ID: $shareId, User: {$_SESSION['user_id']}");
        
        http_response_code(200);
        echo json_encode($response);
    } else {
        http_response_code(500);
        echo json_encode([
            'error' => 'Failed to revoke share link',
            'success' => false
        ]);
    }
    
} catch (Exception $e) {
    error_log("Share revocation error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'success' => false
    ]);
}
?>