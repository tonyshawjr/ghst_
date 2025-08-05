<?php
/**
 * PDF Export API Endpoint
 * POST/GET /api/reports/export-pdf.php
 * Converts reports to PDF with branding
 */

require_once '../../includes/auth_check.php';
require_once '../../includes/Database.php';
require_once '../../includes/ReportGenerator.php';
require_once '../../includes/PDFGenerator.php';
require_once '../../includes/BrandingHelper.php';

header('Content-Type: application/json');

try {
    // Check request method
    if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
        throw new Exception('Method not allowed', 405);
    }
    
    // Get parameters
    $reportId = $_GET['report_id'] ?? $_POST['report_id'] ?? null;
    $download = $_GET['download'] ?? $_POST['download'] ?? false;
    $regenerate = $_GET['regenerate'] ?? $_POST['regenerate'] ?? false;
    $pdfOptions = $_POST['pdf_options'] ?? [];
    
    if (!$reportId || !is_numeric($reportId)) {
        throw new Exception('Invalid report ID', 400);
    }
    
    // Initialize classes
    $reportGenerator = new ReportGenerator();
    $brandingHelper = BrandingHelper::getInstance();
    
    // Get report details
    $report = $reportGenerator->getReport($reportId);
    if (!$report) {
        throw new Exception('Report not found', 404);
    }
    
    // Check access permissions
    $clientId = $_SESSION['current_client_id'] ?? null;
    if ($report['client_id'] != $clientId) {
        throw new Exception('Access denied', 403);
    }
    
    // Check if report is completed
    if ($report['status'] !== 'completed') {
        throw new Exception('Report is not ready for PDF export', 400);
    }
    
    // Initialize PDF generator with options
    $defaultPdfOptions = [
        'orientation' => 'portrait',
        'page_size' => 'A4',
        'margin_top' => 20,
        'margin_bottom' => 20,
        'margin_left' => 15,
        'margin_right' => 15,
        'default_font' => 'helvetica',
        'default_font_size' => 11,
        'enable_remote' => true,
        'compress' => true
    ];
    
    $pdfOptions = array_merge($defaultPdfOptions, $pdfOptions);
    $pdfGenerator = new PDFGenerator('auto', $pdfOptions);
    
    // Check for cached PDF
    $cachedPdfPath = null;
    if (!$regenerate) {
        $cachedPdfPath = $pdfGenerator->getCachedPDF($reportId);
    }
    
    if ($cachedPdfPath && file_exists($cachedPdfPath)) {
        $pdfPath = $cachedPdfPath;
        $fromCache = true;
    } else {
        // Generate new PDF
        $fromCache = false;
        
        // Get client branding
        $branding = $brandingHelper->getBranding($clientId);
        
        // Create reports directory if it doesn't exist
        $reportsDir = $_SERVER['DOCUMENT_ROOT'] . '/reports/pdf/';
        if (!is_dir($reportsDir)) {
            mkdir($reportsDir, 0755, true);
        }
        
        // Generate PDF filename
        $pdfFileName = 'report_' . $reportId . '_' . date('Y-m-d_H-i-s') . '.pdf';
        $pdfPath = $reportsDir . $pdfFileName;
        
        // Check if HTML report exists
        if (!$report['file_path'] || !file_exists($report['file_path'])) {
            throw new Exception('Source report file not found', 404);
        }
        
        // Read HTML content
        $htmlContent = file_get_contents($report['file_path']);
        if ($htmlContent === false) {
            throw new Exception('Failed to read report file', 500);
        }
        
        // Generate PDF
        $success = $pdfGenerator->generatePDF($htmlContent, $pdfPath, $branding);
        
        if (!$success || !file_exists($pdfPath)) {
            throw new Exception('Failed to generate PDF', 500);
        }
        
        // Cache the PDF
        $cachedPath = $pdfGenerator->cachePDF($reportId, $pdfPath);
        if ($cachedPath) {
            $pdfPath = $cachedPath;
        }
        
        // Update report record with PDF info
        $db = Database::getInstance();
        $stmt = $db->prepare("
            UPDATE generated_reports 
            SET pdf_generated = 1, pdf_generated_at = NOW(), pdf_file_size = ?
            WHERE id = ?
        ");
        $stmt->execute([filesize($pdfPath), $reportId]);
        
        // Log the PDF generation
        $stmt = $db->prepare("
            INSERT INTO user_actions (user_id, client_id, action_type, description, ip_address) 
            VALUES (?, ?, 'pdf_export', ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'] ?? null,
            $clientId,
            "Generated PDF for report: {$report['report_name']}",
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    }
    
    // If download is requested, stream the PDF
    if ($download) {
        // Clear any output that might interfere
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Get file info
        $fileSize = filesize($pdfPath);
        $fileName = preg_replace('/[^a-zA-Z0-9\-_\.\s]/', '', $report['report_name']);
        $fileName = preg_replace('/\s+/', '_', $fileName) . '.pdf';
        
        // Set headers for PDF download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . $fileSize);
        header('Cache-Control: private, must-revalidate');
        header('Pragma: private');
        header('Expires: 0');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        
        // Stream the file
        $handle = fopen($pdfPath, 'rb');
        if ($handle === false) {
            throw new Exception('Could not open PDF file for reading', 500);
        }
        
        // Stream in chunks
        $chunkSize = 8192;
        while (!feof($handle)) {
            $chunk = fread($handle, $chunkSize);
            if ($chunk === false) {
                break;
            }
            echo $chunk;
            
            if (ob_get_level()) {
                ob_flush();
            }
            flush();
            
            if (connection_aborted()) {
                break;
            }
        }
        
        fclose($handle);
        
        // Update download count
        $stmt = $db->prepare("
            UPDATE generated_reports 
            SET pdf_download_count = pdf_download_count + 1, 
                last_pdf_downloaded = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$reportId]);
        
        exit;
    }
    
    // Return PDF info as JSON
    $response = [
        'success' => true,
        'pdf_generated' => true,
        'from_cache' => $fromCache,
        'file_size' => filesize($pdfPath),
        'file_size_formatted' => formatFileSize(filesize($pdfPath)),
        'download_url' => '/api/reports/export-pdf.php?report_id=' . $reportId . '&download=1',
        'library_info' => $pdfGenerator->getLibraryInfo(),
        'generated_at' => date('c'),
        'report_name' => $report['report_name']
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    http_response_code($code);
    
    error_log("PDF Export API error: " . $e->getMessage());
    
    // Clear any output that might have been sent
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'error_code' => $code,
        'pdf_generated' => false
    ]);
}

/**
 * Format file size for display
 */
function formatFileSize($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>