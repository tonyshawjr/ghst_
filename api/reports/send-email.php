<?php
/**
 * Send Report via Email API Endpoint
 * Handles sending reports to clients via email
 */

require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Auth.php';
require_once '../../includes/EmailService.php';
require_once '../../includes/RateLimiter.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
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

try {
    // Rate limiting
    $rateLimiter = new RateLimiter();
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    if (!$rateLimiter->checkLimit('email_send', $clientIp, 10, 3600)) { // 10 emails per hour
        http_response_code(429);
        echo json_encode(['error' => 'Rate limit exceeded. Please try again later.']);
        exit();
    }
    
    // Authentication
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        exit();
    }
    
    $userId = $auth->getCurrentUserId();
    
    // Get POST data
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON input']);
        exit();
    }
    
    // Validate required fields
    $reportId = $input['report_id'] ?? null;
    $recipients = $input['recipients'] ?? [];
    $message = $input['message'] ?? '';
    $attachPdf = isset($input['attach_pdf']) ? (bool)$input['attach_pdf'] : true;
    $sendNow = isset($input['send_now']) ? (bool)$input['send_now'] : false;
    $scheduledAt = $input['scheduled_at'] ?? null;
    
    if (!$reportId) {
        http_response_code(400);
        echo json_encode(['error' => 'Report ID is required']);
        exit();
    }
    
    if (empty($recipients)) {
        http_response_code(400);
        echo json_encode(['error' => 'At least one recipient is required']);
        exit();
    }
    
    // Validate recipients format
    $validatedRecipients = [];
    foreach ($recipients as $recipient) {
        if (is_string($recipient)) {
            if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['error' => "Invalid email address: {$recipient}"]);
                exit();
            }
            $validatedRecipients[] = $recipient;
        } elseif (is_array($recipient) && isset($recipient['email'])) {
            if (!filter_var($recipient['email'], FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                echo json_encode(['error' => "Invalid email address: {$recipient['email']}"]);
                exit();
            }
            $validatedRecipients[] = $recipient;
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid recipient format']);
            exit();
        }
    }
    
    // Verify report exists and user has access
    $db = Database::getInstance();
    $stmt = $db->prepare("
        SELECT r.*, c.business_name 
        FROM reports r 
        LEFT JOIN clients c ON r.client_id = c.id 
        WHERE r.id = ? AND r.user_id = ?
    ");
    $stmt->execute([$reportId, $userId]);
    $report = $stmt->fetch();
    
    if (!$report) {
        http_response_code(404);
        echo json_encode(['error' => 'Report not found or access denied']);
        exit();
    }
    
    // Initialize email service
    $emailService = EmailService::getInstance();
    
    // Prepare email options
    $emailOptions = [
        'report_id' => $reportId,
        'user_id' => $userId,
        'custom_message' => $message
    ];
    
    if ($scheduledAt) {
        $emailOptions['scheduled_at'] = $scheduledAt;
    }
    
    $results = [];
    $successCount = 0;
    $failCount = 0;
    
    // Send or queue emails
    foreach ($validatedRecipients as $recipient) {
        try {
            if ($sendNow && !$scheduledAt) {
                // Send immediately
                $result = $emailService->sendReport($reportId, [$recipient], $message, $attachPdf);
                $results[] = [
                    'recipient' => $recipient,
                    'status' => 'sent',
                    'result' => $result[0] ?? null
                ];
                $successCount++;
            } else {
                // Queue for later delivery
                $result = $emailService->queueEmail(
                    $recipient,
                    "Your Social Media Report - " . ($report['business_name'] ?: 'ghst_'),
                    '', // Will be generated in queue processor
                    null,
                    $attachPdf ? ['attach_pdf' => true] : [],
                    $emailOptions
                );
                $results[] = [
                    'recipient' => $recipient,
                    'status' => 'queued',
                    'queue_id' => $result['queue_id'] ?? null,
                    'tracking_id' => $result['tracking_id'] ?? null
                ];
                $successCount++;
            }
        } catch (Exception $e) {
            $results[] = [
                'recipient' => $recipient,
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
            $failCount++;
            error_log("Report email failed for {$recipient}: " . $e->getMessage());
        }
    }
    
    // Log the email sending activity
    try {
        $stmt = $db->prepare("
            INSERT INTO email_activity_log 
            (user_id, report_id, action, recipients_count, success_count, fail_count, details, created_at)
            VALUES (?, ?, 'send_report', ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $userId,
            $reportId,
            count($validatedRecipients),
            $successCount,
            $failCount,
            json_encode([
                'message' => $message,
                'attach_pdf' => $attachPdf,
                'send_now' => $sendNow,
                'scheduled_at' => $scheduledAt,
                'results' => $results
            ])
        ]);
    } catch (Exception $e) {
        error_log("Failed to log email activity: " . $e->getMessage());
    }
    
    // Prepare response
    $response = [
        'success' => $successCount > 0,
        'message' => $sendNow 
            ? "Email sent to {$successCount} recipients" 
            : "Email queued for {$successCount} recipients",
        'summary' => [
            'total' => count($validatedRecipients),
            'success' => $successCount,
            'failed' => $failCount,
            'status' => $sendNow ? 'sent' : 'queued'
        ],
        'results' => $results
    ];
    
    if ($failCount > 0) {
        $response['warning'] = "Some emails failed to send/queue";
    }
    
    http_response_code($successCount > 0 ? 200 : 400);
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Report email API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => ENVIRONMENT === 'development' ? $e->getMessage() : 'Please try again later'
    ]);
}
?>