<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/functions.php';
require_once '../includes/CampaignPDFExporter.php';

$auth = new Auth();
$auth->requireLogin();
requireClient();

$client = $auth->getCurrentClient();

$campaignId = intval($_GET['id'] ?? 0);
$format = $_GET['format'] ?? 'pdf';

if (!$campaignId) {
    jsonResponse(['error' => 'Campaign ID required'], 400);
}

$db = Database::getInstance();

// Verify campaign belongs to client
$stmt = $db->prepare("SELECT * FROM strategy_campaigns WHERE id = ? AND client_id = ?");
$stmt->execute([$campaignId, $client['id']]);
$campaign = $stmt->fetch();

if (!$campaign) {
    jsonResponse(['error' => 'Campaign not found'], 404);
}

try {
    switch ($format) {
        case 'pdf':
            exportToPDF($campaignId, $client['id']);
            break;
        case 'json':
            exportToJSON($campaignId, $client['id']);
            break;
        case 'csv':
            exportToCSV($campaignId, $client['id']);
            break;
        default:
            jsonResponse(['error' => 'Invalid export format'], 400);
    }
    
} catch (Exception $e) {
    error_log("Export error: " . $e->getMessage());
    jsonResponse([
        'error' => 'Export failed',
        'details' => $e->getMessage()
    ], 500);
}

/**
 * Export campaign to PDF
 */
function exportToPDF($campaignId, $clientId) {
    $pdfExporter = new CampaignPDFExporter($campaignId, $clientId);
    
    $options = [
        'format' => $_GET['pdf_format'] ?? 'detailed',
        'include_analytics' => isset($_GET['include_analytics']),
        'include_schedules' => isset($_GET['include_schedules']),
        'include_content_samples' => isset($_GET['include_content_samples']),
        'password' => $_GET['password'] ?? null
    ];
    
    $result = $pdfExporter->exportToPDF($options);
    
    if ($result['success']) {
        // Set headers for file download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($result['filename']) . '"');
        header('Content-Length: ' . $result['filesize']);
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Output file
        readfile($result['filepath']);
        exit;
    } else {
        jsonResponse(['error' => $result['error']], 500);
    }
}

/**
 * Export campaign to JSON
 */
function exportToJSON($campaignId, $clientId) {
    require_once '../includes/CampaignStrategyEngine.php';
    
    $strategyEngine = new CampaignStrategyEngine($clientId, $campaignId);
    
    $options = [
        'include_analytics' => isset($_GET['include_analytics']),
        'include_sensitive_data' => isset($_GET['include_sensitive_data'])
    ];
    
    $exportData = $strategyEngine->exportToJSON($options);
    
    // Generate filename
    $db = Database::getInstance();
    $stmt = $db->prepare("
        SELECT sc.title, c.name as client_name 
        FROM strategy_campaigns sc 
        JOIN clients c ON sc.client_id = c.id 
        WHERE sc.id = ?
    ");
    $stmt->execute([$campaignId]);
    $campaign = $stmt->fetch();
    
    $filename = sanitizeFilename($campaign['client_name'] . '_' . $campaign['title'] . '_strategy_' . date('Y-m-d') . '.json');
    
    // Set headers for file download
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output JSON
    echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Export campaign to CSV
 */
function exportToCSV($campaignId, $clientId) {
    $db = Database::getInstance();
    
    // Get campaign data
    $stmt = $db->prepare("
        SELECT sc.title, sc.description, sc.total_weeks, sc.start_date,
               c.name as client_name
        FROM strategy_campaigns sc 
        JOIN clients c ON sc.client_id = c.id 
        WHERE sc.id = ?
    ");
    $stmt->execute([$campaignId]);
    $campaign = $stmt->fetch();
    
    // Get all posts across all weeks
    $stmt = $db->prepare("
        SELECT cw.week_number, cw.week_theme, cw.week_start_date, cw.week_end_date,
               cwp.platform, cwp.post_type, cwp.content, cwp.hashtags, 
               cwp.call_to_action, cwp.content_pillar, cwp.status,
               cwp.scheduled_datetime, cwp.post_order
        FROM campaign_weeks cw
        JOIN campaign_week_posts cwp ON cw.id = cwp.campaign_week_id
        WHERE cw.campaign_id = ?
        ORDER BY cw.week_number ASC, cwp.post_order ASC
    ");
    $stmt->execute([$campaignId]);
    $posts = $stmt->fetchAll();
    
    $filename = sanitizeFilename($campaign['client_name'] . '_' . $campaign['title'] . '_posts_' . date('Y-m-d') . '.csv');
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Output CSV
    $output = fopen('php://output', 'w');
    
    // Write header row
    $headers = [
        'Client', 'Campaign', 'Week Number', 'Week Theme', 'Week Start', 'Week End',
        'Post Order', 'Platform', 'Post Type', 'Content', 'Hashtags', 
        'Call to Action', 'Content Pillar', 'Status', 'Scheduled Date'
    ];
    fputcsv($output, $headers);
    
    // Write data rows
    foreach ($posts as $post) {
        $row = [
            $campaign['client_name'],
            $campaign['title'],
            $post['week_number'],
            $post['week_theme'],
            $post['week_start_date'],
            $post['week_end_date'],
            $post['post_order'],
            $post['platform'],
            $post['post_type'],
            $post['content'],
            $post['hashtags'],
            $post['call_to_action'],
            $post['content_pillar'],
            $post['status'],
            $post['scheduled_datetime']
        ];
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

/**
 * Sanitize filename for safe downloads
 */
function sanitizeFilename($filename) {
    // Remove or replace unsafe characters
    $filename = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $filename);
    // Remove multiple underscores
    $filename = preg_replace('/_+/', '_', $filename);
    // Trim underscores from start and end
    $filename = trim($filename, '_');
    
    return $filename;
}