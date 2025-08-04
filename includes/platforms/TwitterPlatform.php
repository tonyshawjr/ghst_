<?php
require_once __DIR__ . '/Platform.php';

/**
 * Twitter Platform Integration
 */
class TwitterPlatform extends Platform {
    private $apiUrl = 'https://api.twitter.com/2';
    private $authUrl = 'https://twitter.com/i/oauth2/authorize';
    
    public function getName() {
        return 'twitter';
    }
    
    public function getAuthUrl($redirectUri, $state = null) {
        $params = [
            'response_type' => 'code',
            'client_id' => TWITTER_CLIENT_ID,
            'redirect_uri' => $redirectUri,
            'scope' => 'tweet.read tweet.write users.read offline.access',
            'code_challenge_method' => 'S256',
            'code_challenge' => $this->generateCodeChallenge(),
        ];
        
        if ($state) {
            $params['state'] = $state;
        }
        
        return $this->authUrl . '?' . http_build_query($params);
    }
    
    private function generateCodeChallenge() {
        $codeVerifier = base64url_encode(random_bytes(32));
        $_SESSION['twitter_code_verifier'] = $codeVerifier;
        return base64url_encode(hash('sha256', $codeVerifier, true));
    }
    
    public function handleCallback($code, $state = null) {
        $codeVerifier = $_SESSION['twitter_code_verifier'] ?? null;
        if (!$codeVerifier) {
            throw new Exception("Code verifier not found in session");
        }
        
        // Exchange code for access token
        $tokenData = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => TWITTER_REDIRECT_URI,
            'code_verifier' => $codeVerifier,
        ];
        
        $credentials = base64_encode(TWITTER_CLIENT_ID . ':' . TWITTER_CLIENT_SECRET);
        
        $response = $this->makeApiRequest(
            'https://api.twitter.com/2/oauth2/token',
            'POST',
            http_build_query($tokenData),
            [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . $credentials,
            ]
        );
        
        // Get user info
        $userInfo = $this->makeApiRequest(
            $this->apiUrl . '/users/me',
            'GET',
            null,
            ['Authorization: Bearer ' . $response['access_token']]
        );
        
        $expiresAt = isset($response['expires_in']) ? 
            date('Y-m-d H:i:s', time() + $response['expires_in']) : null;
        
        unset($_SESSION['twitter_code_verifier']);
        
        return [
            'access_token' => $response['access_token'],
            'refresh_token' => $response['refresh_token'] ?? null,
            'expires_at' => $expiresAt,
            'platform_user_id' => $userInfo['data']['id'],
            'platform_username' => $userInfo['data']['username'],
        ];
    }
    
    public function refreshToken() {
        if (!$this->account || !$this->account['refresh_token']) {
            throw new Exception("No refresh token available");
        }
        
        $tokenData = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->account['refresh_token'],
        ];
        
        $credentials = base64_encode(TWITTER_CLIENT_ID . ':' . TWITTER_CLIENT_SECRET);
        
        $response = $this->makeApiRequest(
            'https://api.twitter.com/2/oauth2/token',
            'POST',
            http_build_query($tokenData),
            [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . $credentials,
            ]
        );
        
        $expiresAt = isset($response['expires_in']) ? 
            date('Y-m-d H:i:s', time() + $response['expires_in']) : null;
        
        $this->updateAccountTokens(
            $response['access_token'],
            $response['refresh_token'] ?? $this->account['refresh_token'],
            $expiresAt
        );
        
        return true;
    }
    
    public function post($content, $mediaFiles = [], $options = []) {
        if (!$this->account) {
            throw new Exception("No account loaded");
        }
        
        // TODO: Implement actual Twitter posting
        // This is a placeholder - real implementation would:
        // 1. Upload media if any
        // 2. Create tweet with content and media IDs
        
        return [
            'success' => true,
            'platform_post_id' => 'placeholder_' . uniqid(),
            'message' => 'Twitter posting not yet implemented',
        ];
    }
    
    public function getAccountInfo() {
        if (!$this->account) {
            throw new Exception("No account loaded");
        }
        
        try {
            $response = $this->makeApiRequest(
                $this->apiUrl . '/users/me?user.fields=public_metrics',
                'GET',
                null,
                ['Authorization: Bearer ' . $this->account['access_token']]
            );
            
            return [
                'username' => '@' . $response['data']['username'],
                'followers' => $response['data']['public_metrics']['followers_count'] ?? 0,
                'media_count' => $response['data']['public_metrics']['tweet_count'] ?? 0,
            ];
        } catch (Exception $e) {
            return [
                'username' => 'Unknown',
                'followers' => 0,
                'media_count' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }
    
    public function validatePost($content, $mediaFiles = []) {
        $errors = [];
        
        if (strlen($content) > 280) {
            $errors[] = "Tweet too long (max 280 characters)";
        }
        
        if (count($mediaFiles) > 4) {
            $errors[] = "Too many media files (max 4)";
        }
        
        return $errors;
    }
    
    public function getCharacterLimit() {
        return 280;
    }
    
    public function getMediaLimits() {
        return [
            'max_files' => 4,
            'max_file_size' => 5 * 1024 * 1024, // 5MB
            'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov'],
            'video_max_duration' => 140, // 2:20 minutes
        ];
    }
}

// Helper function for base64url encoding
if (!function_exists('base64url_encode')) {
    function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}