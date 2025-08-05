<?php
/**
 * Twitter/X OAuth Callback Handler
 */

require_once '../../../config.php';
require_once '../../../includes/Database.php';
require_once '../../../includes/Auth.php';
require_once '../../../includes/OAuth.php';
require_once '../../../includes/functions.php';

session_start();

try {
    $auth = new Auth();
    $oauth = new OAuth();
    
    // Check if user is logged in
    if (!$auth->getCurrentUser()) {
        throw new Exception('User not logged in');
    }
    
    // Check for error from Twitter
    if (isset($_GET['error'])) {
        $error = $_GET['error_description'] ?? $_GET['error'];
        throw new Exception('Twitter OAuth error: ' . $error);
    }
    
    // Check for authorization code
    $code = $_GET['code'] ?? '';
    $state = $_GET['state'] ?? '';
    
    if (!$code || !$state) {
        throw new Exception('Missing authorization code or state parameter');
    }
    
    // Handle the OAuth callback
    $accountId = $oauth->handleCallback('twitter', $code, $state);
    
    // Clean up code verifier from session
    unset($_SESSION['twitter_code_verifier']);
    
    // Success - redirect to accounts page with success message
    $_SESSION['success_message'] = 'Twitter account connected successfully!';
    header('Location: /dashboard/accounts.php');
    exit;
    
} catch (Exception $e) {
    error_log('Twitter OAuth error: ' . $e->getMessage());
    
    // Clean up code verifier from session
    unset($_SESSION['twitter_code_verifier']);
    
    // Error - redirect to accounts page with error message
    $_SESSION['error_message'] = 'Failed to connect Twitter account: ' . $e->getMessage();
    header('Location: /dashboard/accounts.php');
    exit;
}