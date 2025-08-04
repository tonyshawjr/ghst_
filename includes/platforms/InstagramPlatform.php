<?php
require_once __DIR__ . '/Platform.php';

/**
 * Instagram Platform Integration
 */
class InstagramPlatform extends Platform {
    private $apiUrl = 'https://graph.instagram.com';
    private $authUrl = 'https://api.instagram.com/oauth/authorize';
    
    public function getName() {
        return 'instagram';
    }
    
    public function getAuthUrl($redirectUri, $state = null) {
        $params = [
            'client_id' => INSTAGRAM_CLIENT_ID,
            'redirect_uri' => $redirectUri,
            'scope' => 'user_profile,user_media',
            'response_type' => 'code',
        ];
        
        if ($state) {
            $params['state'] = $state;
        }
        
        return $this->authUrl . '?' . http_build_query($params);
    }
    
    public function handleCallback($code, $state = null) {
        // Exchange code for access token
        $tokenData = [
            'client_id' => INSTAGRAM_CLIENT_ID,
            'client_secret' => INSTAGRAM_CLIENT_SECRET,
            'grant_type' => 'authorization_code',
            'redirect_uri' => INSTAGRAM_REDIRECT_URI,
            'code' => $code,
        ];
        
        $response = $this->makeApiRequest(
            'https://api.instagram.com/oauth/access_token',
            'POST',
            http_build_query($tokenData),
            ['Content-Type: application/x-www-form-urlencoded']
        );
        
        // Get long-lived token
        $longLivedResponse = $this->makeApiRequest(
            $this->apiUrl . '/access_token?' . http_build_query([
                'grant_type' => 'ig_exchange_token',
                'client_secret' => INSTAGRAM_CLIENT_SECRET,
                'access_token' => $response['access_token'],
            ])
        );
        
        return [
            'access_token' => $longLivedResponse['access_token'],
            'expires_at' => date('Y-m-d H:i:s', time() + $longLivedResponse['expires_in']),
            'platform_user_id' => $response['user_id'],
        ];
    }
    
    public function refreshToken() {
        if (!$this->account) {
            throw new Exception("No account loaded");
        }
        
        $response = $this->makeApiRequest(
            $this->apiUrl . '/refresh_access_token?' . http_build_query([
                'grant_type' => 'ig_refresh_token',
                'access_token' => $this->account['access_token'],
            ])
        );
        
        $expiresAt = date('Y-m-d H:i:s', time() + $response['expires_in']);
        $this->updateAccountTokens($response['access_token'], null, $expiresAt);
        
        return true;
    }
    
    public function post($content, $mediaFiles = [], $options = []) {
        if (!$this->account) {
            throw new Exception("No account loaded");
        }
        
        // Instagram requires media for posts
        if (empty($mediaFiles)) {
            throw new Exception("Instagram posts require at least one media file");
        }
        
        // TODO: Implement actual Instagram posting
        // This is a placeholder - real implementation would:
        // 1. Upload media to Instagram
        // 2. Create media container
        // 3. Publish the container
        
        return [
            'success' => true,
            'platform_post_id' => 'placeholder_' . uniqid(),
            'message' => 'Instagram posting not yet implemented',
        ];
    }
    
    public function getAccountInfo() {
        if (!$this->account) {
            throw new Exception("No account loaded");
        }
        
        try {
            $response = $this->makeApiRequest(
                $this->apiUrl . '/me?' . http_build_query([
                    'fields' => 'id,username,media_count,followers_count',
                    'access_token' => $this->account['access_token'],
                ])
            );
            
            return [
                'username' => $response['username'],
                'followers' => $response['followers_count'] ?? 0,
                'media_count' => $response['media_count'] ?? 0,
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
        
        if (empty($mediaFiles)) {
            $errors[] = "Instagram posts require at least one image or video";
        }
        
        if (strlen($content) > 2200) {
            $errors[] = "Caption too long (max 2200 characters)";
        }
        
        return $errors;
    }
    
    public function getCharacterLimit() {
        return 2200;
    }
    
    public function getMediaLimits() {
        return [
            'max_files' => 10,
            'max_file_size' => 100 * 1024 * 1024, // 100MB
            'allowed_types' => ['jpg', 'jpeg', 'png', 'mp4', 'mov'],
            'video_max_duration' => 60, // seconds
        ];
    }
}