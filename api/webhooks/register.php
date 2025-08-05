<?php
/**
 * Webhook Registration Endpoint
 * 
 * Registers webhooks with each platform
 */

require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Auth.php';

// Check authentication
session_start();
$auth = new Auth();
if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$platform = $input['platform'] ?? '';

// Get webhook URLs
$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
           '://' . $_SERVER['HTTP_HOST'];
$webhookUrl = $baseUrl . '/api/webhooks/' . $platform . '.php';

$response = [];

try {
    switch ($platform) {
        case 'facebook':
            $response = registerFacebookWebhook($webhookUrl);
            break;
        case 'twitter':
            $response = registerTwitterWebhook($webhookUrl);
            break;
        case 'linkedin':
            $response = registerLinkedInWebhook($webhookUrl);
            break;
        default:
            throw new Exception('Invalid platform');
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Register Facebook/Instagram webhook
 */
function registerFacebookWebhook($webhookUrl) {
    // Facebook webhook registration process:
    // 1. Go to Facebook App Dashboard
    // 2. Navigate to Webhooks section
    // 3. Add webhook URL and verify token
    // 4. Subscribe to desired events
    
    return [
        'success' => true,
        'platform' => 'facebook',
        'instructions' => [
            'Go to https://developers.facebook.com/apps/' . FB_APP_ID . '/webhooks/',
            'Click "Add Callback URL"',
            'Enter webhook URL: ' . $webhookUrl,
            'Enter verify token: ' . WEBHOOK_VERIFY_TOKEN,
            'Subscribe to these events: feed, mentions, comments',
            'For Instagram: instagram_mentions, instagram_comments, instagram_messaging'
        ],
        'webhook_url' => $webhookUrl,
        'verify_token' => WEBHOOK_VERIFY_TOKEN
    ];
}

/**
 * Register Twitter webhook
 */
function registerTwitterWebhook($webhookUrl) {
    // Twitter webhook registration via API
    $ch = curl_init();
    
    // First, register the webhook URL
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://api.twitter.com/1.1/account_activity/all/prod/webhooks.json',
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['url' => $webhookUrl]),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . TWITTER_BEARER_TOKEN,
            'Content-Type: application/x-www-form-urlencoded'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        $data = json_decode($response, true);
        
        // Subscribe to events
        subscribeTwitterWebhook($data['id']);
        
        return [
            'success' => true,
            'platform' => 'twitter',
            'webhook_id' => $data['id'],
            'webhook_url' => $webhookUrl,
            'valid' => $data['valid'] ?? false
        ];
    } else {
        $error = json_decode($response, true);
        throw new Exception('Twitter webhook registration failed: ' . 
                          ($error['errors'][0]['message'] ?? 'Unknown error'));
    }
}

/**
 * Subscribe to Twitter webhook events
 */
function subscribeTwitterWebhook($webhookId) {
    // Subscribe for all accounts
    $db = Database::getInstance();
    $stmt = $db->prepare(
        "SELECT * FROM accounts WHERE platform = 'twitter' AND is_active = 1"
    );
    $stmt->execute();
    $accounts = $stmt->fetchAll();
    
    foreach ($accounts as $account) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.twitter.com/1.1/account_activity/all/prod/subscriptions.json',
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $account['access_token'],
                'Content-Type: application/x-www-form-urlencoded'
            ]
        ]);
        
        curl_exec($ch);
        curl_close($ch);
    }
}

/**
 * Register LinkedIn webhook
 */
function registerLinkedInWebhook($webhookUrl) {
    // LinkedIn webhook registration process is manual
    // Must be done through LinkedIn Developer Portal
    
    return [
        'success' => true,
        'platform' => 'linkedin',
        'instructions' => [
            'Go to https://www.linkedin.com/developers/apps/' . LINKEDIN_CLIENT_ID,
            'Navigate to Products > Marketing Developer Platform',
            'Click on "Webhooks" tab',
            'Add webhook URL: ' . $webhookUrl,
            'Select events: ORGANIZATION_SOCIAL_ACTION, MEMBER_SOCIAL_ACTION, COMMENT, SHARE',
            'Save configuration'
        ],
        'webhook_url' => $webhookUrl,
        'secret' => substr(LINKEDIN_WEBHOOK_SECRET, 0, 4) . '****'
    ];
}