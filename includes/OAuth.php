<?php
/**
 * OAuth Service Class
 * Handles OAuth authentication flows for all social media platforms
 */

class OAuth {
    private $db;
    private $auth;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->auth = new Auth();
    }
    
    /**
     * Get authorization URL for a platform
     */
    public function getAuthUrl($platform, $clientId) {
        $state = $this->generateState($platform, $clientId);
        $redirectUri = OAUTH_REDIRECT_BASE . $platform . '.php';
        
        switch ($platform) {
            case 'facebook':
                return "https://www.facebook.com/v" . str_replace('v', '', FB_API_VERSION) . "/dialog/oauth?" . http_build_query([
                    'client_id' => FB_APP_ID,
                    'redirect_uri' => $redirectUri,
                    'scope' => 'pages_manage_posts,pages_read_engagement,instagram_basic,instagram_content_publish',
                    'state' => $state,
                    'response_type' => 'code'
                ]);
                
            case 'twitter':
                // Twitter uses OAuth 2.0 PKCE flow
                $codeVerifier = $this->generateCodeVerifier();
                $codeChallenge = $this->generateCodeChallenge($codeVerifier);
                
                // Store code verifier in session for later use
                $_SESSION['twitter_code_verifier'] = $codeVerifier;
                
                return "https://twitter.com/i/oauth2/authorize?" . http_build_query([
                    'response_type' => 'code',
                    'client_id' => TWITTER_API_KEY,
                    'redirect_uri' => $redirectUri,
                    'scope' => 'tweet.read tweet.write users.read offline.access',
                    'state' => $state,
                    'code_challenge' => $codeChallenge,
                    'code_challenge_method' => 'S256'
                ]);
                
            case 'linkedin':
                return "https://www.linkedin.com/oauth/v2/authorization?" . http_build_query([
                    'response_type' => 'code',
                    'client_id' => LINKEDIN_CLIENT_ID,
                    'redirect_uri' => $redirectUri,
                    'scope' => 'w_member_social',
                    'state' => $state
                ]);
                
            default:
                throw new Exception('Unsupported platform: ' . $platform);
        }
    }
    
    /**
     * Handle OAuth callback and exchange code for tokens
     */
    public function handleCallback($platform, $code, $state) {
        // Verify state parameter
        if (!$this->verifyState($state)) {
            throw new Exception('Invalid state parameter');
        }
        
        $stateData = $this->decodeState($state);
        $clientId = $stateData['client_id'];
        
        // Exchange code for access token
        $tokenData = $this->exchangeCodeForToken($platform, $code);
        
        // Get user/page information
        $accountInfo = $this->getAccountInfo($platform, $tokenData);
        
        // Store account in database
        $accountId = $this->storeAccount($platform, $clientId, $tokenData, $accountInfo);
        
        return $accountId;
    }
    
    /**
     * Exchange authorization code for access token
     */
    private function exchangeCodeForToken($platform, $code) {
        $redirectUri = OAUTH_REDIRECT_BASE . $platform . '.php';
        
        switch ($platform) {
            case 'facebook':
                $tokenUrl = "https://graph.facebook.com/v" . str_replace('v', '', FB_API_VERSION) . "/oauth/access_token";
                $params = [
                    'client_id' => FB_APP_ID,
                    'client_secret' => FB_APP_SECRET,
                    'redirect_uri' => $redirectUri,
                    'code' => $code
                ];
                break;
                
            case 'twitter':
                $tokenUrl = "https://api.twitter.com/2/oauth2/token";
                $params = [
                    'code' => $code,
                    'grant_type' => 'authorization_code',
                    'client_id' => TWITTER_API_KEY,
                    'redirect_uri' => $redirectUri,
                    'code_verifier' => $_SESSION['twitter_code_verifier'] ?? ''
                ];
                
                // Twitter requires Basic Auth
                $headers = [
                    'Authorization: Basic ' . base64_encode(TWITTER_API_KEY . ':' . TWITTER_API_SECRET),
                    'Content-Type: application/x-www-form-urlencoded'
                ];
                break;
                
            case 'linkedin':
                $tokenUrl = "https://www.linkedin.com/oauth/v2/accessToken";
                $params = [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $redirectUri,
                    'client_id' => LINKEDIN_CLIENT_ID,
                    'client_secret' => LINKEDIN_CLIENT_SECRET
                ];
                break;
                
            default:
                throw new Exception('Unsupported platform: ' . $platform);
        }
        
        $response = $this->makeHttpRequest($tokenUrl, 'POST', $params, $headers ?? []);
        $tokenData = json_decode($response, true);
        
        if (!$tokenData || isset($tokenData['error'])) {
            throw new Exception('Failed to exchange code for token: ' . ($tokenData['error_description'] ?? 'Unknown error'));
        }
        
        return $tokenData;
    }
    
    /**
     * Get account information from the platform
     */
    private function getAccountInfo($platform, $tokenData) {
        $accessToken = $tokenData['access_token'];
        
        switch ($platform) {
            case 'facebook':
                // Get user info
                $userUrl = "https://graph.facebook.com/me?fields=id,name,email&access_token=" . $accessToken;
                $userResponse = $this->makeHttpRequest($userUrl);
                $userData = json_decode($userResponse, true);
                
                // Get pages managed by user
                $pagesUrl = "https://graph.facebook.com/me/accounts?access_token=" . $accessToken;
                $pagesResponse = $this->makeHttpRequest($pagesUrl);
                $pagesData = json_decode($pagesResponse, true);
                
                return [
                    'user' => $userData,
                    'pages' => $pagesData['data'] ?? []
                ];
                
            case 'twitter':
                $userUrl = "https://api.twitter.com/2/users/me";
                $headers = ['Authorization: Bearer ' . $accessToken];
                $userResponse = $this->makeHttpRequest($userUrl, 'GET', [], $headers);
                $userData = json_decode($userResponse, true);
                
                return $userData['data'] ?? [];
                
            case 'linkedin':
                $userUrl = "https://api.linkedin.com/v2/people/~?projection=(id,firstName,lastName,emailAddress)";
                $headers = ['Authorization: Bearer ' . $accessToken];
                $userResponse = $this->makeHttpRequest($userUrl, 'GET', [], $headers);
                $userData = json_decode($userResponse, true);
                
                return $userData;
                
            default:
                throw new Exception('Unsupported platform: ' . $platform);
        }
    }
    
    /**
     * Store account in database
     */
    private function storeAccount($platform, $clientId, $tokenData, $accountInfo) {
        $accessToken = $tokenData['access_token'];
        $refreshToken = $tokenData['refresh_token'] ?? null;
        $expiresIn = $tokenData['expires_in'] ?? null;
        $expiresAt = $expiresIn ? date('Y-m-d H:i:s', time() + $expiresIn) : null;
        
        switch ($platform) {
            case 'facebook':
                // Store user account first
                $stmt = $this->db->prepare("
                    INSERT INTO accounts (client_id, platform, platform_user_id, username, display_name, 
                                        access_token, refresh_token, token_expires_at, account_data, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                    ON DUPLICATE KEY UPDATE 
                        access_token = VALUES(access_token),
                        refresh_token = VALUES(refresh_token),
                        token_expires_at = VALUES(token_expires_at),
                        account_data = VALUES(account_data),
                        updated_at = CURRENT_TIMESTAMP
                ");
                
                $userData = $accountInfo['user'];
                $stmt->execute([
                    $clientId,
                    $platform,
                    $userData['id'],
                    $userData['name'],
                    $userData['name'],
                    $accessToken,
                    $refreshToken,
                    $expiresAt,
                    json_encode($userData)
                ]);
                
                $accountId = $this->db->lastInsertId();
                
                // Store Facebook pages
                foreach ($accountInfo['pages'] as $page) {
                    $stmt->execute([
                        $clientId,
                        'facebook_page',
                        $page['id'],
                        $page['name'],
                        $page['name'],
                        $page['access_token'],
                        null, // Pages don't have refresh tokens
                        null, // Page tokens don't expire
                        json_encode($page)
                    ]);
                }
                
                return $accountId;
                
            case 'twitter':
            case 'linkedin':
                $stmt = $this->db->prepare("
                    INSERT INTO accounts (client_id, platform, platform_user_id, username, display_name, 
                                        access_token, refresh_token, token_expires_at, account_data, is_active) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                    ON DUPLICATE KEY UPDATE 
                        access_token = VALUES(access_token),
                        refresh_token = VALUES(refresh_token),
                        token_expires_at = VALUES(token_expires_at),
                        account_data = VALUES(account_data),
                        updated_at = CURRENT_TIMESTAMP
                ");
                
                $userId = $platform === 'twitter' ? $accountInfo['id'] : $accountInfo['id'];
                $username = $platform === 'twitter' ? 
                    ($accountInfo['username'] ?? $accountInfo['name']) : 
                    ($accountInfo['firstName']['localized']['en_US'] . ' ' . $accountInfo['lastName']['localized']['en_US']);
                
                $stmt->execute([
                    $clientId,
                    $platform,
                    $userId,
                    $username,
                    $username,
                    $accessToken,
                    $refreshToken,
                    $expiresAt,
                    json_encode($accountInfo)
                ]);
                
                return $this->db->lastInsertId();
                
            default:
                throw new Exception('Unsupported platform: ' . $platform);
        }
    }
    
    /**
     * Refresh access token
     */
    public function refreshToken($accountId) {
        $stmt = $this->db->prepare("SELECT * FROM accounts WHERE id = ?");
        $stmt->execute([$accountId]);
        $account = $stmt->fetch();
        
        if (!$account || !$account['refresh_token']) {
            return false;
        }
        
        $platform = $account['platform'];
        $refreshToken = $account['refresh_token'];
        
        try {
            switch ($platform) {
                case 'facebook':
                    // Facebook tokens are long-lived, but can be refreshed
                    $tokenUrl = "https://graph.facebook.com/oauth/access_token?" . http_build_query([
                        'grant_type' => 'fb_exchange_token',
                        'client_id' => FB_APP_ID,
                        'client_secret' => FB_APP_SECRET,
                        'fb_exchange_token' => $account['access_token']
                    ]);
                    break;
                    
                case 'twitter':
                    $tokenUrl = "https://api.twitter.com/2/oauth2/token";
                    $params = [
                        'refresh_token' => $refreshToken,
                        'grant_type' => 'refresh_token',
                        'client_id' => TWITTER_API_KEY
                    ];
                    $headers = [
                        'Authorization: Basic ' . base64_encode(TWITTER_API_KEY . ':' . TWITTER_API_SECRET),
                        'Content-Type: application/x-www-form-urlencoded'
                    ];
                    break;
                    
                case 'linkedin':
                    $tokenUrl = "https://www.linkedin.com/oauth/v2/accessToken";
                    $params = [
                        'grant_type' => 'refresh_token',
                        'refresh_token' => $refreshToken,
                        'client_id' => LINKEDIN_CLIENT_ID,
                        'client_secret' => LINKEDIN_CLIENT_SECRET
                    ];
                    break;
                    
                default:
                    return false;
            }
            
            $response = $platform === 'facebook' ? 
                $this->makeHttpRequest($tokenUrl) : 
                $this->makeHttpRequest($tokenUrl, 'POST', $params, $headers ?? []);
            
            $tokenData = json_decode($response, true);
            
            if (!$tokenData || isset($tokenData['error'])) {
                return false;
            }
            
            // Update token in database
            $newAccessToken = $tokenData['access_token'];
            $newRefreshToken = $tokenData['refresh_token'] ?? $refreshToken;
            $expiresIn = $tokenData['expires_in'] ?? null;
            $expiresAt = $expiresIn ? date('Y-m-d H:i:s', time() + $expiresIn) : null;
            
            $stmt = $this->db->prepare("
                UPDATE accounts 
                SET access_token = ?, refresh_token = ?, token_expires_at = ?, updated_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            
            return $stmt->execute([$newAccessToken, $newRefreshToken, $expiresAt, $accountId]);
            
        } catch (Exception $e) {
            error_log("Token refresh failed for account {$accountId}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if token needs refresh and refresh if necessary
     */
    public function ensureValidToken($accountId) {
        $stmt = $this->db->prepare("SELECT * FROM accounts WHERE id = ?");
        $stmt->execute([$accountId]);
        $account = $stmt->fetch();
        
        if (!$account) {
            return false;
        }
        
        // Check if token is expired or expires within 5 minutes
        if ($account['token_expires_at'] && strtotime($account['token_expires_at']) <= (time() + 300)) {
            return $this->refreshToken($accountId);
        }
        
        return true;
    }
    
    /**
     * Generate state parameter for OAuth
     */
    private function generateState($platform, $clientId) {
        $stateData = [
            'platform' => $platform,
            'client_id' => $clientId,
            'user_id' => $this->auth->getCurrentUser()['id'],
            'timestamp' => time(),
            'nonce' => bin2hex(random_bytes(16))
        ];
        
        return base64_encode(json_encode($stateData));
    }
    
    /**
     * Verify state parameter
     */
    private function verifyState($state) {
        try {
            $stateData = json_decode(base64_decode($state), true);
            
            if (!$stateData || !isset($stateData['timestamp'], $stateData['user_id'])) {
                return false;
            }
            
            // Check if state is not older than 10 minutes
            if (time() - $stateData['timestamp'] > 600) {
                return false;
            }
            
            // Verify user
            if ($stateData['user_id'] !== $this->auth->getCurrentUser()['id']) {
                return false;
            }
            
            return true;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Decode state parameter
     */
    private function decodeState($state) {
        return json_decode(base64_decode($state), true);
    }
    
    /**
     * Generate code verifier for PKCE (Twitter)
     */
    private function generateCodeVerifier() {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
    
    /**
     * Generate code challenge for PKCE (Twitter)
     */
    private function generateCodeChallenge($codeVerifier) {
        return rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');
    }
    
    /**
     * Make HTTP request
     */
    private function makeHttpRequest($url, $method = 'GET', $data = [], $headers = []) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'ghst_/1.0'
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            }
        }
        
        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            throw new Exception('cURL error: ' . $error);
        }
        
        if ($httpCode >= 400) {
            throw new Exception('HTTP error: ' . $httpCode . ' - ' . $response);
        }
        
        return $response;
    }
}