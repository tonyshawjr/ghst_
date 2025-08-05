<?php
/**
 * List Reports API Endpoint
 * GET /api/reports/list.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../../includes/auth_check.php';
require_once '../../includes/Database.php';
require_once '../../includes/ReportGenerator.php';

try {
    // Check request method
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Method not allowed', 405);
    }
    
    // Get current client
    $clientId = $_SESSION['current_client_id'] ?? null;
    if (!$clientId) {
        throw new Exception('No client selected', 400);
    }
    
    $reportGenerator = new ReportGenerator();
    
    // Get filters from query parameters
    $filters = [];
    
    if (!empty($_GET['status'])) {
        $validStatuses = ['generating', 'completed', 'failed', 'expired'];
        if (in_array($_GET['status'], $validStatuses)) {
            $filters['status'] = $_GET['status'];
        }
    }
    
    if (!empty($_GET['report_type'])) {
        $validTypes = array_keys($reportGenerator->getReportTypes());
        if (in_array($_GET['report_type'], $validTypes)) {
            $filters['report_type'] = $_GET['report_type'];
        }
    }
    
    if (!empty($_GET['date_from'])) {
        if (strtotime($_GET['date_from'])) {
            $filters['date_from'] = $_GET['date_from'];
        }
    }
    
    if (!empty($_GET['date_to'])) {
        if (strtotime($_GET['date_to'])) {
            $filters['date_to'] = $_GET['date_to'];
        }
    }
    
    // Pagination
    $page = max(1, intval($_GET['page'] ?? 1));
    $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    
    // Get total count for pagination
    $totalReports = count($reportGenerator->getReportsList($clientId, $filters));
    $totalPages = ceil($totalReports / $limit);
    
    // Get reports
    $reports = $reportGenerator->getReportsList($clientId, $filters);
    
    // Apply pagination manually (could be optimized in ReportGenerator)
    $paginatedReports = array_slice($reports, $offset, $limit);
    
    // Format response data
    $formattedReports = array_map(function($report) {
        // Calculate file size in human readable format
        $fileSize = null;
        if ($report['file_size']) {
            if ($report['file_size'] < 1024) {
                $fileSize = $report['file_size'] . ' B';
            } elseif ($report['file_size'] < 1024 * 1024) {
                $fileSize = round($report['file_size'] / 1024, 1) . ' KB';
            } else {
                $fileSize = round($report['file_size'] / (1024 * 1024), 1) . ' MB';
            }
        }
        
        // Format dates
        $createdAt = date('c', strtotime($report['created_at'])); // ISO 8601
        $dateFrom = date('Y-m-d', strtotime($report['date_from']));
        $dateTo = date('Y-m-d', strtotime($report['date_to']));
        
        return [
            'id' => (int)$report['id'],
            'report_name' => $report['report_name'],
            'report_type' => $report['report_type'],
            'status' => $report['status'],
            'date_range' => [
                'from' => $dateFrom,
                'to' => $dateTo,
                'formatted' => date('M j, Y', strtotime($dateFrom)) . ' - ' . date('M j, Y', strtotime($dateTo))
            ],
            'file_size' => $fileSize,
            'file_size_bytes' => (int)$report['file_size'],
            'generation_time' => $report['generation_time'] ? (float)$report['generation_time'] : null,
            'data_points' => (int)$report['data_points'],
            'download_count' => (int)$report['download_count'],
            'last_downloaded' => $report['last_downloaded'] ? date('c', strtotime($report['last_downloaded'])) : null,
            'created_at' => $createdAt,
            'generated_by' => $report['generated_by_name'],
            'error_message' => $report['error_message'],
            'has_file' => !empty($report['file_path']) && file_exists($report['file_path']),
            'can_download' => $report['status'] === 'completed' && !empty($report['file_path']) && file_exists($report['file_path'])
        ];
    }, $paginatedReports);
    
    // Get summary statistics
    $stats = [
        'total_reports' => $totalReports,
        'completed_reports' => count(array_filter($reports, function($r) { return $r['status'] === 'completed'; })),
        'generating_reports' => count(array_filter($reports, function($r) { return $r['status'] === 'generating'; })),
        'failed_reports' => count(array_filter($reports, function($r) { return $r['status'] === 'failed'; })),
        'total_downloads' => array_sum(array_column($reports, 'download_count')),
        'this_month' => count(array_filter($reports, function($r) { 
            return date('Y-m', strtotime($r['created_at'])) === date('Y-m'); 
        }))
    ];
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'data' => [
            'reports' => $formattedReports,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $totalReports,
                'total_pages' => $totalPages,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1
            ],
            'filters' => $filters,
            'statistics' => $stats
        ]
    ]);
    
} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    http_response_code($code);
    
    error_log("List reports API error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error_code' => $code
    ]);
}