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
    
    public function handleCallback($code, $state = null, $redirectUri = null) {
        // Exchange code for access token
        $tokenData = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
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
        
        // Check if token needs refresh (LinkedIn doesn't support refresh)
        if ($this->isTokenExpired()) {
            throw new Exception("LinkedIn access token has expired. Please re-authenticate.");
        }
        
        try {
            // Check rate limits
            $this->checkRateLimit('post');
            
            // Get user URN
            $userUrn = $this->getUserUrn();
            
            // Prepare share content
            $shareData = [
                'author' => $userUrn,
                'lifecycleState' => 'PUBLISHED',
                'specificContent' => [
                    'com.linkedin.ugc.ShareContent' => [
                        'shareCommentary' => [
                            'text' => $content
                        ],
                        'shareMediaCategory' => empty($mediaFiles) ? 'NONE' : 'IMAGE',
                    ]
                ]
            ];
            
            // Add media if provided
            if (!empty($mediaFiles)) {
                $mediaUrns = $this->uploadMedia($mediaFiles, $userUrn);
                $shareData['specificContent']['com.linkedin.ugc.ShareContent']['media'] = 
                    array_map(function($urn) {
                        return [
                            'status' => 'READY',
                            'media' => $urn
                        ];
                    }, $mediaUrns);
            }
            
            // Add visibility settings
            $shareData['visibility'] = [
                'com.linkedin.ugc.MemberNetworkVisibility' => $options['visibility'] ?? 'PUBLIC'
            ];
            
            // Create the post
            $response = $this->makeApiRequest(
                $this->apiUrl . '/ugcPosts',
                'POST',
                json_encode($shareData),
                [
                    'Authorization: Bearer ' . $this->account['access_token'],
                    'Content-Type: application/json',
                    'X-RestLi-Protocol-Version: 2.0.0',
                ]
            );
            
            if (!isset($response['id'])) {
                throw new Exception("Failed to create LinkedIn post");
            }
            
            // Record successful action for rate limiting
            $this->recordApiAction('post');
            
            return [
                'success' => true,
                'platform_post_id' => $response['id'],
                'message' => 'Successfully posted to LinkedIn',
            ];
        } catch (Exception $e) {
            throw new Exception("LinkedIn posting failed: " . $e->getMessage());
        }
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
    
    /**
     * Get the current user's URN
     */
    private function getUserUrn() {
        // Check if we have cached URN
        $accountData = $this->account['account_data'] ? json_decode($this->account['account_data'], true) : [];
        if (isset($accountData['user_urn'])) {
            return $accountData['user_urn'];
        }
        
        // Get user profile
        $response = $this->makeApiRequest(
            $this->apiUrl . '/me',
            'GET',
            null,
            [
                'Authorization: Bearer ' . $this->account['access_token'],
                'X-RestLi-Protocol-Version: 2.0.0',
            ]
        );
        
        if (!isset($response['id'])) {
            throw new Exception("Unable to get LinkedIn user ID");
        }
        
        $userUrn = 'urn:li:person:' . $response['id'];
        
        // Cache URN for future use
        $accountData['user_urn'] = $userUrn;
        $stmt = $this->db->prepare("UPDATE accounts SET account_data = ? WHERE id = ?");
        $stmt->execute([json_encode($accountData), $this->account['id']]);
        
        return $userUrn;
    }
    
    /**
     * Upload media files to LinkedIn
     */
    private function uploadMedia($mediaFiles, $userUrn) {
        $mediaUrns = [];
        
        foreach ($mediaFiles as $mediaFile) {
            $mediaUrn = $this->uploadSingleMedia($mediaFile, $userUrn);
            if ($mediaUrn) {
                $mediaUrns[] = $mediaUrn;
            }
        }
        
        if (empty($mediaUrns)) {
            throw new Exception("Failed to upload any media files");
        }
        
        return $mediaUrns;
    }
    
    /**
     * Upload a single media file
     */
    private function uploadSingleMedia($mediaFile, $userUrn) {
        $filePath = UPLOADS_PATH . '/' . $mediaFile['filename'];
        
        if (!file_exists($filePath)) {
            throw new Exception("Media file not found: " . $mediaFile['filename']);
        }
        
        // Register upload
        $registerData = [
            'registerUploadRequest' => [
                'recipes' => ['urn:li:digitalmediaRecipe:feedshare-image'],
                'owner' => $userUrn,
                'serviceRelationships' => [
                    [
                        'relationshipType' => 'OWNER',
                        'identifier' => 'urn:li:userGeneratedContent'
                    ]
                ]
            ]
        ];
        
        $registerResponse = $this->makeApiRequest(
            'https://api.linkedin.com/v2/assets?action=registerUpload',
            'POST',
            json_encode($registerData),
            [
                'Authorization: Bearer ' . $this->account['access_token'],
                'Content-Type: application/json',
            ]
        );
        
        if (!isset($registerResponse['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'])) {
            throw new Exception("Failed to register media upload");
        }
        
        $uploadUrl = $registerResponse['value']['uploadMechanism']['com.linkedin.digitalmedia.uploading.MediaUploadHttpRequest']['uploadUrl'];
        $asset = $registerResponse['value']['asset'];
        
        // Upload the file
        $fileData = file_get_contents($filePath);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $uploadUrl,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $fileData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->account['access_token'],
                'Content-Type: ' . $mediaFile['type'],
            ],
        ]);
        
        $uploadResponse = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 201) {
            throw new Exception("Failed to upload media file: HTTP $httpCode");
        }
        
        // Check upload status
        $this->checkUploadStatus($asset);
        
        return $asset;
    }
    
    /**
     * Check media upload status
     */
    private function checkUploadStatus($assetUrn, $maxAttempts = 30, $delay = 2) {
        // Extract asset ID from URN
        $assetId = str_replace('urn:li:digitalmediaAsset:', '', $assetUrn);
        
        for ($i = 0; $i < $maxAttempts; $i++) {
            $response = $this->makeApiRequest(
                'https://api.linkedin.com/v2/assets/' . $assetId,
                'GET',
                null,
                ['Authorization: Bearer ' . $this->account['access_token']]
            );
            
            if (isset($response['recipes'][0]['status']) && $response['recipes'][0]['status'] === 'AVAILABLE') {
                return true;
            }
            
            sleep($delay);
        }
        
        throw new Exception("Media upload processing timeout");
    }
}