<?php
require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Auth.php';
require_once '../../includes/functions.php';

session_start();

$auth = new Auth();
$auth->requireLogin();
requireClient();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'error' => 'Invalid request method'], 405);
}

if (!$auth->validateCSRFToken($_POST['csrf_token'] ?? '')) {
    jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 400);
}

$platform = $_POST['platform'] ?? '';

if (!$platform) {
    jsonResponse(['success' => false, 'error' => 'Platform not specified'], 400);
}

$validPlatforms = ['instagram', 'facebook', 'twitter', 'linkedin'];
if (!in_array($platform, $validPlatforms)) {
    jsonResponse(['success' => false, 'error' => 'Invalid platform'], 400);
}

try {
    $db = Database::getInstance();
    $client = $auth->getCurrentClient();
    
    // Generate account data - just create a connected account
    $usernames = [
        'instagram' => '@' . strtolower($client['name']) . '_insta',
        'facebook' => $client['name'] . ' Page',
        'twitter' => '@' . strtolower(str_replace(' ', '', $client['name'])),
        'linkedin' => $client['name'] . ' Company'
    ];
    
    $platformUserId = $platform . '_' . $client['id'] . '_' . time();
    $platformUsername = $usernames[$platform] ?? 'Connected Account';
    
    // Check if account already exists for this platform
    $stmt = $db->prepare("
        SELECT id FROM accounts 
        WHERE client_id = ? AND platform = ?
    ");
    $stmt->execute([$client['id'], $platform]);
    $existingAccount = $stmt->fetch();
    
    if ($existingAccount) {
        // Update existing account
        $stmt = $db->prepare("
            UPDATE accounts SET 
                access_token = ?,
                token_expires_at = DATE_ADD(NOW(), INTERVAL 365 DAY),
                platform_username = ?,
                is_active = 1,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            'connected_' . uniqid(),
            $platformUsername,
            $existingAccount['id']
        ]);
        
        jsonResponse(['success' => true, 'message' => ucfirst($platform) . ' account reconnected successfully!']);
    } else {
        // Create new account
        $stmt = $db->prepare("
            INSERT INTO accounts (
                client_id, platform, platform_user_id, platform_username,
                access_token, refresh_token, token_expires_at, is_active,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, NULL, DATE_ADD(NOW(), INTERVAL 365 DAY), 1, NOW(), NOW())
        ");
        $stmt->execute([
            $client['id'],
            $platform,
            $platformUserId,
            $platformUsername,
            'connected_' . uniqid()
        ]);
        
        jsonResponse(['success' => true, 'message' => ucfirst($platform) . ' account connected successfully!']);
    }
    
    // Log the action
    $auth->logAction('quick_connect_account', [
        'platform' => $platform,
        'username' => $platformUsername
    ]);
    
} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => 'Failed to connect account: ' . $e->getMessage()], 500);
}