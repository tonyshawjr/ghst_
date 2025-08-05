<?php
/**
 * Media Format Conversion API
 * Converts media files to different formats for platform compatibility
 */

require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Auth.php';
require_once '../../includes/MediaProcessor.php';

$auth = new Auth();
$auth->requireLogin();

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Validate CSRF token
if (!$auth->validateCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

// Get parameters
$mediaId = intval($_POST['media_id'] ?? 0);
$format = strtolower($_POST['format'] ?? '');
$platform = strtolower($_POST['platform'] ?? '');

if (!$mediaId) {
    http_response_code(400);
    echo json_encode(['error' => 'Media ID required']);
    exit;
}

// Validate format
$supportedFormats = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
if (!in_array($format, $supportedFormats)) {
    http_response_code(400);
    echo json_encode(['error' => 'Unsupported format']);
    exit;
}

// Get media file
$db = Database::getInstance();
$stmt = $db->prepare("SELECT * FROM media WHERE id = ? AND client_id = ?");
$stmt->execute([$mediaId, $auth->getCurrentClient()['id']]);
$media = $stmt->fetch();

if (!$media) {
    http_response_code(404);
    echo json_encode(['error' => 'Media not found']);
    exit;
}

// Check if source file exists
$sourcePath = $media['file_path'] ?? $media['file_url'];
if (!file_exists($sourcePath)) {
    http_response_code(404);
    echo json_encode(['error' => 'Source file not found']);
    exit;
}

try {
    $processor = new MediaProcessor();
    
    // Generate output path
    $uploadPath = dirname($sourcePath);
    $filename = pathinfo($sourcePath, PATHINFO_FILENAME);
    $outputPath = $uploadPath . '/converted/' . $platform;
    
    if (!is_dir($outputPath)) {
        mkdir($outputPath, 0755, true);
    }
    
    $outputFile = $outputPath . '/' . $filename . '.' . $format;
    
    // Convert format
    $result = $processor->convertFormat($sourcePath, $outputFile, $format);
    
    if ($result['success']) {
        // Update platform versions in database
        $platformVersions = json_decode($media['platform_versions'] ?? '{}', true);
        $platformVersions[$platform] = [
            'format' => $format,
            'path' => $outputFile,
            'size' => filesize($outputFile),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $stmt = $db->prepare("UPDATE media SET platform_versions = ? WHERE id = ?");
        $stmt->execute([json_encode($platformVersions), $mediaId]);
        
        echo json_encode([
            'success' => true,
            'file' => str_replace(ROOT_PATH, '', $outputFile),
            'size' => filesize($outputFile),
            'format' => $format
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['error' => $result['error'] ?? 'Conversion failed']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}