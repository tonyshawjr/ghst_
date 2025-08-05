<?php
/**
 * API Endpoint: Create Shareable Report Link
 * 
 * POST /api/reports/share.php
 * Creates a secure shareable link for a report with customizable settings
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
require_once '../../includes/RateLimiter.php';

// Start session for authentication
session_start();

// Initialize services
$auth = new Auth();
$sharingService = new ReportSharingService();
$rateLimiter = new RateLimiter();

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
    if (!isset($input['report_id']) || !is_numeric($input['report_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Valid report_id is required']);
        exit();
    }
    
    $reportId = (int)$input['report_id'];
    
    // Validate CSRF token if provided
    if (isset($input['csrf_token'])) {
        if (!$auth->validateCSRFToken($input['csrf_token'])) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid CSRF token']);
            exit();
        }
    }
    
    // Parse expiration option
    $expiresIn = ReportSharingService::EXPIRY_7D; // Default 7 days
    if (isset($input['expires_in'])) {
        switch ($input['expires_in']) {
            case '24h':
                $expiresIn = ReportSharingService::EXPIRY_24H;
                break;
            case '7d':
                $expiresIn = ReportSharingService::EXPIRY_7D;
                break;
            case '30d':
                $expiresIn = ReportSharingService::EXPIRY_30D;
                break;
            case '90d':
                $expiresIn = ReportSharingService::EXPIRY_90D;
                break;
            case 'never':
                $expiresIn = 0;
                break;
            case 'custom':
                if (!isset($input['custom_expiry']) || !is_numeric($input['custom_expiry'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'custom_expiry timestamp required for custom expiration']);
                    exit();
                }
                $customTime = (int)$input['custom_expiry'];
                $expiresIn = max(0, $customTime - time());
                break;
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid expires_in value. Use: 24h, 7d, 30d, 90d, never, or custom']);
                exit();
        }
    }
    
    // Parse permissions
    $permissions = [ReportSharingService::PERM_VIEW];
    if (isset($input['permissions']) && is_array($input['permissions'])) {
        $validPermissions = [
            ReportSharingService::PERM_VIEW,
            ReportSharingService::PERM_DOWNLOAD,
            ReportSharingService::PERM_ANALYTICS
        ];
        
        $permissions = array_intersect($input['permissions'], $validPermissions);
        
        if (empty($permissions)) {
            $permissions = [ReportSharingService::PERM_VIEW]; // At least view permission
        }
    } elseif (isset($input['allow_download']) && $input['allow_download']) {
        $permissions[] = ReportSharingService::PERM_DOWNLOAD;
    }
    
    // Parse IP restrictions
    $ipRestrictions = null;
    if (isset($input['ip_restrictions'])) {
        if (is_string($input['ip_restrictions'])) {
            $ipRestrictions = trim($input['ip_restrictions']);
        } elseif (is_array($input['ip_restrictions'])) {
            $ipRestrictions = array_filter($input['ip_restrictions'], function($ip) {
                return filter_var($ip, FILTER_VALIDATE_IP) !== false;
            });
            if (empty($ipRestrictions)) {
                $ipRestrictions = null;
            }
        }
    }
    
    // Build options
    $options = [
        'expires_in' => $expiresIn,
        'permissions' => $permissions,
        'ip_restrictions' => $ipRestrictions,
        'require_email' => isset($input['require_email']) ? (bool)$input['require_email'] : false
    ];
    
    // Handle password
    if (isset($input['password']) && !empty(trim($input['password']))) {
        $password = trim($input['password']);
        
        // Basic password validation
        if (strlen($password) < 4) {
            http_response_code(400);
            echo json_encode(['error' => 'Password must be at least 4 characters long']);
            exit();
        }
        
        $options['password'] = $password;
    }
    
    // Handle download limits
    if (isset($input['max_downloads']) && is_numeric($input['max_downloads'])) {
        $maxDownloads = (int)$input['max_downloads'];
        if ($maxDownloads > 0) {
            $options['max_downloads'] = $maxDownloads;
        }
    }
    
    // Create the share
    $result = $sharingService->createShareLink($reportId, $options);
    
    // Format response
    $response = [
        'success' => true,
        'data' => [
            'share_id' => $result['share_id'],
            'share_token' => $result['share_token'],
            'share_url' => $result['share_url'],
            'qr_code_url' => $result['qr_code_url'],
            'expires_at' => $result['expires_at'],
            'settings' => [
                'has_password' => !empty($options['password']),
                'permissions' => $permissions,
                'max_downloads' => $options['max_downloads'] ?? null,
                'ip_restricted' => !empty($ipRestrictions),
                'require_email' => $options['require_email']
            ]
        ],
        'message' => 'Share link created successfully'
    ];
    
    // Log successful creation
    error_log("Share link created - Report ID: $reportId, Share ID: {$result['share_id']}, User: {$_SESSION['user_id']}");
    
    http_response_code(201);
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Share creation error: " . $e->getMessage());
    
    // Determine appropriate error response
    $errorMessage = $e->getMessage();
    $statusCode = 500;
    
    if (strpos($errorMessage, 'Rate limit exceeded') !== false) {
        $statusCode = 429;
    } elseif (strpos($errorMessage, 'not found') !== false || strpos($errorMessage, 'access denied') !== false) {
        $statusCode = 404;
        $errorMessage = 'Report not found or access denied';
    } elseif (strpos($errorMessage, 'Invalid') !== false) {
        $statusCode = 400;
    }
    
    http_response_code($statusCode);
    echo json_encode([
        'error' => $errorMessage,
        'success' => false
    ]);
}
?>