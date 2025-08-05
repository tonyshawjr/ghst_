<?php
/**
 * Branding Management Class
 * Handles client branding settings, logo uploads, and customization
 */

class Branding {
    private $db;
    private $uploadDir;
    private $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp'];
    private $maxFileSize = 5242880; // 5MB in bytes
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->uploadDir = ROOT_PATH . '/uploads/branding/';
        
        // Ensure upload directory exists
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
    
    /**
     * Get branding settings for a client
     */
    public function getBrandingSettings($clientId) {
        $stmt = $this->db->prepare("SELECT * FROM client_branding WHERE client_id = ?");
        $stmt->execute([$clientId]);
        $branding = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Return defaults if no branding exists
        if (!$branding) {
            return $this->getDefaults($clientId);
        }
        
        return $branding;
    }
    
    /**
     * Get default branding settings
     */
    private function getDefaults($clientId) {
        return [
            'id' => null,
            'client_id' => $clientId,
            'business_name' => '',
            'tagline' => '',
            'website' => '',
            'email' => '',
            'phone' => '',
            'logo_path' => null,
            'logo_filename' => null,
            'primary_color' => '#8B5CF6',
            'secondary_color' => '#A855F7',
            'accent_color' => '#10B981',
            'email_signature' => '',
            'report_header' => '',
            'report_footer' => ''
        ];
    }
    
    /**
     * Save branding settings
     */
    public function saveBrandingSettings($clientId, $data, $logoFile = null) {
        try {
            $this->db->getConnection()->beginTransaction();
            
            // Handle logo upload if provided
            $logoPath = null;
            $logoFilename = null;
            
            if ($logoFile && $logoFile['error'] === UPLOAD_ERR_OK) {
                $uploadResult = $this->handleLogoUpload($clientId, $logoFile);
                if ($uploadResult['success']) {
                    $logoPath = $uploadResult['path'];
                    $logoFilename = $uploadResult['filename'];
                    
                    // Delete old logo if exists
                    $this->deleteOldLogo($clientId);
                } else {
                    throw new Exception($uploadResult['error']);
                }
            }
            
            // Validate and sanitize data
            $brandingData = $this->validateBrandingData($data);
            
            // Check if branding record exists
            $stmt = $this->db->prepare("SELECT id FROM client_branding WHERE client_id = ?");
            $stmt->execute([$clientId]);
            $exists = $stmt->fetch();
            
            if ($exists) {
                // Update existing record
                $sql = "UPDATE client_branding SET 
                        business_name = ?, tagline = ?, website = ?, email = ?, phone = ?,
                        primary_color = ?, secondary_color = ?, accent_color = ?,
                        email_signature = ?, report_header = ?, report_footer = ?,
                        updated_at = CURRENT_TIMESTAMP";
                
                $params = [
                    $brandingData['business_name'],
                    $brandingData['tagline'],
                    $brandingData['website'],
                    $brandingData['email'],
                    $brandingData['phone'],
                    $brandingData['primary_color'],
                    $brandingData['secondary_color'],
                    $brandingData['accent_color'],
                    $brandingData['email_signature'],
                    $brandingData['report_header'],
                    $brandingData['report_footer']
                ];
                
                if ($logoPath) {
                    $sql .= ", logo_path = ?, logo_filename = ?";
                    $params[] = $logoPath;
                    $params[] = $logoFilename;
                }
                
                $sql .= " WHERE client_id = ?";
                $params[] = $clientId;
                
            } else {
                // Insert new record
                $sql = "INSERT INTO client_branding 
                        (client_id, business_name, tagline, website, email, phone,
                         primary_color, secondary_color, accent_color,
                         email_signature, report_header, report_footer,
                         logo_path, logo_filename) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $params = [
                    $clientId,
                    $brandingData['business_name'],
                    $brandingData['tagline'],
                    $brandingData['website'],
                    $brandingData['email'],
                    $brandingData['phone'],
                    $brandingData['primary_color'],
                    $brandingData['secondary_color'],
                    $brandingData['accent_color'],
                    $brandingData['email_signature'],
                    $brandingData['report_header'],
                    $brandingData['report_footer'],
                    $logoPath,
                    $logoFilename
                ];
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            $this->db->getConnection()->commit();
            
            return ['success' => true, 'message' => 'Branding settings saved successfully'];
            
        } catch (Exception $e) {
            $this->db->getConnection()->rollback();
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Handle logo file upload
     */
    private function handleLogoUpload($clientId, $file) {
        // Validate file
        if (!$this->validateLogoFile($file)) {
            return ['success' => false, 'error' => 'Invalid file type or size'];
        }
        
        // Generate unique filename
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = 'logo_' . $clientId . '_' . time() . '.' . $extension;
        $filepath = $this->uploadDir . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Create web-accessible path
            $webPath = '/uploads/branding/' . $filename;
            
            return [
                'success' => true,
                'path' => $webPath,
                'filename' => $filename
            ];
        }
        
        return ['success' => false, 'error' => 'Failed to upload file'];
    }
    
    /**
     * Validate uploaded logo file
     */
    private function validateLogoFile($file) {
        // Check file size
        if ($file['size'] > $this->maxFileSize) {
            return false;
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $this->allowedTypes)) {
            return false;
        }
        
        // Additional validation for images
        if (strpos($mimeType, 'image/') === 0 && $mimeType !== 'image/svg+xml') {
            $imageInfo = getimagesize($file['tmp_name']);
            if (!$imageInfo) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Delete old logo file
     */
    private function deleteOldLogo($clientId) {
        $stmt = $this->db->prepare("SELECT logo_filename FROM client_branding WHERE client_id = ?");
        $stmt->execute([$clientId]);
        $result = $stmt->fetch();
        
        if ($result && $result['logo_filename']) {
            $oldFile = $this->uploadDir . $result['logo_filename'];
            if (file_exists($oldFile)) {
                unlink($oldFile);
            }
        }
    }
    
    /**
     * Validate and sanitize branding data
     */
    private function validateBrandingData($data) {
        return [
            'business_name' => trim($data['business_name'] ?? ''),
            'tagline' => trim($data['tagline'] ?? ''),
            'website' => $this->validateUrl($data['website'] ?? ''),
            'email' => $this->validateEmail($data['email'] ?? ''),
            'phone' => trim($data['phone'] ?? ''),
            'primary_color' => $this->validateColor($data['primary_color'] ?? '#8B5CF6'),
            'secondary_color' => $this->validateColor($data['secondary_color'] ?? '#A855F7'),
            'accent_color' => $this->validateColor($data['accent_color'] ?? '#10B981'),
            'email_signature' => trim($data['email_signature'] ?? ''),
            'report_header' => trim($data['report_header'] ?? ''),
            'report_footer' => trim($data['report_footer'] ?? '')
        ];
    }
    
    /**
     * Validate URL format
     */
    private function validateUrl($url) {
        if (empty($url)) return '';
        
        // Add protocol if missing
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = 'https://' . $url;
        }
        
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
    }
    
    /**
     * Validate email format
     */
    private function validateEmail($email) {
        if (empty($email)) return '';
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }
    
    /**
     * Validate hex color format
     */
    private function validateColor($color) {
        if (empty($color)) return '#8B5CF6';
        
        // Remove # if present
        $color = ltrim($color, '#');
        
        // Validate hex format
        if (preg_match('/^[a-fA-F0-9]{6}$/', $color)) {
            return '#' . strtoupper($color);
        }
        
        // Return default if invalid
        return '#8B5CF6';
    }
    
    /**
     * Get logo URL for display
     */
    public function getLogoUrl($clientId) {
        $branding = $this->getBrandingSettings($clientId);
        
        if ($branding['logo_path']) {
            return $branding['logo_path'];
        }
        
        return null;
    }
    
    /**
     * Delete branding settings and logo
     */
    public function deleteBranding($clientId) {
        try {
            // Delete logo file first
            $this->deleteOldLogo($clientId);
            
            // Delete database record
            $stmt = $this->db->prepare("DELETE FROM client_branding WHERE client_id = ?");
            $stmt->execute([$clientId]);
            
            return ['success' => true, 'message' => 'Branding settings deleted successfully'];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Generate CSS variables for client branding
     */
    public function getBrandingCSS($clientId) {
        $branding = $this->getBrandingSettings($clientId);
        
        return ":root {
            --brand-primary: {$branding['primary_color']};
            --brand-secondary: {$branding['secondary_color']};
            --brand-accent: {$branding['accent_color']};
        }";
    }
}