<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/functions.php';
require_once '../includes/layout.php';
require_once '../includes/AnalyticsDashboard.php';

$auth = new Auth();
$auth->requireLogin();
requireClient();

$db = Database::getInstance();
$client = $auth->getCurrentClient();
$analytics = new AnalyticsDashboard();

// Enhanced filters
$period = $_GET['period'] ?? '30d';
$platform = $_GET['platform'] ?? 'all';
$dateRange = $_GET['custom_range'] ?? null;
$export = $_GET['export'] ?? null;

// Handle custom date range
if ($dateRange && preg_match('/^(\d{4}-\d{2}-\d{2}):(\d{4}-\d{2}-\d{2})$/', $dateRange, $matches)) {
    $period = "{$matches[1]}:{$matches[2]}";
}

// Handle export requests
if ($export) {
    handleExport($analytics, $client['id'], $period, $platform, $export);
    exit;
}

// Check if client has any analytics data (server-side optimization)
$hasData = checkClientHasData($db, $client['id']);
$dashboardData = $hasData ? $analytics->getDashboardData($client['id'], $period, $platform) : null;
$hasAccounts = checkClientHasAccounts($db, $client['id']);
$hasPosts = checkClientHasPosts($db, $client['id']);

// Helper function for export
function handleExport($analytics, $clientId, $period, $platform, $exportType) {
    $data = $analytics->getDashboardData($clientId, $period, $platform);
    
    switch ($exportType) {
        case 'csv':
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="analytics_'.date('Y-m-d').'.csv"');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Metric', 'Value', 'Change', 'Trend']);
            
            foreach ($data['overview'] as $metric => $values) {
                fputcsv($output, [
                    ucwords(str_replace('_', ' ', $metric)),
                    number_format($values['current']),
                    $values['change'] . '%',
                    $values['trend']
                ]);
            }
            fclose($output);
            break;
            
        case 'json':
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="analytics_'.date('Y-m-d').'.json"');
            echo json_encode($data, JSON_PRETTY_PRINT);
            break;
    }
}

/**
 * Check if client has any analytics data
 */
function checkClientHasData($db, $clientId) {
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM posts p 
        LEFT JOIN post_analytics pa ON p.id = pa.post_id 
        WHERE p.client_id = ? 
        AND p.status = 'published' 
        AND (pa.id IS NOT NULL OR p.published_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))
        LIMIT 1
    ");
    $stmt->execute([$clientId]);
    $result = $stmt->fetch();
    return $result['count'] > 0;
}

/**
 * Check if client has connected accounts
 */
function checkClientHasAccounts($db, $clientId) {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM accounts WHERE client_id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$clientId]);
    $result = $stmt->fetch();
    return $result['count'] > 0;
}

/**
 * Check if client has any posts
 */
function checkClientHasPosts($db, $clientId) {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM posts WHERE client_id = ? LIMIT 1");
    $stmt->execute([$clientId]);
    $result = $stmt->fetch();
    return $result['count'] > 0;
}

renderHeader('Analytics');
?>

<!-- Analytics-specific CSS for performance -->
<link rel="stylesheet" href="/assets/css/analytics.css">

<?php if (!$hasData): ?>
<!-- Empty State -->
<div class="mb-8">
    <div class="text-center py-12">
        <div class="max-w-md mx-auto">
            <svg class="w-20 h-20 mx-auto mb-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
            </svg>
            
            <h2 class="text-2xl font-bold text-white mb-4">Welcome to Analytics</h2>
            
            <?php if (!$hasAccounts): ?>
                <p class="text-gray-400 mb-8">Connect your social media accounts to start tracking your performance.</p>
                
                <div class="bg-gray-900 rounded-xl p-6 mb-8 text-left">
                    <h3 class="font-semibold mb-4 text-center">Get Started in 3 Steps:</h3>
                    <div class="space-y-4">
                        <div class="flex items-start space-x-3">
                            <div class="w-8 h-8 bg-purple-600 rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0">1</div>
                            <div>
                                <h4 class="font-medium">Connect Your Accounts</h4>
                                <p class="text-sm text-gray-400">Link your Facebook, Instagram, Twitter, and LinkedIn accounts</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3">
                            <div class="w-8 h-8 bg-purple-600 rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0">2</div>
                            <div>
                                <h4 class="font-medium">Create Content</h4>
                                <p class="text-sm text-gray-400">Schedule and publish posts across your platforms</p>
                            </div>
                        </div>
                        <div class="flex items-start space-x-3">
                            <div class="w-8 h-8 bg-purple-600 rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0">3</div>
                            <div>
                                <h4 class="font-medium">Track Performance</h4>
                                <p class="text-sm text-gray-400">Analytics will automatically appear as your content performs</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <a href="/dashboard/accounts.php" class="inline-flex items-center px-6 py-3 bg-purple-600 hover:bg-purple-700 rounded-lg font-medium transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Connect Your First Account
                </a>
            
            <?php elseif (!$hasPosts): ?>
                <p class="text-gray-400 mb-8">Your accounts are connected! Create your first post to see analytics.</p>
                
                <div class="bg-gray-900 rounded-xl p-6 mb-8 text-left">
                    <h3 class="font-semibold mb-4 text-center">Ready to Post?</h3>
                    <div class="space-y-3">
                        <div class="flex items-center space-x-3 text-sm">
                            <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span class="text-gray-400">Accounts connected and ready</span>
                        </div>
                        <div class="flex items-center space-x-3 text-sm">
                            <svg class="w-5 h-5 text-purple-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                            </svg>
                            <span class="text-gray-400">Create content that resonates</span>
                        </div>
                        <div class="flex items-center space-x-3 text-sm">
                            <svg class="w-5 h-5 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                            <span class="text-gray-400">Watch your analytics grow</span>
                        </div>
                    </div>
                </div>
                
                <a href="/dashboard/posts.php" class="inline-flex items-center px-6 py-3 bg-purple-600 hover:bg-purple-700 rounded-lg font-medium transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                    </svg>
                    Create Your First Post
                </a>
            
            <?php else: ?>
                <p class="text-gray-400 mb-8">Your content is being processed. Analytics will appear shortly after your posts go live.</p>
                
                <div class="bg-gray-900 rounded-xl p-6 mb-8">
                    <div class="flex items-center justify-center space-x-2 mb-4">
                        <svg class="w-5 h-5 text-purple-500 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        <span class="text-purple-400 font-medium">Processing your content...</span>
                    </div>
                    <p class="text-sm text-gray-400">Analytics typically appear within 2-24 hours after posting, depending on the platform.</p>
                </div>
                
                <button onclick="location.reload()" class="inline-flex items-center px-6 py-3 bg-gray-800 hover:bg-gray-700 rounded-lg font-medium transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Refresh Page
                </button>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Preview of What's Coming -->
    <div class="mt-16">
        <h3 class="text-xl font-semibold text-center mb-8">What You'll See</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="text-center p-6 bg-gray-900 rounded-lg border border-gray-800">
                <div class="w-12 h-12 bg-purple-600/20 rounded-lg flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                    </svg>
                </div>
                <h4 class="font-semibold mb-2">Engagement Metrics</h4>
                <p class="text-sm text-gray-400">Track likes, comments, shares across all platforms</p>
            </div>
            <div class="text-center p-6 bg-gray-900 rounded-lg border border-gray-800">
                <div class="w-12 h-12 bg-blue-600/20 rounded-lg flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                </div>
                <h4 class="font-semibold mb-2">Reach & Impressions</h4>
                <p class="text-sm text-gray-400">See how many people your content reaches</p>
            </div>
            <div class="text-center p-6 bg-gray-900 rounded-lg border border-gray-800">
                <div class="w-12 h-12 bg-green-600/20 rounded-lg flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                </div>
                <h4 class="font-semibold mb-2">Growth Tracking</h4>
                <p class="text-sm text-gray-400">Monitor follower growth and trends</p>
            </div>
            <div class="text-center p-6 bg-gray-900 rounded-lg border border-gray-800">
                <div class="w-12 h-12 bg-yellow-600/20 rounded-lg flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h4 class="font-semibold mb-2">Best Posting Times</h4>
                <p class="text-sm text-gray-400">Discover when your audience is most active</p>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Analytics Dashboard with Data -->
<div class="analytics-dashboard">
<div class="mb-8 flex flex-col lg:flex-row lg:items-center lg:justify-between space-y-4 lg:space-y-0" 
     x-data="{ 
         showDatePicker: false, 
         autoRefresh: true, 
         refreshInterval: null,
         activeView: 'overview'
     }"
     x-init="
         if (autoRefresh) {
             refreshInterval = setInterval(() => {
                 if (document.visibilityState === 'visible') {
                     refreshData();
                 }
             }, 300000); // 5 minutes
         }
         $watch('autoRefresh', value => {
             if (value && !refreshInterval) {
                 refreshInterval = setInterval(() => refreshData(), 300000);
             } else if (!value && refreshInterval) {
                 clearInterval(refreshInterval);
             }
         });
     ">
    
    <div class="flex flex-col sm:flex-row sm:items-center space-y-2 sm:space-y-0 sm:space-x-4">
        <h3 class="text-lg font-semibold">Analytics Dashboard</h3>
        
        <!-- Auto-refresh indicator -->
        <div class="flex items-center space-x-2 text-sm text-gray-400">
            <div class="flex items-center space-x-1">
                <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse" x-show="autoRefresh"></div>
                <span x-text="autoRefresh ? 'Live' : 'Static'"></span>
            </div>
            <button 
                @click="autoRefresh = !autoRefresh" 
                class="text-xs px-2 py-1 rounded-full border transition-colors"
                :class="autoRefresh ? 'border-green-500 text-green-400' : 'border-gray-600 text-gray-400'"
            >
                <span x-text="autoRefresh ? 'ON' : 'OFF'"></span>
            </button>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center space-y-2 sm:space-y-0 sm:space-x-3">
        <!-- Period Filter -->
        <div class="flex items-center space-x-2">
            <label class="text-sm text-gray-400 whitespace-nowrap">Period:</label>
            <select 
                onchange="updateFilters()"
                id="periodFilter"
                class="px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500 text-sm min-w-0"
            >
                <option value="7d" <?= $period == '7d' ? 'selected' : '' ?>>Last 7 days</option>
                <option value="30d" <?= $period == '30d' ? 'selected' : '' ?>>Last 30 days</option>
                <option value="90d" <?= $period == '90d' ? 'selected' : '' ?>>Last 90 days</option>
                <option value="1y" <?= $period == '1y' ? 'selected' : '' ?>>Last year</option>
                <option value="custom">Custom range</option>
            </select>
        </div>
        
        <!-- Platform Filter -->
        <div class="flex items-center space-x-2">
            <label class="text-sm text-gray-400 whitespace-nowrap">Platform:</label>
            <select 
                onchange="updateFilters()"
                id="platformFilter"
                class="px-3 py-2 bg-gray-900 border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500 text-sm min-w-0"
            >
                <option value="all" <?= $platform == 'all' ? 'selected' : '' ?>>All Platforms</option>
                <option value="facebook" <?= $platform == 'facebook' ? 'selected' : '' ?>>Facebook</option>
                <option value="instagram" <?= $platform == 'instagram' ? 'selected' : '' ?>>Instagram</option>
                <option value="twitter" <?= $platform == 'twitter' ? 'selected' : '' ?>>Twitter</option>
                <option value="linkedin" <?= $platform == 'linkedin' ? 'selected' : '' ?>>LinkedIn</option>
            </select>
        </div>
        
        <!-- Export Options -->
        <div class="flex items-center space-x-2">
            <div class="relative" x-data="{ showExport: false }" @click.away="showExport = false">
                <button 
                    @click="showExport = !showExport"
                    class="px-3 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg text-sm font-medium transition-colors flex items-center space-x-1 touch-target"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <span>Export</span>
                </button>
                
                <div x-show="showExport" x-transition class="absolute right-0 top-full mt-2 bg-gray-800 rounded-lg shadow-lg border border-gray-700 overflow-hidden z-50 min-w-32">
                    <a href="?export=csv&period=<?= urlencode($period) ?>&platform=<?= urlencode($platform) ?>" class="block px-4 py-2 hover:bg-gray-700 transition-colors text-sm">
                        CSV Export
                    </a>
                    <a href="?export=json&period=<?= urlencode($period) ?>&platform=<?= urlencode($platform) ?>" class="block px-4 py-2 hover:bg-gray-700 transition-colors text-sm">
                        JSON Export
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Custom Date Picker -->
    <div x-show="showDatePicker" x-transition class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
        <div class="bg-gray-900 rounded-lg border border-gray-800 p-6 max-w-md w-full">
            <h4 class="text-lg font-semibold mb-4">Custom Date Range</h4>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium mb-2">From Date</label>
                    <input type="date" id="dateFrom" class="w-full px-3 py-2 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-2">To Date</label>
                    <input type="date" id="dateTo" class="w-full px-3 py-2 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500">
                </div>
                <div class="flex justify-end space-x-3">
                    <button @click="showDatePicker = false" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 rounded-lg transition-colors">Cancel</button>
                    <button onclick="applyCustomRange()" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg transition-colors">Apply</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Enhanced Key Metrics -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6 mb-8" id="metricsCards">
    <?php 
    $metrics = [
        'total_engagement' => [
            'label' => 'Total Engagement',
            'icon' => 'M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z',
            'color' => 'text-pink-500'
        ],
        'total_reach' => [
            'label' => 'Total Reach',
            'icon' => 'M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z',
            'color' => 'text-blue-500'
        ],
        'total_impressions' => [
            'label' => 'Impressions',
            'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z',
            'color' => 'text-purple-500'
        ],
        'follower_growth' => [
            'label' => 'Follower Growth',
            'icon' => 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6',
            'color' => 'text-green-500'
        ]
    ];
    
    foreach ($metrics as $key => $metric): 
        $value = $dashboardData['overview'][$key] ?? ['current' => 0, 'change' => 0, 'trend' => 'flat'];
        $changeClass = $value['trend'] === 'up' ? 'text-green-400' : ($value['trend'] === 'down' ? 'text-red-400' : 'text-gray-400');
        $changeIcon = $value['trend'] === 'up' ? '↗' : ($value['trend'] === 'down' ? '↘' : '→');
    ?>
    <div class="metric-card bg-gray-900 rounded-lg p-4 lg:p-6 border border-gray-800 hover:border-gray-700 transition-colors touch-feedback" 
         data-metric="<?= $key ?>">
        <div class="flex items-center justify-between mb-2">
            <div class="flex items-center space-x-2">
                <svg class="w-5 h-5 <?= $metric['color'] ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $metric['icon'] ?>"></path>
                </svg>
                <p class="text-gray-400 text-xs lg:text-sm font-medium truncate"><?= $metric['label'] ?></p>
            </div>
        </div>
        
        <div class="flex items-end justify-between">
            <div>
                <p class="text-xl lg:text-3xl font-bold mb-1" id="metric-<?= $key ?>"><?= formatNumber($value['current']) ?></p>
                <div class="flex items-center space-x-1 text-xs">
                    <span class="<?= $changeClass ?>"><?= $changeIcon ?></span>
                    <span class="<?= $changeClass ?>" id="change-<?= $key ?>"><?= abs($value['change']) ?>%</span>
                    <span class="text-gray-500">vs prev</span>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Chart Tabs for Mobile -->
<div class="lg:hidden mb-6">
    <div class="flex overflow-x-auto space-x-1 pb-2" id="chartTabs">
        <button onclick="showMobileChart('engagement')" class="chart-tab active flex-shrink-0 px-4 py-2 bg-purple-600 text-white rounded-lg text-sm font-medium">
            Engagement
        </button>
        <button onclick="showMobileChart('platforms')" class="chart-tab flex-shrink-0 px-4 py-2 bg-gray-800 text-gray-300 rounded-lg text-sm font-medium">
            Platforms
        </button>
        <button onclick="showMobileChart('posting-times')" class="chart-tab flex-shrink-0 px-4 py-2 bg-gray-800 text-gray-300 rounded-lg text-sm font-medium">
            Best Times
        </button>
        <button onclick="showMobileChart('hashtags')" class="chart-tab flex-shrink-0 px-4 py-2 bg-gray-800 text-gray-300 rounded-lg text-sm font-medium">
            Hashtags
        </button>
    </div>
</div>

<!-- Engagement Rate Trend Chart -->
<div class="chart-container bg-gray-900 rounded-lg border border-gray-800 p-4 lg:p-6 mb-6 lg:mb-8 mobile-chart" id="chart-engagement">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold">Engagement Rate Trend</h3>
        <div class="flex items-center space-x-2 text-sm">
            <div class="w-2 h-2 bg-purple-500 rounded-full"></div>
            <span class="text-gray-400">Engagement Rate</span>
        </div>
    </div>
    <div class="relative">
        <canvas id="engagementChart" class="w-full" style="height: 300px;"></canvas>
        <div id="engagementLoading" class="absolute inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-500"></div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8 mb-6 lg:mb-8">
    <!-- Platform Comparison Chart -->
    <div class="bg-gray-900 rounded-lg border border-gray-800 p-4 lg:p-6 mobile-chart" id="chart-platforms" style="display: none;">
        <h3 class="text-lg font-semibold mb-4">Platform Performance</h3>
        <div class="relative">
            <canvas id="platformChart" class="w-full" style="height: 300px;"></canvas>
            <div id="platformLoading" class="absolute inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
            </div>
        </div>
    </div>
    
    <!-- Best Posting Times Heatmap -->
    <div class="bg-gray-900 rounded-lg border border-gray-800 p-4 lg:p-6 mobile-chart" id="chart-posting-times" style="display: none;">
        <h3 class="text-lg font-semibold mb-4">Best Posting Times</h3>
        <div class="relative">
            <div id="heatmapContainer" class="w-full overflow-x-auto">
                <div class="min-w-96">
                    <div id="postingTimesHeatmap" style="height: 300px;"></div>
                </div>
            </div>
            <div id="heatmapLoading" class="absolute inset-0 bg-gray-900 bg-opacity-75 flex items-center justify-center">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-yellow-500"></div>
            </div>
        </div>
    </div>
</div>

<!-- Desktop Chart Grid -->
<div class="hidden lg:grid lg:grid-cols-2 gap-8 mb-8">
    <!-- Platform Comparison Chart -->
    <div class="bg-gray-900 rounded-lg border border-gray-800 p-6">
        <h3 class="text-lg font-semibold mb-4">Platform Performance</h3>
        <div class="relative">
            <canvas id="platformChartDesktop" class="w-full" style="height: 300px;"></canvas>
        </div>
    </div>
    
    <!-- Best Posting Times Heatmap -->
    <div class="bg-gray-900 rounded-lg border border-gray-800 p-6">
        <h3 class="text-lg font-semibold mb-4">Best Posting Times</h3>
        <div class="relative">
            <div id="postingTimesHeatmapDesktop" style="height: 300px;"></div>
        </div>
    </div>
</div>

<!-- Top Performing Posts & Hashtag Cloud -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 lg:gap-8 mb-6 lg:mb-8">
    <!-- Top Performing Posts -->
    <div class="bg-gray-900 rounded-lg border border-gray-800 p-4 lg:p-6">
        <h3 class="text-lg font-semibold mb-4">Top Performing Posts</h3>
        <div id="topPostsContainer" class="space-y-3 max-h-80 overflow-y-auto">
            <!-- Dynamic content loaded via JavaScript -->
        </div>
    </div>
    
    <!-- Hashtag Performance Cloud -->
    <div class="bg-gray-900 rounded-lg border border-gray-800 p-4 lg:p-6 mobile-chart" id="chart-hashtags" style="display: none;">
        <h3 class="text-lg font-semibold mb-4">Hashtag Performance</h3>
        <div id="hashtagCloud" class="min-h-64 flex items-center justify-center">
            <div class="text-gray-500 text-center">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-500 mx-auto mb-2"></div>
                <p>Loading hashtag data...</p>
            </div>
        </div>
    </div>
</div>

<!-- Desktop Hashtag Cloud -->
<div class="hidden lg:block">
    <div class="bg-gray-900 rounded-lg border border-gray-800 p-6 mb-8">
        <h3 class="text-lg font-semibold mb-4">Hashtag Performance</h3>
        <div id="hashtagCloudDesktop" class="min-h-64 flex items-center justify-center">
            <div class="text-gray-500 text-center">
                <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-500 mx-auto mb-2"></div>
                <p>Loading hashtag data...</p>
            </div>
        </div>
    </div>
</div>

<!-- Audience Demographics -->
<div class="bg-gray-900 rounded-lg border border-gray-800 p-4 lg:p-6 mb-6 lg:mb-8">
    <h3 class="text-lg font-semibold mb-4">Audience Demographics</h3>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Age Distribution -->
        <div>
            <h4 class="text-sm font-medium text-gray-400 mb-3">Age Groups</h4>
            <canvas id="ageChart" style="height: 200px;"></canvas>
        </div>
        
        <!-- Gender Distribution -->
        <div>
            <h4 class="text-sm font-medium text-gray-400 mb-3">Gender</h4>
            <canvas id="genderChart" style="height: 200px;"></canvas>
        </div>
        
        <!-- Top Locations -->
        <div>
            <h4 class="text-sm font-medium text-gray-400 mb-3">Top Locations</h4>
            <div id="locationsContainer" class="space-y-2">
                <!-- Dynamic content -->
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="bg-gray-900 rounded-lg border border-gray-800 p-4 lg:p-6">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg font-semibold">Recent Activity</h3>
        <div class="flex items-center space-x-2 text-sm text-gray-400">
            <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
            <span>Live updates</span>
        </div>
    </div>
    <div id="activityFeed" class="space-y-3 max-h-80 overflow-y-auto">
        <!-- Dynamic content loaded via JavaScript -->
    </div>
</div>

<!-- Progressive Enhancement: Only load analytics when data exists -->
<script src="/assets/js/analytics-loader.js" defer></script>
<script>
// Global variables for backward compatibility
let currentPeriod = '<?= $period ?>';
let currentPlatform = '<?= $platform ?>';

// Chart.js global configuration
Chart.defaults.color = '#9CA3AF';
Chart.defaults.borderColor = '#374151';
Chart.defaults.backgroundColor = 'rgba(139, 92, 246, 0.1)';

// Mobile chart switching function (called from HTML)
function showMobileChart(chartType) {
    if (window.mobileAnalytics) {
        window.mobileAnalytics.showChart(chartType);
    }
}

// Filter update functions
function updateFilters() {
    const periodSelect = document.getElementById('periodFilter');
    const platformSelect = document.getElementById('platformFilter');
    
    if (periodSelect.value === 'custom') {
        document.querySelector('[x-data]').__x.$data.showDatePicker = true;
        return;
    }
    
    currentPeriod = periodSelect.value;
    currentPlatform = platformSelect.value;
    
    const url = new URL(window.location);
    url.searchParams.set('period', currentPeriod);
    url.searchParams.set('platform', currentPlatform);
    window.history.pushState({}, '', url);
    
    if (window.analyticsCharts) {
        window.analyticsCharts.updatePeriod(currentPeriod);
        window.analyticsCharts.updatePlatform(currentPlatform);
    }
}

function applyCustomRange() {
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;
    
    if (dateFrom && dateTo) {
        currentPeriod = `${dateFrom}:${dateTo}`;
        document.querySelector('[x-data]').__x.$data.showDatePicker = false;
        
        const url = new URL(window.location);
        url.searchParams.set('period', currentPeriod);
        url.searchParams.set('platform', currentPlatform);
        window.history.pushState({}, '', url);
        
        if (window.analyticsCharts) {
            window.analyticsCharts.updatePeriod(currentPeriod);
        }
    }
}

function refreshData() {
    if (window.analyticsCharts) {
        window.analyticsCharts.refreshAllData();
    }
}

// Analytics loader will handle all initialization
// Functions are made available globally through the loader

</script>

</div> <!-- End analytics-dashboard -->
<?php endif; // End hasData check ?>

<?php renderFooter(); ?>