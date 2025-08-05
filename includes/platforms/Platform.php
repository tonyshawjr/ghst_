<?php
require_once __DIR__ . '/../RateLimiter.php';
require_once __DIR__ . '/../exceptions/PlatformExceptions.php';

/**
 * Base Platform Class
 */
abstract class Platform {
    protected $db;
    protected $account;
    protected $rateLimiter;
    
    public function __construct($accountId = null) {
        $this->db = Database::getInstance();
        $this->rateLimiter = new RateLimiter();
        if ($accountId) {
            $this->loadAccount($accountId);
        }
    }
    
    protected function loadAccount($accountId) {
        $stmt = $this->db->prepare("SELECT * FROM accounts WHERE id = ? AND is_active = 1");
        $stmt->execute([$accountId]);
        $this->account = $stmt->fetch();
        
        if (!$this->account) {
            throw new Exception("Account not found or inactive");
        }
    }
    
    // Abstract methods that each platform must implement
    abstract public function getName();
    abstract public function getAuthUrl($redirectUri, $state = null);
    abstract public function handleCallback($code, $state = null);
    abstract public function refreshToken();
    abstract public function post($content, $mediaFiles = [], $options = []);
    abstract public function getAccountInfo();
    abstract public function validatePost($content, $mediaFiles = []);
    abstract public function getCharacterLimit();
    abstract public function getMediaLimits();
    
    // Common utility methods
    protected function makeApiRequest($url, $method = 'GET', $data = null, $headers = [], $retryCount = 0) {
        $maxRetries = 3;
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_USERAGENT => 'ghst_ Social Media Manager/1.0',
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // Log API request
        $this->logApiRequest($url, $method, $httpCode, $error ?: null);
        
        if ($error) {
            // Network errors - retry with exponential backoff
            if ($retryCount < $maxRetries) {
                $waitTime = pow(2, $retryCount) * 1000000; // Exponential backoff in microseconds
                usleep($waitTime);
                return $this->makeApiRequest($url, $method, $data, $headers, $retryCount + 1);
            }
            throw new PlatformNetworkException("Network error after $maxRetries retries: $error", $httpCode);
        }
        
        $decodedResponse = json_decode($response, true);
        
        // Handle different HTTP error codes
        if ($httpCode >= 400) {
            $errorMsg = $this->extractErrorMessage($decodedResponse, $httpCode);
            
            // Retry on rate limit or temporary errors
            if (in_array($httpCode, [429, 502, 503, 504]) && $retryCount < $maxRetries) {
                $waitTime = $this->getRetryDelay($httpCode, $decodedResponse, $retryCount);
                usleep($waitTime * 1000000);
                return $this->makeApiRequest($url, $method, $data, $headers, $retryCount + 1);
            }
            
            // Throw specific exceptions based on error type
            switch ($httpCode) {
                case 400:
                    throw new PlatformBadRequestException($errorMsg, $httpCode);
                case 401:
                    throw new PlatformAuthException($errorMsg, $httpCode);
                case 403:
                    throw new PlatformForbiddenException($errorMsg, $httpCode);
                case 404:
                    throw new PlatformNotFoundException($errorMsg, $httpCode);
                case 429:
                    throw new PlatformRateLimitException($errorMsg, $httpCode);
                case 500:
                case 502:
                case 503:
                case 504:
                    throw new PlatformServerException($errorMsg, $httpCode);
                default:
                    throw new PlatformApiException($errorMsg, $httpCode);
            }
        }
        
        return $decodedResponse;
    }
    
    /**
     * Extract error message from API response
     */
    private function extractErrorMessage($response, $httpCode) {
        if (!is_array($response)) {
            return "HTTP Error $httpCode";
        }
        
        // Try common error message locations
        return $response['error']['message'] 
            ?? $response['error_description'] 
            ?? $response['message'] 
            ?? $response['error'] 
            ?? "HTTP Error $httpCode";
    }
    
    /**
     * Calculate retry delay based on error type
     */
    private function getRetryDelay($httpCode, $response, $retryCount) {
        // Check for Retry-After header
        if (isset($response['retry_after'])) {
            return (int) $response['retry_after'];
        }
        
        // Rate limit: wait longer
        if ($httpCode === 429) {
            return min(60, pow(2, $retryCount + 2)); // Max 60 seconds
        }
        
        // Server errors: exponential backoff
        return pow(2, $retryCount);
    }
    
    /**
     * Log API requests for debugging
     */
    private function logApiRequest($url, $method, $httpCode, $error = null) {
        if (!$this->account) {
            return;
        }
        
        $logData = [
            'platform' => $this->getName(),
            'client_id' => $this->account['client_id'],
            'url' => $url,
            'method' => $method,
            'http_code' => $httpCode,
            'error' => $error,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
        
        // Log to database
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO logs (client_id, action, details, level, created_at) 
                 VALUES (?, ?, ?, ?, NOW())"
            );
            $stmt->execute([
                $this->account['client_id'],
                'api_request',
                json_encode($logData),
                $httpCode >= 400 ? 'error' : 'info'
            ]);
        } catch (Exception $e) {
            // Silently fail - don't break API requests due to logging errors
        }
    }
    
    protected function updateAccountTokens($accessToken, $refreshToken = null, $expiresAt = null) {
        $sql = "UPDATE accounts SET access_token = ?, updated_at = NOW()";
        $params = [$accessToken];
        
        if ($refreshToken !== null) {
            $sql .= ", refresh_token = ?";
            $params[] = $refreshToken;
        }
        
        if ($expiresAt !== null) {
            $sql .= ", token_expires_at = ?";
            $params[] = $expiresAt;
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $this->account['id'];
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        // Update local account data
        $this->account['access_token'] = $accessToken;
        if ($refreshToken !== null) {
            $this->account['refresh_token'] = $refreshToken;
        }
        if ($expiresAt !== null) {
            $this->account['token_expires_at'] = $expiresAt;
        }
    }
    
    public function isTokenExpired() {
        if (!$this->account['token_expires_at']) {
            return false; // No expiry set, assume valid
        }
        
        return strtotime($this->account['token_expires_at']) <= time();
    }
    
    public function getTokenExpiryWarning() {
        if (!$this->account['token_expires_at']) {
            return null;
        }
        
        $expiryTime = strtotime($this->account['token_expires_at']);
        $hoursUntilExpiry = ($expiryTime - time()) / 3600;
        
        if ($hoursUntilExpiry <= 24) {
            return "Token expires in " . round($hoursUntilExpiry) . " hours";
        } elseif ($hoursUntilExpiry <= 168) { // 7 days
            return "Token expires in " . round($hoursUntilExpiry / 24) . " days";
        }
        
        return null;
    }
    
    /**
     * Check rate limits before making API calls
     */
    protected function checkRateLimit($actionType = 'post') {
        if (!$this->account) {
            throw new Exception("No account loaded");
        }
        
        $result = $this->rateLimiter->checkLimit(
            $this->getName(),
            $this->account['client_id'],
            $actionType
        );
        
        if (!$result['allowed']) {
            $retryAfter = $result['retry_after'];
            $resetTime = date('Y-m-d H:i:s', $result['reset_time']);
            
            throw new Exception(
                "Rate limit exceeded for {$this->getName()}. " .
                "Retry after {$retryAfter} seconds (resets at {$resetTime}). " .
                implode(' ', $result['errors'])
            );
        }
        
        return true;
    }
    
    /**
     * Record an API action for rate limiting
     */
    protected function recordApiAction($actionType = 'post') {
        if (!$this->account) {
            return;
        }
        
        $this->rateLimiter->recordAction(
            $this->getName(),
            $this->account['client_id'],
            $actionType
        );
    }
    
    public static function create($platform) {
        $className = ucfirst($platform) . 'Platform';
        $filePath = __DIR__ . "/{$className}.php";
        
        if (!file_exists($filePath)) {
            throw new Exception("Platform '$platform' not supported");
        }
        
        require_once $filePath;
        
        if (!class_exists($className)) {
            throw new Exception("Platform class '$className' not found");
        }
        
        return new $className();
    }
}