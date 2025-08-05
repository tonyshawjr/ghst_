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
            'client_id' => TWITTER_API_KEY,
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
    
    public function handleCallback($code, $state = null, $redirectUri = null) {
        $codeVerifier = $_SESSION['twitter_code_verifier'] ?? null;
        if (!$codeVerifier) {
            throw new Exception("Code verifier not found in session");
        }
        
        // Exchange code for access token
        $tokenData = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'code_verifier' => $codeVerifier,
        ];
        
        $credentials = base64_encode(TWITTER_API_KEY . ':' . TWITTER_API_SECRET);
        
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
        
        $credentials = base64_encode(TWITTER_API_KEY . ':' . TWITTER_API_SECRET);
        
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
        
        // Check if token needs refresh
        if ($this->isTokenExpired()) {
            $this->refreshToken();
        }
        
        try {
            // Check rate limits
            $this->checkRateLimit('post');
            
            $tweetData = ['text' => $content];
            
            // Upload media if provided
            if (!empty($mediaFiles)) {
                $mediaIds = $this->uploadMedia($mediaFiles);
                $tweetData['media'] = ['media_ids' => $mediaIds];
            }
            
            // Add reply settings if specified
            if (isset($options['reply_settings'])) {
                $tweetData['reply_settings'] = $options['reply_settings'];
            }
            
            // Create tweet
            $response = $this->makeApiRequest(
                $this->apiUrl . '/tweets',
                'POST',
                json_encode($tweetData),
                [
                    'Authorization: Bearer ' . $this->account['access_token'],
                    'Content-Type: application/json',
                ]
            );
            
            if (!isset($response['data']['id'])) {
                throw new Exception("Failed to create tweet");
            }
            
            // Record successful action for rate limiting
            $this->recordApiAction('post');
            
            return [
                'success' => true,
                'platform_post_id' => $response['data']['id'],
                'message' => 'Successfully posted to Twitter',
            ];
        } catch (Exception $e) {
            throw new Exception("Twitter posting failed: " . $e->getMessage());
        }
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
    
    /**
     * Upload media files to Twitter
     */
    private function uploadMedia($mediaFiles) {
        $mediaIds = [];
        
        foreach ($mediaFiles as $mediaFile) {
            $mediaId = $this->uploadSingleMedia($mediaFile);
            if ($mediaId) {
                $mediaIds[] = $mediaId;
            }
        }
        
        if (empty($mediaIds)) {
            throw new Exception("Failed to upload any media files");
        }
        
        return $mediaIds;
    }
    
    /**
     * Upload a single media file
     */
    private function uploadSingleMedia($mediaFile) {
        $filePath = UPLOADS_PATH . '/' . $mediaFile['filename'];
        
        if (!file_exists($filePath)) {
            throw new Exception("Media file not found: " . $mediaFile['filename']);
        }
        
        $fileSize = filesize($filePath);
        $mimeType = $mediaFile['type'];
        $isVideo = $this->isVideoFile($mediaFile);
        
        // Initialize upload
        $initParams = [
            'command' => 'INIT',
            'total_bytes' => $fileSize,
            'media_type' => $mimeType,
        ];
        
        if ($isVideo) {
            $initParams['media_category'] = 'tweet_video';
        }
        
        $initResponse = $this->makeApiRequest(
            'https://upload.twitter.com/1.1/media/upload.json',
            'POST',
            http_build_query($initParams),
            [
                'Authorization: Bearer ' . $this->account['access_token'],
                'Content-Type: application/x-www-form-urlencoded',
            ]
        );
        
        if (!isset($initResponse['media_id_string'])) {
            throw new Exception("Failed to initialize media upload");
        }
        
        $mediaId = $initResponse['media_id_string'];
        
        // Upload chunks
        $chunkSize = 5 * 1024 * 1024; // 5MB chunks
        $segmentIndex = 0;
        $handle = fopen($filePath, 'rb');
        
        while (!feof($handle)) {
            $chunk = fread($handle, $chunkSize);
            
            $appendParams = [
                'command' => 'APPEND',
                'media_id' => $mediaId,
                'segment_index' => $segmentIndex,
                'media' => base64_encode($chunk),
            ];
            
            $this->makeApiRequest(
                'https://upload.twitter.com/1.1/media/upload.json',
                'POST',
                http_build_query($appendParams),
                [
                    'Authorization: Bearer ' . $this->account['access_token'],
                    'Content-Type: application/x-www-form-urlencoded',
                ]
            );
            
            $segmentIndex++;
        }
        
        fclose($handle);
        
        // Finalize upload
        $finalizeParams = [
            'command' => 'FINALIZE',
            'media_id' => $mediaId,
        ];
        
        $finalizeResponse = $this->makeApiRequest(
            'https://upload.twitter.com/1.1/media/upload.json',
            'POST',
            http_build_query($finalizeParams),
            [
                'Authorization: Bearer ' . $this->account['access_token'],
                'Content-Type: application/x-www-form-urlencoded',
            ]
        );
        
        // Wait for processing if needed (videos)
        if (isset($finalizeResponse['processing_info'])) {
            $this->waitForMediaProcessing($mediaId);
        }
        
        return $mediaId;
    }
    
    /**
     * Wait for media processing to complete
     */
    private function waitForMediaProcessing($mediaId, $maxAttempts = 30, $delay = 2) {
        for ($i = 0; $i < $maxAttempts; $i++) {
            $statusResponse = $this->makeApiRequest(
                'https://upload.twitter.com/1.1/media/upload.json?' . http_build_query([
                    'command' => 'STATUS',
                    'media_id' => $mediaId,
                ]),
                'GET',
                null,
                ['Authorization: Bearer ' . $this->account['access_token']]
            );
            
            if (!isset($statusResponse['processing_info'])) {
                return true; // Processing complete
            }
            
            $state = $statusResponse['processing_info']['state'];
            
            if ($state === 'succeeded') {
                return true;
            } elseif ($state === 'failed') {
                throw new Exception("Media processing failed: " . 
                    ($statusResponse['processing_info']['error']['message'] ?? 'Unknown error'));
            }
            
            // Still processing, wait
            sleep($delay);
        }
        
        throw new Exception("Media processing timeout");
    }
    
    /**
     * Check if file is a video
     */
    private function isVideoFile($mediaFile) {
        $videoExtensions = ['mp4', 'mov'];
        $extension = strtolower(pathinfo($mediaFile['filename'], PATHINFO_EXTENSION));
        return in_array($extension, $videoExtensions);
    }
}

// Helper function for base64url encoding
if (!function_exists('base64url_encode')) {
    function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}