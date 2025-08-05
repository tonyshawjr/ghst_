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
            'client_id' => FB_APP_ID,
            'redirect_uri' => $redirectUri,
            'scope' => 'user_profile,user_media',
            'response_type' => 'code',
        ];
        
        if ($state) {
            $params['state'] = $state;
        }
        
        return $this->authUrl . '?' . http_build_query($params);
    }
    
    public function handleCallback($code, $state = null, $redirectUri = null) {
        // Exchange code for access token
        $tokenData = [
            'client_id' => FB_APP_ID,
            'client_secret' => FB_APP_SECRET,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
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
                'client_secret' => FB_APP_SECRET,
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
        
        // Check if token needs refresh
        if ($this->isTokenExpired()) {
            $this->refreshToken();
        }
        
        try {
            // Check rate limits
            $this->checkRateLimit('post');
            
            // Get Instagram Business Account ID
            $igAccountId = $this->getInstagramBusinessAccountId();
            
            if (count($mediaFiles) === 1) {
                // Single media post
                $mediaId = $this->createMediaContainer($igAccountId, $mediaFiles[0], $content);
            } else {
                // Carousel post (multiple media)
                $mediaId = $this->createCarouselContainer($igAccountId, $mediaFiles, $content);
            }
            
            // Publish the media container
            $result = $this->publishMedia($igAccountId, $mediaId);
            
            // Record successful action for rate limiting
            $this->recordApiAction('post');
            
            return [
                'success' => true,
                'platform_post_id' => $result['id'],
                'message' => 'Successfully posted to Instagram',
            ];
        } catch (Exception $e) {
            throw new Exception("Instagram posting failed: " . $e->getMessage());
        }
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
    
    /**
     * Get Instagram Business Account ID from Facebook Page
     */
    private function getInstagramBusinessAccountId() {
        // First, get the Facebook Page ID associated with this Instagram account
        $pageId = $this->account['account_data'] ? json_decode($this->account['account_data'], true)['page_id'] ?? null : null;
        
        if (!$pageId) {
            // Get pages and find one with Instagram Business Account
            $response = $this->makeApiRequest(
                'https://graph.facebook.com/v18.0/me/accounts?' . http_build_query([
                    'access_token' => $this->account['access_token'],
                    'fields' => 'id,name,instagram_business_account',
                ])
            );
            
            foreach ($response['data'] as $page) {
                if (isset($page['instagram_business_account'])) {
                    // Update account data with page info
                    $accountData = json_decode($this->account['account_data'], true) ?: [];
                    $accountData['page_id'] = $page['id'];
                    $accountData['page_name'] = $page['name'];
                    $accountData['instagram_business_account_id'] = $page['instagram_business_account']['id'];
                    
                    $stmt = $this->db->prepare("UPDATE accounts SET account_data = ? WHERE id = ?");
                    $stmt->execute([json_encode($accountData), $this->account['id']]);
                    
                    return $page['instagram_business_account']['id'];
                }
            }
            
            throw new Exception("No Instagram Business Account found connected to your Facebook pages");
        }
        
        // Get Instagram Business Account ID from page
        $response = $this->makeApiRequest(
            "https://graph.facebook.com/v18.0/{$pageId}?" . http_build_query([
                'fields' => 'instagram_business_account',
                'access_token' => $this->account['access_token'],
            ])
        );
        
        if (!isset($response['instagram_business_account'])) {
            throw new Exception("No Instagram Business Account connected to this Facebook page");
        }
        
        return $response['instagram_business_account']['id'];
    }
    
    /**
     * Create a media container for a single image/video
     */
    private function createMediaContainer($igAccountId, $mediaFile, $caption) {
        $mediaUrl = $this->getMediaUrl($mediaFile);
        $isVideo = $this->isVideoFile($mediaFile);
        
        $params = [
            'access_token' => $this->account['access_token'],
            'caption' => $caption,
        ];
        
        if ($isVideo) {
            $params['media_type'] = 'VIDEO';
            $params['video_url'] = $mediaUrl;
        } else {
            $params['image_url'] = $mediaUrl;
        }
        
        $response = $this->makeApiRequest(
            "https://graph.facebook.com/v18.0/{$igAccountId}/media",
            'POST',
            http_build_query($params),
            ['Content-Type: application/x-www-form-urlencoded']
        );
        
        if (!isset($response['id'])) {
            throw new Exception("Failed to create media container");
        }
        
        // Wait for container to be ready (especially for videos)
        if ($isVideo) {
            $this->waitForMediaProcessing($response['id']);
        }
        
        return $response['id'];
    }
    
    /**
     * Create a carousel container for multiple images/videos
     */
    private function createCarouselContainer($igAccountId, $mediaFiles, $caption) {
        $childMediaIds = [];
        
        // Create child media containers (without caption)
        foreach ($mediaFiles as $mediaFile) {
            $mediaUrl = $this->getMediaUrl($mediaFile);
            $isVideo = $this->isVideoFile($mediaFile);
            
            $params = [
                'access_token' => $this->account['access_token'],
                'is_carousel_item' => true,
            ];
            
            if ($isVideo) {
                $params['media_type'] = 'VIDEO';
                $params['video_url'] = $mediaUrl;
            } else {
                $params['image_url'] = $mediaUrl;
            }
            
            $response = $this->makeApiRequest(
                "https://graph.facebook.com/v18.0/{$igAccountId}/media",
                'POST',
                http_build_query($params),
                ['Content-Type: application/x-www-form-urlencoded']
            );
            
            if (!isset($response['id'])) {
                throw new Exception("Failed to create child media container");
            }
            
            $childMediaIds[] = $response['id'];
            
            // Wait for video processing if needed
            if ($isVideo) {
                $this->waitForMediaProcessing($response['id']);
            }
        }
        
        // Create carousel container with children
        $params = [
            'access_token' => $this->account['access_token'],
            'media_type' => 'CAROUSEL',
            'caption' => $caption,
            'children' => implode(',', $childMediaIds),
        ];
        
        $response = $this->makeApiRequest(
            "https://graph.facebook.com/v18.0/{$igAccountId}/media",
            'POST',
            http_build_query($params),
            ['Content-Type: application/x-www-form-urlencoded']
        );
        
        if (!isset($response['id'])) {
            throw new Exception("Failed to create carousel container");
        }
        
        return $response['id'];
    }
    
    /**
     * Publish a media container
     */
    private function publishMedia($igAccountId, $mediaId) {
        $response = $this->makeApiRequest(
            "https://graph.facebook.com/v18.0/{$igAccountId}/media_publish",
            'POST',
            http_build_query([
                'creation_id' => $mediaId,
                'access_token' => $this->account['access_token'],
            ]),
            ['Content-Type: application/x-www-form-urlencoded']
        );
        
        if (!isset($response['id'])) {
            throw new Exception("Failed to publish media");
        }
        
        return $response;
    }
    
    /**
     * Wait for media processing to complete
     */
    private function waitForMediaProcessing($containerId, $maxAttempts = 30, $delay = 2) {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $response = $this->makeApiRequest(
                "https://graph.facebook.com/v18.0/{$containerId}?" . http_build_query([
                    'fields' => 'status_code',
                    'access_token' => $this->account['access_token'],
                ])
            );
            
            if (isset($response['status_code'])) {
                if ($response['status_code'] === 'FINISHED') {
                    return true;
                } elseif ($response['status_code'] === 'ERROR') {
                    throw new Exception("Media processing failed");
                }
            }
            
            sleep($delay);
        }
        
        throw new Exception("Media processing timeout");
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