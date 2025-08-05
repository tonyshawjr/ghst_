<?php
/**
 * Background PDF Generation Service
 * Processes PDF generation in the background for large reports
 */

require_once '../../includes/auth_check.php';
require_once '../../includes/Database.php';
require_once '../../includes/ReportGenerator.php';
require_once '../../includes/PDFGenerator.php';
require_once '../../includes/BrandingHelper.php';

header('Content-Type: application/json');

try {
    // Check request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed', 405);
    }
    
    // Get request data
    $input = json_decode(file_get_contents('php://input'), true);
    $reportId = $input['report_id'] ?? null;
    $pdfOptions = $input['pdf_options'] ?? [];
    $priority = $input['priority'] ?? 'normal'; // low, normal, high
    
    if (!$reportId || !is_numeric($reportId)) {
        throw new Exception('Invalid report ID', 400);
    }
    
    // Initialize classes
    $db = Database::getInstance();
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
        throw new Exception('Report is not ready for PDF generation', 400);
    }
    
    // Check if PDF generation is already in progress
    $stmt = $db->prepare("
        SELECT * FROM pdf_generation_queue 
        WHERE report_id = ? AND status IN ('pending', 'processing')
    ");
    $stmt->execute([$reportId]);
    $existingJob = $stmt->fetch();
    
    if ($existingJob) {
        echo json_encode([
            'success' => true,
            'message' => 'PDF generation already in progress',
            'job_id' => $existingJob['id'],
            'status' => $existingJob['status'],
            'created_at' => $existingJob['created_at']
        ]);
        exit;
    }
    
    // Create PDF generation job
    $jobId = createPDFGenerationJob($db, $reportId, $clientId, $pdfOptions, $priority);
    
    // Attempt immediate processing for high priority jobs
    if ($priority === 'high') {
        $processResult = processImmediatePDF($jobId, $report, $pdfOptions);
        if ($processResult['success']) {
            echo json_encode([
                'success' => true,
                'job_id' => $jobId,
                'status' => 'completed',
                'pdf_ready' => true,
                'download_url' => '/api/reports/export-pdf.php?report_id=' . $reportId . '&download=1',
                'processing_time' => $processResult['processing_time']
            ]);
            exit;
        }
    }
    
    // Queue for background processing
    echo json_encode([
        'success' => true,
        'job_id' => $jobId,
        'status' => 'queued',
        'message' => 'PDF generation queued for background processing',
        'estimated_completion' => estimateCompletionTime($priority),
        'check_status_url' => '/api/reports/pdf-status.php?job_id=' . $jobId
    ]);
    
} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    http_response_code($code);
    
    error_log("Background PDF API error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'error_code' => $code
    ]);
}

/**
 * Create PDF generation job in queue
 */
function createPDFGenerationJob($db, $reportId, $clientId, $pdfOptions, $priority) {
    $stmt = $db->prepare("
        INSERT INTO pdf_generation_queue (
            report_id, client_id, pdf_options, priority, status, 
            created_at, created_by
        ) VALUES (?, ?, ?, ?, 'pending', NOW(), ?)
    ");
    
    $stmt->execute([
        $reportId,
        $clientId,
        json_encode($pdfOptions),
        $priority,
        $_SESSION['user_id'] ?? null
    ]);
    
    return $db->lastInsertId();
}

/**
 * Process PDF immediately for high priority jobs
 */
function processImmediatePDF($jobId, $report, $pdfOptions) {
    $startTime = microtime(true);
    
    try {
        $db = Database::getInstance();
        
        // Update job status
        $stmt = $db->prepare("
            UPDATE pdf_generation_queue 
            SET status = 'processing', started_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$jobId]);
        
        // Generate PDF
        $brandingHelper = BrandingHelper::getInstance();
        $branding = $brandingHelper->getBranding($report['client_id']);
        
        $defaultOptions = [
            'orientation' => 'portrait',
            'page_size' => 'A4',
            'margin_top' => 20,
            'margin_bottom' => 20,
            'margin_left' => 15,
            'margin_right' => 15,
            'default_font' => 'helvetica',
            'default_font_size' => 11
        ];
        
        $pdfOptions = array_merge($defaultOptions, $pdfOptions);
        $pdfGenerator = new PDFGenerator('auto', $pdfOptions);
        
        // Create PDF directory
        $pdfDir = $_SERVER['DOCUMENT_ROOT'] . '/reports/pdf/';
        if (!is_dir($pdfDir)) {
            mkdir($pdfDir, 0755, true);
        }
        
        $pdfPath = $pdfDir . 'report_' . $report['id'] . '_' . date('Y-m-d_H-i-s') . '.pdf';
        
        // Read HTML content
        $htmlContent = file_get_contents($report['file_path']);
        if ($htmlContent === false) {
            throw new Exception('Failed to read report file');
        }
        
        // Generate PDF
        $success = $pdfGenerator->generatePDF($htmlContent, $pdfPath, $branding);
        
        if (!$success || !file_exists($pdfPath)) {
            throw new Exception('PDF generation failed');
        }
        
        // Cache the PDF
        $cachedPath = $pdfGenerator->cachePDF($report['id'], $pdfPath);
        
        $processingTime = microtime(true) - $startTime;
        
        // Update job status
        $stmt = $db->prepare("
            UPDATE pdf_generation_queue 
            SET status = 'completed', completed_at = NOW(), 
                pdf_path = ?, processing_time = ?, file_size = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $cachedPath ?: $pdfPath,
            round($processingTime, 2),
            filesize($pdfPath),
            $jobId
        ]);
        
        // Update report record
        $stmt = $db->prepare("
            UPDATE generated_reports 
            SET pdf_generated = 1, pdf_generated_at = NOW(), pdf_file_size = ?
            WHERE id = ?
        ");
        $stmt->execute([filesize($pdfPath), $report['id']]);
        
        return [
            'success' => true,
            'processing_time' => round($processingTime, 2),
            'pdf_path' => $cachedPath ?: $pdfPath
        ];
        
    } catch (Exception $e) {
        // Update job status with error
        $stmt = $db->prepare("
            UPDATE pdf_generation_queue 
            SET status = 'failed', completed_at = NOW(), error_message = ?
            WHERE id = ?
        ");
        $stmt->execute([$e->getMessage(), $jobId]);
        
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Estimate completion time based on priority and queue length
 */
function estimateCompletionTime($priority) {
    $db = Database::getInstance();
    
    // Count pending jobs ahead in queue
    $stmt = $db->prepare("
        SELECT COUNT(*) as queue_length
        FROM pdf_generation_queue 
        WHERE status = 'pending' AND (
            priority = 'high' OR 
            (priority = 'normal' AND ? != 'low') OR
            (priority = 'low' AND ? = 'low')
        )
    ");
    $stmt->execute([$priority, $priority]);
    $result = $stmt->fetch();
    
    $queueLength = $result['queue_length'] ?? 0;
    
    // Estimate processing time per job (in minutes)
    $avgProcessingTime = 2; // 2 minutes average
    
    $estimatedMinutes = $queueLength * $avgProcessingTime;
    
    if ($estimatedMinutes < 1) {
        return 'Within 1 minute';
    } elseif ($estimatedMinutes < 60) {
        return "Approximately {$estimatedMinutes} minutes";
    } else {
        $hours = round($estimatedMinutes / 60, 1);
        return "Approximately {$hours} hours";
    }
}
?>