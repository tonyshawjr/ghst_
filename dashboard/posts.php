<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/functions.php';
require_once '../includes/layout.php';
require_once '../includes/platforms/Platform.php';

$auth = new Auth();
$auth->requireLogin();
requireClient();

$db = Database::getInstance();
$client = $auth->getCurrentClient();
$action = $_GET['action'] ?? 'list';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 400);
    }
    
    if ($action === 'create') {
        $content = trim($_POST['content'] ?? '');
        $platforms = $_POST['platforms'] ?? [];
        $scheduledAt = $_POST['scheduled_at'] ?? '';
        
        if (empty($content)) {
            jsonResponse(['success' => false, 'error' => 'Content is required'], 400);
        }
        
        if (empty($platforms)) {
            jsonResponse(['success' => false, 'error' => 'At least one platform must be selected'], 400);
        }
        
        if (empty($scheduledAt)) {
            jsonResponse(['success' => false, 'error' => 'Schedule date is required'], 400);
        }
        
        // Validate platforms exist and are connected
        $placeholders = str_repeat('?,', count($platforms) - 1) . '?';
        $stmt = $db->prepare("
            SELECT platform FROM accounts 
            WHERE client_id = ? AND platform IN ($placeholders) AND is_active = 1
        ");
        $stmt->execute(array_merge([$client['id']], $platforms));
        $connectedPlatforms = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $missingPlatforms = array_diff($platforms, $connectedPlatforms);
        if (!empty($missingPlatforms)) {
            jsonResponse(['success' => false, 'error' => 'Some selected platforms are not connected: ' . implode(', ', $missingPlatforms)], 400);
        }
        
        // Create post
        $stmt = $db->prepare("
            INSERT INTO posts (client_id, content, platforms_json, scheduled_at, status, created_by)
            VALUES (?, ?, ?, ?, 'draft', ?)
        ");
        
        $stmt->execute([
            $client['id'],
            $content,
            json_encode($platforms),
            $scheduledAt,
            $auth->getCurrentUser()['id']
        ]);
        
        jsonResponse(['success' => true, 'message' => 'Post created successfully']);
    }
    
    if ($action === 'delete' && isset($_POST['post_id'])) {
        $stmt = $db->prepare("DELETE FROM posts WHERE id = ? AND client_id = ?");
        $stmt->execute([$_POST['post_id'], $client['id']]);
        
        jsonResponse(['success' => true, 'message' => 'Post deleted successfully']);
    }
    
    if ($action === 'update_status' && isset($_POST['post_id']) && isset($_POST['status'])) {
        $allowedStatuses = ['draft', 'scheduled', 'published', 'failed'];
        if (!in_array($_POST['status'], $allowedStatuses)) {
            jsonResponse(['success' => false, 'error' => 'Invalid status'], 400);
        }
        
        $stmt = $db->prepare("UPDATE posts SET status = ? WHERE id = ? AND client_id = ?");
        $stmt->execute([$_POST['status'], $_POST['post_id'], $client['id']]);
        
        jsonResponse(['success' => true, 'message' => 'Post status updated']);
    }
}

// Get connected accounts for platform selection
$stmt = $db->prepare("
    SELECT DISTINCT platform FROM accounts 
    WHERE client_id = ? AND is_active = 1 
    ORDER BY platform
");
$stmt->execute([$client['id']]);
$connectedPlatforms = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Get posts
$statusFilter = $_GET['status'] ?? 'all';
$whereClause = "WHERE p.client_id = ?";
$params = [$client['id']];

if ($statusFilter !== 'all') {
    $whereClause .= " AND p.status = ?";
    $params[] = $statusFilter;
}

$stmt = $db->prepare("
    SELECT p.*, u.name as created_by_name
    FROM posts p
    LEFT JOIN users u ON p.created_by = u.id
    $whereClause
    ORDER BY p.scheduled_at DESC, p.created_at DESC
");
$stmt->execute($params);
$posts = $stmt->fetchAll();

// Get platform info for character limits
$platformLimits = [];
foreach ($connectedPlatforms as $platform) {
    try {
        $platformObj = Platform::create($platform);
        $platformLimits[$platform] = $platformObj->getCharacterLimit();
    } catch (Exception $e) {
        $platformLimits[$platform] = 280; // Default fallback
    }
}

renderHeader('Posts & Scheduler');
?>

<div class="space-y-4 lg:space-y-6">
    <!-- Page Header -->
    <div class="flex justify-between items-center">
        <div>
            <h3 class="text-base lg:text-lg font-semibold text-gray-200">Posts & Scheduler</h3>
            <p class="text-gray-400 text-xs lg:text-sm mt-1">Create and manage your social media posts</p>
        </div>
        
        <?php if (!empty($connectedPlatforms)): ?>
            <button 
                onclick="showCreateModal()" 
                class="px-3 py-2 lg:px-4 lg:py-2 bg-purple-600 hover:bg-purple-700 rounded-lg text-white font-medium transition-colors touch-target touch-feedback lg:inline-flex hidden text-sm"
            >
                <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                Create Post
            </button>
            
            <!-- Mobile Quick Actions Hint -->
            <div class="lg:hidden text-xs text-gray-500 mt-2 flex items-center space-x-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                </svg>
                <span>Swipe posts left/right for quick actions, or use the FAB to create</span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Quick Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
        <?php
        $statusCounts = array_count_values(array_column($posts, 'status'));
        $statuses = [
            'draft' => ['label' => 'Drafts', 'color' => 'gray'],
            'scheduled' => ['label' => 'Scheduled', 'color' => 'blue'],
            'published' => ['label' => 'Published', 'color' => 'green'],
            'failed' => ['label' => 'Failed', 'color' => 'red'],
        ];
        ?>
        <?php foreach ($statuses as $status => $config): ?>
            <div class="bg-gray-900 rounded-lg p-3 lg:p-4 border border-gray-800 touch-feedback">
                <div class="text-xl lg:text-2xl font-bold text-<?= $config['color'] ?>-400">
                    <?= $statusCounts[$status] ?? 0 ?>
                </div>
                <div class="text-xs lg:text-sm text-gray-400"><?= $config['label'] ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Filter Tabs -->
    <div class="flex space-x-1 bg-gray-900 rounded-lg p-1 overflow-x-auto">
        <?php
        $filters = [
            'all' => 'All Posts',
            'draft' => 'Drafts',
            'scheduled' => 'Scheduled',
            'published' => 'Published',
            'failed' => 'Failed',
        ];
        ?>
        <?php foreach ($filters as $filter => $label): ?>
            <a 
                href="?status=<?= $filter ?>" 
                class="flex-shrink-0 px-3 lg:px-4 py-2 rounded-md text-sm font-medium transition-colors touch-target touch-feedback <?= $statusFilter === $filter ? 'bg-purple-600 text-white' : 'text-gray-400 hover:text-white hover:bg-gray-800' ?>"
            >
                <span class="whitespace-nowrap"><?= $label ?></span>
                <?php if (isset($statusCounts[$filter]) || $filter === 'all'): ?>
                    <span class="ml-1 lg:ml-2 px-1.5 lg:px-2 py-0.5 bg-gray-700 rounded-full text-xs">
                        <?= $filter === 'all' ? count($posts) : ($statusCounts[$filter] ?? 0) ?>
                    </span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Posts List -->
    <?php if (empty($connectedPlatforms)): ?>
        <div class="bg-gray-900 rounded-lg border border-gray-800 p-12 text-center">
            <svg class="w-16 h-16 mx-auto text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
            </svg>
            <h3 class="text-lg lg:text-xl font-semibold text-gray-300 mb-2">No Social Media Accounts Connected</h3>
            <p class="text-gray-500 mb-6 text-sm lg:text-base">Connect your social media accounts first to start creating posts</p>
            <a 
                href="/dashboard/accounts.php" 
                class="px-6 py-3 bg-purple-600 hover:bg-purple-700 rounded-lg text-white font-medium transition-colors inline-block touch-target touch-feedback"
            >
                Connect Accounts
            </a>
        </div>
    <?php elseif (empty($posts)): ?>
        <div class="bg-gray-900 rounded-lg border border-gray-800 p-12 text-center">
            <svg class="w-16 h-16 mx-auto text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <h3 class="text-lg lg:text-xl font-semibold text-gray-300 mb-2">No Posts Yet</h3>
            <p class="text-gray-500 mb-6 text-sm lg:text-base">Create your first post to get started</p>
            <button 
                onclick="showCreateModal()" 
                class="px-6 py-3 bg-purple-600 hover:bg-purple-700 rounded-lg text-white font-medium transition-colors touch-target touch-feedback"
            >
                Create First Post
            </button>
        </div>
    <?php else: ?>
        <div class="bg-gray-900 rounded-lg border border-gray-800 divide-y divide-gray-800">
            <?php foreach ($posts as $post): ?>
                <?php
                $platforms = json_decode($post['platforms_json'], true) ?: [];
                $statusColor = [
                    'draft' => 'gray',
                    'scheduled' => 'blue',
                    'published' => 'green',
                    'failed' => 'red',
                ][$post['status']] ?? 'gray';
                ?>
                <div class="p-4 lg:p-6 relative post-card" data-post-id="<?= $post['id'] ?>">
                    <!-- Swipe indicator -->
                    <div class="absolute left-0 top-1/2 transform -translate-y-1/2 swipe-indicator lg:hidden"></div>
                    <div class="absolute right-0 top-1/2 transform -translate-y-1/2 swipe-indicator lg:hidden"></div>
                    
                    <div class="flex items-start justify-between">
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 lg:space-x-3 mb-3">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-<?= $statusColor ?>-900 text-<?= $statusColor ?>-300">
                                    <?= ucfirst($post['status']) ?>
                                </span>
                                <span class="text-xs lg:text-sm text-gray-400 whitespace-nowrap">
                                    <?= formatDate($post['scheduled_at'], $client['timezone']) ?>
                                </span>
                                <div class="flex space-x-1 flex-shrink-0">
                                    <?php foreach ($platforms as $platform): ?>
                                        <div class="text-gray-400 w-4 h-4" title="<?= ucfirst($platform) ?>">
                                            <?= getPlatformIcon($platform) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="prose prose-invert max-w-none mb-3">
                                <p class="text-gray-200 text-sm lg:text-base leading-relaxed"><?= nl2br(sanitize($post['content'])) ?></p>
                            </div>
                            
                            <div class="flex flex-wrap items-center gap-2 lg:space-x-4 text-xs lg:text-sm text-gray-500">
                                <span>By <?= sanitize($post['created_by_name'] ?? 'Unknown') ?></span>
                                <span>Created <?= getRelativeTime($post['created_at']) ?></span>
                                <span><?= strlen($post['content']) ?> characters</span>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-1 lg:space-x-2 flex-shrink-0">
                            <?php if ($post['status'] === 'draft'): ?>
                                <button 
                                    onclick="updatePostStatus(<?= $post['id'] ?>, 'scheduled')"
                                    class="px-2 lg:px-3 py-1 bg-blue-600 hover:bg-blue-700 rounded text-xs lg:text-sm text-white transition-colors touch-target touch-feedback"
                                    title="Schedule post"
                                >
                                    Schedule
                                </button>
                            <?php endif; ?>
                            
                            <button 
                                onclick="editPost(<?= $post['id'] ?>)"
                                class="p-2 text-gray-400 hover:text-white transition-colors touch-target touch-feedback"
                                title="Edit post"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                            </button>
                            
                            <button 
                                onclick="deletePost(<?= $post['id'] ?>)"
                                class="p-2 text-gray-400 hover:text-red-400 transition-colors touch-target touch-feedback"
                                title="Delete post"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Create Post Modal -->
<div id="createModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-gray-900 rounded-lg border border-gray-800 p-4 lg:p-6 max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold">Create New Post</h3>
            <button onclick="hideCreateModal()" class="text-gray-400 hover:text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <form id="createPostForm" onsubmit="createPost(event)">
            <!-- Content -->
            <div class="mb-4 lg:mb-6">
                <label class="block text-sm font-medium text-gray-300 mb-2">Content</label>
                <textarea 
                    id="postContent"
                    name="content"
                    rows="8"
                    class="w-full px-3 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent resize-none text-base"
                    placeholder="What's on your mind?"
                    oninput="updateCharacterCounts()"
                    required
                ></textarea>
                <div id="characterCounts" class="mt-2 text-xs lg:text-sm text-gray-400"></div>
            </div>

            <!-- Platforms -->
            <div class="mb-4 lg:mb-6">
                <label class="block text-sm font-medium text-gray-300 mb-2">Platforms</label>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                    <?php foreach ($connectedPlatforms as $platform): ?>
                        <label class="flex items-center space-x-3 p-4 bg-gray-800 rounded-lg hover:bg-gray-700 cursor-pointer transition-colors touch-target touch-feedback">
                            <input 
                                type="checkbox" 
                                name="platforms[]" 
                                value="<?= $platform ?>"
                                class="form-checkbox h-5 w-5 text-purple-600 bg-gray-700 border-gray-600 rounded focus:ring-purple-500"
                                onchange="updateCharacterCounts()"
                            >
                            <div class="text-gray-400 w-5 h-5">
                                <?= getPlatformIcon($platform) ?>
                            </div>
                            <span class="text-base font-medium"><?= ucfirst($platform) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Schedule Date/Time -->
            <div class="mb-4 lg:mb-6">
                <label class="block text-sm font-medium text-gray-300 mb-2">Schedule Date & Time</label>
                <input 
                    type="datetime-local"
                    name="scheduled_at"
                    class="w-full px-3 py-3 bg-gray-800 border border-gray-700 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent text-base"
                    min="<?= date('Y-m-d\TH:i') ?>"
                    required
                >
            </div>

            <!-- Media Upload Placeholder -->
            <div class="mb-6 p-4 border-2 border-dashed border-gray-700 rounded-lg text-center">
                <svg class="w-8 h-8 mx-auto text-gray-500 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                </svg>
                <p class="text-sm text-gray-500">Media upload coming soon</p>
            </div>

            <!-- Actions -->
            <div class="flex flex-col lg:flex-row justify-end space-y-3 lg:space-y-0 lg:space-x-3">
                <button 
                    type="button" 
                    onclick="hideCreateModal()"
                    class="px-4 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg text-white transition-colors touch-target touch-feedback order-2 lg:order-1"
                >
                    Cancel
                </button>
                <button 
                    type="submit"
                    class="px-4 py-3 bg-purple-600 hover:bg-purple-700 rounded-lg text-white transition-colors touch-target touch-feedback order-1 lg:order-2"
                >
                    Create Post
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const platformLimits = <?= json_encode($platformLimits) ?>;

function showCreateModal() {
    document.getElementById('createModal').classList.remove('hidden');
    document.getElementById('createModal').classList.add('flex');
    // Set default date to 1 hour from now
    const now = new Date();
    now.setHours(now.getHours() + 1);
    document.querySelector('input[name="scheduled_at"]').value = now.toISOString().slice(0, 16);
}

function hideCreateModal() {
    document.getElementById('createModal').classList.add('hidden');
    document.getElementById('createModal').classList.remove('flex');
    document.getElementById('createPostForm').reset();
    document.getElementById('characterCounts').innerHTML = '';
}

function updateCharacterCounts() {
    const content = document.getElementById('postContent').value;
    const selectedPlatforms = Array.from(document.querySelectorAll('input[name="platforms[]"]:checked')).map(cb => cb.value);
    const countsDiv = document.getElementById('characterCounts');
    
    if (selectedPlatforms.length === 0) {
        countsDiv.innerHTML = '';
        return;
    }
    
    let html = 'Character count: ';
    const counts = selectedPlatforms.map(platform => {
        const limit = platformLimits[platform] || 280;
        const count = content.length;
        const color = count > limit ? 'text-red-400' : (count > limit * 0.8 ? 'text-yellow-400' : 'text-green-400');
        return `<span class="${color}">${platform}: ${count}/${limit}</span>`;
    });
    
    countsDiv.innerHTML = html + counts.join(', ');
}

function createPost(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    formData.append('action', 'create');
    formData.append('csrf_token', '<?= $auth->generateCSRFToken() ?>');
    
    fetch('', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            hideCreateModal();
            location.reload();
        } else {
            alert('Error: ' + (data.error || 'Failed to create post'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to create post');
    });
}

function deletePost(postId) {
    if (!confirm('Are you sure you want to delete this post?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('post_id', postId);
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
            alert('Error: ' + (data.error || 'Failed to delete post'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to delete post');
    });
}

function updatePostStatus(postId, status) {
    const formData = new FormData();
    formData.append('action', 'update_status');
    formData.append('post_id', postId);
    formData.append('status', status);
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
            alert('Error: ' + (data.error || 'Failed to update post'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update post');
    });
}

function editPost(postId) {
    // TODO: Implement post editing
    alert('Post editing not yet implemented');
}

// Close modal on escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        hideCreateModal();
    }
});

// Mobile swipe functionality for post cards
let touchStartX = 0;
let touchEndX = 0;
let currentSwipeElement = null;

document.addEventListener('touchstart', function(e) {
    if (e.target.closest('.post-card')) {
        currentSwipeElement = e.target.closest('.post-card');
        touchStartX = e.changedTouches[0].screenX;
    }
}, { passive: true });

document.addEventListener('touchend', function(e) {
    if (currentSwipeElement) {
        touchEndX = e.changedTouches[0].screenX;
        handleSwipe();
        currentSwipeElement = null;
    }
}, { passive: true });

function handleSwipe() {
    const swipeThreshold = 100;
    const swipeDistance = touchEndX - touchStartX;
    
    if (Math.abs(swipeDistance) > swipeThreshold) {
        const postId = currentSwipeElement.dataset.postId;
        
        if (swipeDistance > 0) {
            // Swipe right - edit post
            editPost(postId);
        } else {
            // Swipe left - delete post
            if (confirm('Delete this post?')) {
                deletePost(postId);
            }
        }
    }
}

// Haptic feedback for supported devices
function triggerHapticFeedback() {
    if (navigator.vibrate) {
        navigator.vibrate(50);
    }
}

// Enhanced touch feedback for buttons
document.addEventListener('touchstart', function(e) {
    if (e.target.classList.contains('touch-feedback') || e.target.closest('.touch-feedback')) {
        triggerHapticFeedback();
    }
}, { passive: true });

// Pull to refresh functionality
let pullToRefreshStartY = 0;
let pullToRefreshCurrentY = 0;
let pullToRefreshActive = false;

document.addEventListener('touchstart', function(e) {
    if (window.scrollY === 0) {
        pullToRefreshStartY = e.touches[0].pageY;
        pullToRefreshActive = true;
    }
}, { passive: true });

document.addEventListener('touchmove', function(e) {
    if (pullToRefreshActive) {
        pullToRefreshCurrentY = e.touches[0].pageY;
        const pullDistance = pullToRefreshCurrentY - pullToRefreshStartY;
        
        if (pullDistance > 100) {
            // Show pull to refresh indicator
            document.body.style.transform = `translateY(${Math.min(pullDistance * 0.5, 50)}px)`;
        }
    }
}, { passive: true });

document.addEventListener('touchend', function(e) {
    if (pullToRefreshActive) {
        const pullDistance = pullToRefreshCurrentY - pullToRefreshStartY;
        
        if (pullDistance > 150) {
            // Trigger refresh
            location.reload();
        } else {
            // Reset
            document.body.style.transform = '';
        }
        
        pullToRefreshActive = false;
    }
}, { passive: true });
</script>

<?php renderFooter(); ?>