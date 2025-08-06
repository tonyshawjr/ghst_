<?php
/**
 * Test Email Configuration
 * Sends a test email to verify settings are working
 */

require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Auth.php';
require_once '../../includes/EmailService.php';

header('Content-Type: application/json');

// Check authentication
$auth = new Auth();
if (!$auth->isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate email address
$testEmail = $input['email'] ?? '';
if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email address']);
    exit;
}

try {
    // Get email service instance
    $emailService = EmailService::getInstance();
    
    // Create test email content
    $subject = 'ghst_ Email Configuration Test';
    $htmlBody = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Test Email</title>
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f4; margin: 0; padding: 0;">
        <div style="max-width: 600px; margin: 20px auto; background: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <!-- Header -->
            <div style="background: linear-gradient(135deg, #8B5CF6 0%, #6D28D9 100%); color: white; padding: 30px; text-align: center;">
                <h1 style="margin: 0; font-size: 28px;">ghst_</h1>
                <p style="margin: 10px 0 0; opacity: 0.9;">Email Configuration Test</p>
            </div>
            
            <!-- Content -->
            <div style="padding: 30px;">
                <h2 style="color: #2563eb; margin-top: 0;">✅ Configuration Successful!</h2>
                <p>Congratulations! Your email configuration is working correctly.</p>
                <p>This test email confirms that your ghst_ social media management tool can successfully send emails using the configured provider.</p>
                
                <div style="background: #f8f9fa; border-left: 4px solid #8B5CF6; padding: 15px; margin: 20px 0;">
                    <h3 style="margin-top: 0; color: #6D28D9;">Configuration Details:</h3>
                    <ul style="margin: 10px 0; padding-left: 20px;">
                        <li>Test sent at: ' . date('Y-m-d H:i:s T') . '</li>
                        <li>Sent to: ' . htmlspecialchars($testEmail) . '</li>
                        <li>From: ' . htmlspecialchars($auth->getCurrentUser()['email']) . '</li>
                    </ul>
                </div>
                
                <p style="color: #64748b; font-size: 14px; margin-top: 30px;">
                    You can now use email features in ghst_ including:
                </p>
                <ul style="color: #64748b; font-size: 14px;">
                    <li>Automated report delivery</li>
                    <li>Client notifications</li>
                    <li>Campaign updates</li>
                    <li>Analytics summaries</li>
                </ul>
            </div>
            
            <!-- Footer -->
            <div style="background: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #e2e8f0;">
                <p style="margin: 0; color: #64748b; font-size: 12px;">
                    Powered by ghst_ - Your Self-Hosted Social Media Management Suite
                </p>
            </div>
        </div>
    </body>
    </html>';
    
    // Send test email
    $result = $emailService->sendEmail(
        $testEmail,
        $subject,
        $htmlBody
    );
    
    if ($result['success']) {
        echo json_encode([
            'success' => true,
            'message' => 'Test email sent successfully!',
            'provider' => $result['provider'] ?? 'unknown',
            'details' => 'Check your inbox (and spam folder) for the test email.'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to send test email',
            'details' => $result['error'] ?? 'Unknown error',
            'provider' => $result['provider'] ?? 'unknown'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Email service error',
        'details' => $e->getMessage()
    ]);
}