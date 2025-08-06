<?php
/**
 * Campaign PDF Exporter
 * 
 * Creates professional PDF exports of campaign strategies with client branding
 * Uses TCPDF for PDF generation with custom styling and layouts
 * 
 * Features:
 * - Client branding (logo, colors, fonts)
 * - Professional multi-page layouts
 * - Executive summary and detailed views
 * - Charts and analytics visualization
 * - Password protection and watermarks
 * 
 * @package GHST_WRTR
 * @version 1.0.0
 * @author Tony Shaw Jr.
 */

require_once __DIR__ . '/../vendor/autoload.php'; // For TCPDF
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/functions.php';

class CampaignPDFExporter {
    
    private $db;
    private $campaignId;
    private $campaign;
    private $client;
    private $settings;
    private $pdf;
    
    // PDF Configuration
    const PAGE_ORIENTATION = 'P';
    const PAGE_UNIT = 'mm';
    const PAGE_FORMAT = 'A4';
    const MARGIN_LEFT = 15;
    const MARGIN_TOP = 27;
    const MARGIN_RIGHT = 15;
    const MARGIN_BOTTOM = 25;
    const MARGIN_HEADER = 5;
    const MARGIN_FOOTER = 10;
    
    // Brand Colors (can be overridden by client settings)
    const PRIMARY_COLOR = '#8B5CF6';   // Purple-600
    const SECONDARY_COLOR = '#1F2937'; // Gray-800
    const ACCENT_COLOR = '#6366F1';    // Indigo-600
    const TEXT_COLOR = '#1F2937';      // Gray-800
    const LIGHT_COLOR = '#F9FAFB';     // Gray-50
    
    public function __construct($campaignId, $clientId = null) {
        $this->db = Database::getInstance();
        $this->campaignId = $campaignId;
        
        $this->loadCampaignData();
        
        if ($this->campaign && $this->campaign['client_id']) {
            $this->loadClientData();
            $this->loadExportSettings();
        }
        
        $this->initializePDF();
    }
    
    /**
     * Export campaign to PDF
     * 
     * @param array $options Export options
     * @return array Result with file path and metadata
     */
    public function exportToPDF(array $options = []): array {
        try {
            // Set default options
            $options = array_merge([
                'format' => 'detailed',
                'include_analytics' => true,
                'include_schedules' => true,
                'include_content_samples' => true,
                'sections' => ['overview', 'weekly_plans', 'content_calendar', 'analytics'],
                'password' => null,
                'watermark' => null
            ], $options);
            
            // Build PDF content based on format
            switch ($options['format']) {
                case 'summary':
                    $this->buildSummaryPDF($options);
                    break;
                case 'presentation':
                    $this->buildPresentationPDF($options);
                    break;
                case 'client_ready':
                    $this->buildClientReadyPDF($options);
                    break;
                case 'detailed':
                default:
                    $this->buildDetailedPDF($options);
                    break;
            }
            
            // Apply password protection if specified
            if (!empty($options['password'])) {
                $this->pdf->SetProtection(['print', 'copy'], $options['password']);
            }
            
            // Generate filename and save
            $filename = $this->generateFilename($options['format']);
            $filepath = UPLOAD_PATH . '/exports/' . $filename;
            
            // Ensure exports directory exists
            $exportDir = dirname($filepath);
            if (!is_dir($exportDir)) {
                mkdir($exportDir, 0755, true);
            }
            
            // Output PDF to file
            $this->pdf->Output($filepath, 'F');
            
            // Store export record
            $this->storeExportRecord($filename, $filepath, $options);
            
            return [
                'success' => true,
                'filename' => $filename,
                'filepath' => $filepath,
                'filesize' => filesize($filepath),
                'download_url' => '/downloads/exports/' . $filename
            ];
            
        } catch (Exception $e) {
            error_log("PDF Export Error: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Build detailed PDF with all sections
     */
    private function buildDetailedPDF(array $options): void {
        // Cover Page
        $this->addCoverPage();
        
        // Executive Summary
        if (in_array('overview', $options['sections'])) {
            $this->addExecutiveSummary();
        }
        
        // Campaign Overview
        $this->addCampaignOverview();
        
        // Weekly Strategy Plans
        if (in_array('weekly_plans', $options['sections'])) {
            $this->addWeeklyPlans($options);
        }
        
        // Content Calendar
        if (in_array('content_calendar', $options['sections']) && $options['include_schedules']) {
            $this->addContentCalendar();
        }
        
        // Analytics & Performance
        if (in_array('analytics', $options['sections']) && $options['include_analytics']) {
            $this->addAnalyticsSection();
        }
        
        // Appendix
        $this->addAppendix();
    }
    
    /**
     * Build summary PDF (executive overview only)
     */
    private function buildSummaryPDF(array $options): void {
        $this->addCoverPage();
        $this->addExecutiveSummary();
        $this->addCampaignOverview();
        
        if ($options['include_analytics']) {
            $this->addAnalyticsSummary();
        }
    }
    
    /**
     * Build presentation-style PDF
     */
    private function buildPresentationPDF(array $options): void {
        $this->addPresentationCover();
        $this->addPresentationOverview();
        $this->addPresentationStrategy();
        
        if ($options['include_analytics']) {
            $this->addPresentationMetrics();
        }
    }
    
    /**
     * Build client-ready PDF with professional styling
     */
    private function buildClientReadyPDF(array $options): void {
        $this->addProfessionalCover();
        $this->addClientExecutiveSummary();
        $this->addClientCampaignDetails();
        $this->addClientWeeklyBreakdown();
        
        if ($options['include_analytics']) {
            $this->addClientPerformanceReport();
        }
        
        $this->addClientNextSteps();
    }
    
    /**
     * Add cover page
     */
    private function addCoverPage(): void {
        $this->pdf->AddPage();
        
        // Add client logo if available
        if (!empty($this->client['logo_path']) && file_exists($this->client['logo_path'])) {
            $this->pdf->Image($this->client['logo_path'], 15, 15, 40);
        }
        
        // Title section
        $this->pdf->SetY(60);
        $this->pdf->SetFont('helvetica', 'B', 28);
        $this->pdf->SetTextColor(75, 85, 99); // Gray-600
        $this->pdf->Cell(0, 15, 'Social Media Strategy', 0, 1, 'C');
        
        $this->pdf->SetFont('helvetica', 'B', 22);
        $this->pdf->SetTextColor(139, 92, 246); // Purple-600
        $this->pdf->Cell(0, 12, sanitize($this->campaign['title']), 0, 1, 'C');
        
        // Campaign details
        $this->pdf->SetY(100);
        $this->pdf->SetFont('helvetica', '', 12);
        $this->pdf->SetTextColor(107, 114, 128); // Gray-500
        
        $details = [
            'Client: ' . sanitize($this->client['name']),
            'Campaign Duration: ' . $this->campaign['total_weeks'] . ' weeks',
            'Generated: ' . date('F j, Y'),
            'Status: ' . ucfirst($this->campaign['status'])
        ];
        
        foreach ($details as $detail) {
            $this->pdf->Cell(0, 8, $detail, 0, 1, 'C');
        }
        
        // Add brand design elements
        $this->pdf->SetFillColor(139, 92, 246); // Purple-600
        $this->pdf->Rect(0, 250, 210, 5, 'F');
        
        // Footer
        $this->pdf->SetY(270);
        $this->pdf->SetFont('helvetica', '', 10);
        $this->pdf->SetTextColor(156, 163, 175); // Gray-400
        $this->pdf->Cell(0, 5, 'Generated by ghst_wrtr AI Strategy Engine', 0, 0, 'C');
    }
    
    /**
     * Add executive summary
     */
    private function addExecutiveSummary(): void {
        $this->pdf->AddPage();
        
        $this->addSectionHeader('Executive Summary');
        
        // Get campaign data
        $goals = $this->getCampaignGoals();
        $analytics = $this->getCampaignAnalytics();
        
        $this->pdf->SetFont('helvetica', '', 11);
        $this->pdf->SetTextColor(55, 65, 81); // Gray-700
        
        // Campaign Overview
        $overview = "This comprehensive social media strategy spans {$this->campaign['total_weeks']} weeks and is designed to ";
        
        if ($goals) {
            $goal = str_replace('_', ' ', $goals['primary_goal']);
            $overview .= "achieve {$goal} for {$this->client['name']}. ";
        }
        
        $overview .= "The strategy includes detailed weekly plans, platform-specific content, and performance tracking to ensure maximum ROI.";
        
        $this->pdf->WriteHTML('<p style="text-align: justify; line-height: 1.6;">' . $overview . '</p>');
        
        $this->pdf->Ln(8);
        
        // Key Metrics (if available)
        if ($analytics) {
            $this->addSubsectionHeader('Projected Performance');
            
            $this->pdf->SetFont('helvetica', '', 10);
            $metricsData = [
                ['Metric', 'Target', 'Projected'],
                ['Total Posts', $analytics['total_posts'] ?? 'TBD', ($analytics['total_posts'] ?? 0) . ' posts'],
                ['Avg Engagement Rate', '3.5%', ($analytics['avg_engagement_rate'] ?? 0) . '%'],
                ['Total Impressions', '50K+', formatNumber($analytics['total_impressions'] ?? 0)],
                ['Campaign Reach', '25K+', 'TBD']
            ];
            
            $this->addTable($metricsData, [50, 35, 35]);
        }
        
        $this->pdf->Ln(8);
        
        // Strategy Highlights
        $this->addSubsectionHeader('Strategy Highlights');
        
        $highlights = [
            'AI-Generated Content: Personalized posts optimized for your brand voice and audience',
            'Multi-Platform Approach: Coordinated campaigns across Instagram, Facebook, and LinkedIn',
            'Data-Driven Optimization: Weekly performance analysis and strategy refinement',
            'Scalable Framework: Flexible structure that adapts to changing business needs'
        ];
        
        foreach ($highlights as $highlight) {
            $this->pdf->WriteHTML('<p style="margin-bottom: 4px;">• ' . $highlight . '</p>');
        }
    }
    
    /**
     * Add campaign overview section
     */
    private function addCampaignOverview(): void {
        $this->pdf->AddPage();
        
        $this->addSectionHeader('Campaign Overview');
        
        $goals = $this->getCampaignGoals();
        $offers = $this->getCampaignOffers();
        $voiceTone = $this->getCampaignVoiceTone();
        
        if ($goals) {
            $this->addSubsectionHeader('Campaign Goals & Objectives');
            
            $this->pdf->SetFont('helvetica', '', 10);
            $this->pdf->WriteHTML('<p><strong>Primary Goal:</strong> ' . ucwords(str_replace('_', ' ', $goals['primary_goal'])) . '</p>');
            
            if (!empty($goals['target_audience'])) {
                $this->pdf->WriteHTML('<p><strong>Target Audience:</strong> ' . sanitize($goals['target_audience']) . '</p>');
            }
            
            if (!empty($goals['business_objectives'])) {
                $this->pdf->WriteHTML('<p><strong>Business Objectives:</strong> ' . sanitize($goals['business_objectives']) . '</p>');
            }
            
            $this->pdf->Ln(5);
        }
        
        if ($offers) {
            $this->addSubsectionHeader('Primary Offer & Value Proposition');
            
            $this->pdf->SetFont('helvetica', '', 10);
            $this->pdf->WriteHTML('<p><strong>Offer:</strong> ' . sanitize($offers['primary_offer']) . '</p>');
            
            if (!empty($offers['call_to_action'])) {
                $this->pdf->WriteHTML('<p><strong>Call to Action:</strong> ' . sanitize($offers['call_to_action']) . '</p>');
            }
            
            $this->pdf->Ln(5);
        }
        
        if ($voiceTone) {
            $this->addSubsectionHeader('Brand Voice & Messaging');
            
            $this->pdf->SetFont('helvetica', '', 10);
            $this->pdf->WriteHTML('<p><strong>Brand Voice:</strong> ' . ucfirst($voiceTone['brand_voice']) . '</p>');
            $this->pdf->WriteHTML('<p><strong>Writing Style:</strong> ' . ucfirst($voiceTone['writing_style']) . '</p>');
            
            if (!empty($voiceTone['tone_attributes'])) {
                $attributes = json_decode($voiceTone['tone_attributes'], true);
                if (is_array($attributes)) {
                    $this->pdf->WriteHTML('<p><strong>Tone Attributes:</strong> ' . implode(', ', $attributes) . '</p>');
                }
            }
        }
    }
    
    /**
     * Add weekly plans section
     */
    private function addWeeklyPlans(array $options): void {
        $weeks = $this->getCampaignWeeks();
        
        if (empty($weeks)) {
            return;
        }
        
        $this->pdf->AddPage();
        $this->addSectionHeader('Weekly Strategy Plans');
        
        foreach ($weeks as $week) {
            // Start new page for each week if detailed
            if ($options['format'] === 'detailed' && $week['week_number'] > 1) {
                $this->pdf->AddPage();
            }
            
            $this->addWeeklyPlan($week, $options);
        }
    }
    
    /**
     * Add individual weekly plan
     */
    private function addWeeklyPlan(array $week, array $options): void {
        $this->addSubsectionHeader("Week {$week['week_number']}: " . ($week['week_theme'] ?: 'Weekly Strategy'));
        
        $this->pdf->SetFont('helvetica', '', 9);
        $this->pdf->SetTextColor(107, 114, 128); // Gray-500
        $this->pdf->Cell(0, 5, 'Duration: ' . formatDate($week['week_start_date']) . ' - ' . formatDate($week['week_end_date']), 0, 1);
        
        $this->pdf->Ln(3);
        
        // Week objectives
        if (!empty($week['objectives'])) {
            $objectives = json_decode($week['objectives'], true);
            if (is_array($objectives) && !empty($objectives)) {
                $this->pdf->SetFont('helvetica', 'B', 10);
                $this->pdf->SetTextColor(55, 65, 81);
                $this->pdf->Cell(0, 6, 'Objectives:', 0, 1);
                
                $this->pdf->SetFont('helvetica', '', 10);
                foreach ($objectives as $objective) {
                    $this->pdf->WriteHTML('<p>• ' . sanitize($objective) . '</p>');
                }
                
                $this->pdf->Ln(2);
            }
        }
        
        // Key messages
        if (!empty($week['key_messages'])) {
            $messages = json_decode($week['key_messages'], true);
            if (is_array($messages) && !empty($messages)) {
                $this->pdf->SetFont('helvetica', 'B', 10);
                $this->pdf->Cell(0, 6, 'Key Messages:', 0, 1);
                
                $this->pdf->SetFont('helvetica', '', 10);
                foreach ($messages as $message) {
                    $this->pdf->WriteHTML('<p>• ' . sanitize($message) . '</p>');
                }
                
                $this->pdf->Ln(2);
            }
        }
        
        // Posts
        if ($options['include_content_samples']) {
            $posts = $this->getWeekPosts($week['id']);
            
            if (!empty($posts)) {
                $this->pdf->SetFont('helvetica', 'B', 10);
                $this->pdf->Cell(0, 6, 'Content Posts (' . count($posts) . '):', 0, 1);
                
                foreach (array_slice($posts, 0, 5) as $index => $post) { // Limit to 5 posts for PDF
                    $this->addPostPreview($post, $index + 1);
                }
                
                if (count($posts) > 5) {
                    $this->pdf->SetFont('helvetica', 'I', 9);
                    $this->pdf->SetTextColor(107, 114, 128);
                    $this->pdf->Cell(0, 5, '... and ' . (count($posts) - 5) . ' more posts', 0, 1);
                }
            }
        }
        
        $this->pdf->Ln(8);
    }
    
    /**
     * Add post preview in PDF
     */
    private function addPostPreview(array $post, int $index): void {
        $this->pdf->SetFont('helvetica', '', 9);
        $this->pdf->SetTextColor(55, 65, 81);
        
        // Post header
        $platform = ucfirst($post['platform']);
        $type = ucfirst(str_replace('_', ' ', $post['post_type']));
        
        $this->pdf->SetFont('helvetica', 'B', 9);
        $this->pdf->Cell(0, 5, "{$index}. {$platform} {$type}", 0, 1);
        
        // Content preview
        $this->pdf->SetFont('helvetica', '', 8);
        $content = sanitize($post['content']);
        $content = strlen($content) > 150 ? substr($content, 0, 150) . '...' : $content;
        
        $this->pdf->SetTextColor(75, 85, 99);
        $this->pdf->WriteHTML('<p style="margin-left: 10px; line-height: 1.4;">' . $content . '</p>');
        
        // Hashtags
        if (!empty($post['hashtags'])) {
            $this->pdf->SetTextColor(59, 130, 246); // Blue-500
            $hashtags = strlen($post['hashtags']) > 80 ? substr($post['hashtags'], 0, 80) . '...' : $post['hashtags'];
            $this->pdf->WriteHTML('<p style="margin-left: 10px; font-size: 8px;">' . sanitize($hashtags) . '</p>');
        }
        
        $this->pdf->Ln(3);
    }
    
    /**
     * Initialize PDF with settings
     */
    private function initializePDF(): void {
        $this->pdf = new TCPDF(
            self::PAGE_ORIENTATION,
            self::PAGE_UNIT,
            self::PAGE_FORMAT,
            true,
            'UTF-8',
            false
        );
        
        // Set document information
        $this->pdf->SetCreator('ghst_wrtr AI Strategy Engine');
        $this->pdf->SetAuthor($this->client['name'] ?? 'ghst_');
        $this->pdf->SetTitle(($this->campaign['title'] ?? 'Campaign Strategy') . ' - Social Media Strategy');
        $this->pdf->SetSubject('AI-Generated Social Media Campaign Strategy');
        $this->pdf->SetKeywords('social media, strategy, AI, campaign, marketing');
        
        // Set default header data
        $this->pdf->SetHeaderData('', 0, $this->campaign['title'] ?? 'Campaign Strategy', 'Generated by ghst_wrtr');
        
        // Set header and footer fonts
        $this->pdf->setHeaderFont(['helvetica', '', 10]);
        $this->pdf->setFooterFont(['helvetica', '', 8]);
        
        // Set margins
        $this->pdf->SetMargins(self::MARGIN_LEFT, self::MARGIN_TOP, self::MARGIN_RIGHT);
        $this->pdf->SetHeaderMargin(self::MARGIN_HEADER);
        $this->pdf->SetFooterMargin(self::MARGIN_FOOTER);
        
        // Set auto page breaks
        $this->pdf->SetAutoPageBreak(TRUE, self::MARGIN_BOTTOM);
        
        // Set image scale factor
        $this->pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
        
        // Set font
        $this->pdf->SetFont('helvetica', '', 10);
    }
    
    /**
     * Add section header
     */
    private function addSectionHeader(string $title): void {
        $this->pdf->SetFont('helvetica', 'B', 16);
        $this->pdf->SetTextColor(139, 92, 246); // Purple-600
        $this->pdf->Cell(0, 12, $title, 0, 1, 'L');
        
        // Add underline
        $this->pdf->SetDrawColor(139, 92, 246);
        $this->pdf->Line(15, $this->pdf->GetY(), 195, $this->pdf->GetY());
        
        $this->pdf->Ln(8);
    }
    
    /**
     * Add subsection header
     */
    private function addSubsectionHeader(string $title): void {
        $this->pdf->SetFont('helvetica', 'B', 12);
        $this->pdf->SetTextColor(75, 85, 99); // Gray-600
        $this->pdf->Cell(0, 8, $title, 0, 1, 'L');
        $this->pdf->Ln(2);
    }
    
    /**
     * Add table with data
     */
    private function addTable(array $data, array $colWidths): void {
        $this->pdf->SetFont('helvetica', '', 9);
        
        foreach ($data as $rowIndex => $row) {
            $isHeader = $rowIndex === 0;
            
            if ($isHeader) {
                $this->pdf->SetFont('helvetica', 'B', 9);
                $this->pdf->SetFillColor(139, 92, 246); // Purple-600
                $this->pdf->SetTextColor(255, 255, 255);
            } else {
                $this->pdf->SetFont('helvetica', '', 9);
                $this->pdf->SetFillColor(249, 250, 251); // Gray-50
                $this->pdf->SetTextColor(55, 65, 81); // Gray-700
            }
            
            foreach ($row as $colIndex => $cell) {
                $width = $colWidths[$colIndex] ?? 40;
                $this->pdf->Cell($width, 8, sanitize($cell), 1, 0, 'C', true);
            }
            
            $this->pdf->Ln();
        }
        
        $this->pdf->Ln(5);
    }
    
    /**
     * Load campaign data
     */
    private function loadCampaignData(): void {
        $stmt = $this->db->prepare("SELECT * FROM strategy_campaigns WHERE id = ?");
        $stmt->execute([$this->campaignId]);
        $this->campaign = $stmt->fetch();
    }
    
    /**
     * Load client data
     */
    private function loadClientData(): void {
        $stmt = $this->db->prepare("SELECT * FROM clients WHERE id = ?");
        $stmt->execute([$this->campaign['client_id']]);
        $this->client = $stmt->fetch();
    }
    
    /**
     * Load export settings
     */
    private function loadExportSettings(): void {
        $stmt = $this->db->prepare("SELECT * FROM campaign_pdf_settings WHERE campaign_id = ?");
        $stmt->execute([$this->campaignId]);
        $this->settings = $stmt->fetch();
        
        // Set defaults if no settings found
        if (!$this->settings) {
            $this->settings = [
                'template_name' => 'default',
                'include_sections' => json_encode(['overview', 'weekly_plans', 'content_calendar', 'analytics']),
                'export_format' => 'detailed',
                'include_analytics' => 1,
                'include_schedules' => 1,
                'include_content_samples' => 1
            ];
        }
    }
    
    /**
     * Get campaign goals
     */
    private function getCampaignGoals(): ?array {
        $stmt = $this->db->prepare("SELECT * FROM campaign_goals WHERE campaign_id = ?");
        $stmt->execute([$this->campaignId]);
        return $stmt->fetch();
    }
    
    /**
     * Get campaign offers
     */
    private function getCampaignOffers(): ?array {
        $stmt = $this->db->prepare("SELECT * FROM campaign_offers WHERE campaign_id = ?");
        $stmt->execute([$this->campaignId]);
        return $stmt->fetch();
    }
    
    /**
     * Get campaign voice/tone
     */
    private function getCampaignVoiceTone(): ?array {
        $stmt = $this->db->prepare("SELECT * FROM campaign_voice_tone WHERE campaign_id = ?");
        $stmt->execute([$this->campaignId]);
        return $stmt->fetch();
    }
    
    /**
     * Get campaign weeks
     */
    private function getCampaignWeeks(): array {
        $stmt = $this->db->prepare("
            SELECT * FROM campaign_weeks 
            WHERE campaign_id = ? 
            ORDER BY week_number ASC
        ");
        $stmt->execute([$this->campaignId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get week posts
     */
    private function getWeekPosts(int $weekId): array {
        $stmt = $this->db->prepare("
            SELECT * FROM campaign_week_posts 
            WHERE campaign_week_id = ? 
            ORDER BY post_order ASC
        ");
        $stmt->execute([$weekId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Get campaign analytics
     */
    private function getCampaignAnalytics(): ?array {
        $stmt = $this->db->prepare("
            SELECT * FROM campaign_analytics 
            WHERE campaign_id = ? AND week_number IS NULL 
            LIMIT 1
        ");
        $stmt->execute([$this->campaignId]);
        return $stmt->fetch();
    }
    
    /**
     * Generate filename for export
     */
    private function generateFilename(string $format): string {
        $campaignName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $this->campaign['title'] ?? 'campaign');
        $clientName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $this->client['name'] ?? 'client');
        $timestamp = date('Y-m-d_H-i-s');
        
        return "{$clientName}_{$campaignName}_{$format}_{$timestamp}.pdf";
    }
    
    /**
     * Store export record in database
     */
    private function storeExportRecord(string $filename, string $filepath, array $options): void {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO campaign_pdf_exports (
                    campaign_id, export_type, file_name, file_path, 
                    file_size, export_settings, status, generated_by, 
                    expires_at, created_at, completed_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'completed', NULL, ?, NOW(), NOW())
            ");
            
            $fileSize = filesize($filepath);
            $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days')); // 30 day expiry
            
            $stmt->execute([
                $this->campaignId,
                'full_campaign',
                $filename,
                $filepath,
                $fileSize,
                json_encode($options),
                $expiresAt
            ]);
            
        } catch (Exception $e) {
            error_log("Failed to store export record: " . $e->getMessage());
        }
    }
    
    /**
     * Additional methods for different PDF formats would be implemented here:
     * - addContentCalendar()
     * - addAnalyticsSection()
     * - addAppendix()
     * - addPresentationCover()
     * - addProfessionalCover()
     * - addClientExecutiveSummary()
     * - etc.
     */
}