<?php
/**
 * Base Platform Class
 */
abstract class Platform {
    protected $db;
    protected $account;
    
    public function __construct($accountId = null) {
        $this->db = Database::getInstance();
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
    protected function makeApiRequest($url, $method = 'GET', $data = null, $headers = []) {
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
        
        if ($error) {
            throw new Exception("cURL Error: $error");
        }
        
        $decodedResponse = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorMsg = $decodedResponse['error']['message'] ?? $decodedResponse['error'] ?? "HTTP Error $httpCode";
            throw new Exception("API Error: $errorMsg");
        }
        
        return $decodedResponse;
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