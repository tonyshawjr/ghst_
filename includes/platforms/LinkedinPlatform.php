<?php
require_once __DIR__ . '/Platform.php';

/**
 * LinkedIn Platform Integration
 */
class LinkedinPlatform extends Platform {
    private $apiUrl = 'https://api.linkedin.com/v2';
    private $authUrl = 'https://www.linkedin.com/oauth/v2/authorization';
    
    public function getName() {
        return 'linkedin';
    }
    
    public function getAuthUrl($redirectUri, $state = null) {
        $params = [
            'response_type' => 'code',
            'client_id' => LINKEDIN_CLIENT_ID,
            'redirect_uri' => $redirectUri,
            'scope' => 'r_liteprofile r_emailaddress w_member_social',
        ];
        
        if ($state) {
            $params['state'] = $state;
        }
        
        return $this->authUrl . '?' . http_build_query($params);
    }
    
    public function handleCallback($code, $state = null) {
        // Exchange code for access token
        $tokenData = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => LINKEDIN_REDIRECT_URI,
            'client_id' => LINKEDIN_CLIENT_ID,
            'client_secret' => LINKEDIN_CLIENT_SECRET,
        ];
        
        $response = $this->makeApiRequest(
            'https://www.linkedin.com/oauth/v2/accessToken',
            'POST',
            http_build_query($tokenData),
            ['Content-Type: application/x-www-form-urlencoded']
        );
        
        // Get user info
        $userInfo = $this->makeApiRequest(
            $this->apiUrl . '/people/~',
            'GET',
            null,
            ['Authorization: Bearer ' . $response['access_token']]
        );
        
        $expiresAt = date('Y-m-d H:i:s', time() + $response['expires_in']);
        
        return [
            'access_token' => $response['access_token'],
            'expires_at' => $expiresAt,
            'platform_user_id' => $userInfo['id'],
            'platform_username' => ($userInfo['localizedFirstName'] ?? '') . ' ' . ($userInfo['localizedLastName'] ?? ''),
        ];
    }
    
    public function refreshToken() {
        // LinkedIn doesn't support refresh tokens in their current API
        // Users need to re-authenticate when tokens expire
        throw new Exception("LinkedIn requires re-authentication when tokens expire");
    }
    
    public function post($content, $mediaFiles = [], $options = []) {
        if (!$this->account) {
            throw new Exception("No account loaded");
        }
        
        // TODO: Implement actual LinkedIn posting
        // This is a placeholder - real implementation would:
        // 1. Upload media if any
        // 2. Create share/post with content and media references
        
        return [
            'success' => true,
            'platform_post_id' => 'placeholder_' . uniqid(),
            'message' => 'LinkedIn posting not yet implemented',
        ];
    }
    
    public function getAccountInfo() {
        if (!$this->account) {
            throw new Exception("No account loaded");
        }
        
        try {
            $response = $this->makeApiRequest(
                $this->apiUrl . '/people/~',
                'GET',
                null,
                ['Authorization: Bearer ' . $this->account['access_token']]
            );
            
            $name = ($response['localizedFirstName'] ?? '') . ' ' . ($response['localizedLastName'] ?? '');
            
            return [
                'username' => trim($name) ?: 'LinkedIn User',
                'followers' => 0, // Would require additional API calls
                'media_count' => 0,
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
        
        if (strlen($content) > 3000) {
            $errors[] = "Post too long (max 3,000 characters)";
        }
        
        if (count($mediaFiles) > 20) {
            $errors[] = "Too many media files (max 20)";
        }
        
        return $errors;
    }
    
    public function getCharacterLimit() {
        return 3000;
    }
    
    public function getMediaLimits() {
        return [
            'max_files' => 20,
            'max_file_size' => 200 * 1024 * 1024, // 200MB
            'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov'],
            'video_max_duration' => 10 * 60, // 10 minutes
        ];
    }
}