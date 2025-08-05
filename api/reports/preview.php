<?php
/**
 * Preview Report API Endpoint
 * GET /api/reports/preview.php?id={report_id}
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
        throw new Exception('Report is not ready for preview', 400);
    }
    
    // Check if file exists
    if (!$report['file_path'] || !file_exists($report['file_path'])) {
        throw new Exception('Report file not found', 404);
    }
    
    // Determine content type
    $extension = pathinfo($report['file_path'], PATHINFO_EXTENSION);
    $contentType = 'text/html';
    
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
        default:
            $contentType = 'application/octet-stream';
    }
    
    // Set headers for preview
    header('Content-Type: ' . $contentType);
    header('Content-Length: ' . filesize($report['file_path']));
    header('X-Report-ID: ' . $report['id']);
    header('X-Report-Name: ' . $report['report_name']);
    header('X-Report-Status: ' . $report['status']);
    
    // For HTML reports, we can add some additional headers
    if ($contentType === 'text/html') {
        header('X-Frame-Options: SAMEORIGIN');
        header('Content-Security-Policy: default-src \'self\' \'unsafe-inline\'; img-src \'self\' data: https:; font-src \'self\' https:');
    }
    
    // Output the file content
    readfile($report['file_path']);
    
    // Log the preview access (optional)
    error_log("Report {$reportId} previewed by user " . ($_SESSION['user_id'] ?? 'unknown'));
    
} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    http_response_code($code);
    
    error_log("Preview report API error: " . $e->getMessage());
    
    // For preview, we'll show a simple error page instead of JSON
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Report Preview Error</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background: #f5f5f5;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
            }
            .error-container {
                background: white;
                padding: 40px;
                border-radius: 12px;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                text-align: center;
                max-width: 500px;
            }
            .error-icon {
                font-size: 48px;
                color: #ef4444;
                margin-bottom: 20px;
            }
            .error-title {
                font-size: 24px;
                font-weight: bold;
                color: #374151;
                margin-bottom: 10px;
            }
            .error-message {
                color: #6b7280;
                margin-bottom: 30px;
                line-height: 1.5;
            }
            .error-code {
                font-size: 12px;
                color: #9ca3af;
                background: #f3f4f6;
                padding: 8px 12px;
                border-radius: 6px;
                display: inline-block;
            }
            .back-button {
                background: #8b5cf6;
                color: white;
                padding: 12px 24px;
                border: none;
                border-radius: 8px;
                text-decoration: none;
                display: inline-block;
                margin-top: 20px;
                cursor: pointer;
                transition: background-color 0.2s;
            }
            .back-button:hover {
                background: #7c3aed;
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">⚠️</div>
            <h1 class="error-title">Preview Not Available</h1>
            <p class="error-message"><?= htmlspecialchars($e->getMessage()) ?></p>
            <div class="error-code">Error Code: <?= $code ?></div>
            <button onclick="history.back()" class="back-button">← Go Back</button>
        </div>
        
        <script>
            // Auto-close if opened in new tab after 5 seconds
            if (window.opener) {
                setTimeout(() => {
                    window.close();
                }, 5000);
            }
        </script>
    </body>
    </html>
    <?php
}