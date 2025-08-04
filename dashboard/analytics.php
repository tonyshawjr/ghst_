<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/functions.php';
require_once '../includes/layout.php';

$auth = new Auth();
$auth->requireLogin();
requireClient();

$db = Database::getInstance();
$client = $auth->getCurrentClient();

// Date range filter
$range = $_GET['range'] ?? '30';
$dateFrom = date('Y-m-d', strtotime("-{$range} days"));
$dateTo = date('Y-m-d');

// Get posting statistics
$stmt = $db->prepare("
    SELECT 
        DATE(scheduled_at) as date,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published,
        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
    FROM posts
    WHERE client_id = ? AND scheduled_at BETWEEN ? AND ?
    GROUP BY DATE(scheduled_at)
    ORDER BY date ASC
");
$stmt->execute([$client['id'], $dateFrom, $dateTo]);
$dailyStats = $stmt->fetchAll();

// Platform distribution
$stmt = $db->prepare("
    SELECT 
        JSON_UNQUOTE(JSON_EXTRACT(platforms_json, '$[*]')) as platforms
    FROM posts
    WHERE client_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
");
$stmt->execute([$client['id'], $range]);
$platformData = [];
while ($row = $stmt->fetch()) {
    $platforms = json_decode($row['platforms'], true);
    foreach ($platforms as $platform) {
        $platformData[$platform] = ($platformData[$platform] ?? 0) + 1;
    }
}

// Success rate
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as successful
    FROM posts
    WHERE client_id = ? AND scheduled_at <= NOW() AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
");
$stmt->execute([$client['id'], $range]);
$successData = $stmt->fetch();
$successRate = $successData['total'] > 0 ? round(($successData['successful'] / $successData['total']) * 100, 1) : 0;

// Top performing times
$stmt = $db->prepare("
    SELECT 
        HOUR(scheduled_at) as hour,
        COUNT(*) as count
    FROM posts
    WHERE client_id = ? AND status = 'published' AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    GROUP BY HOUR(scheduled_at)
    ORDER BY count DESC
    LIMIT 5
");
$stmt->execute([$client['id'], $range]);
$topHours = $stmt->fetchAll();

// Recent errors
$stmt = $db->prepare("
    SELECT * FROM logs
    WHERE client_id = ? AND status = 'error' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$client['id']]);
$recentErrors = $stmt->fetchAll();

renderHeader('Analytics');
?>

<!-- Date Range Filter -->
<div class="mb-8 flex items-center justify-between">
    <h3 class="text-lg font-semibold">Analytics Overview</h3>
    <div class="flex items-center space-x-2">
        <label class="text-sm text-gray-400">Range:</label>
        <select 
            onchange="window.location.href='?range=' + this.value"
            class="px-4 py-2 bg-gray-900 border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500"
        >
            <option value="7" <?= $range == '7' ? 'selected' : '' ?>>Last 7 days</option>
            <option value="30" <?= $range == '30' ? 'selected' : '' ?>>Last 30 days</option>
            <option value="90" <?= $range == '90' ? 'selected' : '' ?>>Last 90 days</option>
        </select>
    </div>
</div>

<!-- Key Metrics -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="bg-gray-900 rounded-lg p-6 border border-gray-800">
        <p class="text-gray-400 text-sm mb-1">Total Posts</p>
        <p class="text-3xl font-bold"><?= number_format($successData['total']) ?></p>
    </div>
    
    <div class="bg-gray-900 rounded-lg p-6 border border-gray-800">
        <p class="text-gray-400 text-sm mb-1">Success Rate</p>
        <p class="text-3xl font-bold text-green-500"><?= $successRate ?>%</p>
    </div>
    
    <div class="bg-gray-900 rounded-lg p-6 border border-gray-800">
        <p class="text-gray-400 text-sm mb-1">Active Platforms</p>
        <p class="text-3xl font-bold"><?= count($platformData) ?></p>
    </div>
    
    <div class="bg-gray-900 rounded-lg p-6 border border-gray-800">
        <p class="text-gray-400 text-sm mb-1">Recent Errors</p>
        <p class="text-3xl font-bold text-red-500"><?= count($recentErrors) ?></p>
    </div>
</div>

<!-- Posting Activity Chart -->
<div class="bg-gray-900 rounded-lg border border-gray-800 p-6 mb-8">
    <h3 class="text-lg font-semibold mb-4">Posting Activity</h3>
    <div class="h-64 flex items-end space-x-2">
        <?php
        $maxPosts = max(array_column($dailyStats, 'total') ?: [1]);
        foreach ($dailyStats as $day):
            $height = ($day['total'] / $maxPosts) * 100;
            $successHeight = ($day['published'] / $maxPosts) * 100;
        ?>
        <div class="flex-1 flex flex-col items-center">
            <div class="w-full bg-gray-800 rounded-t relative" style="height: <?= $height ?>%">
                <div class="absolute bottom-0 w-full bg-green-600 rounded-t" style="height: <?= ($successHeight / $height) * 100 ?>%"></div>
            </div>
            <span class="text-xs text-gray-500 mt-1"><?= date('d', strtotime($day['date'])) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="flex items-center justify-center mt-4 space-x-6">
        <span class="flex items-center text-sm">
            <span class="w-3 h-3 bg-gray-800 rounded mr-2"></span> Total
        </span>
        <span class="flex items-center text-sm">
            <span class="w-3 h-3 bg-green-600 rounded mr-2"></span> Published
        </span>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Platform Distribution -->
    <div class="bg-gray-900 rounded-lg border border-gray-800 p-6">
        <h3 class="text-lg font-semibold mb-4">Platform Distribution</h3>
        <?php if (empty($platformData)): ?>
            <p class="text-gray-500 text-center py-8">No data available</p>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($platformData as $platform => $count): 
                    $percentage = round(($count / array_sum($platformData)) * 100, 1);
                ?>
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-sm"><?= ucfirst($platform) ?></span>
                        <span class="text-sm text-gray-400"><?= $count ?> posts (<?= $percentage ?>%)</span>
                    </div>
                    <div class="w-full bg-gray-800 rounded-full h-2">
                        <div class="bg-purple-600 h-2 rounded-full" style="width: <?= $percentage ?>%"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Best Posting Times -->
    <div class="bg-gray-900 rounded-lg border border-gray-800 p-6">
        <h3 class="text-lg font-semibold mb-4">Best Posting Times</h3>
        <?php if (empty($topHours)): ?>
            <p class="text-gray-500 text-center py-8">Not enough data</p>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($topHours as $hour): ?>
                <div class="flex items-center justify-between p-3 bg-gray-800 rounded-lg">
                    <span><?= str_pad($hour['hour'], 2, '0', STR_PAD_LEFT) ?>:00 - <?= str_pad($hour['hour'] + 1, 2, '0', STR_PAD_LEFT) ?>:00</span>
                    <span class="text-purple-500 font-medium"><?= $hour['count'] ?> posts</span>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Errors -->
<div class="mt-8 bg-gray-900 rounded-lg border border-gray-800 p-6">
    <h3 class="text-lg font-semibold mb-4">Recent Errors</h3>
    <?php if (empty($recentErrors)): ?>
        <p class="text-gray-500 text-center py-8">No recent errors</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="text-left border-b border-gray-800">
                    <tr>
                        <th class="pb-3 text-sm font-medium text-gray-400">Date</th>
                        <th class="pb-3 text-sm font-medium text-gray-400">Platform</th>
                        <th class="pb-3 text-sm font-medium text-gray-400">Error</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    <?php foreach ($recentErrors as $error): ?>
                    <tr class="border-b border-gray-800">
                        <td class="py-3"><?= formatDate($error['created_at'], $client['timezone']) ?></td>
                        <td class="py-3"><?= ucfirst($error['platform'] ?? 'System') ?></td>
                        <td class="py-3 text-red-400"><?= sanitize($error['message']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php renderFooter(); ?>