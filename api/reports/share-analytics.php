<?php
/**
 * API Endpoint: Get Share Analytics
 * 
 * GET /api/reports/share-analytics.php
 * Retrieves detailed analytics for shared report links
 */

// CORS headers for API
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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
$db = Database::getInstance();

try {
    // Check authentication
    if (!$auth->isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        exit();
    }
    
    // Get query parameters
    $shareId = $_GET['share_id'] ?? null;
    $shareToken = $_GET['share_token'] ?? null;
    $reportId = $_GET['report_id'] ?? null;
    $dateFrom = $_GET['date_from'] ?? null;
    $dateTo = $_GET['date_to'] ?? null;
    
    // Validate input
    if ($shareId) {
        if (!is_numeric($shareId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Valid share_id is required']);
            exit();
        }
        $shareId = (int)$shareId;
    } elseif ($shareToken) {
        if (!preg_match('/^[a-f0-9]{64}$/', $shareToken)) {
            http_response_code(400);
            echo json_encode(['error' => 'Valid share_token is required']);
            exit();
        }
        
        // Get share ID from token
        $stmt = $db->prepare("SELECT id FROM shareable_reports WHERE share_token = ?");
        $stmt->execute([$shareToken]);
        $share = $stmt->fetch();
        
        if (!$share) {
            http_response_code(404);
            echo json_encode(['error' => 'Share not found']);
            exit();
        }
        
        $shareId = $share['id'];
    } elseif ($reportId) {
        if (!is_numeric($reportId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Valid report_id is required']);
            exit();
        }
        $reportId = (int)$reportId;
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Either share_id, share_token, or report_id is required']);
        exit();
    }
    
    // Verify user has permission to view analytics
    if ($shareId) {
        $stmt = $db->prepare("
            SELECT sr.id, sr.created_by, gr.client_id, gr.report_name
            FROM shareable_reports sr
            JOIN generated_reports gr ON sr.report_id = gr.id
            JOIN user_clients uc ON gr.client_id = uc.client_id
            WHERE sr.id = ? AND uc.user_id = ?
        ");
        $stmt->execute([$shareId, $_SESSION['user_id']]);
        $sharePermission = $stmt->fetch();
        
        if (!$sharePermission) {
            http_response_code(404);
            echo json_encode(['error' => 'Share not found or access denied']);
            exit();
        }
        
        // Get analytics for specific share
        $analytics = $sharingService->getShareAnalytics($shareId);
        
        if (!$analytics) {
            http_response_code(404);
            echo json_encode(['error' => 'Analytics not found']);
            exit();
        }
        
        // Apply date filters if provided
        if ($dateFrom && $dateTo) {
            // Filter timeline data
            $filteredTimeline = array_filter($analytics['timeline'], function($item) use ($dateFrom, $dateTo) {
                $itemDate = $item['date'];
                return $itemDate >= $dateFrom && $itemDate <= $dateTo;
            });
            $analytics['timeline'] = array_values($filteredTimeline);
        }
        
        $response = [
            'success' => true,
            'data' => [
                'share_id' => $shareId,
                'report_name' => $analytics['share']['report_name'],
                'analytics' => $analytics
            ]
        ];
        
    } elseif ($reportId) {
        // Get all shares for a report and their combined analytics
        $stmt = $db->prepare("
            SELECT gr.id, gr.report_name, gr.client_id
            FROM generated_reports gr
            JOIN user_clients uc ON gr.client_id = uc.client_id
            WHERE gr.id = ? AND uc.user_id = ?
        ");
        $stmt->execute([$reportId, $_SESSION['user_id']]);
        $reportPermission = $stmt->fetch();
        
        if (!$reportPermission) {
            http_response_code(404);
            echo json_encode(['error' => 'Report not found or access denied']);
            exit();
        }
        
        // Get all shares for the report
        $shares = $sharingService->getReportShares($reportId);
        
        $combinedAnalytics = [
            'total_shares' => count($shares),
            'active_shares' => count(array_filter($shares, function($s) { return $s['is_active']; })),
            'total_views' => 0,
            'total_downloads' => 0,
            'shares' => []
        ];
        
        foreach ($shares as $share) {
            $shareAnalytics = $sharingService->getShareAnalytics($share['id']);
            
            if ($shareAnalytics) {
                $combinedAnalytics['total_views'] += $shareAnalytics['total_views'];
                $combinedAnalytics['total_downloads'] += $shareAnalytics['total_downloads'];
                
                $combinedAnalytics['shares'][] = [
                    'share_id' => $share['id'],
                    'share_token' => $share['share_token'],
                    'created_at' => $share['created_at'],
                    'expires_at' => $share['expires_at'],
                    'is_active' => (bool)$share['is_active'],
                    'has_password' => !empty($share['password_hash']),
                    'total_views' => $shareAnalytics['total_views'],
                    'total_downloads' => $shareAnalytics['total_downloads'],
                    'last_accessed' => $share['last_accessed'],
                    'created_by_name' => $share['created_by_name']
                ];
            }
        }
        
        // Sort shares by creation date (newest first)
        usort($combinedAnalytics['shares'], function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        $response = [
            'success' => true,
            'data' => [
                'report_id' => $reportId,
                'report_name' => $reportPermission['report_name'],
                'analytics' => $combinedAnalytics
            ]
        ];
    }
    
    // Add date range info if filters were applied
    if ($dateFrom && $dateTo) {
        $response['data']['date_range'] = [
            'from' => $dateFrom,
            'to' => $dateTo
        ];
    }
    
    http_response_code(200);
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Share analytics error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'success' => false
    ]);
}
?>