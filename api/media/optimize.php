<?php
/**
 * Media Optimization API
 * Optimizes media files for specific platforms
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
$platform = strtolower($_POST['platform'] ?? '');

if (!$mediaId) {
    http_response_code(400);
    echo json_encode(['error' => 'Media ID required']);
    exit;
}

// Validate platform
$supportedPlatforms = ['instagram', 'facebook', 'twitter', 'linkedin'];
if (!in_array($platform, $supportedPlatforms)) {
    http_response_code(400);
    echo json_encode(['error' => 'Unsupported platform']);
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
    
    // Check if this is an image or video
    $mimeType = $media['mime_type'];
    $isVideo = strpos($mimeType, 'video/') === 0;
    
    if ($isVideo) {
        // For now, we'll just return the original for videos
        // Full video compression would require FFmpeg
        echo json_encode([
            'success' => true,
            'message' => 'Video optimization not yet implemented',
            'file' => str_replace(ROOT_PATH, '', $sourcePath),
            'optimized' => false
        ]);
    } else {
        // Get platform requirements
        $requirements = $processor->getPlatformRequirements()[$platform]['image'] ?? null;
        
        if (!$requirements) {
            http_response_code(400);
            echo json_encode(['error' => 'No requirements found for platform']);
            exit;
        }
        
        // Generate output path
        $uploadPath = dirname($sourcePath);
        $filename = basename($sourcePath);
        $platformPath = $uploadPath . '/platforms/' . $platform;
        
        if (!is_dir($platformPath)) {
            mkdir($platformPath, 0755, true);
        }
        
        $outputFile = $platformPath . '/' . $filename;
        
        // Create platform-specific version
        $result = $processor->createPlatformVersionPublic($sourcePath, $outputFile, $platform, $requirements);
        
        if ($result['success']) {
            // Update platform versions in database
            $platformVersions = json_decode($media['platform_versions'] ?? '{}', true);
            $platformVersions[$platform] = [
                'path' => $result['file'],
                'size' => filesize($result['file']),
                'optimized' => true,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $stmt = $db->prepare("UPDATE media SET platform_versions = ? WHERE id = ?");
            $stmt->execute([json_encode($platformVersions), $mediaId]);
            
            echo json_encode([
                'success' => true,
                'file' => str_replace(ROOT_PATH, '', $result['file']),
                'size' => filesize($result['file']),
                'platform' => $platform,
                'optimized' => true
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => $result['error'] ?? 'Optimization failed']);
        }
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}