<?php
/**
 * API Endpoint: Update Share Settings
 * 
 * PUT /api/reports/share-settings.php
 * Updates settings for an existing shareable report link
 */

// CORS headers for API
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow PUT requests
if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
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
    
    // Verify user has permission to update this share
    $stmt = $db->prepare("
        SELECT sr.*, gr.client_id 
        FROM shareable_reports sr
        JOIN generated_reports gr ON sr.report_id = gr.id
        JOIN user_clients uc ON gr.client_id = uc.client_id
        WHERE sr.id = ? AND uc.user_id = ?
    ");
    
    $stmt->execute([$shareId, $_SESSION['user_id']]);
    $existingShare = $stmt->fetch();
    
    if (!$existingShare) {
        http_response_code(404);
        echo json_encode(['error' => 'Share not found or access denied']);
        exit();
    }
    
    // Prepare settings to update
    $settings = [];
    $validUpdates = [];
    
    // Handle expiration update
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
                $settings['expires_at'] = null;
                $validUpdates[] = 'expiration';
                break;
            case 'custom':
                if (!isset($input['custom_expiry']) || !is_numeric($input['custom_expiry'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'custom_expiry timestamp required for custom expiration']);
                    exit();
                }
                $customTime = (int)$input['custom_expiry'];
                if ($customTime <= time()) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Custom expiry must be in the future']);
                    exit();
                }
                $settings['expires_at'] = date('Y-m-d H:i:s', $customTime);
                $validUpdates[] = 'expiration';
                break;
            default:
                http_response_code(400);
                echo json_encode(['error' => 'Invalid expires_in value. Use: 24h, 7d, 30d, 90d, never, or custom']);
                exit();
        }
        
        if (isset($expiresIn)) {
            $settings['expires_at'] = date('Y-m-d H:i:s', time() + $expiresIn);
            $validUpdates[] = 'expiration';
        }
    }
    
    // Handle password update
    if (isset($input['password'])) {
        if (empty(trim($input['password']))) {
            // Remove password protection
            $settings['password_hash'] = null;
            $validUpdates[] = 'password removed';
        } else {
            $password = trim($input['password']);
            
            // Basic password validation
            if (strlen($password) < 4) {
                http_response_code(400);
                echo json_encode(['error' => 'Password must be at least 4 characters long']);
                exit();
            }
            
            $settings['password_hash'] = $password; // Will be hashed in updateShareSettings
            $validUpdates[] = 'password updated';
        }
    }
    
    // Handle download limit update
    if (isset($input['max_downloads'])) {
        if ($input['max_downloads'] === null || $input['max_downloads'] === 0) {
            $settings['allowed_downloads'] = null;
            $validUpdates[] = 'download limit removed';
        } elseif (is_numeric($input['max_downloads']) && $input['max_downloads'] > 0) {
            $maxDownloads = (int)$input['max_downloads'];
            
            // Don't allow setting limit below current download count
            if ($maxDownloads < $existingShare['download_count']) {
                http_response_code(400);
                echo json_encode([
                    'error' => "Download limit cannot be less than current download count ({$existingShare['download_count']})"
                ]);
                exit();
            }
            
            $settings['allowed_downloads'] = $maxDownloads;
            $validUpdates[] = 'download limit updated';
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'max_downloads must be a positive number or null']);
            exit();
        }
    }
    
    // Handle active status
    if (isset($input['is_active'])) {
        $isActive = (bool)$input['is_active'];
        $settings['is_active'] = $isActive ? 1 : 0;
        $validUpdates[] = $isActive ? 'activated' : 'deactivated';
    }
    
    // Check if there are any valid updates
    if (empty($settings)) {
        http_response_code(400);
        echo json_encode(['error' => 'No valid settings provided for update']);
        exit();
    }
    
    // Perform the update
    $result = $sharingService->updateShareSettings($shareId, $settings);
    
    if ($result) {
        // Get updated share information
        $stmt = $db->prepare("
            SELECT sr.*, gr.report_name
            FROM shareable_reports sr
            JOIN generated_reports gr ON sr.report_id = gr.id
            WHERE sr.id = ?
        ");
        $stmt->execute([$shareId]);
        $updatedShare = $stmt->fetch();
        
        $response = [
            'success' => true,
            'message' => 'Share settings updated successfully',
            'data' => [
                'share_id' => $shareId,
                'updated_at' => date('Y-m-d H:i:s'),
                'changes' => $validUpdates,
                'current_settings' => [
                    'expires_at' => $updatedShare['expires_at'],
                    'has_password' => !empty($updatedShare['password_hash']),
                    'max_downloads' => $updatedShare['allowed_downloads'],
                    'current_downloads' => $updatedShare['download_count'],
                    'is_active' => (bool)$updatedShare['is_active']
                ]
            ]
        ];
        
        // Log successful update
        error_log("Share settings updated - Share ID: $shareId, Changes: " . implode(', ', $validUpdates) . ", User: {$_SESSION['user_id']}");
        
        http_response_code(200);
        echo json_encode($response);
    } else {
        http_response_code(500);
        echo json_encode([
            'error' => 'Failed to update share settings',
            'success' => false
        ]);
    }
    
} catch (Exception $e) {
    error_log("Share settings update error: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'success' => false
    ]);
}
?>