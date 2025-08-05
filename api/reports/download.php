<?php
/**
 * Download Report API Endpoint
 * GET /api/reports/download.php?id={report_id}
 */

require_once '../../includes/auth_check.php';
require_once '../../includes/Database.php';
require_once '../../includes/ReportGenerator.php';

try {
    // Check request method
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Method not allowed', 405);
    }
    
    // Get report ID
    $reportId = $_GET['id'] ?? null;
    if (!$reportId || !is_numeric($reportId)) {
        throw new Exception('Invalid report ID', 400);
    }
    
    $reportGenerator = new ReportGenerator();
    $report = $reportGenerator->getReport($reportId);
    
    if (!$report) {
        throw new Exception('Report not found', 404);
    }
    
    // Check if user has access to this report
    $clientId = $_SESSION['current_client_id'] ?? null;
    if ($report['client_id'] != $clientId) {
        throw new Exception('Access denied', 403);
    }
    
    // Check if report is completed
    if ($report['status'] !== 'completed') {
        throw new Exception('Report is not ready for download', 400);
    }
    
    // Check for PDF download preference
    $preferPdf = $_GET['format'] === 'pdf' || $_GET['pdf'] === '1';
    $pdfPath = null;
    
    if ($preferPdf) {
        // Check for cached PDF or generate one
        require_once '../../includes/PDFGenerator.php';
        $pdfGenerator = new PDFGenerator();
        $pdfPath = $pdfGenerator->getCachedPDF($reportId);
        
        if (!$pdfPath && $report['file_path'] && file_exists($report['file_path'])) {
            // Generate PDF on demand
            $pdfDir = dirname($report['file_path']) . '/pdf/';
            if (!is_dir($pdfDir)) {
                mkdir($pdfDir, 0755, true);
            }
            
            $pdfPath = $pdfDir . 'report_' . $reportId . '_' . date('Y-m-d_H-i-s') . '.pdf';
            $htmlContent = file_get_contents($report['file_path']);
            
            if ($htmlContent) {
                require_once '../../includes/BrandingHelper.php';
                $brandingHelper = BrandingHelper::getInstance();
                $branding = $brandingHelper->getBranding($clientId);
                
                try {
                    $pdfGenerator->generatePDF($htmlContent, $pdfPath, $branding);
                    if (file_exists($pdfPath)) {
                        $pdfGenerator->cachePDF($reportId, $pdfPath);
                    }
                } catch (Exception $e) {
                    error_log('On-demand PDF generation failed: ' . $e->getMessage());
                    $pdfPath = null;
                }
            }
        }
    }
    
    // Determine file path (PDF or HTML)
    $filePath = $pdfPath && file_exists($pdfPath) ? $pdfPath : $report['file_path'];
    
    // Check if file exists
    if (!$filePath || !file_exists($filePath)) {
        throw new Exception('Report file not found', 404);
    }
    
    // Update download count
    $db = Database::getInstance();
    if ($extension === 'pdf') {
        $stmt = $db->prepare("
            UPDATE generated_reports 
            SET pdf_download_count = pdf_download_count + 1, 
                last_pdf_downloaded = NOW() 
            WHERE id = ?
        ");
    } else {
        $stmt = $db->prepare("
            UPDATE generated_reports 
            SET download_count = download_count + 1, 
                last_downloaded = NOW() 
            WHERE id = ?
        ");
    }
    $stmt->execute([$reportId]);
    
    // Get file info
    $fileName = $report['report_name'];
    $fileSize = filesize($filePath);
    $extension = pathinfo($filePath, PATHINFO_EXTENSION);
    
    // Clean filename for download
    $cleanFileName = preg_replace('/[^a-zA-Z0-9\-_\.\s]/', '', $fileName);
    $cleanFileName = preg_replace('/\s+/', '_', $cleanFileName);
    
    // Add extension if not present
    if (!pathinfo($cleanFileName, PATHINFO_EXTENSION)) {
        $cleanFileName .= '.' . $extension;
    }
    
    // Determine content type
    $contentType = 'application/octet-stream';
    switch (strtolower($extension)) {
        case 'pdf':
            $contentType = 'application/pdf';
            break;
        case 'html':
        case 'htm':
            $contentType = 'text/html';
            break;
        case 'csv':
            $contentType = 'text/csv';
            break;
        case 'xlsx':
            $contentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            break;
        case 'docx':
            $contentType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
            break;
    }
    
    // Set headers for download
    header('Content-Type: ' . $contentType);
    header('Content-Disposition: attachment; filename="' . $cleanFileName . '"');
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: private, must-revalidate');
    header('Pragma: private');
    header('Expires: 0');
    
    // Additional headers for security
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    
    // Clear output buffer to prevent corruption
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Output file
    $handle = fopen($filePath, 'rb');
    if ($handle === false) {
        throw new Exception('Could not open file for reading', 500);
    }
    
    // Stream the file in chunks to handle large files
    $chunkSize = 8192; // 8KB chunks
    while (!feof($handle)) {
        $chunk = fread($handle, $chunkSize);
        if ($chunk === false) {
            break;
        }
        echo $chunk;
        
        // Flush output to browser
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
        
        // Check if client disconnected
        if (connection_aborted()) {
            break;
        }
    }
    
    fclose($handle);
    
    // Log the download
    error_log("Report {$reportId} downloaded by user " . ($_SESSION['user_id'] ?? 'unknown'));
    
    // Log user action
    if (isset($_SESSION['user_id'])) {
        $stmt = $db->prepare("
            INSERT INTO user_actions (user_id, client_id, action_type, description, ip_address) 
            VALUES (?, ?, 'report_download', ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $clientId,
            "Downloaded report: {$report['report_name']}",
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    }
    
} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    http_response_code($code);
    
    error_log("Download report API error: " . $e->getMessage());
    
    // Clear any output that might have been sent
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Send JSON error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => $code
    ]);
}