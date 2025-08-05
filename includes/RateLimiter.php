<?php
/**
 * Rate Limiter for Platform APIs
 * 
 * Tracks and enforces rate limits for each social media platform
 * to prevent API throttling and ensure compliance with platform limits
 */
class RateLimiter {
    private $db;
    
    // Platform rate limits (requests per window)
    private $limits = [
        'facebook' => [
            'posts_per_hour' => 200,
            'posts_per_day' => 1000,
            'media_uploads_per_hour' => 100,
        ],
        'instagram' => [
            'posts_per_hour' => 25,
            'posts_per_day' => 100,
            'media_uploads_per_hour' => 50,
        ],
        'twitter' => [
            'posts_per_15min' => 300,
            'posts_per_day' => 2400,
            'media_uploads_per_15min' => 100,
        ],
        'linkedin' => [
            'posts_per_day' => 100,
            'posts_per_month' => 1000,
            'media_uploads_per_day' => 100,
        ],
    ];
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->createRateLimitTable();
    }
    
    /**
     * Create rate limit tracking table if it doesn't exist
     */
    private function createRateLimitTable() {
        $sql = "CREATE TABLE IF NOT EXISTS rate_limits (
            id INT AUTO_INCREMENT PRIMARY KEY,
            platform VARCHAR(50) NOT NULL,
            client_id INT NOT NULL,
            action_type VARCHAR(50) NOT NULL,
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_platform_client_action (platform, client_id, action_type),
            INDEX idx_timestamp (timestamp)
        )";
        
        $this->db->exec($sql);
    }
    
    /**
     * Check if an action is allowed based on rate limits
     */
    public function checkLimit($platform, $clientId, $actionType = 'post') {
        if (!isset($this->limits[$platform])) {
            return ['allowed' => true, 'reset_time' => null];
        }
        
        $platformLimits = $this->limits[$platform];
        $errors = [];
        $nearestResetTime = null;
        
        // Check each limit type for the platform
        foreach ($platformLimits as $limitType => $limit) {
            $window = $this->getWindowFromLimitType($limitType);
            $count = $this->getActionCount($platform, $clientId, $actionType, $window);
            
            if ($count >= $limit) {
                $resetTime = $this->getResetTime($window);
                $errors[] = "Rate limit exceeded: $limitType (limit: $limit, current: $count)";
                
                if (!$nearestResetTime || $resetTime < $nearestResetTime) {
                    $nearestResetTime = $resetTime;
                }
            }
        }
        
        if (!empty($errors)) {
            return [
                'allowed' => false,
                'errors' => $errors,
                'reset_time' => $nearestResetTime,
                'retry_after' => $nearestResetTime - time(),
            ];
        }
        
        return ['allowed' => true, 'reset_time' => null];
    }
    
    /**
     * Record an action for rate limiting
     */
    public function recordAction($platform, $clientId, $actionType = 'post') {
        $stmt = $this->db->prepare(
            "INSERT INTO rate_limits (platform, client_id, action_type) VALUES (?, ?, ?)"
        );
        $stmt->execute([$platform, $clientId, $actionType]);
    }
    
    /**
     * Get action count within a time window
     */
    private function getActionCount($platform, $clientId, $actionType, $window) {
        $since = date('Y-m-d H:i:s', time() - $window);
        
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM rate_limits 
             WHERE platform = ? AND client_id = ? AND action_type = ? AND timestamp >= ?"
        );
        $stmt->execute([$platform, $clientId, $actionType, $since]);
        
        return (int) $stmt->fetchColumn();
    }
    
    /**
     * Get window duration in seconds from limit type
     */
    private function getWindowFromLimitType($limitType) {
        if (strpos($limitType, '_per_15min') !== false) {
            return 15 * 60; // 15 minutes
        } elseif (strpos($limitType, '_per_hour') !== false) {
            return 60 * 60; // 1 hour
        } elseif (strpos($limitType, '_per_day') !== false) {
            return 24 * 60 * 60; // 24 hours
        } elseif (strpos($limitType, '_per_month') !== false) {
            return 30 * 24 * 60 * 60; // 30 days
        }
        
        return 60 * 60; // Default to 1 hour
    }
    
    /**
     * Get reset time for a window
     */
    private function getResetTime($window) {
        return time() + $window;
    }
    
    /**
     * Get current usage statistics for a platform
     */
    public function getUsageStats($platform, $clientId) {
        if (!isset($this->limits[$platform])) {
            return [];
        }
        
        $stats = [];
        $platformLimits = $this->limits[$platform];
        
        foreach ($platformLimits as $limitType => $limit) {
            $actionType = strpos($limitType, 'media_upload') !== false ? 'media_upload' : 'post';
            $window = $this->getWindowFromLimitType($limitType);
            $count = $this->getActionCount($platform, $clientId, $actionType, $window);
            
            $stats[$limitType] = [
                'current' => $count,
                'limit' => $limit,
                'percentage' => round(($count / $limit) * 100, 2),
                'remaining' => $limit - $count,
            ];
        }
        
        return $stats;
    }
    
    /**
     * Clean up old rate limit records
     */
    public function cleanup($daysToKeep = 7) {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-$daysToKeep days"));
        
        $stmt = $this->db->prepare("DELETE FROM rate_limits WHERE timestamp < ?");
        $stmt->execute([$cutoffDate]);
        
        return $stmt->rowCount();
    }
    
    /**
     * Check if we're approaching a rate limit (80% threshold)
     */
    public function isApproachingLimit($platform, $clientId, $actionType = 'post') {
        if (!isset($this->limits[$platform])) {
            return false;
        }
        
        $platformLimits = $this->limits[$platform];
        
        foreach ($platformLimits as $limitType => $limit) {
            $window = $this->getWindowFromLimitType($limitType);
            $count = $this->getActionCount($platform, $clientId, $actionType, $window);
            
            if ($count >= ($limit * 0.8)) {
                return true;
            }
        }
        
        return false;
    }
}