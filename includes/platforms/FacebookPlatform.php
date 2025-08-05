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
        
        // Check if token needs refresh
        if ($this->isTokenExpired()) {
            $this->refreshToken();
        }
        
        try {
            // Check rate limits
            $this->checkRateLimit('post');
            
            // Get target page (use provided page_id or get first available)
            $pageId = $options['page_id'] ?? $this->getDefaultPageId();
            $pageAccessToken = $this->getPageAccessToken($pageId);
            
            if (empty($mediaFiles)) {
                // Text-only post
                $result = $this->createTextPost($pageId, $content, $pageAccessToken);
            } elseif (count($mediaFiles) === 1 && $this->isVideoFile($mediaFiles[0])) {
                // Single video post
                $result = $this->createVideoPost($pageId, $content, $mediaFiles[0], $pageAccessToken);
            } elseif (count($mediaFiles) === 1) {
                // Single photo post
                $result = $this->createPhotoPost($pageId, $content, $mediaFiles[0], $pageAccessToken);
            } else {
                // Multi-photo post
                $result = $this->createMultiPhotoPost($pageId, $content, $mediaFiles, $pageAccessToken);
            }
            
            // Record successful action for rate limiting
            $this->recordApiAction('post');
            
            return [
                'success' => true,
                'platform_post_id' => $result['id'],
                'message' => 'Successfully posted to Facebook',
            ];
        } catch (Exception $e) {
            throw new Exception("Facebook posting failed: " . $e->getMessage());
        }
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
    
    /**
     * Get the default page ID for posting
     */
    private function getDefaultPageId() {
        $accountData = $this->account['account_data'] ? json_decode($this->account['account_data'], true) : [];
        
        if (isset($accountData['default_page_id'])) {
            return $accountData['default_page_id'];
        }
        
        // Get user's pages and select the first one
        $response = $this->makeApiRequest(
            $this->apiUrl . '/me/accounts?' . http_build_query([
                'access_token' => $this->account['access_token'],
                'fields' => 'id,name,access_token',
            ])
        );
        
        if (empty($response['data'])) {
            throw new Exception("No Facebook pages found. Please create a page first.");
        }
        
        // Save default page for future use
        $page = $response['data'][0];
        $accountData['default_page_id'] = $page['id'];
        $accountData['default_page_name'] = $page['name'];
        
        $stmt = $this->db->prepare("UPDATE accounts SET account_data = ? WHERE id = ?");
        $stmt->execute([json_encode($accountData), $this->account['id']]);
        
        return $page['id'];
    }
    
    /**
     * Get page access token
     */
    private function getPageAccessToken($pageId) {
        $response = $this->makeApiRequest(
            $this->apiUrl . '/' . $pageId . '?' . http_build_query([
                'fields' => 'access_token',
                'access_token' => $this->account['access_token'],
            ])
        );
        
        if (!isset($response['access_token'])) {
            throw new Exception("Unable to get page access token");
        }
        
        return $response['access_token'];
    }
    
    /**
     * Create a text-only post
     */
    private function createTextPost($pageId, $content, $pageAccessToken) {
        return $this->makeApiRequest(
            $this->apiUrl . '/' . $pageId . '/feed',
            'POST',
            http_build_query([
                'message' => $content,
                'access_token' => $pageAccessToken,
            ]),
            ['Content-Type: application/x-www-form-urlencoded']
        );
    }
    
    /**
     * Create a single photo post
     */
    private function createPhotoPost($pageId, $content, $mediaFile, $pageAccessToken) {
        $photoUrl = $this->getMediaUrl($mediaFile);
        
        return $this->makeApiRequest(
            $this->apiUrl . '/' . $pageId . '/photos',
            'POST',
            http_build_query([
                'url' => $photoUrl,
                'message' => $content,
                'access_token' => $pageAccessToken,
            ]),
            ['Content-Type: application/x-www-form-urlencoded']
        );
    }
    
    /**
     * Create a video post
     */
    private function createVideoPost($pageId, $content, $mediaFile, $pageAccessToken) {
        $videoUrl = $this->getMediaUrl($mediaFile);
        
        // Start video upload
        $response = $this->makeApiRequest(
            $this->apiUrl . '/' . $pageId . '/videos',
            'POST',
            http_build_query([
                'file_url' => $videoUrl,
                'description' => $content,
                'access_token' => $pageAccessToken,
            ]),
            ['Content-Type: application/x-www-form-urlencoded']
        );
        
        return $response;
    }
    
    /**
     * Create a multi-photo post
     */
    private function createMultiPhotoPost($pageId, $content, $mediaFiles, $pageAccessToken) {
        $photoIds = [];
        
        // Upload each photo individually without publishing
        foreach ($mediaFiles as $mediaFile) {
            if ($this->isVideoFile($mediaFile)) {
                continue; // Skip videos in multi-photo posts
            }
            
            $photoUrl = $this->getMediaUrl($mediaFile);
            
            $response = $this->makeApiRequest(
                $this->apiUrl . '/' . $pageId . '/photos',
                'POST',
                http_build_query([
                    'url' => $photoUrl,
                    'published' => 'false',
                    'access_token' => $pageAccessToken,
                ]),
                ['Content-Type: application/x-www-form-urlencoded']
            );
            
            if (isset($response['id'])) {
                $photoIds[] = $response['id'];
            }
        }
        
        if (empty($photoIds)) {
            throw new Exception("No valid photos to post");
        }
        
        // Create the multi-photo post
        $attachedMedia = [];
        foreach ($photoIds as $photoId) {
            $attachedMedia[] = json_encode(['media_fbid' => $photoId]);
        }
        
        return $this->makeApiRequest(
            $this->apiUrl . '/' . $pageId . '/feed',
            'POST',
            [
                'message' => $content,
                'attached_media' => $attachedMedia,
                'access_token' => $pageAccessToken,
            ]
        );
    }
    
    /**
     * Get publicly accessible URL for media file
     */
    private function getMediaUrl($mediaFile) {
        // Media files should be accessible via public URL
        // In production, these would be uploaded to a CDN or temporary storage
        $baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . 
                  '://' . $_SERVER['HTTP_HOST'];
        
        return $baseUrl . '/uploads/' . $mediaFile['filename'];
    }
    
    /**
     * Check if file is a video
     */
    private function isVideoFile($mediaFile) {
        $videoExtensions = ['mp4', 'mov', 'avi'];
        $extension = strtolower(pathinfo($mediaFile['filename'], PATHINFO_EXTENSION));
        return in_array($extension, $videoExtensions);
    }
}