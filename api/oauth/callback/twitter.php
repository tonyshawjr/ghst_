<?php
require_once '../../../config.php';
require_once '../../../includes/Database.php';
require_once '../../../includes/Auth.php';
require_once '../../../includes/functions.php';
require_once '../../../includes/platforms/TwitterPlatform.php';

session_start();

$auth = new Auth();
$auth->requireLogin();
requireClient();

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';
$error = $_GET['error'] ?? '';

// Check for errors from Twitter
if ($error) {
    $_SESSION['error'] = 'Twitter OAuth error: ' . ($_GET['error_description'] ?? $error);
    header('Location: ' . APP_URL . '/dashboard/accounts.php');
    exit;
}

// Verify state token
if (!isset($_SESSION['oauth_state']) || $state !== $_SESSION['oauth_state']) {
    $_SESSION['error'] = 'Invalid state token';
    header('Location: ' . APP_URL . '/dashboard/accounts.php');
    exit;
}

// Clear state token
unset($_SESSION['oauth_state']);
unset($_SESSION['oauth_platform']);

if (!$code) {
    $_SESSION['error'] = 'No authorization code received';
    header('Location: ' . APP_URL . '/dashboard/accounts.php');
    exit;
}

try {
    $db = Database::getInstance();
    $client = $auth->getCurrentClient();
    
    // Create Twitter platform instance
    $twitter = new TwitterPlatform();
    
    // Exchange code for access token
    $redirectUri = APP_URL . '/api/oauth/callback/twitter.php';
    $tokenData = $twitter->handleCallback($code, $state, $redirectUri);
    
    // Check if account already exists
    $stmt = $db->prepare("
        SELECT id FROM accounts 
        WHERE client_id = ? AND platform = 'twitter' AND platform_user_id = ?
    ");
    $stmt->execute([$client['id'], $tokenData['platform_user_id']]);
    $existingAccount = $stmt->fetch();
    
    if ($existingAccount) {
        // Update existing account
        $stmt = $db->prepare("
            UPDATE accounts SET 
                access_token = ?,
                refresh_token = ?,
                token_expires_at = ?,
                platform_username = ?,
                is_active = 1,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $tokenData['access_token'],
            $tokenData['refresh_token'] ?? null,
            $tokenData['expires_at'],
            $tokenData['platform_username'],
            $existingAccount['id']
        ]);
        
        $_SESSION['success'] = 'Twitter account reconnected successfully!';
    } else {
        // Create new account
        $stmt = $db->prepare("
            INSERT INTO accounts (
                client_id, platform, platform_user_id, platform_username,
                access_token, refresh_token, token_expires_at, is_active,
                created_at, updated_at
            ) VALUES (?, 'twitter', ?, ?, ?, ?, ?, 1, NOW(), NOW())
        ");
        $stmt->execute([
            $client['id'],
            $tokenData['platform_user_id'],
            $tokenData['platform_username'],
            $tokenData['access_token'],
            $tokenData['refresh_token'] ?? null,
            $tokenData['expires_at']
        ]);
        
        $_SESSION['success'] = 'Twitter account connected successfully!';
    }
    
    // Log the action
    $auth->logAction('connect_account', [
        'platform' => 'twitter',
        'username' => $tokenData['platform_username']
    ]);
    
} catch (Exception $e) {
    $_SESSION['error'] = 'Failed to connect Twitter account: ' . $e->getMessage();
}

header('Location: ' . APP_URL . '/dashboard/accounts.php');
exit;