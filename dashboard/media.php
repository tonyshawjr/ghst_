<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/functions.php';
require_once '../includes/layout.php';
require_once '../includes/MediaProcessor.php';

$auth = new Auth();
$auth->requireLogin();
requireClient();

$db = Database::getInstance();
$client = $auth->getCurrentClient();
$action = $_GET['action'] ?? 'list';

// Handle file uploads and actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Enable error reporting for debugging
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    if (!$auth->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 400);
    }
    
    if ($action === 'upload' && isset($_FILES['media'])) {
        $uploadPath = MEDIA_PATH . '/' . $client['id'];
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }
        
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov', 'avi', 'webp'];
        $maxSize = 100 * 1024 * 1024; // 100MB
        
        $uploadResult = uploadFile($_FILES['media'], $allowedTypes, $maxSize, $uploadPath);
        
        if ($uploadResult['success']) {
            // Get file info
            $filePath = $uploadResult['path'];
            $fileName = $uploadResult['filename'];
            $fileSize = filesize($filePath);
            $mimeType = mime_content_type($filePath);
            
            // Process media file
            try {
                $processor = new MediaProcessor();
                $processResult = $processor->processUploadedMedia($filePath, $client['id']);
                
                if ($processResult['success']) {
                    $thumbnailPath = $processResult['thumbnail'] ?? null;
                    $optimizedPath = $processResult['optimized'] ?? $filePath;
                    $platformVersions = $processResult['platform_versions'] ?? [];
                } else {
                    // If processing fails, continue with original file
                    $thumbnailPath = null;
                    $optimizedPath = $filePath;
                    $platformVersions = [];
                    error_log('Media processing failed: ' . ($processResult['error'] ?? 'Unknown error'));
                }
            } catch (Exception $e) {
                // If processing throws exception, continue with original file
                $thumbnailPath = null;
                $optimizedPath = $filePath;
                $platformVersions = [];
                error_log('Media processing exception: ' . $e->getMessage());
            }
            
            // Save to database
            // Determine file type based on mime type
            $fileType = 'document';
            if (strpos($mimeType, 'image/') === 0) {
                $fileType = 'image';
            } elseif (strpos($mimeType, 'video/') === 0) {
                $fileType = 'video';
            }
            
            $stmt = $db->prepare("
                INSERT INTO media (client_id, filename, original_filename, file_path, thumbnail_path, file_size, mime_type, type, uploaded_by, optimized_path, platform_versions, file_url, file_name)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $relativeUrl = str_replace(ROOT_PATH, '', $filePath);
            
            try {
                $stmt->execute([
                    $client['id'],
                    $fileName,
                    $_FILES['media']['name'],
                    $filePath,
                    $thumbnailPath,
                    $fileSize,
                    $mimeType,
                    $fileType,
                    $auth->getCurrentUser()['id'],
                    $optimizedPath ?? $filePath,
                    json_encode($platformVersions ?? []),
                    $relativeUrl,
                    $_FILES['media']['name']
                ]);
            } catch (PDOException $e) {
                error_log('Database error on media insert: ' . $e->getMessage());
                jsonResponse(['success' => false, 'error' => 'Database error: ' . $e->getMessage()], 500);
            }
            
            jsonResponse(['success' => true, 'message' => 'File uploaded successfully']);
        } else {
            error_log('Upload failed: ' . json_encode($uploadResult));
            jsonResponse(['success' => false, 'error' => $uploadResult['error']], 400);
        }
    }
    
    if ($action === 'delete' && isset($_POST['media_id'])) {
        // Get media file info
        $stmt = $db->prepare("SELECT * FROM media WHERE id = ? AND client_id = ?");
        $stmt->execute([$_POST['media_id'], $client['id']]);
        $media = $stmt->fetch();
        
        if ($media) {
            // Delete file from filesystem - try both possible path columns
            $filePath = $media['file_path'] ?? $media['file_url'];
            if ($filePath && file_exists($filePath)) {
                unlink($filePath);
            }
            
            $thumbnailPath = $media['thumbnail_path'] ?? $media['thumbnail_url'];
            if ($thumbnailPath && file_exists($thumbnailPath)) {
                unlink($thumbnailPath);
            }
            
            // Delete from database
            $stmt = $db->prepare("DELETE FROM media WHERE id = ? AND client_id = ?");
            $stmt->execute([$_POST['media_id'], $client['id']]);
            
            jsonResponse(['success' => true, 'message' => 'Media deleted successfully']);
        } else {
            jsonResponse(['success' => false, 'error' => 'Media not found'], 404);
        }
    }
}

// Get media files
$searchTerm = $_GET['search'] ?? '';
$mediaType = $_GET['type'] ?? 'all';

$whereClause = "WHERE client_id = ?";
$params = [$client['id']];

if (!empty($searchTerm)) {
    $whereClause .= " AND (original_filename LIKE ? OR filename LIKE ?)";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
}

if ($mediaType !== 'all') {
    if ($mediaType === 'image') {
        $whereClause .= " AND mime_type LIKE 'image/%'";
    } elseif ($mediaType === 'video') {
        $whereClause .= " AND mime_type LIKE 'video/%'";
    }
}

$stmt = $db->prepare("
    SELECT m.*, u.name as uploaded_by_name,
           COALESCE(m.original_filename, m.file_name) as display_filename
    FROM media m
    LEFT JOIN users u ON m.uploaded_by = u.id
    $whereClause
    ORDER BY m.created_at DESC
");
$stmt->execute($params);
$mediaFiles = $stmt->fetchAll();

// Get storage stats
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_files,
        COALESCE(SUM(file_size), 0) as total_size,
        COALESCE(SUM(CASE WHEN mime_type LIKE 'image/%' THEN 1 ELSE 0 END), 0) as image_count,
        COALESCE(SUM(CASE WHEN mime_type LIKE 'video/%' THEN 1 ELSE 0 END), 0) as video_count
    FROM media 
    WHERE client_id = ?
");
$stmt->execute([$client['id']]);
$stats = $stmt->fetch();

renderHeader('Media Library');
?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex justify-between items-center">
        <div>
            <h3 class="text-lg font-semibold text-gray-200">Media Library</h3>
            <p class="text-gray-400 text-sm mt-1">Manage your media files for social posts</p>
        </div>
        
        <button 
            onclick="showUploadModal()" 
            class="px-4 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg text-white font-medium transition-colors"
        >
            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
            </svg>
            Upload Media
        </button>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-gray-900 rounded-lg p-4 border border-gray-800">
            <div class="text-2xl font-bold text-purple-400"><?= number_format($stats['total_files'] ?? 0) ?></div>
            <div class="text-sm text-gray-400">Total Files</div>
        </div>
        <div class="bg-gray-900 rounded-lg p-4 border border-gray-800">
            <div class="text-2xl font-bold text-blue-400"><?= number_format($stats['image_count'] ?? 0) ?></div>
            <div class="text-sm text-gray-400">Images</div>
        </div>
        <div class="bg-gray-900 rounded-lg p-4 border border-gray-800">
            <div class="text-2xl font-bold text-green-400"><?= number_format($stats['video_count'] ?? 0) ?></div>
            <div class="text-sm text-gray-400">Videos</div>
        </div>
        <div class="bg-gray-900 rounded-lg p-4 border border-gray-800">
            <div class="text-2xl font-bold text-yellow-400"><?= formatBytes($stats['total_size'] ?? 0) ?></div>
            <div class="text-sm text-gray-400">Storage Used</div>
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="flex flex-col sm:flex-row gap-4">
        <div class="flex-1">
            <div class="relative">
                <svg class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                <input 
                    type="text" 
                    placeholder="Search media files..." 
                    value="<?= sanitize($searchTerm) ?>"
                    onkeyup="if(event.key==='Enter') filterMedia()"
                    class="w-full pl-10 pr-4 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                >
            </div>
        </div>
        
        <div class="flex space-x-2">
            <select 
                onchange="filterByType(this.value)"
                class="px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-purple-500"
            >
                <option value="all" <?= $mediaType === 'all' ? 'selected' : '' ?>>All Types</option>
                <option value="image" <?= $mediaType === 'image' ? 'selected' : '' ?>>Images</option>
                <option value="video" <?= $mediaType === 'video' ? 'selected' : '' ?>>Videos</option>
            </select>
        </div>
    </div>

    <!-- Media Grid -->
    <?php if (empty($mediaFiles)): ?>
        <div class="bg-gray-900 rounded-lg border border-gray-800 p-12 text-center">
            <svg class="w-16 h-16 mx-auto text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            <h3 class="text-xl font-semibold text-gray-300 mb-2">No Media Files</h3>
            <p class="text-gray-500 mb-6">Upload your first media file to get started</p>
            <button 
                onclick="showUploadModal()" 
                class="px-6 py-3 bg-purple-600 hover:bg-purple-700 rounded-lg text-white font-medium transition-colors"
            >
                Upload Media
            </button>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
            <?php foreach ($mediaFiles as $media): ?>
                <div class="bg-gray-900 rounded-lg border border-gray-800 overflow-hidden group hover:border-purple-500 transition-colors">
                    <div class="aspect-square bg-gray-800 flex items-center justify-center relative">
                        <?php if (strpos($media['mime_type'], 'image/') === 0): ?>
                            <?php if ($media['thumbnail_path'] && file_exists($media['thumbnail_path'])): ?>
                                <!-- Actual Thumbnail -->
                                <img src="<?= str_replace(ROOT_PATH, '', $media['thumbnail_path'] ?? '') ?>" 
                                     alt="<?= sanitize($media['display_filename']) ?>"
                                     class="w-full h-full object-cover">
                            <?php else: ?>
                                <!-- Image Preview Placeholder -->
                                <div class="w-full h-full bg-gradient-to-br from-purple-600 to-blue-600 flex items-center justify-center">
                                    <svg class="w-8 h-8 text-white opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if ($media['thumbnail_path'] && file_exists($media['thumbnail_path'])): ?>
                                <!-- Video Thumbnail -->
                                <img src="<?= str_replace(ROOT_PATH, '', $media['thumbnail_path'] ?? '') ?>" 
                                     alt="<?= sanitize($media['display_filename']) ?>"
                                     class="w-full h-full object-cover">
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <div class="bg-black bg-opacity-50 rounded-full p-3">
                                        <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M8 5v14l11-7z"/>
                                        </svg>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- Video Preview Placeholder -->
                                <div class="w-full h-full bg-gradient-to-br from-red-600 to-orange-600 flex items-center justify-center">
                                    <svg class="w-8 h-8 text-white opacity-70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <!-- Overlay Actions -->
                        <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-50 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all">
                            <div class="flex space-x-2">
                                <button 
                                    onclick="viewMedia(<?= $media['id'] ?>)"
                                    class="p-2 bg-purple-600 hover:bg-purple-700 rounded-full text-white transition-colors"
                                    title="View"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                </button>
                                <button 
                                    onclick="deleteMedia(<?= $media['id'] ?>, '<?= sanitize($media['display_filename']) ?>')"
                                    class="p-2 bg-red-600 hover:bg-red-700 rounded-full text-white transition-colors"
                                    title="Delete"
                                >
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-3">
                        <h4 class="text-sm font-medium text-white truncate" title="<?= sanitize($media['display_filename']) ?>">
                            <?= sanitize($media['display_filename']) ?>
                        </h4>
                        <div class="flex items-center justify-between mt-2 text-xs text-gray-400">
                            <span><?= formatBytes($media['file_size']) ?></span>
                            <span><?= getRelativeTime($media['created_at']) ?></span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Upload Modal -->
<div id="uploadModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-gray-900 rounded-lg border border-gray-800 p-6 max-w-md w-full mx-4">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold">Upload Media</h3>
            <button onclick="hideUploadModal()" class="text-gray-400 hover:text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <form id="uploadForm" onsubmit="uploadMedia(event)">
            <div 
                id="dropZone" 
                class="border-2 border-dashed border-gray-700 rounded-lg p-8 text-center hover:border-purple-500 transition-colors cursor-pointer"
                ondrop="handleDrop(event)" 
                ondragover="handleDragOver(event)"
                ondragleave="handleDragLeave(event)"
                onclick="document.getElementById('fileInput').click()"
            >
                <svg class="w-12 h-12 mx-auto text-gray-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                </svg>
                <h4 class="text-lg font-medium text-gray-300 mb-2">Drop files here or click to browse</h4>
                <p class="text-sm text-gray-500">Supports: JPG, PNG, GIF, MP4, MOV, AVI, WEBP</p>
                <p class="text-xs text-gray-600 mt-1">Max file size: 100MB</p>
                
                <input 
                    type="file" 
                    id="fileInput"
                    name="media"
                    accept="image/*,video/*"
                    class="hidden"
                    onchange="handleFileSelect(event)"
                >
            </div>
            
            <div id="filePreview" class="mt-4 hidden">
                <div class="flex items-center space-x-3 p-3 bg-gray-800 rounded-lg">
                    <div id="previewIcon" class="text-purple-500"></div>
                    <div class="flex-1">
                        <div id="fileName" class="text-sm font-medium"></div>
                        <div id="fileSize" class="text-xs text-gray-400"></div>
                    </div>
                    <button type="button" onclick="clearFile()" class="text-gray-400 hover:text-red-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3 mt-6">
                <button 
                    type="button" 
                    onclick="hideUploadModal()"
                    class="px-4 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-white transition-colors"
                >
                    Cancel
                </button>
                <button 
                    type="submit"
                    id="uploadBtn"
                    class="px-4 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg text-white transition-colors"
                    disabled
                >
                    Upload
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let selectedFile = null;

function showUploadModal() {
    document.getElementById('uploadModal').classList.remove('hidden');
    document.getElementById('uploadModal').classList.add('flex');
}

function hideUploadModal() {
    document.getElementById('uploadModal').classList.add('hidden');
    document.getElementById('uploadModal').classList.remove('flex');
    clearFile();
}

function handleDragOver(event) {
    event.preventDefault();
    event.currentTarget.classList.add('border-purple-500', 'bg-purple-900', 'bg-opacity-10');
}

function handleDragLeave(event) {
    event.preventDefault();
    event.currentTarget.classList.remove('border-purple-500', 'bg-purple-900', 'bg-opacity-10');
}

function handleDrop(event) {
    event.preventDefault();
    event.currentTarget.classList.remove('border-purple-500', 'bg-purple-900', 'bg-opacity-10');
    
    const files = event.dataTransfer.files;
    if (files.length > 0) {
        handleFile(files[0]);
    }
}

function handleFileSelect(event) {
    const files = event.target.files;
    if (files.length > 0) {
        handleFile(files[0]);
    }
}

function handleFile(file) {
    selectedFile = file;
    
    // Show preview
    const preview = document.getElementById('filePreview');
    const icon = document.getElementById('previewIcon');
    const fileName = document.getElementById('fileName');
    const fileSize = document.getElementById('fileSize');
    
    if (file.type.startsWith('image/')) {
        icon.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>';
    } else {
        icon.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>';
    }
    
    fileName.textContent = file.name;
    fileSize.textContent = formatBytes(file.size);
    
    preview.classList.remove('hidden');
    document.getElementById('uploadBtn').disabled = false;
}

function clearFile() {
    selectedFile = null;
    document.getElementById('fileInput').value = '';
    document.getElementById('filePreview').classList.add('hidden');
    document.getElementById('uploadBtn').disabled = true;
}

function uploadMedia(event) {
    event.preventDefault();
    
    if (!selectedFile) {
        alert('Please select a file');
        return;
    }
    
    const formData = new FormData();
    formData.append('media', selectedFile);
    formData.append('action', 'upload');
    formData.append('csrf_token', '<?= $auth->generateCSRFToken() ?>');
    
    const uploadBtn = document.getElementById('uploadBtn');
    uploadBtn.disabled = true;
    uploadBtn.textContent = 'Uploading...';
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            hideUploadModal();
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Upload failed'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Upload failed');
    })
    .finally(() => {
        uploadBtn.disabled = false;
        uploadBtn.textContent = 'Upload';
    });
}

function deleteMedia(mediaId, fileName) {
    if (!confirm(`Are you sure you want to delete "${fileName}"?\n\nThis action cannot be undone.`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('media_id', mediaId);
    formData.append('csrf_token', '<?= $auth->generateCSRFToken() ?>');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to delete media'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to delete media');
    });
}

function viewMedia(mediaId) {
    // TODO: Implement media viewer modal
    alert('Media viewer not yet implemented');
}

function filterMedia() {
    const searchTerm = document.querySelector('input[type="text"]').value;
    const currentUrl = new URL(window.location);
    
    if (searchTerm) {
        currentUrl.searchParams.set('search', searchTerm);
    } else {
        currentUrl.searchParams.delete('search');
    }
    
    window.location.href = currentUrl.toString();
}

function filterByType(type) {
    const currentUrl = new URL(window.location);
    
    if (type !== 'all') {
        currentUrl.searchParams.set('type', type);
    } else {
        currentUrl.searchParams.delete('type');
    }
    
    window.location.href = currentUrl.toString();
}

function formatBytes(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Close modal on escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        hideUploadModal();
    }
});
</script>

<?php renderFooter(); ?>