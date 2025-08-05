<?php
/**
 * OAuth Authorization Endpoint
 * Redirects users to the appropriate OAuth provider
 */

require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Auth.php';
require_once '../../includes/OAuth.php';
require_once '../../includes/functions.php';

session_start();

try {
    $auth = new Auth();
    $oauth = new OAuth();
    
    // Check if user is logged in
    if (!$auth->getCurrentUser()) {
        throw new Exception('User not logged in');
    }
    
    // Get parameters
    $platform = $_GET['platform'] ?? '';
    $clientId = $_GET['client_id'] ?? '';
    
    if (!$platform || !$clientId) {
        throw new Exception('Missing required parameters');
    }
    
    // Validate platform
    $supportedPlatforms = ['facebook', 'twitter', 'linkedin'];
    if (!in_array($platform, $supportedPlatforms)) {
        throw new Exception('Unsupported platform: ' . $platform);
    }
    
    // Verify client access
    $client = $auth->getCurrentClient();
    if (!$client || $client['id'] != $clientId) {
        throw new Exception('Invalid client access');
    }
    
    // Get OAuth authorization URL
    $authUrl = $oauth->getAuthUrl($platform, $clientId);
    
    // Redirect to OAuth provider
    header('Location: ' . $authUrl);
    exit;
    
} catch (Exception $e) {
    error_log('OAuth authorization error: ' . $e->getMessage());
    
    // Redirect back to accounts page with error
    $_SESSION['error_message'] = 'Failed to start OAuth flow: ' . $e->getMessage();
    header('Location: /dashboard/accounts.php');
    exit;
}