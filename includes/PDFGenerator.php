<?php
/**
 * PDF Generator Class
 * Converts HTML reports to PDF with professional branding
 * Supports multiple PDF libraries (TCPDF, Dompdf, mPDF)
 */

require_once 'Database.php';
require_once 'BrandingHelper.php';

class PDFGenerator {
    private $db;
    private $brandingHelper;
    private $library;
    private $options;
    
    // Default PDF options
    private $defaultOptions = [
        'orientation' => 'portrait',
        'page_size' => 'A4',
        'margin_top' => 20,
        'margin_bottom' => 20,
        'margin_left' => 15,
        'margin_right' => 15,
        'header_height' => 15,
        'footer_height' => 15,
        'enable_remote' => true,
        'enable_php' => false,
        'dpi' => 96,
        'default_font' => 'helvetica',
        'default_font_size' => 11,
        'compress' => true,
        'author' => 'GHST Social Media Management',
        'creator' => 'GHST Reports System',
        'enable_html5_parser' => true
    ];
    
    public function __construct($library = 'auto', $options = []) {
        $this->db = Database::getInstance();
        $this->brandingHelper = BrandingHelper::getInstance();
        $this->options = array_merge($this->defaultOptions, $options);
        
        // Auto-detect best available PDF library
        if ($library === 'auto') {
            $this->library = $this->detectBestLibrary();
        } else {
            $this->library = $library;
        }
        
        $this->initializeLibrary();
    }
    
    /**
     * Generate PDF from HTML report
     */
    public function generatePDF($htmlContent, $outputPath, $branding = null) {
        try {
            // Prepare HTML for PDF conversion
            $processedHtml = $this->prepareHtmlForPDF($htmlContent, $branding);
            
            // Generate PDF based on selected library
            switch ($this->library) {
                case 'tcpdf':
                    return $this->generateWithTCPDF($processedHtml, $outputPath, $branding);
                    
                case 'dompdf':
                    return $this->generateWithDompdf($processedHtml, $outputPath, $branding);
                    
                case 'mpdf':
                    return $this->generateWithMPDF($processedHtml, $outputPath, $branding);
                    
                default:
                    throw new Exception("Unsupported PDF library: {$this->library}");
            }
            
        } catch (Exception $e) {
            error_log("PDF Generation Error: " . $e->getMessage());
            throw new Exception("Failed to generate PDF: " . $e->getMessage());
        }
    }
    
    /**
     * Generate PDF from report data
     */
    public function generateReportPDF($reportData, $template, $outputPath) {
        // Apply branding to template
        $branding = $reportData['branding'] ?? [];
        
        // Generate HTML from template
        $html = $this->renderTemplate($template, $reportData);
        
        // Convert charts to images if present
        $html = $this->convertChartsToImages($html, $reportData);
        
        // Generate PDF
        return $this->generatePDF($html, $outputPath, $branding);
    }
    
    /**
     * Detect best available PDF library
     */
    private function detectBestLibrary() {
        // Check for mPDF (recommended)
        if (class_exists('Mpdf\Mpdf')) {
            return 'mpdf';
        }
        
        // Check for TCPDF
        if (class_exists('TCPDF')) {
            return 'tcpdf';
        }
        
        // Check for Dompdf
        if (class_exists('Dompdf\Dompdf')) {
            return 'dompdf';
        }
        
        throw new Exception("No PDF library found. Please install mPDF, TCPDF, or Dompdf.");
    }
    
    /**
     * Initialize PDF library
     */
    private function initializeLibrary() {
        switch ($this->library) {
            case 'mpdf':
                if (!class_exists('Mpdf\Mpdf')) {
                    throw new Exception("mPDF library not found. Please install via composer: require mpdf/mpdf");
                }
                break;
                
            case 'tcpdf':
                if (!class_exists('TCPDF')) {
                    throw new Exception("TCPDF library not found. Please install via composer: require tecnickcom/tcpdf");
                }
                break;
                
            case 'dompdf':
                if (!class_exists('Dompdf\Dompdf')) {
                    throw new Exception("Dompdf library not found. Please install via composer: require dompdf/dompdf");
                }
                break;
        }
    }
    
    /**
     * Generate PDF using mPDF
     */
    private function generateWithMPDF($html, $outputPath, $branding = null) {
        $config = [
            'mode' => 'utf-8',
            'orientation' => $this->options['orientation'][0], // P or L
            'format' => $this->options['page_size'],
            'margin_left' => $this->options['margin_left'],
            'margin_right' => $this->options['margin_right'],
            'margin_top' => $this->options['margin_top'],
            'margin_bottom' => $this->options['margin_bottom'],
            'margin_header' => $this->options['header_height'],
            'margin_footer' => $this->options['footer_height'],
            'default_font' => $this->options['default_font'],
            'default_font_size' => $this->options['default_font_size'],
            'tempDir' => sys_get_temp_dir() . '/mpdf'
        ];
        
        $mpdf = new \Mpdf\Mpdf($config);
        
        // Set document properties
        $mpdf->SetCreator($this->options['creator']);
        $mpdf->SetAuthor($this->options['author']);
        $mpdf->SetTitle($branding['business_name'] ?? 'Social Media Report');
        $mpdf->SetSubject('Social Media Analytics Report');
        
        // Add header and footer if branding is provided
        if ($branding) {
            $this->addMPDFHeaderFooter($mpdf, $branding);
        }
        
        // Write HTML
        $mpdf->WriteHTML($html);
        
        // Output PDF
        $mpdf->Output($outputPath, 'F');
        
        return true;
    }
    
    /**
     * Generate PDF using TCPDF
     */
    private function generateWithTCPDF($html, $outputPath, $branding = null) {
        // Create TCPDF instance
        $pdf = new TCPDF(
            $this->options['orientation'][0], // P or L
            'mm',
            $this->options['page_size'],
            true,
            'UTF-8',
            false
        );
        
        // Set document information
        $pdf->SetCreator($this->options['creator']);
        $pdf->SetAuthor($this->options['author']);
        $pdf->SetTitle($branding['business_name'] ?? 'Social Media Report');
        $pdf->SetSubject('Social Media Analytics Report');
        
        // Set margins
        $pdf->SetMargins(
            $this->options['margin_left'],
            $this->options['margin_top'],
            $this->options['margin_right']
        );
        $pdf->SetAutoPageBreak(true, $this->options['margin_bottom']);
        
        // Add header and footer if branding is provided
        if ($branding) {
            $this->addTCPDFHeaderFooter($pdf, $branding);
        }
        
        // Set font
        $pdf->SetFont($this->options['default_font'], '', $this->options['default_font_size']);
        
        // Add page
        $pdf->AddPage();
        
        // Write HTML
        $pdf->writeHTML($html, true, false, true, false, '');
        
        // Output PDF
        $pdf->Output($outputPath, 'F');
        
        return true;
    }
    
    /**
     * Generate PDF using Dompdf
     */
    private function generateWithDompdf($html, $outputPath, $branding = null) {
        // Create Dompdf instance
        $options = new \Dompdf\Options();
        $options->set('defaultFont', $this->options['default_font']);
        $options->set('isRemoteEnabled', $this->options['enable_remote']);
        $options->set('isPhpEnabled', $this->options['enable_php']);
        $options->set('dpi', $this->options['dpi']);
        $options->set('defaultPaperSize', $this->options['page_size']);
        $options->set('defaultPaperOrientation', $this->options['orientation']);
        
        $dompdf = new \Dompdf\Dompdf($options);
        
        // Load HTML
        $dompdf->loadHtml($html);
        
        // Render PDF
        $dompdf->render();
        
        // Output PDF
        file_put_contents($outputPath, $dompdf->output());
        
        return true;
    }
    
    /**
     * Add header and footer to mPDF
     */
    private function addMPDFHeaderFooter($mpdf, $branding) {
        // Header HTML
        $headerHtml = '<div style="text-align: center; border-bottom: 1px solid #ddd; padding-bottom: 10px;">';
        
        if (!empty($branding['logo_url'])) {
            $headerHtml .= '<img src="' . htmlspecialchars($branding['logo_url']) . '" style="height: 30px; margin-bottom: 5px;"><br>';
        }
        
        if (!empty($branding['business_name'])) {
            $headerHtml .= '<span style="font-size: 12px; color: ' . ($branding['primary_color'] ?? '#8B5CF6') . '; font-weight: bold;">';
            $headerHtml .= htmlspecialchars($branding['business_name']);
            $headerHtml .= '</span>';
        }
        
        $headerHtml .= '</div>';
        
        // Footer HTML
        $footerHtml = '<div style="text-align: center; border-top: 1px solid #ddd; padding-top: 10px; font-size: 10px; color: #666;">';
        $footerHtml .= 'Generated on {DATE j-m-Y} | Page {PAGENO} of {nbpg}';
        
        if (!empty($branding['website_url'])) {
            $footerHtml .= ' | ' . htmlspecialchars($branding['website_url']);
        }
        
        $footerHtml .= '</div>';
        
        // Set header and footer
        $mpdf->SetHTMLHeader($headerHtml);
        $mpdf->SetHTMLFooter($footerHtml);
    }
    
    /**
     * Add header and footer to TCPDF
     */
    private function addTCPDFHeaderFooter($pdf, $branding) {
        // Custom header class would be needed for TCPDF
        // For now, we'll add a simple text header
        $pdf->SetHeaderData(
            '', // logo
            0, // logo width
            $branding['business_name'] ?? 'Social Media Report',
            'Generated on ' . date('F j, Y'),
            array(139, 92, 246), // header color
            array(0, 0, 0) // line color
        );
        
        $pdf->setFooterData(
            array(0, 0, 0), // text color
            array(139, 92, 246) // line color
        );
        
        // Set header and footer fonts
        $pdf->setHeaderFont(array('helvetica', '', 10));
        $pdf->setFooterFont(array('helvetica', '', 8));
        
        // Enable header and footer
        $pdf->SetPrintHeader(true);
        $pdf->SetPrintFooter(true);
    }
    
    /**
     * Prepare HTML for PDF conversion
     */
    private function prepareHtmlForPDF($html, $branding = null) {
        // Convert relative URLs to absolute
        $html = $this->convertRelativeUrls($html);
        
        // Optimize images for PDF
        $html = $this->optimizeImagesForPDF($html);
        
        // Add page break CSS classes
        $html = $this->addPageBreakStyles($html);
        
        // Apply PDF-specific CSS
        $html = $this->applyPDFStyles($html, $branding);
        
        return $html;
    }
    
    /**
     * Convert relative URLs to absolute
     */
    private function convertRelativeUrls($html) {
        $baseUrl = 'https://' . $_SERVER['HTTP_HOST'];
        
        // Convert image sources
        $html = preg_replace('/src="\/([^"]*)"/', 'src="' . $baseUrl . '/$1"', $html);
        
        // Convert CSS links
        $html = preg_replace('/href="\/([^"]*\.css[^"]*)"/', 'href="' . $baseUrl . '/$1"', $html);
        
        return $html;
    }
    
    /**
     * Optimize images for PDF
     */
    private function optimizeImagesForPDF($html) {
        // Add max-width to images to prevent overflow
        $html = preg_replace(
            '/<img([^>]*?)style="([^"]*?)"([^>]*?)>/',
            '<img$1style="$2; max-width: 100%; height: auto;"$3>',
            $html
        );
        
        // Add style to images without existing styles
        $html = preg_replace(
            '/<img(?![^>]*style=)([^>]*?)>/',
            '<img$1 style="max-width: 100%; height: auto;">',
            $html
        );
        
        return $html;
    }
    
    /**
     * Add page break styles
     */
    private function addPageBreakStyles($html) {
        $pageBreakCSS = '
        <style>
            .page-break-before { page-break-before: always; }
            .page-break-after { page-break-after: always; }
            .page-break-inside-avoid { page-break-inside: avoid; }
            .pdf-section { 
                page-break-inside: avoid; 
                margin-bottom: 20px; 
            }
            .pdf-chart-container {
                page-break-inside: avoid;
                text-align: center;
                margin: 20px 0;
            }
            .pdf-table {
                page-break-inside: auto;
            }
            .pdf-table tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }
            @media print {
                .no-print { display: none !important; }
                .print-only { display: block !important; }
            }
        </style>';
        
        // Insert CSS into head if it exists, otherwise add to beginning
        if (strpos($html, '</head>') !== false) {
            $html = str_replace('</head>', $pageBreakCSS . '</head>', $html);
        } else {
            $html = $pageBreakCSS . $html;
        }
        
        return $html;
    }
    
    /**
     * Apply PDF-specific styles
     */
    private function applyPDFStyles($html, $branding = null) {
        $pdfCSS = '
        <style>
            body {
                margin: 0;
                padding: 0;
                font-family: "' . $this->options['default_font'] . '", Arial, sans-serif;
                font-size: ' . $this->options['default_font_size'] . 'px;
                line-height: 1.4;
                color: #333;
            }
            
            .container {
                max-width: none;
                margin: 0;
                padding: 0;
            }
            
            h1, h2, h3, h4, h5, h6 {
                margin-top: 0;
                margin-bottom: 10px;
                page-break-after: avoid;
            }
            
            p {
                margin-bottom: 10px;
                orphans: 3;
                widows: 3;
            }
            
            table {
                border-collapse: collapse;
                width: 100%;
                margin-bottom: 20px;
            }
            
            th, td {
                padding: 8px;
                text-align: left;
                border-bottom: 1px solid #ddd;
            }
            
            th {
                background-color: #f8f9fa;
                font-weight: bold;
            }
            
            .chart-container {
                text-align: center;
                margin: 20px 0;
                page-break-inside: avoid;
            }
            
            .metric-card {
                border: 1px solid #ddd;
                margin-bottom: 10px;
                padding: 15px;
                page-break-inside: avoid;
            }
            
            .section {
                margin-bottom: 30px;
                page-break-inside: avoid;
            }
            
            .header {
                text-align: center;
                margin-bottom: 30px;
                padding-bottom: 20px;
                border-bottom: 2px solid ' . ($branding['primary_color'] ?? '#8B5CF6') . ';
            }
            
            .footer {
                margin-top: 40px;
                padding-top: 20px;
                border-top: 1px solid #ddd;
                text-align: center;
                font-size: 10px;
                color: #666;
            }
        </style>';
        
        // Insert CSS
        if (strpos($html, '</head>') !== false) {
            $html = str_replace('</head>', $pdfCSS . '</head>', $html);
        } else {
            $html = $pdfCSS . $html;
        }
        
        return $html;
    }
    
    /**
     * Convert Chart.js charts to static images
     */
    private function convertChartsToImages($html, $reportData) {
        // This would require a headless browser like Puppeteer or PhantomJS
        // For now, we'll replace chart containers with placeholder text
        
        $chartPattern = '/<canvas[^>]*id="([^"]*chart[^"]*)"[^>]*><\/canvas>/i';
        
        $html = preg_replace_callback($chartPattern, function($matches) use ($reportData) {
            $chartId = $matches[1];
            
            // Generate chart image placeholder
            return '<div class="pdf-chart-container" style="background: #f8f9fa; padding: 40px; border: 1px solid #ddd; text-align: center;">
                <h3 style="margin: 0; color: #666;">Chart Placeholder</h3>
                <p style="margin: 10px 0 0 0; color: #888; font-size: 12px;">Chart ID: ' . htmlspecialchars($chartId) . '</p>
                <p style="margin: 5px 0 0 0; color: #888; font-size: 10px;">Interactive charts are not available in PDF format</p>
            </div>';
        }, $html);
        
        return $html;
    }
    
    /**
     * Render template with data
     */
    private function renderTemplate($template, $data) {
        // Extract variables for template
        extract($data);
        $branding = $data['branding'] ?? [];
        
        // Start output buffering
        ob_start();
        
        // Include template file
        $templatePath = $_SERVER['DOCUMENT_ROOT'] . '/includes/report-templates/' . $template . '.php';
        
        if (file_exists($templatePath)) {
            include $templatePath;
        } else {
            throw new Exception("Template not found: {$template}");
        }
        
        // Get rendered HTML
        $html = ob_get_clean();
        
        return $html;
    }
    
    /**
     * Generate chart image using headless browser (placeholder)
     */
    private function generateChartImage($chartConfig, $outputPath) {
        // This would integrate with a service like:
        // - Puppeteer (Node.js)
        // - Chart.js to image service
        // - Server-side chart generation library
        
        // For now, return false to indicate chart image generation is not available
        return false;
    }
    
    /**
     * Get supported PDF libraries
     */
    public static function getSupportedLibraries() {
        $libraries = [];
        
        if (class_exists('Mpdf\Mpdf')) {
            $libraries['mpdf'] = 'mPDF (Recommended)';
        }
        
        if (class_exists('TCPDF')) {
            $libraries['tcpdf'] = 'TCPDF';
        }
        
        if (class_exists('Dompdf\Dompdf')) {
            $libraries['dompdf'] = 'Dompdf';
        }
        
        return $libraries;
    }
    
    /**
     * Get PDF library info
     */
    public function getLibraryInfo() {
        return [
            'library' => $this->library,
            'options' => $this->options,
            'available_libraries' => self::getSupportedLibraries()
        ];
    }
    
    /**
     * Set PDF options
     */
    public function setOptions($options) {
        $this->options = array_merge($this->options, $options);
    }
    
    /**
     * Cache PDF for faster subsequent access
     */
    public function cachePDF($reportId, $pdfPath) {
        $cacheDir = $_SERVER['DOCUMENT_ROOT'] . '/cache/pdfs/';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }
        
        $cachedPath = $cacheDir . 'report_' . $reportId . '.pdf';
        
        if (copy($pdfPath, $cachedPath)) {
            // Update database with cached path
            $stmt = $this->db->prepare("
                UPDATE generated_reports 
                SET cached_pdf_path = ?, pdf_cached_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$cachedPath, $reportId]);
            
            return $cachedPath;
        }
        
        return false;
    }
    
    /**
     * Get cached PDF path
     */
    public function getCachedPDF($reportId) {
        $stmt = $this->db->prepare("
            SELECT cached_pdf_path, pdf_cached_at 
            FROM generated_reports 
            WHERE id = ? AND cached_pdf_path IS NOT NULL
        ");
        $stmt->execute([$reportId]);
        $result = $stmt->fetch();
        
        if ($result && file_exists($result['cached_pdf_path'])) {
            return $result['cached_pdf_path'];
        }
        
        return null;
    }
}