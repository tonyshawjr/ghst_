<?php
require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/layout.php';

$auth = new Auth();
$auth->requireLogin();
requireClient();

$db = Database::getInstance();
$client = $auth->getCurrentClient();

// Get statistics
$stats = [];

// Total posts
$stmt = $db->prepare("SELECT COUNT(*) as total FROM posts WHERE client_id = ?");
$stmt->execute([$client['id']]);
$stats['total_posts'] = $stmt->fetch()['total'];

// Scheduled posts
$stmt = $db->prepare("SELECT COUNT(*) as total FROM posts WHERE client_id = ? AND status = 'scheduled'");
$stmt->execute([$client['id']]);
$stats['scheduled_posts'] = $stmt->fetch()['total'];

// Published posts (last 30 days)
$stmt = $db->prepare("SELECT COUNT(*) as total FROM posts WHERE client_id = ? AND status = 'published' AND published_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stmt->execute([$client['id']]);
$stats['published_posts'] = $stmt->fetch()['total'];

// Connected accounts
$stmt = $db->prepare("SELECT COUNT(*) as total FROM accounts WHERE client_id = ? AND is_active = 1");
$stmt->execute([$client['id']]);
$stats['connected_accounts'] = $stmt->fetch()['total'];

// Recent posts
$stmt = $db->prepare("
    SELECT p.*, GROUP_CONCAT(a.platform) as platforms
    FROM posts p
    LEFT JOIN accounts a ON JSON_CONTAINS(p.platforms_json, CONCAT('\"', a.platform, '\"'))
    WHERE p.client_id = ?
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT 5
");
$stmt->execute([$client['id']]);
$recentPosts = $stmt->fetchAll();

// Recent activity
$stmt = $db->prepare("
    SELECT * FROM user_actions 
    WHERE client_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$client['id']]);
$recentActivity = $stmt->fetchAll();

renderHeader('Dashboard');
?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <!-- Stats Cards -->
    <div class="bg-gray-900 rounded-lg p-6 border border-gray-800">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-400 text-sm">Total Posts</p>
                <p class="text-3xl font-bold mt-1"><?= number_format($stats['total_posts']) ?></p>
            </div>
            <svg class="w-8 h-8 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
        </div>
    </div>
    
    <div class="bg-gray-900 rounded-lg p-6 border border-gray-800">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-400 text-sm">Scheduled</p>
                <p class="text-3xl font-bold mt-1"><?= number_format($stats['scheduled_posts']) ?></p>
            </div>
            <svg class="w-8 h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
    </div>
    
    <div class="bg-gray-900 rounded-lg p-6 border border-gray-800">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-400 text-sm">Published (30d)</p>
                <p class="text-3xl font-bold mt-1"><?= number_format($stats['published_posts']) ?></p>
            </div>
            <svg class="w-8 h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
    </div>
    
    <div class="bg-gray-900 rounded-lg p-6 border border-gray-800">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-gray-400 text-sm">Connected</p>
                <p class="text-3xl font-bold mt-1"><?= number_format($stats['connected_accounts']) ?></p>
            </div>
            <svg class="w-8 h-8 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
            </svg>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Recent Posts -->
    <div class="bg-gray-900 rounded-lg border border-gray-800">
        <div class="p-6 border-b border-gray-800">
            <h3 class="text-lg font-semibold">Recent Posts</h3>
        </div>
        <div class="p-6">
            <?php if (empty($recentPosts)): ?>
                <p class="text-gray-500 text-center py-8">No posts yet. Create your first post!</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($recentPosts as $post): ?>
                    <div class="flex items-start space-x-4 p-4 bg-gray-800 rounded-lg">
                        <div class="flex-1">
                            <p class="text-sm"><?= truncateText(sanitize($post['content']), 100) ?></p>
                            <div class="flex items-center space-x-4 mt-2">
                                <span class="text-xs text-gray-500"><?= formatDate($post['scheduled_at'], $client['timezone']) ?></span>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?= $post['status'] === 'published' ? 'bg-green-900 text-green-300' : ($post['status'] === 'failed' ? 'bg-red-900 text-red-300' : 'bg-blue-900 text-blue-300') ?>">
                                    <?= ucfirst($post['status']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Recent Activity -->
    <div class="bg-gray-900 rounded-lg border border-gray-800">
        <div class="p-6 border-b border-gray-800">
            <h3 class="text-lg font-semibold">Recent Activity</h3>
        </div>
        <div class="p-6">
            <?php if (empty($recentActivity)): ?>
                <p class="text-gray-500 text-center py-8">No activity yet.</p>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($recentActivity as $activity): ?>
                    <div class="flex items-start space-x-3">
                        <div class="w-2 h-2 bg-purple-500 rounded-full mt-1.5"></div>
                        <div class="flex-1">
                            <p class="text-sm"><?= sanitize($activity['description']) ?></p>
                            <p class="text-xs text-gray-500"><?= getRelativeTime($activity['created_at']) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="mt-8 bg-gray-900 rounded-lg border border-gray-800 p-6">
    <h3 class="text-lg font-semibold mb-4">Quick Actions</h3>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <a href="/dashboard/posts.php?action=new" class="flex items-center justify-center space-x-2 p-4 bg-purple-600 hover:bg-purple-700 rounded-lg transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            <span>New Post</span>
        </a>
        <a href="/dashboard/accounts.php?action=connect" class="flex items-center justify-center space-x-2 p-4 bg-gray-800 hover:bg-gray-700 rounded-lg transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
            </svg>
            <span>Connect Account</span>
        </a>
        <a href="/dashboard/media.php?action=upload" class="flex items-center justify-center space-x-2 p-4 bg-gray-800 hover:bg-gray-700 rounded-lg transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
            </svg>
            <span>Upload Media</span>
        </a>
        <a href="/dashboard/calendar.php" class="flex items-center justify-center space-x-2 p-4 bg-gray-800 hover:bg-gray-700 rounded-lg transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            <span>View Calendar</span>
        </a>
    </div>
</div>

<?php renderFooter(); ?>