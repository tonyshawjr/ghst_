<?php
require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/platforms/Platform.php';

session_start();

$auth = new Auth();
$auth->requireLogin();
requireClient();

$action = $_GET['action'] ?? '';
$platform = $_GET['platform'] ?? '';

if ($action === 'connect' && $platform) {
    try {
        // Get the platform class
        $platformObj = Platform::create($platform);
        
        // Generate state token for CSRF protection
        $state = bin2hex(random_bytes(16));
        $_SESSION['oauth_state'] = $state;
        $_SESSION['oauth_platform'] = $platform;
        
        // Get the OAuth redirect URL
        $redirectUri = APP_URL . '/api/oauth/callback/' . $platform . '.php';
        $authUrl = $platformObj->getAuthUrl($redirectUri, $state);
        
        // Redirect to platform OAuth
        header('Location: ' . $authUrl);
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Failed to initiate OAuth: ' . $e->getMessage();
        header('Location: ' . APP_URL . '/dashboard/accounts.php');
        exit;
    }
} else {
    $_SESSION['error'] = 'Invalid OAuth request';
    header('Location: ' . APP_URL . '/dashboard/accounts.php');
    exit;
}