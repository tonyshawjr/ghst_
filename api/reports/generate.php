<?php
/**
 * Generate Report API Endpoint
 * POST /api/reports/generate.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../../includes/auth_check.php';
require_once '../../includes/Database.php';
require_once '../../includes/ReportGenerator.php';
require_once '../../includes/RateLimiter.php';

try {
    // Check request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed', 405);
    }
    
    // Get current client
    $clientId = $_SESSION['current_client_id'] ?? null;
    if (!$clientId) {
        throw new Exception('No client selected', 400);
    }
    
    // Rate limiting
    $rateLimiter = new RateLimiter();
    $userKey = 'report_generation_' . ($_SESSION['user_id'] ?? 'anonymous');
    
    if (!$rateLimiter->checkLimit($userKey, 10, 3600)) { // 10 reports per hour
        throw new Exception('Rate limit exceeded. Please wait before generating another report.', 429);
    }
    
    // Get input data
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        // Fallback to POST data
        $input = $_POST;
    }
    
    // Validate required fields
    $requiredFields = ['report_type', 'date_from', 'date_to'];
    foreach ($requiredFields as $field) {
        if (empty($input[$field])) {
            throw new Exception("Missing required field: {$field}", 400);
        }
    }
    
    // Validate date range
    $dateFrom = $input['date_from'];
    $dateTo = $input['date_to'];
    
    if (!strtotime($dateFrom) || !strtotime($dateTo)) {
        throw new Exception('Invalid date format', 400);
    }
    
    if (strtotime($dateFrom) > strtotime($dateTo)) {
        throw new Exception('From date must be before to date', 400);
    }
    
    // Check date range limit (max 1 year)
    $daysDiff = (strtotime($dateTo) - strtotime($dateFrom)) / (60 * 60 * 24);
    if ($daysDiff > 365) {
        throw new Exception('Date range cannot exceed 365 days', 400);
    }
    
    // Validate report type
    $reportGenerator = new ReportGenerator();
    $validTypes = array_keys($reportGenerator->getReportTypes());
    
    if (!in_array($input['report_type'], $validTypes)) {
        throw new Exception('Invalid report type', 400);
    }
    
    // Prepare options
    $options = [
        'report_type' => $input['report_type'],
        'date_from' => $dateFrom,
        'date_to' => $dateTo,
        'template_id' => $input['template_id'] ?? null,
        'report_name' => $input['report_name'] ?? null,
        'format' => $input['format'] ?? 'html',
        'include_branding' => $input['include_branding'] ?? true,
        'sections' => $input['sections'] ?? null,
        'metrics' => $input['metrics'] ?? null
    ];
    
    // Validate template if provided
    if ($options['template_id']) {
        $templates = $reportGenerator->getReportTemplates($clientId);
        $validTemplate = false;
        foreach ($templates as $template) {
            if ($template['id'] == $options['template_id']) {
                $validTemplate = true;
                break;
            }
        }
        
        if (!$validTemplate) {
            throw new Exception('Invalid template ID', 400);
        }
    }
    
    // Start generation
    $startTime = microtime(true);
    $result = $reportGenerator->generateReport($clientId, $options);
    $generationTime = microtime(true) - $startTime;
    
    if ($result['success']) {
        // Update rate limiter
        $rateLimiter->increment($userKey, 3600);
        
        // Log successful generation
        error_log("Report generated successfully: {$result['report_id']} in {$generationTime}s");
        
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Report generated successfully',
            'data' => [
                'report_id' => $result['report_id'],
                'report_name' => $result['report_name'],
                'file_path' => $result['file_path'],
                'generation_time' => round($generationTime, 3)
            ]
        ]);
    } else {
        // Log error but don't increment rate limiter
        error_log("Report generation failed: " . $result['error']);
        
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Report generation failed',
            'error' => $result['error']
        ]);
    }
    
} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    http_response_code($code);
    
    error_log("Report generation API error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => $code
    ]);
}