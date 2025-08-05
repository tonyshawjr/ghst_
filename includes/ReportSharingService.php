<?php
/**
 * Report Sharing Service
 * 
 * Handles secure generation and management of shareable report links
 * with expiration, password protection, and access tracking
 */
class ReportSharingService {
    private $db;
    private $rateLimiter;
    
    // Default expiration options (in seconds)
    const EXPIRY_24H = 86400;      // 24 hours
    const EXPIRY_7D = 604800;      // 7 days
    const EXPIRY_30D = 2592000;    // 30 days
    const EXPIRY_90D = 7776000;    // 90 days
    
    // Share permissions
    const PERM_VIEW = 'view';
    const PERM_DOWNLOAD = 'download';
    const PERM_ANALYTICS = 'analytics';
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->rateLimiter = new RateLimiter();
        $this->createShareAnalyticsTable();
    }
    
    /**
     * Create share analytics table if it doesn't exist
     */
    private function createShareAnalyticsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS share_analytics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            share_id INT NOT NULL,
            access_type ENUM('view', 'download', 'expired_attempt', 'password_fail') NOT NULL,
            ip_address VARCHAR(45),
            user_agent TEXT,
            country VARCHAR(2),
            city VARCHAR(100),
            referrer VARCHAR(500),
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_share_id (share_id),
            INDEX idx_timestamp (timestamp),
            INDEX idx_access_type (access_type),
            FOREIGN KEY (share_id) REFERENCES shareable_reports(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $this->db->query($sql);
    }
    
    /**
     * Generate a secure shareable link for a report
     */
    public function createShareLink($reportId, $options = []) {
        // Validate report exists and user has access
        if (!$this->validateReportAccess($reportId)) {
            throw new Exception('Report not found or access denied');
        }
        
        // Check rate limits for share creation
        $clientId = $_SESSION['client_id'] ?? 0;
        $rateLimitCheck = $this->rateLimiter->checkLimit('share_creation', $clientId, 'create_share');
        if (!$rateLimitCheck['allowed']) {
            throw new Exception('Rate limit exceeded for share creation. Please try again later.');
        }
        
        // Set default options
        $options = array_merge([
            'expires_in' => self::EXPIRY_7D,
            'password' => null,
            'permissions' => [self::PERM_VIEW, self::PERM_DOWNLOAD],
            'max_downloads' => null,
            'ip_restrictions' => null,
            'require_email' => false
        ], $options);
        
        try {
            $this->db->beginTransaction();
            
            // Generate cryptographically secure token
            $shareToken = $this->generateSecureToken();
            
            // Calculate expiration timestamp
            $expiresAt = null;
            if ($options['expires_in'] > 0) {
                $expiresAt = date('Y-m-d H:i:s', time() + $options['expires_in']);
            }
            
            // Hash password if provided
            $passwordHash = null;
            if (!empty($options['password'])) {
                $passwordHash = password_hash($options['password'], PASSWORD_DEFAULT);
            }
            
            // Prepare share settings
            $shareSettings = [
                'permissions' => $options['permissions'],
                'ip_restrictions' => $options['ip_restrictions'],
                'require_email' => $options['require_email'],
                'created_by_ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'created_by_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ];
            
            // Insert shareable report record
            $stmt = $this->db->prepare("
                INSERT INTO shareable_reports 
                (report_id, share_token, password_hash, allowed_downloads, expires_at, 
                 is_active, created_by, access_log, created_at) 
                VALUES (?, ?, ?, ?, ?, 1, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $reportId,
                $shareToken,
                $passwordHash,
                $options['max_downloads'],
                $expiresAt,
                $_SESSION['user_id'] ?? null,
                json_encode($shareSettings)
            ]);
            
            $shareId = $this->db->lastInsertId();
            
            // Record the rate limit action
            $this->rateLimiter->recordAction('share_creation', $clientId, 'create_share');
            
            $this->db->commit();
            
            // Log share creation
            $this->logShareActivity($shareId, 'created', [
                'report_id' => $reportId,
                'expires_at' => $expiresAt,
                'has_password' => !empty($options['password']),
                'permissions' => $options['permissions']
            ]);
            
            return [
                'share_id' => $shareId,
                'share_token' => $shareToken,
                'share_url' => APP_URL . '/shared/report.php?token=' . $shareToken,
                'expires_at' => $expiresAt,
                'qr_code_url' => $this->generateQRCodeUrl($shareToken)
            ];
            
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Share creation failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Retrieve share information by token
     */
    public function getShareByToken($token) {
        $stmt = $this->db->prepare("
            SELECT sr.*, gr.report_name, gr.file_path, gr.client_id, gr.report_type,
                   c.name as client_name, c.branding_logo, c.branding_colors
            FROM shareable_reports sr
            JOIN generated_reports gr ON sr.report_id = gr.id
            LEFT JOIN clients c ON gr.client_id = c.id
            WHERE sr.share_token = ? AND sr.is_active = 1
        ");
        
        $stmt->execute([$token]);
        $share = $stmt->fetch();
        
        if (!$share) {
            return null;
        }
        
        // Check if expired
        if ($share['expires_at'] && strtotime($share['expires_at']) < time()) {
            $this->recordShareAnalytics($share['id'], 'expired_attempt');
            return null;
        }
        
        // Parse access log JSON
        $share['access_settings'] = json_decode($share['access_log'], true) ?: [];
        
        return $share;
    }
    
    /**
     * Validate password for protected share
     */
    public function validateSharePassword($shareId, $password) {
        $stmt = $this->db->prepare("SELECT password_hash FROM shareable_reports WHERE id = ?");
        $stmt->execute([$shareId]);
        $share = $stmt->fetch();
        
        if (!$share || !$share['password_hash']) {
            return true; // No password required
        }
        
        $isValid = password_verify($password, $share['password_hash']);
        
        if (!$isValid) {
            $this->recordShareAnalytics($shareId, 'password_fail');
        }
        
        return $isValid;
    }
    
    /**
     * Record share access for analytics
     */
    public function recordShareAccess($shareId, $accessType = 'view') {
        // Update last accessed time
        $stmt = $this->db->prepare("
            UPDATE shareable_reports 
            SET last_accessed = NOW(), 
                download_count = CASE WHEN ? = 'download' THEN download_count + 1 ELSE download_count END
            WHERE id = ?
        ");
        $stmt->execute([$accessType, $shareId]);
        
        // Record detailed analytics
        $this->recordShareAnalytics($shareId, $accessType);
        
        // Check if download limit reached
        if ($accessType === 'download') {
            $stmt = $this->db->prepare("
                SELECT download_count, allowed_downloads 
                FROM shareable_reports 
                WHERE id = ?
            ");
            $stmt->execute([$shareId]);
            $share = $stmt->fetch();
            
            if ($share && $share['allowed_downloads'] && 
                $share['download_count'] >= $share['allowed_downloads']) {
                $this->revokeShare($shareId);
            }
        }
    }
    
    /**
     * Record detailed analytics for share access
     */
    private function recordShareAnalytics($shareId, $accessType) {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $referrer = $_SERVER['HTTP_REFERER'] ?? null;
        
        // Get geographic data (simplified - could integrate with GeoIP service)
        $geoData = $this->getGeoDataFromIP($ipAddress);
        
        $stmt = $this->db->prepare("
            INSERT INTO share_analytics 
            (share_id, access_type, ip_address, user_agent, country, city, referrer)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $shareId,
            $accessType,
            $ipAddress,
            $userAgent,
            $geoData['country'] ?? null,
            $geoData['city'] ?? null,
            $referrer
        ]);
    }
    
    /**
     * Get analytics data for a share
     */
    public function getShareAnalytics($shareId) {
        // Basic share stats
        $stmt = $this->db->prepare("
            SELECT sr.*, gr.report_name, gr.client_id
            FROM shareable_reports sr
            JOIN generated_reports gr ON sr.report_id = gr.id
            WHERE sr.id = ?
        ");
        $stmt->execute([$shareId]);
        $share = $stmt->fetch();
        
        if (!$share) {
            return null;
        }
        
        // Access statistics
        $stmt = $this->db->prepare("
            SELECT 
                access_type,
                COUNT(*) as count,
                MIN(timestamp) as first_access,
                MAX(timestamp) as last_access
            FROM share_analytics 
            WHERE share_id = ?
            GROUP BY access_type
        ");
        $stmt->execute([$shareId]);
        $accessStats = $stmt->fetchAll();
        
        // Geographic distribution
        $stmt = $this->db->prepare("
            SELECT country, city, COUNT(*) as count
            FROM share_analytics 
            WHERE share_id = ? AND country IS NOT NULL
            GROUP BY country, city
            ORDER BY count DESC
            LIMIT 10
        ");
        $stmt->execute([$shareId]);
        $geoStats = $stmt->fetchAll();
        
        // Access timeline (daily)
        $stmt = $this->db->prepare("
            SELECT 
                DATE(timestamp) as date,
                access_type,
                COUNT(*) as count
            FROM share_analytics 
            WHERE share_id = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(timestamp), access_type
            ORDER BY date ASC
        ");
        $stmt->execute([$shareId]);
        $timeline = $stmt->fetchAll();
        
        // Top referrers
        $stmt = $this->db->prepare("
            SELECT referrer, COUNT(*) as count
            FROM share_analytics 
            WHERE share_id = ? AND referrer IS NOT NULL
            GROUP BY referrer
            ORDER BY count DESC
            LIMIT 10
        ");
        $stmt->execute([$shareId]);
        $referrers = $stmt->fetchAll();
        
        return [
            'share' => $share,
            'access_stats' => $accessStats,
            'geographic_stats' => $geoStats,
            'timeline' => $timeline,
            'top_referrers' => $referrers,
            'total_views' => array_sum(array_column(array_filter($accessStats, function($s) { 
                return $s['access_type'] === 'view'; 
            }), 'count')),
            'total_downloads' => array_sum(array_column(array_filter($accessStats, function($s) { 
                return $s['access_type'] === 'download'; 
            }), 'count'))
        ];
    }
    
    /**
     * Revoke a shareable link
     */
    public function revokeShare($shareId) {
        $stmt = $this->db->prepare("
            UPDATE shareable_reports 
            SET is_active = 0, updated_at = NOW() 
            WHERE id = ?
        ");
        $result = $stmt->execute([$shareId]);
        
        if ($result) {
            $this->logShareActivity($shareId, 'revoked');
        }
        
        return $result;
    }
    
    /**
     * Update share settings
     */
    public function updateShareSettings($shareId, $settings) {
        $allowedSettings = ['expires_at', 'password_hash', 'allowed_downloads', 'is_active'];
        $updateFields = [];
        $updateValues = [];
        
        foreach ($settings as $key => $value) {
            if (in_array($key, $allowedSettings)) {
                if ($key === 'password_hash' && !empty($value)) {
                    $value = password_hash($value, PASSWORD_DEFAULT);
                }
                $updateFields[] = "$key = ?";
                $updateValues[] = $value;
            }
        }
        
        if (empty($updateFields)) {
            return false;
        }
        
        $updateValues[] = $shareId;
        $sql = "UPDATE shareable_reports SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = ?";
        
        $stmt = $this->db->prepare($sql);
        $result = $stmt->execute($updateValues);
        
        if ($result) {
            $this->logShareActivity($shareId, 'updated', $settings);
        }
        
        return $result;
    }
    
    /**
     * Get all shares for a specific report
     */
    public function getReportShares($reportId) {
        $stmt = $this->db->prepare("
            SELECT sr.*, u.name as created_by_name,
                   (SELECT COUNT(*) FROM share_analytics sa WHERE sa.share_id = sr.id AND sa.access_type = 'view') as view_count,
                   (SELECT COUNT(*) FROM share_analytics sa WHERE sa.share_id = sr.id AND sa.access_type = 'download') as download_count
            FROM shareable_reports sr
            LEFT JOIN users u ON sr.created_by = u.id
            WHERE sr.report_id = ?
            ORDER BY sr.created_at DESC
        ");
        
        $stmt->execute([$reportId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Clean up expired shares
     */
    public function cleanupExpiredShares() {
        $stmt = $this->db->prepare("
            UPDATE shareable_reports 
            SET is_active = 0 
            WHERE expires_at < NOW() AND is_active = 1
        ");
        
        return $stmt->execute();
    }
    
    /**
     * Generate cryptographically secure token
     */
    private function generateSecureToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Validate user has access to report
     */
    private function validateReportAccess($reportId) {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        $stmt = $this->db->prepare("
            SELECT gr.id 
            FROM generated_reports gr
            JOIN clients c ON gr.client_id = c.id
            JOIN user_clients uc ON c.id = uc.client_id
            WHERE gr.id = ? AND uc.user_id = ? AND gr.status = 'completed'
        ");
        
        $stmt->execute([$reportId, $_SESSION['user_id']]);
        return $stmt->fetch() !== false;
    }
    
    /**
     * Generate QR code URL for share link (placeholder - would integrate with QR service)
     */
    private function generateQRCodeUrl($token) {
        $shareUrl = urlencode(APP_URL . '/shared/report.php?token=' . $token);
        return "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . $shareUrl;
    }
    
    /**
     * Get geographic data from IP (placeholder - would integrate with GeoIP service)
     */
    private function getGeoDataFromIP($ipAddress) {
        // This is a placeholder. In production, you'd integrate with a service like:
        // - MaxMind GeoIP2
        // - IPStack
        // - ipapi.com
        return [
            'country' => null,
            'city' => null
        ];
    }
    
    /**
     * Log share-related activities
     */
    private function logShareActivity($shareId, $action, $details = []) {
        $stmt = $this->db->prepare("
            INSERT INTO user_actions 
            (user_id, client_id, action_type, description, details, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        $description = "Share $action for share ID: $shareId";
        
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $_SESSION['client_id'] ?? null,
            'share_' . $action,
            $description,
            json_encode($details),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }
    
    /**
     * Check if IP is allowed to access share
     */
    public function isIPAllowed($shareId, $ipAddress) {
        $stmt = $this->db->prepare("SELECT access_log FROM shareable_reports WHERE id = ?");
        $stmt->execute([$shareId]);
        $share = $stmt->fetch();
        
        if (!$share) {
            return false;
        }
        
        $settings = json_decode($share['access_log'], true) ?: [];
        $ipRestrictions = $settings['ip_restrictions'] ?? null;
        
        if (!$ipRestrictions) {
            return true; // No restrictions
        }
        
        if (is_array($ipRestrictions)) {
            return in_array($ipAddress, $ipRestrictions);
        }
        
        // Single IP or CIDR range check
        if (strpos($ipRestrictions, '/') !== false) {
            return $this->ipInRange($ipAddress, $ipRestrictions);
        }
        
        return $ipAddress === $ipRestrictions;
    }
    
    /**
     * Check if IP is in CIDR range
     */
    private function ipInRange($ip, $range) {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }
        
        list($range, $netmask) = explode('/', $range, 2);
        $range_decimal = ip2long($range);
        $ip_decimal = ip2long($ip);
        $wildcard_decimal = pow(2, (32 - $netmask)) - 1;
        $netmask_decimal = ~ $wildcard_decimal;
        
        return (($ip_decimal & $netmask_decimal) === ($range_decimal & $netmask_decimal));
    }
}