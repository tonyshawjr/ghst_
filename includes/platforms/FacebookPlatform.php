<?php
require_once __DIR__ . '/Platform.php';

/**
 * Facebook Platform Integration
 */
class FacebookPlatform extends Platform {
    private $apiUrl = 'https://graph.facebook.com/v18.0';
    private $authUrl = 'https://www.facebook.com/v18.0/dialog/oauth';
    
    public function getName() {
        return 'facebook';
    }
    
    public function getAuthUrl($redirectUri, $state = null) {
        $params = [
            'client_id' => FB_APP_ID,
            'redirect_uri' => $redirectUri,
            'scope' => 'pages_manage_posts,pages_read_engagement,pages_show_list',
            'response_type' => 'code',
        ];
        
        if ($state) {
            $params['state'] = $state;
        }
        
        return $this->authUrl . '?' . http_build_query($params);
    }
    
    public function handleCallback($code, $state = null, $redirectUri = null) {
        // Exchange code for access token
        $response = $this->makeApiRequest(
            $this->apiUrl . '/oauth/access_token?' . http_build_query([
                'client_id' => FB_APP_ID,
                'client_secret' => FB_APP_SECRET,
                'redirect_uri' => $redirectUri,
                'code' => $code,
            ])
        );
        
        // Get user info
        $userInfo = $this->makeApiRequest(
            $this->apiUrl . '/me?' . http_build_query([
                'access_token' => $response['access_token'],
                'fields' => 'id,name',
            ])
        );
        
        return [
            'access_token' => $response['access_token'],
            'expires_at' => null, // Facebook tokens don't expire unless explicitly set
            'platform_user_id' => $userInfo['id'],
            'platform_username' => $userInfo['name'],
        ];
    }
    
    public function refreshToken() {
        if (!$this->account) {
            throw new Exception("No account loaded");
        }
        
        // Facebook long-lived tokens don't need refreshing unless they expire
        // This is a placeholder for when we implement short-lived token refresh
        return true;
    }
    
    public function post($content, $mediaFiles = [], $options = []) {
        if (!$this->account) {
            throw new Exception("No account loaded");
        }
        
        // TODO: Implement actual Facebook posting
        // This is a placeholder - real implementation would:
        // 1. Get user's pages
        // 2. Select target page
        // 3. Upload media if any
        // 4. Create post with content and media
        
        return [
            'success' => true,
            'platform_post_id' => 'placeholder_' . uniqid(),
            'message' => 'Facebook posting not yet implemented',
        ];
    }
    
    public function getAccountInfo() {
        if (!$this->account) {
            throw new Exception("No account loaded");
        }
        
        try {
            $response = $this->makeApiRequest(
                $this->apiUrl . '/me?' . http_build_query([
                    'access_token' => $this->account['access_token'],
                    'fields' => 'id,name',
                ])
            );
            
            return [
                'username' => $response['name'],
                'followers' => 0, // Would need page-specific API call
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
        
        if (strlen($content) > 63206) {
            $errors[] = "Post too long (max 63,206 characters)";
        }
        
        if (count($mediaFiles) > 30) {
            $errors[] = "Too many media files (max 30)";
        }
        
        return $errors;
    }
    
    public function getCharacterLimit() {
        return 63206;
    }
    
    public function getMediaLimits() {
        return [
            'max_files' => 30,
            'max_file_size' => 4 * 1024 * 1024 * 1024, // 4GB
            'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov', 'avi'],
            'video_max_duration' => 240 * 60, // 240 minutes
        ];
    }
}