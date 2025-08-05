<?php
/**
 * Email Tracking API Endpoint
 * Handles email open and click tracking
 */

require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/EmailService.php';

// No JSON header for tracking pixels - we might return images
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit();
}

try {
    // Get tracking parameters
    $trackingId = $_GET['t'] ?? null;
    $action = $_GET['a'] ?? null;
    $url = $_GET['u'] ?? null;
    
    if (!$trackingId || !$action) {
        http_response_code(400);
        if ($action === 'open') {
            // Return 1x1 transparent pixel for open tracking
            returnTrackingPixel();
        } else {
            echo 'Invalid tracking parameters';
        }
        exit();
    }
    
    // Validate tracking ID format
    if (!preg_match('/^email_[a-zA-Z0-9_.]+$/', $trackingId)) {
        http_response_code(400);
        if ($action === 'open') {
            returnTrackingPixel();
        } else {
            echo 'Invalid tracking ID format';
        }
        exit();
    }
    
    // Initialize email service
    $emailService = EmailService::getInstance();
    
    // Process tracking based on action
    switch ($action) {
        case 'open':
            handleOpenTracking($emailService, $trackingId);
            break;
            
        case 'click':
            handleClickTracking($emailService, $trackingId, $url);
            break;
            
        default:
            http_response_code(400);
            echo 'Invalid tracking action';
            exit();
    }
    
} catch (Exception $e) {
    error_log("Email tracking error: " . $e->getMessage());
    
    // For open tracking, always return pixel even on error
    if (($action ?? '') === 'open') {
        returnTrackingPixel();
    } else {
        http_response_code(500);
        echo 'Tracking error';
    }
}

/**
 * Handle email open tracking
 */
function handleOpenTracking($emailService, $trackingId) {
    try {
        // Record the open
        $success = $emailService->trackOpen($trackingId);
        
        // Also log additional analytics
        $db = Database::getInstance();
        
        // Get user agent and IP for analytics
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $ipAddress = getRealIpAddress();
        $timestamp = date('Y-m-d H:i:s');
        
        // Insert detailed tracking data
        $stmt = $db->prepare("
            INSERT INTO email_open_analytics 
            (tracking_id, ip_address, user_agent, opened_at, created_at)
            VALUES (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE 
            open_count = open_count + 1,
            last_opened_at = ?
        ");
        $stmt->execute([$trackingId, $ipAddress, $userAgent, $timestamp, $timestamp]);
        
    } catch (Exception $e) {
        error_log("Open tracking failed: " . $e->getMessage());
    }
    
    // Always return tracking pixel
    returnTrackingPixel();
}

/**
 * Handle email click tracking
 */
function handleClickTracking($emailService, $trackingId, $url) {
    try {
        if (!$url) {
            http_response_code(400);
            echo 'URL parameter required for click tracking';
            return;
        }
        
        // Validate and decode URL
        $decodedUrl = urldecode($url);
        
        // Basic URL validation
        if (!filter_var($decodedUrl, FILTER_VALIDATE_URL)) {
            http_response_code(400);
            echo 'Invalid URL';
            return;
        }
        
        // Record the click
        $success = $emailService->trackClick($trackingId, $decodedUrl);
        
        // Log additional analytics
        $db = Database::getInstance();
        
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $ipAddress = getRealIpAddress();
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        
        // Insert detailed click analytics
        $stmt = $db->prepare("
            INSERT INTO email_click_analytics 
            (tracking_id, url, ip_address, user_agent, referer, clicked_at, created_at)
            VALUES (?, ?, ?, ?, ?, NOW(), NOW())
        ");
        $stmt->execute([$trackingId, $decodedUrl, $ipAddress, $userAgent, $referer]);
        
        // Redirect to the original URL
        header("Location: {$decodedUrl}", true, 302);
        exit();
        
    } catch (Exception $e) {
        error_log("Click tracking failed: " . $e->getMessage());
        
        // Still redirect to URL even if tracking fails
        if ($url) {
            $decodedUrl = urldecode($url);
            if (filter_var($decodedUrl, FILTER_VALIDATE_URL)) {
                header("Location: {$decodedUrl}", true, 302);
                exit();
            }
        }
        
        http_response_code(500);
        echo 'Click tracking error';
    }
}

/**
 * Return 1x1 transparent tracking pixel
 */
function returnTrackingPixel() {
    header('Content-Type: image/png');
    header('Content-Length: 67');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // 1x1 transparent PNG (67 bytes)
    echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    exit();
}

/**
 * Get real IP address (handles proxies and load balancers)
 */
function getRealIpAddress() {
    $ipKeys = [
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];
    
    foreach ($ipKeys as $key) {
        if (!empty($_SERVER[$key])) {
            $ips = explode(',', $_SERVER[$key]);
            $ip = trim($ips[0]); // Take the first IP if multiple
            
            // Validate IP
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

/**
 * Get email tracking statistics (for API calls)
 */
function getTrackingStats() {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET' || !isset($_GET['stats'])) {
        return;
    }
    
    header('Content-Type: application/json');
    
    try {
        $trackingId = $_GET['tracking_id'] ?? null;
        
        if (!$trackingId) {
            http_response_code(400);
            echo json_encode(['error' => 'Tracking ID required']);
            return;
        }
        
        $db = Database::getInstance();
        
        // Get main tracking info
        $stmt = $db->prepare("
            SELECT tracking_id, recipient_email, subject, status, 
                   opened_at, clicked_at, open_count, click_count, created_at
            FROM email_tracking 
            WHERE tracking_id = ?
        ");
        $stmt->execute([$trackingId]);
        $mainTracking = $stmt->fetch();
        
        if (!$mainTracking) {
            http_response_code(404);
            echo json_encode(['error' => 'Tracking record not found']);
            return;
        }
        
        // Get open analytics
        $stmt = $db->prepare("
            SELECT ip_address, user_agent, opened_at, open_count
            FROM email_open_analytics 
            WHERE tracking_id = ?
            ORDER BY opened_at DESC
        ");
        $stmt->execute([$trackingId]);
        $openAnalytics = $stmt->fetchAll();
        
        // Get click analytics
        $stmt = $db->prepare("
            SELECT url, ip_address, user_agent, referer, clicked_at
            FROM email_click_analytics 
            WHERE tracking_id = ?
            ORDER BY clicked_at DESC
        ");
        $stmt->execute([$trackingId]);
        $clickAnalytics = $stmt->fetchAll();
        
        $response = [
            'tracking_id' => $trackingId,
            'email_info' => $mainTracking,
            'open_analytics' => $openAnalytics,
            'click_analytics' => $clickAnalytics,
            'summary' => [
                'total_opens' => (int)$mainTracking['open_count'],
                'total_clicks' => (int)$mainTracking['click_count'],
                'unique_opens' => count($openAnalytics),
                'unique_clicks' => count($clickAnalytics),
                'is_opened' => !empty($mainTracking['opened_at']),
                'is_clicked' => !empty($mainTracking['clicked_at']),
                'first_opened' => $mainTracking['opened_at'],
                'first_clicked' => $mainTracking['clicked_at']
            ]
        ];
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        error_log("Tracking stats error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to retrieve tracking stats']);
    }
}

// Handle stats request if present
if (isset($_GET['stats'])) {
    getTrackingStats();
}
?>