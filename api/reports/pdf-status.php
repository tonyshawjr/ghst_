<?php
/**
 * PDF Generation Status Checker
 * GET /api/reports/pdf-status.php?job_id={job_id}
 */

require_once '../../includes/auth_check.php';
require_once '../../includes/Database.php';

header('Content-Type: application/json');

try {
    // Check request method
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Method not allowed', 405);
    }
    
    // Get job ID
    $jobId = $_GET['job_id'] ?? null;
    if (!$jobId || !is_numeric($jobId)) {
        throw new Exception('Invalid job ID', 400);
    }
    
    $db = Database::getInstance();
    
    // Get job details
    $stmt = $db->prepare("
        SELECT 
            pq.*,
            gr.report_name,
            gr.client_id
        FROM pdf_generation_queue pq
        JOIN generated_reports gr ON pq.report_id = gr.id
        WHERE pq.id = ?
    ");
    $stmt->execute([$jobId]);
    $job = $stmt->fetch();
    
    if (!$job) {
        throw new Exception('Job not found', 404);
    }
    
    // Check access permissions
    $clientId = $_SESSION['current_client_id'] ?? null;
    if ($job['client_id'] != $clientId) {
        throw new Exception('Access denied', 403);
    }
    
    // Prepare response based on job status
    $response = [
        'success' => true,
        'job_id' => $job['id'],
        'report_id' => $job['report_id'],
        'report_name' => $job['report_name'],
        'status' => $job['status'],
        'priority' => $job['priority'],
        'created_at' => $job['created_at'],
        'started_at' => $job['started_at'],
        'completed_at' => $job['completed_at'],
        'processing_time' => $job['processing_time'],
        'file_size' => $job['file_size'],
        'error_message' => $job['error_message']
    ];
    
    // Add status-specific information
    switch ($job['status']) {
        case 'pending':
            // Calculate queue position
            $stmt = $db->prepare("
                SELECT COUNT(*) as position
                FROM pdf_generation_queue 
                WHERE status = 'pending' 
                AND created_at < ? 
                AND (
                    priority = 'high' OR 
                    (priority = 'normal' AND ? != 'low') OR
                    (priority = 'low' AND ? = 'low')
                )
            ");
            $stmt->execute([$job['created_at'], $job['priority'], $job['priority']]);
            $positionResult = $stmt->fetch();
            
            $response['queue_position'] = ($positionResult['position'] ?? 0) + 1;
            $response['estimated_start'] = estimateStartTime($response['queue_position']);
            break;
            
        case 'processing':
            $response['message'] = 'PDF generation in progress...';
            if ($job['started_at']) {
                $startTime = strtotime($job['started_at']);
                $elapsed = time() - $startTime;
                $response['elapsed_time'] = $elapsed;
                $response['elapsed_formatted'] = formatDuration($elapsed);
            }
            break;
            
        case 'completed':
            $response['pdf_ready'] = true;
            $response['download_url'] = '/api/reports/export-pdf.php?report_id=' . $job['report_id'] . '&download=1';
            $response['file_size_formatted'] = formatFileSize($job['file_size']);
            $response['processing_time_formatted'] = $job['processing_time'] . ' seconds';
            break;
            
        case 'failed':
            $response['pdf_ready'] = false;
            $response['retry_url'] = '/api/reports/background-pdf.php';
            break;
    }
    
    // Add progress percentage
    $response['progress'] = calculateProgress($job['status'], $job['started_at']);
    
    echo json_encode($response);
    
} catch (Exception $e) {
    $code = $e->getCode() ?: 500;
    http_response_code($code);
    
    error_log("PDF Status API error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'error_code' => $code
    ]);
}

/**
 * Estimate start time based on queue position
 */
function estimateStartTime($queuePosition) {
    if ($queuePosition <= 1) {
        return 'Starting soon';
    }
    
    $avgProcessingTime = 120; // 2 minutes in seconds
    $estimatedSeconds = ($queuePosition - 1) * $avgProcessingTime;
    
    if ($estimatedSeconds < 60) {
        return 'Within 1 minute';
    } elseif ($estimatedSeconds < 3600) {
        $minutes = round($estimatedSeconds / 60);
        return "In approximately {$minutes} minutes";
    } else {
        $hours = round($estimatedSeconds / 3600, 1);
        return "In approximately {$hours} hours";
    }
}

/**
 * Calculate progress percentage
 */
function calculateProgress($status, $startedAt) {
    switch ($status) {
        case 'pending':
            return 0;
            
        case 'processing':
            if ($startedAt) {
                $startTime = strtotime($startedAt);
                $elapsed = time() - $startTime;
                $estimatedTotal = 120; // 2 minutes
                
                $progress = min(90, ($elapsed / $estimatedTotal) * 100);
                return round($progress);
            }
            return 25;
            
        case 'completed':
            return 100;
            
        case 'failed':
            return 0;
            
        default:
            return 0;
    }
}

/**
 * Format duration in seconds to human readable
 */
function formatDuration($seconds) {
    if ($seconds < 60) {
        return $seconds . ' seconds';
    } elseif ($seconds < 3600) {
        $minutes = floor($seconds / 60);
        $remainingSeconds = $seconds % 60;
        return $minutes . ' minutes' . ($remainingSeconds > 0 ? ', ' . $remainingSeconds . ' seconds' : '');
    } else {
        $hours = floor($seconds / 3600);
        $remainingMinutes = floor(($seconds % 3600) / 60);
        return $hours . ' hours' . ($remainingMinutes > 0 ? ', ' . $remainingMinutes . ' minutes' : '');
    }
}

/**
 * Format file size for display
 */
function formatFileSize($bytes) {
    if ($bytes === null) return 'Unknown';
    
    $units = array('B', 'KB', 'MB', 'GB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, 2) . ' ' . $units[$i];
}
?>