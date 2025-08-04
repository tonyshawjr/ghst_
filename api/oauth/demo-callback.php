<?php
require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Auth.php';
require_once '../../includes/functions.php';

session_start();

$auth = new Auth();
$auth->requireLogin();
requireClient();

$platform = $_GET['platform'] ?? '';

if (!DEMO_MODE) {
    $_SESSION['error'] = 'Demo mode is disabled';
    header('Location: ' . APP_URL . '/dashboard/accounts.php');
    exit;
}

if (!$platform) {
    $_SESSION['error'] = 'No platform specified';
    header('Location: ' . APP_URL . '/dashboard/accounts.php');
    exit;
}

try {
    $db = Database::getInstance();
    $client = $auth->getCurrentClient();
    
    // Generate demo account data
    $demoUsernames = [
        'instagram' => '@demo_user_' . rand(100, 999),
        'facebook' => 'Demo User ' . rand(100, 999),
        'twitter' => '@demo_user_' . rand(100, 999),
        'linkedin' => 'Demo Professional ' . rand(100, 999)
    ];
    
    $platformUserId = 'demo_' . $platform . '_' . uniqid();
    $platformUsername = $demoUsernames[$platform] ?? 'Demo User';
    
    // Check if demo account already exists for this platform
    $stmt = $db->prepare("
        SELECT id FROM accounts 
        WHERE client_id = ? AND platform = ? AND platform_username LIKE 'Demo %' OR platform_username LIKE '@demo_%'
    ");
    $stmt->execute([$client['id'], $platform]);
    $existingAccount = $stmt->fetch();
    
    if ($existingAccount) {
        // Update existing demo account
        $stmt = $db->prepare("
            UPDATE accounts SET 
                access_token = ?,
                token_expires_at = DATE_ADD(NOW(), INTERVAL 60 DAY),
                platform_username = ?,
                is_active = 1,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            'demo_token_' . uniqid(),
            $platformUsername,
            $existingAccount['id']
        ]);
        
        $_SESSION['success'] = "Demo {$platform} account reconnected successfully! You can now schedule posts.";
    } else {
        // Create new demo account
        $stmt = $db->prepare("
            INSERT INTO accounts (
                client_id, platform, platform_user_id, platform_username,
                access_token, refresh_token, token_expires_at, is_active,
                created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, NULL, DATE_ADD(NOW(), INTERVAL 60 DAY), 1, NOW(), NOW())
        ");
        $stmt->execute([
            $client['id'],
            $platform,
            $platformUserId,
            $platformUsername,
            'demo_token_' . uniqid()
        ]);
        
        $_SESSION['success'] = "Demo {$platform} account connected successfully! You can now schedule posts.";
    }
    
    // Log the action
    $auth->logAction('connect_demo_account', [
        'platform' => $platform,
        'username' => $platformUsername,
        'demo_mode' => true
    ]);
    
} catch (Exception $e) {
    $_SESSION['error'] = 'Failed to connect demo account: ' . $e->getMessage();
}

header('Location: ' . APP_URL . '/dashboard/accounts.php');
exit;