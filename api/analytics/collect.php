<?php
/**
 * Manual Analytics Collection API Endpoint
 * 
 * Allows triggering analytics collection manually for testing or emergency collection
 * POST /api/analytics/collect.php
 */

require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Auth.php';
require_once '../../includes/AnalyticsCollector.php';

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

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Parse request body
$input = json_decode(file_get_contents('php://input'), true);
$platform = $input['platform'] ?? null;
$clientId = $input['client_id'] ?? $user['client_id'];
$options = $input['options'] ?? [];

// Validate client access
if ($user['role'] !== 'admin' && $clientId != $user['client_id']) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied']);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Log the manual collection request
    $stmt = $db->prepare(
        "INSERT INTO logs (client_id, user_id, action, level, message, details, created_at)
         VALUES (?, ?, 'manual_analytics_collection', 'info', 'Manual analytics collection triggered', ?, NOW())"
    );
    $stmt->execute([
        $clientId,
        $user['id'],
        json_encode([
            'platform' => $platform,
            'options' => $options,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
        ])
    ]);
    
    // Initialize analytics collector
    $collector = new AnalyticsCollector();
    
    // Collect analytics
    $results = $collector->collectAnalytics($platform, $clientId, $options);
    
    // Return results
    echo json_encode([
        'success' => true,
        'data' => $results,
        'message' => 'Analytics collection completed'
    ]);
    
} catch (Exception $e) {
    // Log error
    if (isset($db)) {
        try {
            $stmt = $db->prepare(
                "INSERT INTO logs (client_id, user_id, action, level, message, details, created_at)
                 VALUES (?, ?, 'manual_analytics_collection', 'error', 'Manual analytics collection failed', ?, NOW())"
            );
            $stmt->execute([
                $clientId,
                $user['id'],
                json_encode([
                    'error' => $e->getMessage(),
                    'platform' => $platform,
                    'options' => $options
                ])
            ]);
        } catch (Exception $logError) {
            // Ignore logging errors
        }
    }
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}