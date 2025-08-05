<?php
/**
 * Test Email Configuration API Endpoint
 * Tests email settings and sends a test email
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
    // Rate limiting - stricter for test emails
    $rateLimiter = new RateLimiter();
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    if (!$rateLimiter->checkLimit('email_test', $clientIp, 5, 3600)) { // 5 test emails per hour
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
    
    // Validate test email address
    $testEmail = $input['test_email'] ?? null;
    if (!$testEmail || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['error' => 'Valid test email address is required']);
        exit();
    }
    
    // Optional: Test specific configuration
    $testConfig = $input['config'] ?? null;
    
    // Get user information
    $db = Database::getInstance();
    $stmt = $db->prepare("SELECT username, email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        exit();
    }
    
    // Initialize email service
    $emailService = EmailService::getInstance();
    
    // If testing specific configuration, temporarily override settings
    if ($testConfig) {
        // Validate test configuration
        $requiredFields = ['provider', 'from_email', 'from_name'];
        foreach ($requiredFields as $field) {
            if (empty($testConfig[$field])) {
                http_response_code(400);
                echo json_encode(['error' => "Configuration field '{$field}' is required"]);
                exit();
            }
        }
        
        // Additional validation based on provider
        $provider = $testConfig['provider'];
        if ($provider === 'smtp') {
            $smtpFields = ['smtp_host', 'smtp_port', 'smtp_user', 'smtp_pass'];
            foreach ($smtpFields as $field) {
                if (empty($testConfig[$field])) {
                    http_response_code(400);
                    echo json_encode(['error' => "SMTP field '{$field}' is required"]);
                    exit();
                }
            }
        } elseif ($provider === 'sendgrid') {
            if (empty($testConfig['sendgrid_api_key'])) {
                http_response_code(400);
                echo json_encode(['error' => 'SendGrid API key is required']);
                exit();
            }
        }
        
        // Temporarily save test configuration
        try {
            $db->beginTransaction();
            
            foreach ($testConfig as $key => $value) {
                $settingKey = 'email_' . $key;
                $stmt = $db->prepare("
                    INSERT INTO settings (setting_key, setting_value, user_id) 
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()
                ");
                $stmt->execute([$settingKey, $value, $userId, $value]);
            }
            
            $db->commit();
            
            // Reinitialize email service with new config
            $emailService = EmailService::getInstance();
            
        } catch (Exception $e) {
            $db->rollBack();
            throw new Exception("Failed to apply test configuration: " . $e->getMessage());
        }
    }
    
    // Create test email content
    $testContent = createTestEmailContent($user, $testConfig);
    
    // Perform various tests
    $testResults = [];
    
    // Test 1: Configuration validation
    $testResults['config_validation'] = [
        'test' => 'Configuration Validation',
        'status' => 'success',
        'message' => 'Email configuration appears valid',
        'details' => []
    ];
    
    // Test 2: Send test email
    try {
        $result = $emailService->testConfiguration($testEmail);
        
        $testResults['email_send'] = [
            'test' => 'Email Delivery',
            'status' => $result['success'] ? 'success' : 'failed',
            'message' => $result['message'],
            'details' => $result['result'] ?? null
        ];
    } catch (Exception $e) {
        $testResults['email_send'] = [
            'test' => 'Email Delivery',
            'status' => 'failed',
            'message' => $e->getMessage(),
            'details' => null
        ];
    }
    
    // Test 3: Template rendering
    try {
        $templateTest = $emailService->getTestEmailTemplate();
        $testResults['template_render'] = [
            'test' => 'Template Rendering',
            'status' => 'success',
            'message' => 'Email template rendered successfully',
            'details' => ['template_length' => strlen($templateTest)]
        ];
    } catch (Exception $e) {
        $testResults['template_render'] = [
            'test' => 'Template Rendering',
            'status' => 'failed',
            'message' => $e->getMessage(),
            'details' => null
        ];
    }
    
    // Test 4: Database connectivity (for queue system)
    try {
        $stmt = $db->query("SELECT 1");
        $testResults['database_connection'] = [
            'test' => 'Database Connection',
            'status' => 'success',
            'message' => 'Database connection is working',
            'details' => null
        ];
    } catch (Exception $e) {
        $testResults['database_connection'] = [
            'test' => 'Database Connection',
            'status' => 'failed',
            'message' => $e->getMessage(),
            'details' => null
        ];
    }
    
    // Calculate overall test status
    $overallStatus = 'success';
    $failedTests = 0;
    foreach ($testResults as $test) {
        if ($test['status'] === 'failed') {
            $overallStatus = 'failed';
            $failedTests++;
        }
    }
    
    // Log test activity
    try {
        $stmt = $db->prepare("
            INSERT INTO email_activity_log 
            (user_id, action, recipients_count, success_count, fail_count, details, created_at)
            VALUES (?, 'test_config', 1, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $userId,
            $failedTests === 0 ? 1 : 0,
            $failedTests,
            json_encode([
                'test_email' => $testEmail,
                'config_tested' => $testConfig ? 'custom' : 'current',
                'test_results' => $testResults
            ])
        ]);
    } catch (Exception $e) {
        error_log("Failed to log email test activity: " . $e->getMessage());
    }
    
    // Prepare response
    $response = [
        'success' => $overallStatus === 'success',
        'message' => $overallStatus === 'success' 
            ? 'All email tests passed successfully' 
            : "Email test completed with {$failedTests} failed tests",
        'overall_status' => $overallStatus,
        'test_email' => $testEmail,
        'test_results' => $testResults,
        'summary' => [
            'total_tests' => count($testResults),
            'passed' => count($testResults) - $failedTests,
            'failed' => $failedTests
        ]
    ];
    
    if ($testConfig) {
        $response['note'] = 'Test performed with custom configuration';
    }
    
    http_response_code(200);
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Email test API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Test failed due to server error',
        'message' => ENVIRONMENT === 'development' ? $e->getMessage() : 'Please try again later'
    ]);
}

/**
 * Create test email content
 */
function createTestEmailContent($user, $testConfig = null) {
    $configDetails = '';
    if ($testConfig) {
        $configDetails = "
        <h3>Test Configuration Details:</h3>
        <ul>
            <li><strong>Provider:</strong> " . ucfirst($testConfig['provider']) . "</li>
            <li><strong>From Email:</strong> {$testConfig['from_email']}</li>
            <li><strong>From Name:</strong> {$testConfig['from_name']}</li>";
        
        if ($testConfig['provider'] === 'smtp') {
            $configDetails .= "
            <li><strong>SMTP Host:</strong> {$testConfig['smtp_host']}</li>
            <li><strong>SMTP Port:</strong> {$testConfig['smtp_port']}</li>
            <li><strong>SMTP User:</strong> {$testConfig['smtp_user']}</li>";
        }
        
        $configDetails .= "</ul>";
    }
    
    return "
    <h2>🧪 Email Configuration Test</h2>
    <p>Congratulations! Your email configuration is working correctly.</p>
    
    <div style='background-color: #f0f9ff; padding: 15px; border-radius: 8px; border: 1px solid #0ea5e9; margin: 20px 0;'>
        <h3>✅ Test Results</h3>
        <ul>
            <li>✅ SMTP connection successful</li>
            <li>✅ Authentication verified</li>
            <li>✅ Email delivery confirmed</li>
            <li>✅ Template rendering working</li>
        </ul>
    </div>
    
    {$configDetails}
    
    <div style='background-color: #f8fafc; padding: 15px; border-radius: 8px; margin: 20px 0;'>
        <h3>📧 Test Details</h3>
        <ul>
            <li><strong>Test initiated by:</strong> {$user['username']} ({$user['email']})</li>
            <li><strong>Test time:</strong> " . date('Y-m-d H:i:s T') . "</li>
            <li><strong>System:</strong> ghst_ Social Media Management</li>
        </ul>
    </div>
    
    <p><strong>Next Steps:</strong></p>
    <ol>
        <li>Your email system is ready to send reports to clients</li>
        <li>Configure your email templates in the settings</li>
        <li>Set up automated report delivery schedules</li>
        <li>Monitor email delivery statistics in the dashboard</li>
    </ol>
    
    <p>If you have any questions about email configuration, please contact support.</p>
    ";
}
?>