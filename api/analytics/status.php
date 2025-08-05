<?php
/**
 * Analytics Status Check API
 * Quick endpoint to check if client has analytics data available
 * GET /api/analytics/status.php
 */

require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Auth.php';

// Set content type
header('Content-Type: application/json');

// Check authentication
$auth = new Auth();
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

$user = $auth->getUser();

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$clientId = $_GET['client_id'] ?? $user['client_id'];

// Validate client access
if ($user['role'] !== 'admin' && $clientId != $user['client_id']) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Quick checks for data availability
    $status = [
        'has_accounts' => false,
        'has_posts' => false,
        'has_analytics' => false,
        'has_recent_activity' => false,
        'account_count' => 0,
        'post_count' => 0,
        'analytics_count' => 0
    ];
    
    // Check accounts
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM accounts WHERE client_id = ? AND is_active = 1");
    $stmt->execute([$clientId]);
    $result = $stmt->fetch();
    $status['account_count'] = (int)$result['count'];
    $status['has_accounts'] = $status['account_count'] > 0;
    
    // Check posts
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM posts WHERE client_id = ?");
    $stmt->execute([$clientId]);
    $result = $stmt->fetch();
    $status['post_count'] = (int)$result['count'];
    $status['has_posts'] = $status['post_count'] > 0;
    
    // Check analytics data
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT pa.id) as count 
        FROM post_analytics pa 
        JOIN posts p ON pa.post_id = p.id 
        WHERE p.client_id = ?
    ");
    $stmt->execute([$clientId]);
    $result = $stmt->fetch();
    $status['analytics_count'] = (int)$result['count'];
    $status['has_analytics'] = $status['analytics_count'] > 0;
    
    // Check recent activity (posts in last 7 days)
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM posts 
        WHERE client_id = ? 
        AND published_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        AND status = 'published'
    ");
    $stmt->execute([$clientId]);
    $result = $stmt->fetch();
    $status['has_recent_activity'] = (int)$result['count'] > 0;
    
    // Determine overall status
    if ($status['has_analytics']) {
        $status['status'] = 'ready';
        $status['message'] = 'Analytics data is available';
    } elseif ($status['has_posts']) {
        $status['status'] = 'processing';
        $status['message'] = 'Posts are being processed for analytics';
    } elseif ($status['has_accounts']) {
        $status['status'] = 'needs_content';
        $status['message'] = 'Accounts connected, ready to create content';
    } else {
        $status['status'] = 'setup_required';
        $status['message'] = 'Connect accounts to get started';
    }
    
    echo json_encode([
        'success' => true,
        'data' => $status,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}