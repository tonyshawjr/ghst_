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
    private function createTextPost($pageId, $content, $pageAccessToken): array {
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
    private function createPhotoPost($pageId, $content, $mediaFile, $pageAccessToken): array {
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
    private function createVideoPost($pageId, $content, $mediaFile, $pageAccessToken): array {
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
    private function createMultiPhotoPost($pageId, $content, $mediaFiles, $pageAccessToken): array {
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
    
    /**
     * Get post analytics from Facebook API
     */
    public function getPostAnalytics($postId, $metrics = null) {
        if (!$this->account) {
            throw new Exception("No account loaded");
        }
        
        // Default metrics to fetch
        if ($metrics === null) {
            $metrics = [
                'post_impressions',
                'post_impressions_unique',
                'post_engaged_users',
                'post_clicks',
                'post_reactions_like_total',
                'post_reactions_love_total',
                'post_reactions_wow_total',
                'post_reactions_haha_total',
                'post_reactions_sorry_total',
                'post_reactions_anger_total',
                'post_video_views',
                'post_video_complete_views_30s'
            ];
        }
        
        try {
            // Get insights data
            $insightsResponse = $this->makeApiRequest(
                $this->apiUrl . '/' . $postId . '/insights?' . http_build_query([
                    'metric' => implode(',', $metrics),
                    'access_token' => $this->account['access_token']
                ])
            );
            
            // Get basic post data for additional metrics
            $postResponse = $this->makeApiRequest(
                $this->apiUrl . '/' . $postId . '?' . http_build_query([
                    'fields' => 'comments.summary(true),shares',
                    'access_token' => $this->account['access_token']
                ])
            );
            
            // Process metrics
            $analytics = [
                'impressions' => 0,
                'reach' => 0,
                'engagement_rate' => 0,
                'clicks' => 0,
                'shares' => 0,
                'comments' => 0,
                'likes' => 0,
                'reactions' => [],
                'video_views' => 0,
                'video_completion_rate' => 0
            ];
            
            // Process insights data
            if (isset($insightsResponse['data'])) {
                foreach ($insightsResponse['data'] as $metric) {
                    $name = $metric['name'];
                    $value = $metric['values'][0]['value'] ?? 0;
                    
                    switch ($name) {
                        case 'post_impressions':
                            $analytics['impressions'] = $value;
                            break;
                        case 'post_impressions_unique':
                            $analytics['reach'] = $value;
                            break;
                        case 'post_engaged_users':
                            if ($analytics['reach'] > 0) {
                                $analytics['engagement_rate'] = round(($value / $analytics['reach']) * 100, 2);
                            }
                            break;
                        case 'post_clicks':
                            $analytics['clicks'] = $value;
                            break;
                        case 'post_video_views':
                            $analytics['video_views'] = $value;
                            break;
                        case 'post_video_complete_views_30s':
                            if ($analytics['video_views'] > 0) {
                                $analytics['video_completion_rate'] = round(($value / $analytics['video_views']) * 100, 2);
                            }
                            break;
                        default:
                            if (strpos($name, 'post_reactions_') === 0) {
                                $reactionType = str_replace(['post_reactions_', '_total'], '', $name);
                                $analytics['reactions'][$reactionType] = $value;
                                $analytics['likes'] += $value;
                            }
                    }
                }
            }
            
            // Add post data
            if (isset($postResponse['comments']['summary']['total_count'])) {
                $analytics['comments'] = $postResponse['comments']['summary']['total_count'];
            }
            
            if (isset($postResponse['shares']['count'])) {
                $analytics['shares'] = $postResponse['shares']['count'];
            }
            
            return $analytics;
            
        } catch (Exception $e) {
            throw new Exception("Failed to fetch Facebook post analytics: " . $e->getMessage());
        }
    }
    
    /**
     * Get page analytics (follower data, etc.)
     */
    public function getPageAnalytics($pageId = null, $metrics = null) {
        if (!$this->account) {
            throw new Exception("No account loaded");
        }
        
        if ($pageId === null) {
            $pageId = $this->getDefaultPageId();
        }
        
        // Default metrics to fetch
        if ($metrics === null) {
            $metrics = [
                'page_fans',
                'page_fan_adds',
                'page_fan_removes',
                'page_impressions',
                'page_impressions_unique',
                'page_engaged_users'
            ];
        }
        
        try {
            $pageAccessToken = $this->getPageAccessToken($pageId);
            
            // Get page insights
            $response = $this->makeApiRequest(
                $this->apiUrl . '/' . $pageId . '/insights?' . http_build_query([
                    'metric' => implode(',', $metrics),
                    'period' => 'day',
                    'since' => date('Y-m-d', strtotime('-7 days')),
                    'until' => date('Y-m-d'),
                    'access_token' => $pageAccessToken
                ])
            );
            
            $analytics = [
                'follower_count' => 0,
                'following_count' => 0,
                'daily_growth' => 0,
                'new_followers' => 0,
                'unfollows' => 0,
                'impressions' => 0,
                'reach' => 0,
                'engaged_users' => 0
            ];
            
            if (isset($response['data'])) {
                foreach ($response['data'] as $metric) {
                    $name = $metric['name'];
                    $values = $metric['values'];
                    $latestValue = end($values)['value'] ?? 0;
                    
                    switch ($name) {
                        case 'page_fans':
                            $analytics['follower_count'] = $latestValue;
                            break;
                        case 'page_fan_adds':
                            $analytics['new_followers'] = array_sum(array_column($values, 'value'));
                            break;
                        case 'page_fan_removes':
                            $analytics['unfollows'] = array_sum(array_column($values, 'value'));
                            break;
                        case 'page_impressions':
                            $analytics['impressions'] = array_sum(array_column($values, 'value'));
                            break;
                        case 'page_impressions_unique':
                            $analytics['reach'] = array_sum(array_column($values, 'value'));
                            break;
                        case 'page_engaged_users':
                            $analytics['engaged_users'] = array_sum(array_column($values, 'value'));
                            break;
                    }
                }
            }
            
            $analytics['daily_growth'] = $analytics['new_followers'] - $analytics['unfollows'];
            
            return $analytics;
            
        } catch (Exception $e) {
            throw new Exception("Failed to fetch Facebook page analytics: " . $e->getMessage());
        }
    }
    
    /**
     * Get available pages for this account
     */
    public function getPages() {
        if (!$this->account) {
            throw new Exception("No account loaded");
        }
        
        try {
            $response = $this->makeApiRequest(
                $this->apiUrl . '/me/accounts?' . http_build_query([
                    'access_token' => $this->account['access_token'],
                    'fields' => 'id,name,category,fan_count,access_token',
                ])
            );
            
            return $response['data'] ?? [];
            
        } catch (Exception $e) {
            throw new Exception("Failed to fetch Facebook pages: " . $e->getMessage());
        }
    }
    
    /**
     * Get historical analytics for a date range
     */
    public function getHistoricalAnalytics($pageId, $startDate, $endDate, $metrics = null) {
        if (!$this->account) {
            throw new Exception("No account loaded");
        }
        
        if ($metrics === null) {
            $metrics = [
                'page_fans',
                'page_impressions',
                'page_impressions_unique',
                'page_engaged_users',
                'page_post_engagements'
            ];
        }
        
        try {
            $pageAccessToken = $this->getPageAccessToken($pageId);
            
            $response = $this->makeApiRequest(
                $this->apiUrl . '/' . $pageId . '/insights?' . http_build_query([
                    'metric' => implode(',', $metrics),
                    'period' => 'day',
                    'since' => $startDate,
                    'until' => $endDate,
                    'access_token' => $pageAccessToken
                ])
            );
            
            $analytics = [];
            
            if (isset($response['data'])) {
                foreach ($response['data'] as $metric) {
                    $name = $metric['name'];
                    $values = $metric['values'];
                    
                    foreach ($values as $value) {
                        $date = substr($value['end_time'], 0, 10); // Extract date from timestamp
                        
                        if (!isset($analytics[$date])) {
                            $analytics[$date] = [];
                        }
                        
                        $analytics[$date][$name] = $value['value'];
                    }
                }
            }
            
            return $analytics;
            
        } catch (Exception $e) {
            throw new Exception("Failed to fetch Facebook historical analytics: " . $e->getMessage());
        }
    }
}