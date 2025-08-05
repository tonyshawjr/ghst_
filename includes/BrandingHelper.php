<?php
/**
 * Branding Helper Functions
 * Utility functions for integrating branding throughout the application
 */

class BrandingHelper {
    private static $instance = null;
    private $branding;
    private $cache = [];
    
    private function __construct() {
        $this->branding = new Branding();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Get branding settings with caching
     */
    public function getBranding($clientId) {
        if (!isset($this->cache[$clientId])) {
            $this->cache[$clientId] = $this->branding->getBrandingSettings($clientId);
        }
        return $this->cache[$clientId];
    }
    
    /**
     * Clear branding cache
     */
    public function clearCache($clientId = null) {
        if ($clientId) {
            unset($this->cache[$clientId]);
        } else {
            $this->cache = [];
        }
    }
    
    /**
     * Generate inline CSS for client branding
     */
    public function getBrandingStyles($clientId) {
        $branding = $this->getBranding($clientId);
        
        return "
        <style>
            :root {
                --brand-primary: {$branding['primary_color']};
                --brand-secondary: {$branding['secondary_color']};
                --brand-accent: {$branding['accent_color']};
            }
            
            .btn-brand-primary {
                background-color: {$branding['primary_color']};
                border-color: {$branding['primary_color']};
            }
            
            .btn-brand-primary:hover {
                background-color: " . $this->darkenColor($branding['primary_color'], 10) . ";
                border-color: " . $this->darkenColor($branding['primary_color'], 10) . ";
            }
            
            .text-brand-primary {
                color: {$branding['primary_color']};
            }
            
            .border-brand-primary {
                border-color: {$branding['primary_color']};
            }
            
            .bg-brand-gradient {
                background: linear-gradient(135deg, {$branding['primary_color']}, {$branding['secondary_color']});
            }
            
            .ring-brand-primary:focus {
                --tw-ring-color: {$branding['primary_color']};
            }
        </style>
        ";
    }
    
    /**
     * Generate branded email signature
     */
    public function getEmailSignature($clientId) {
        $branding = $this->getBranding($clientId);
        
        if (empty($branding['email_signature'])) {
            return $this->getDefaultEmailSignature($branding);
        }
        
        return $branding['email_signature'];
    }
    
    /**
     * Generate default email signature
     */
    private function getDefaultEmailSignature($branding) {
        $signature = "Best regards,\n\n";
        
        if ($branding['business_name']) {
            $signature .= $branding['business_name'] . "\n";
        }
        
        if ($branding['tagline']) {
            $signature .= $branding['tagline'] . "\n";
        }
        
        if ($branding['email'] || $branding['phone'] || $branding['website']) {
            $signature .= "\n";
            
            if ($branding['email']) {
                $signature .= "Email: " . $branding['email'] . "\n";
            }
            
            if ($branding['phone']) {
                $signature .= "Phone: " . $branding['phone'] . "\n";
            }
            
            if ($branding['website']) {
                $signature .= "Website: " . $branding['website'] . "\n";
            }
        }
        
        return $signature;
    }
    
    /**
     * Generate branded report header
     */
    public function getReportHeader($clientId, $reportTitle = 'Social Media Report', $dateRange = null) {
        $branding = $this->getBranding($clientId);
        
        if (!empty($branding['report_header'])) {
            $header = $branding['report_header'];
            
            // Replace placeholders
            $header = str_replace('[Date Range]', $dateRange ?: date('F Y'), $header);
            $header = str_replace('[Report Title]', $reportTitle, $header);
            
            return $header;
        }
        
        // Default header
        $header = $reportTitle . "\n";
        
        if ($branding['business_name']) {
            $header .= "Prepared by: " . $branding['business_name'] . "\n";
        }
        
        if ($dateRange) {
            $header .= "Period: " . $dateRange . "\n";
        }
        
        return $header;
    }
    
    /**
     * Generate branded report footer
     */
    public function getReportFooter($clientId) {
        $branding = $this->getBranding($clientId);
        
        if (!empty($branding['report_footer'])) {
            return $branding['report_footer'];
        }
        
        // Default footer
        $footer = "";
        
        if ($branding['business_name']) {
            $footer .= "Thank you for choosing " . $branding['business_name'] . "!\n";
        }
        
        if ($branding['email']) {
            $footer .= "For questions, contact us at: " . $branding['email'] . "\n";
        }
        
        if ($branding['website']) {
            $footer .= "Visit us: " . $branding['website'] . "\n";
        }
        
        return $footer;
    }
    
    /**
     * Get logo HTML for display
     */
    public function getLogoHtml($clientId, $classes = 'h-12 w-auto', $alt = 'Logo') {
        $branding = $this->getBranding($clientId);
        
        if ($branding['logo_path']) {
            return "<img src=\"{$branding['logo_path']}\" alt=\"{$alt}\" class=\"{$classes}\">";
        }
        
        // Fallback to business name
        if ($branding['business_name']) {
            return "<span class=\"font-bold text-lg {$classes}\">{$branding['business_name']}</span>";
        }
        
        return '';
    }
    
    /**
     * Generate branded business card data
     */
    public function getBusinessCardData($clientId) {
        $branding = $this->getBranding($clientId);
        
        return [
            'name' => $branding['business_name'],
            'tagline' => $branding['tagline'],
            'email' => $branding['email'],
            'phone' => $branding['phone'],
            'website' => $branding['website'],
            'logo' => $branding['logo_path'],
            'colors' => [
                'primary' => $branding['primary_color'],
                'secondary' => $branding['secondary_color'],
                'accent' => $branding['accent_color']
            ]
        ];
    }
    
    /**
     * Check if client has custom branding
     */
    public function hasCustomBranding($clientId) {
        $branding = $this->getBranding($clientId);
        
        return !empty($branding['business_name']) || 
               !empty($branding['logo_path']) || 
               $branding['primary_color'] !== '#8B5CF6';
    }
    
    /**
     * Darken a hex color by a percentage
     */
    private function darkenColor($hex, $percent) {
        $hex = ltrim($hex, '#');
        
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        $r = max(0, min(255, $r - ($r * $percent / 100)));
        $g = max(0, min(255, $g - ($g * $percent / 100)));
        $b = max(0, min(255, $b - ($b * $percent / 100)));
        
        return sprintf("#%02x%02x%02x", $r, $g, $b);
    }
    
    /**
     * Lighten a hex color by a percentage
     */
    private function lightenColor($hex, $percent) {
        $hex = ltrim($hex, '#');
        
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        
        $r = max(0, min(255, $r + ((255 - $r) * $percent / 100)));
        $g = max(0, min(255, $g + ((255 - $g) * $percent / 100)));
        $b = max(0, min(255, $b + ((255 - $b) * $percent / 100)));
        
        return sprintf("#%02x%02x%02x", $r, $g, $b);
    }
}