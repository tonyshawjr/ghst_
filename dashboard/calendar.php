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

// Get current month/year from query params or use current
$currentMonth = $_GET['month'] ?? date('n');
$currentYear = $_GET['year'] ?? date('Y');

// Validate month/year
$currentMonth = max(1, min(12, intval($currentMonth)));
$currentYear = max(2020, min(2030, intval($currentYear)));

// Calculate navigation dates
$prevMonth = $currentMonth - 1;
$prevYear = $currentYear;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $currentMonth + 1;
$nextYear = $currentYear;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

// Get first day of month and number of days
$firstDay = mktime(0, 0, 0, $currentMonth, 1, $currentYear);
$numDays = date('t', $firstDay);
$firstDayOfWeek = date('w', $firstDay); // 0 = Sunday

// Get posts for the current month
$monthStart = date('Y-m-01', $firstDay);  
$monthEnd = date('Y-m-t', $firstDay);

$stmt = $db->prepare("
    SELECT 
        p.*,
        DATE(p.scheduled_at) as schedule_date,
        TIME(p.scheduled_at) as schedule_time
    FROM posts p
    WHERE p.client_id = ? 
    AND DATE(p.scheduled_at) BETWEEN ? AND ?
    ORDER BY p.scheduled_at ASC
");
$stmt->execute([$client['id'], $monthStart, $monthEnd]);
$posts = $stmt->fetchAll();

// Group posts by date
$postsByDate = [];
foreach ($posts as $post) {
    $date = $post['schedule_date'];
    if (!isset($postsByDate[$date])) {
        $postsByDate[$date] = [];
    }
    $postsByDate[$date][] = $post;
}

// Generate calendar days
$calendarDays = [];
$currentDate = 1;

// Add empty days for previous month
for ($i = 0; $i < $firstDayOfWeek; $i++) {
    $calendarDays[] = null;
}

// Add days for current month
for ($day = 1; $day <= $numDays; $day++) {
    $dateStr = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $day);
    $calendarDays[] = [
        'day' => $day,
        'date' => $dateStr,
        'posts' => $postsByDate[$dateStr] ?? [],
        'isToday' => $dateStr === date('Y-m-d'),
        'isPast' => $dateStr < date('Y-m-d'),
    ];
}

$monthName = date('F Y', $firstDay);

renderHeader('Calendar');
?>

<div class="space-y-4 lg:space-y-6">
    <!-- Page Header - Mobile Optimized -->
    <div class="space-y-3">
        <!-- Title and Desktop Controls -->
        <div class="flex flex-col lg:flex-row lg:justify-between lg:items-center space-y-3 lg:space-y-0">
            <div>
                <h3 class="text-base lg:text-lg font-semibold text-gray-200">Content Calendar</h3>
                <p class="text-gray-400 text-xs lg:text-sm mt-1">View and manage your scheduled posts</p>
            </div>
            
            <!-- Desktop Controls -->
            <div class="hidden lg:flex items-center space-x-4">
                <!-- Month Navigation -->
                <div class="flex items-center space-x-2">
                    <a 
                        href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>"
                        class="p-2 text-gray-400 hover:text-white hover:bg-gray-800 rounded-lg transition-colors"
                        title="Previous month"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                    </a>
                    
                    <h2 class="text-xl font-semibold min-w-[200px] text-center"><?= $monthName ?></h2>
                    
                    <a 
                        href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>"
                        class="p-2 text-gray-400 hover:text-white hover:bg-gray-800 rounded-lg transition-colors"
                        title="Next month"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </div>
                
                <!-- View Toggle -->
                <div class="bg-gray-900 rounded-lg p-1 flex">
                    <button 
                        id="calendarViewBtnDesktop"
                        onclick="switchView('calendar')"
                        class="px-3 py-1 rounded text-sm font-medium transition-colors view-toggle-btn"
                    >
                        Calendar
                    </button>
                    <button 
                        id="agendaViewBtnDesktop"
                        onclick="switchView('agenda')"
                        class="px-3 py-1 rounded text-sm font-medium transition-colors view-toggle-btn"
                    >
                        Agenda
                    </button>
                </div>
                
                <!-- Today Button -->
                <a 
                    href="?month=<?= date('n') ?>&year=<?= date('Y') ?>"
                    class="px-4 py-2 bg-gray-800 hover:bg-gray-700 rounded-lg text-white text-sm transition-colors"
                >
                    Today
                </a>
            </div>
        </div>
        
        <!-- Mobile Controls - Split into two rows -->
        <div class="lg:hidden space-y-2">
            <!-- First Row: Month Navigation -->
            <div class="flex items-center justify-between">
                <a 
                    href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>"
                    class="p-2 text-gray-400 hover:text-white hover:bg-gray-800 rounded-lg transition-colors touch-target touch-feedback"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                </a>
                
                <h2 class="text-base font-semibold flex-1 text-center"><?= $monthName ?></h2>
                
                <a 
                    href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>"
                    class="p-2 text-gray-400 hover:text-white hover:bg-gray-800 rounded-lg transition-colors touch-target touch-feedback"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
            </div>
            
            <!-- Second Row: View Toggle and Today -->
            <div class="flex items-center gap-2">
                <div class="bg-gray-900 rounded-lg p-1 flex flex-1">
                    <button 
                        id="calendarViewBtn"
                        onclick="switchView('calendar')"
                        class="flex-1 px-3 py-1.5 rounded text-xs font-medium transition-colors view-toggle-btn touch-feedback"
                    >
                        Grid
                    </button>
                    <button 
                        id="agendaViewBtn"
                        onclick="switchView('agenda')"
                        class="flex-1 px-3 py-1.5 rounded text-xs font-medium transition-colors view-toggle-btn touch-feedback"
                    >
                        List
                    </button>
                </div>
                
                <a 
                    href="?month=<?= date('n') ?>&year=<?= date('Y') ?>"
                    class="px-4 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg text-white text-xs font-medium transition-colors touch-target touch-feedback"
                >
                    Today
                </a>
            </div>
        </div>
    </div>

    <!-- Calendar Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 lg:gap-4">
        <?php
        $statusCounts = array_count_values(array_column($posts, 'status'));
        $stats = [
            ['label' => 'Total Posts', 'value' => count($posts), 'color' => 'purple'],
            ['label' => 'Scheduled', 'value' => $statusCounts['scheduled'] ?? 0, 'color' => 'blue'],
            ['label' => 'Published', 'value' => $statusCounts['published'] ?? 0, 'color' => 'green'],
            ['label' => 'Failed', 'value' => $statusCounts['failed'] ?? 0, 'color' => 'red'],
        ];
        ?>
        <?php foreach ($stats as $stat): ?>
            <div class="bg-gray-900 rounded-lg p-3 lg:p-4 border border-gray-800 touch-feedback">
                <div class="text-xl lg:text-2xl font-bold text-<?= $stat['color'] ?>-400"><?= $stat['value'] ?></div>
                <div class="text-xs lg:text-sm text-gray-400"><?= $stat['label'] ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Calendar View -->
    <div id="calendarView" class="view-container">
        <div class="bg-gray-900 rounded-lg border border-gray-800 overflow-hidden">
            <!-- Calendar Header -->
            <div class="grid grid-cols-7 border-b border-gray-800">
                <?php 
                $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                $mobileDayNames = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
                foreach ($dayNames as $index => $dayName): 
                ?>
                    <div class="p-2 lg:p-4 text-center text-sm font-medium text-gray-400 border-r border-gray-800 last:border-r-0">
                        <span class="lg:hidden"><?= $mobileDayNames[$index] ?></span>
                        <span class="hidden lg:inline"><?= $dayName ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Calendar Body -->
            <div class="grid grid-cols-7">
                <?php 
                $weekRow = 0;
                foreach ($calendarDays as $index => $day): 
                    $isNewWeek = $index % 7 === 0;
                    if ($isNewWeek && $index > 0) $weekRow++;
                ?>
                    <div class="min-h-[60px] lg:min-h-[120px] p-1 lg:p-2 border-r border-gray-800 border-b border-gray-800 last:border-r-0 <?= $weekRow >= 5 ? 'last:border-b-0' : '' ?> <?= $day === null ? 'bg-gray-950' : ($day['isToday'] ? 'bg-purple-900/20' : 'bg-gray-900') ?> relative group hover:bg-gray-800/50 transition-colors cursor-pointer" <?= $day ? "onclick=\"showDayBottomSheet('{$day['date']}')\"": '' ?>>
                        <?php if ($day !== null): ?>
                            <!-- Day Number -->
                            <div class="flex items-center justify-between mb-1 lg:mb-2">
                                <span class="text-xs lg:text-sm font-medium <?= $day['isToday'] ? 'text-purple-400 bg-purple-500 w-6 h-6 rounded-full flex items-center justify-center text-white' : ($day['isPast'] ? 'text-gray-500' : 'text-gray-300') ?>">
                                    <?= $day['day'] ?>
                                </span>
                                <?php if (count($day['posts']) > 0): ?>
                                    <div class="flex -space-x-1">
                                        <?php foreach (array_slice($day['posts'], 0, 3) as $post): ?>
                                            <?php
                                            $statusColor = [
                                                'draft' => 'gray',
                                                'scheduled' => 'blue', 
                                                'published' => 'green',
                                                'failed' => 'red',
                                            ][$post['status']] ?? 'gray';
                                            ?>
                                            <div class="w-2 h-2 lg:w-3 lg:h-3 bg-<?= $statusColor ?>-500 rounded-full border border-gray-800"></div>
                                        <?php endforeach; ?>
                                        <?php if (count($day['posts']) > 3): ?>
                                            <div class="w-2 h-2 lg:w-3 lg:h-3 bg-gray-600 rounded-full border border-gray-800 flex items-center justify-center">
                                                <span class="text-[6px] lg:text-[8px] text-white font-bold">+</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Posts count for mobile -->
                            <div class="lg:hidden">
                                <?php if (count($day['posts']) > 0): ?>
                                    <div class="text-[10px] text-gray-400 text-center">
                                        <?= count($day['posts']) ?> post<?= count($day['posts']) !== 1 ? 's' : '' ?>
                                    </div>
                                    <div class="flex justify-center mt-1">
                                        <?php
                                        $platforms = [];
                                        foreach ($day['posts'] as $post) {
                                            $postPlatforms = json_decode($post['platforms_json'], true) ?: [];
                                            $platforms = array_merge($platforms, $postPlatforms);
                                        }
                                        $uniquePlatforms = array_unique($platforms);
                                        ?>
                                        <?php foreach (array_slice($uniquePlatforms, 0, 4) as $platform): ?>
                                            <div class="text-gray-400 w-2.5 h-2.5 mr-0.5">
                                                <?= getPlatformIcon($platform) ?>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if (count($uniquePlatforms) > 4): ?>
                                            <span class="text-[8px] text-gray-500">+<?= count($uniquePlatforms) - 4 ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Desktop post previews -->
                            <div class="hidden lg:block space-y-1">
                                <?php 
                                $displayPosts = array_slice($day['posts'], 0, 2);
                                $remainingCount = count($day['posts']) - count($displayPosts);
                                ?>
                                
                                <?php foreach ($displayPosts as $post): ?>
                                    <?php
                                    $statusColor = [
                                        'draft' => 'gray',
                                        'scheduled' => 'blue', 
                                        'published' => 'green',
                                        'failed' => 'red',
                                    ][$post['status']] ?? 'gray';
                                    
                                    $platforms = json_decode($post['platforms_json'], true) ?: [];
                                    ?>
                                    <div 
                                        class="p-2 bg-<?= $statusColor ?>-900/50 border-l-2 border-<?= $statusColor ?>-500 rounded text-xs cursor-pointer hover:bg-<?= $statusColor ?>-900/70 transition-colors"
                                        onclick="event.stopPropagation(); viewPost(<?= $post['id'] ?>)"
                                        title="<?= sanitize($post['content']) ?>"
                                    >
                                        <div class="flex items-center justify-between mb-1">
                                            <span class="text-<?= $statusColor ?>-300 font-medium text-[10px] uppercase">
                                                <?= $post['status'] ?>
                                            </span>
                                            <span class="text-gray-400 text-[10px]">
                                                <?= date('g:i A', strtotime($post['schedule_time'])) ?>
                                            </span>
                                        </div>
                                        
                                        <div class="text-gray-200 mb-1 line-clamp-2">
                                            <?= truncateText(sanitize($post['content']), 50) ?>
                                        </div>
                                        
                                        <div class="flex space-x-1">
                                            <?php foreach (array_slice($platforms, 0, 3) as $platform): ?>
                                                <div class="text-gray-400 w-3 h-3">
                                                    <?= getPlatformIcon($platform) ?>
                                                </div>
                                            <?php endforeach; ?>
                                            <?php if (count($platforms) > 3): ?>
                                                <span class="text-gray-500 text-[10px]">+<?= count($platforms) - 3 ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                
                                <?php if ($remainingCount > 0): ?>
                                    <div class="p-1 text-center">
                                        <span class="text-gray-400 text-[10px] font-medium">
                                            +<?= $remainingCount ?> more
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Agenda/List View -->
    <div id="agendaView" class="view-container hidden">
        <div class="agenda-container" data-pull-to-refresh>
            <!-- Pull to refresh indicator -->
            <div id="pullToRefreshIndicator" class="lg:hidden text-center py-4 text-gray-400 transform -translate-y-full transition-transform duration-300">
                <svg class="w-6 h-6 mx-auto animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                </svg>
                <span class="text-sm mt-2">Pull to refresh</span>
            </div>

            <?php
            // Sort all posts chronologically for agenda view
            $sortedPosts = $posts;
            usort($sortedPosts, function($a, $b) {
                return strtotime($a['scheduled_at']) - strtotime($b['scheduled_at']);
            });
            
            // Group posts by date for agenda view
            $currentDateGroup = null;
            ?>
            
            <?php if (empty($sortedPosts)): ?>
                <div class="text-center py-12 text-gray-500">
                    <svg class="w-12 h-12 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <p class="text-lg font-medium mb-2">No posts scheduled</p>
                    <p class="text-sm">Create your first post to see it here</p>
                </div>
            <?php else: ?>
                <?php foreach ($sortedPosts as $post): ?>
                    <?php
                    $postDate = date('Y-m-d', strtotime($post['scheduled_at']));
                    $showDateHeader = $currentDateGroup !== $postDate;
                    $currentDateGroup = $postDate;
                    
                    $statusColor = [
                        'draft' => 'gray',
                        'scheduled' => 'blue', 
                        'published' => 'green',
                        'failed' => 'red',
                    ][$post['status']] ?? 'gray';
                    
                    $platforms = json_decode($post['platforms_json'], true) ?: [];
                    $isToday = $postDate === date('Y-m-d');
                    $isPast = $postDate < date('Y-m-d');
                    ?>
                    
                    <?php if ($showDateHeader): ?>
                        <div class="sticky top-0 bg-gray-950/95 backdrop-blur-sm py-3 px-4 border-b border-gray-800 z-10 date-header">
                            <div class="flex items-center justify-between">
                                <h3 class="font-semibold <?= $isToday ? 'text-purple-400' : 'text-gray-200' ?>">
                                    <?php if ($isToday): ?>
                                        Today
                                    <?php else: ?>
                                        <?= date('l, F j', strtotime($postDate)) ?>
                                    <?php endif; ?>
                                </h3>
                                <span class="text-sm text-gray-400">
                                    <?= count($postsByDate[$postDate] ?? []) ?> post<?= count($postsByDate[$postDate] ?? []) !== 1 ? 's' : '' ?>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="agenda-post-card bg-gray-900 border border-gray-800 rounded-lg mx-4 mb-4 overflow-hidden swipeable" data-post-id="<?= $post['id'] ?>">
                        <!-- Status border -->
                        <div class="h-1 bg-<?= $statusColor ?>-500"></div>
                        
                        <div class="p-4">
                            <!-- Header -->
                            <div class="flex items-start justify-between mb-3">
                                <div class="flex items-center space-x-3">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-<?= $statusColor ?>-900 text-<?= $statusColor ?>-300">
                                        <?= ucfirst($post['status']) ?>
                                    </span>
                                    <div class="text-lg font-semibold text-white">
                                        <?= date('g:i A', strtotime($post['scheduled_at'])) ?>
                                    </div>
                                </div>
                                
                                <div class="flex items-center space-x-2">
                                    <?php foreach ($platforms as $platform): ?>
                                        <div class="text-gray-400 w-5 h-5">
                                            <?= getPlatformIcon($platform) ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Content -->
                            <div class="text-gray-200 mb-4 leading-relaxed">
                                <?= nl2br(sanitize($post['content'])) ?>
                            </div>
                            
                            <!-- Footer -->
                            <div class="flex items-center justify-between text-sm text-gray-500">
                                <span><?= strlen($post['content']) ?> characters</span>
                                <div class="flex items-center space-x-3">
                                    <button 
                                        onclick="editPost(<?= $post['id'] ?>)"
                                        class="text-purple-400 hover:text-purple-300 font-medium touch-feedback"
                                    >
                                        Edit
                                    </button>
                                    <button 
                                        onclick="deletePost(<?= $post['id'] ?>)"
                                        class="text-red-400 hover:text-red-300 font-medium touch-feedback"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Swipe actions overlay -->
                        <div class="swipe-actions absolute inset-0 flex">
                            <div class="swipe-action-left flex-1 bg-purple-600 flex items-center justify-start pl-6">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                            </div>
                            <div class="swipe-action-right flex-1 bg-red-600 flex items-center justify-end pr-6">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Legend (Desktop only) -->
    <div class="hidden lg:flex items-center justify-center space-x-6 text-sm">
        <div class="flex items-center space-x-2">
            <div class="w-3 h-3 bg-gray-600 rounded"></div>
            <span class="text-gray-400">Draft</span>
        </div>
        <div class="flex items-center space-x-2">
            <div class="w-3 h-3 bg-blue-600 rounded"></div>
            <span class="text-gray-400">Scheduled</span>
        </div>
        <div class="flex items-center space-x-2">
            <div class="w-3 h-3 bg-green-600 rounded"></div>
            <span class="text-gray-400">Published</span>
        </div>
        <div class="flex items-center space-x-2">
            <div class="w-3 h-3 bg-red-600 rounded"></div>
            <span class="text-gray-400">Failed</span>
        </div>
    </div>
</div>

<!-- Floating Action Button (Mobile) -->
<button 
    id="fabBtn"
    onclick="createNewPost()"
    class="lg:hidden fixed bottom-6 right-6 w-14 h-14 bg-purple-600 hover:bg-purple-700 rounded-full shadow-lg flex items-center justify-center z-40 touch-feedback transform transition-all duration-300 hover:scale-110"
    title="Create new post"
>
    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
    </svg>
</button>

<!-- Mini Calendar Modal -->
<div id="miniCalendarModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-gray-900 rounded-lg border border-gray-800 p-6 max-w-sm w-full">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-semibold">Jump to Date</h3>
            <button onclick="hideMiniCalendar()" class="text-gray-400 hover:text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <div class="grid grid-cols-3 gap-2 mb-4">
            <?php 
            $months = [
                1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr',
                5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Aug',
                9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
            ];
            foreach ($months as $monthNum => $monthName):
            ?>
                <button 
                    onclick="jumpToMonth(<?= $monthNum ?>, <?= $currentYear ?>)"
                    class="p-2 text-center rounded <?= $monthNum === $currentMonth ? 'bg-purple-600 text-white' : 'bg-gray-800 text-gray-300 hover:bg-gray-700' ?> transition-colors"
                >
                    <?= $monthName ?>
                </button>
            <?php endforeach; ?>
        </div>
        
        <div class="flex items-center justify-between">
            <button 
                onclick="jumpToMonth(<?= $currentMonth ?>, <?= $currentYear - 1 ?>)"
                class="p-2 text-gray-400 hover:text-white"
            >
                <?= $currentYear - 1 ?>
            </button>
            <span class="font-semibold"><?= $currentYear ?></span>
            <button 
                onclick="jumpToMonth(<?= $currentMonth ?>, <?= $currentYear + 1 ?>)"
                class="p-2 text-gray-400 hover:text-white"
            >
                <?= $currentYear + 1 ?>
            </button>
        </div>
    </div>
</div>

<!-- Day Bottom Sheet (Mobile) -->
<div id="dayBottomSheet" class="fixed inset-0 bg-black bg-opacity-50 hidden z-40 lg:hidden">
    <div class="absolute bottom-0 left-0 right-0 bg-gray-900 rounded-t-xl border-t border-gray-800 max-h-[80vh] overflow-hidden transform translate-y-full transition-transform duration-300" id="bottomSheetContent">
        <div class="p-4 border-b border-gray-800">
            <div class="flex items-center justify-between">
                <h3 id="bottomSheetTitle" class="text-lg font-semibold">Posts for [Date]</h3>
                <button onclick="hideDayBottomSheet()" class="text-gray-400 hover:text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <!-- Handle for swipe down -->
            <div class="w-12 h-1 bg-gray-600 rounded-full mx-auto mt-2"></div>
        </div>
        
        <div id="bottomSheetPostsList" class="p-4 overflow-y-auto max-h-[60vh]">
            <!-- Posts will be loaded here -->
        </div>
        
        <div class="p-4 border-t border-gray-800">
            <button 
                id="addPostForDateBtn"
                onclick="createPostForSelectedDate()"
                class="w-full py-3 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-medium transition-colors touch-feedback"
            >
                Add Post for This Date
            </button>
        </div>
    </div>
</div>

<!-- Day Posts Modal (Desktop) -->
<div id="dayPostsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-gray-900 rounded-lg border border-gray-800 p-4 lg:p-6 max-w-2xl w-full max-h-[80vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-6">
            <h3 id="dayPostsTitle" class="text-lg font-semibold">Posts for [Date]</h3>
            <button onclick="hideDayPostsModal()" class="text-gray-400 hover:text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <div id="dayPostsList" class="space-y-4">
            <!-- Posts will be loaded here -->
        </div>
    </div>
</div>

<style>
.line-clamp-1 {
    display: -webkit-box;
    -webkit-line-clamp: 1;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* View transitions */
.view-container {
    transition: opacity 0.3s ease, transform 0.3s ease;
}

.view-container.hidden {
    opacity: 0;
    transform: translateY(10px);
    pointer-events: none;
}

/* View toggle buttons */
.view-toggle-btn {
    position: relative;
    z-index: 1;
}

.view-toggle-btn.active {
    background: #7c3aed;
    color: white;
}

.view-toggle-btn:not(.active) {
    color: #9ca3af;
}

.view-toggle-btn:not(.active):hover {
    color: white;
}

/* Touch feedback */
.touch-feedback:active {
    transform: scale(0.95);
}

/* Swipe cards */
.swipeable {
    position: relative;
    transition: transform 0.3s ease;
}

.swipe-actions {
    opacity: 0;
    transition: opacity 0.3s ease;
    z-index: -1;
}

.swipeable.swiping .swipe-actions {
    opacity: 1;
    z-index: 1;
}

.swipe-action-left,
.swipe-action-right {
    display: flex;
    align-items: center;
    color: white;
    font-weight: 600;
}

/* Sticky date headers */
.date-header {
    backdrop-filter: blur(8px);
}

/* Floating Action Button */
#fabBtn {
    box-shadow: 0 8px 25px rgba(124, 58, 237, 0.3);
}

#fabBtn:hover {
    box-shadow: 0 12px 35px rgba(124, 58, 237, 0.4);
}

/* Bottom sheet */
#dayBottomSheet.show #bottomSheetContent {
    transform: translateY(0);
}

/* Pull to refresh */
.agenda-container {
    position: relative;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}

/* Responsive improvements */
@media (max-width: 768px) {
    .agenda-post-card {
        margin-left: 1rem;
        margin-right: 1rem;
    }
    
    /* Hide FAB when agenda view is active */
    body.agenda-view #fabBtn {
        transform: scale(0);
        opacity: 0;
    }
    
    /* Improve touch targets */
    .touch-target {
        min-height: 44px;
        min-width: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
}

/* Animations */
@keyframes slideUp {
    from {
        transform: translateY(100%);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

@keyframes slideDown {
    from {
        transform: translateY(0);
        opacity: 1;
    }
    to {
        transform: translateY(100%);
        opacity: 0;
    }
}

.slide-up {
    animation: slideUp 0.3s ease;
}

.slide-down {
    animation: slideDown 0.3s ease;
}

/* Haptic feedback simulation */
@keyframes haptic {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-1px); }
    75% { transform: translateX(1px); }
}

.haptic-feedback {
    animation: haptic 0.1s ease;
}
</style>

<script>
// Global variables
let currentView = window.innerWidth < 768 ? 'agenda' : 'calendar';
let selectedDate = null;
let swipeStartX = null;
let swipeStartY = null;
let isSwipingHorizontally = false;
let pullStartY = null;
let isPullingToRefresh = false;

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initializeView();
    setupSwipeGestures();
    setupPullToRefresh();
    setupViewToggle();
    
    // Add haptic feedback for supported devices
    if ('vibrate' in navigator) {
        document.addEventListener('touchstart', function(e) {
            if (e.target.closest('.touch-feedback')) {
                navigator.vibrate(10);
            }
        });
    }
});

function initializeView() {
    if (window.innerWidth < 768) {
        switchView('agenda');
    } else {
        switchView('calendar');
    }
}

function switchView(view) {
    currentView = view;
    
    const calendarView = document.getElementById('calendarView');
    const agendaView = document.getElementById('agendaView');
    
    // Get both mobile and desktop buttons
    const calendarBtns = [
        document.getElementById('calendarViewBtn'),
        document.getElementById('calendarViewBtnDesktop')
    ].filter(Boolean);
    
    const agendaBtns = [
        document.getElementById('agendaViewBtn'),
        document.getElementById('agendaViewBtnDesktop')
    ].filter(Boolean);
    
    if (view === 'calendar') {
        calendarView.classList.remove('hidden');
        agendaView.classList.add('hidden');
        calendarBtns.forEach(btn => btn?.classList.add('active'));
        agendaBtns.forEach(btn => btn?.classList.remove('active'));
        document.body.classList.remove('agenda-view');
    } else {
        calendarView.classList.add('hidden');
        agendaView.classList.remove('hidden');
        calendarBtns.forEach(btn => btn?.classList.remove('active'));
        agendaBtns.forEach(btn => btn?.classList.add('active'));
        document.body.classList.add('agenda-view');
    }
    
    // Save preference
    localStorage.setItem('calendarView', view);
}

function setupViewToggle() {
    // Load saved preference
    const savedView = localStorage.getItem('calendarView');
    if (savedView && window.innerWidth >= 768) {
        switchView(savedView);
    }
}

function viewPost(postId) {
    // TODO: Implement post detail view
    window.location.href = `/dashboard/posts.php?action=edit&id=${postId}`;
}

function editPost(postId) {
    addHapticFeedback();
    window.location.href = `/dashboard/posts.php?action=edit&id=${postId}`;
}

function deletePost(postId) {
    if (confirm('Are you sure you want to delete this post?')) {
        addHapticFeedback();
        // TODO: Implement delete functionality
        alert('Delete functionality not yet implemented');
    }
}

function createNewPost() {
    addHapticFeedback();
    const now = new Date();
    const localDateTime = new Date(now.getTime() - now.getTimezoneOffset() * 60000)
        .toISOString().slice(0, 16);
    
    window.location.href = `/dashboard/posts.php?action=new&date=${localDateTime}`;
}

function createPostForDate(date) {
    addHapticFeedback();
    const dateObj = new Date(date + 'T12:00:00');
    const localDateTime = new Date(dateObj.getTime() - dateObj.getTimezoneOffset() * 60000)
        .toISOString().slice(0, 16);
    
    window.location.href = `/dashboard/posts.php?action=new&date=${localDateTime}`;
}

function createPostForSelectedDate() {
    if (selectedDate) {
        createPostForDate(selectedDate);
    }
}

// Mobile bottom sheet functions
function showDayBottomSheet(date) {
    if (window.innerWidth >= 768) {
        showDayPosts(date);
        return;
    }
    
    selectedDate = date;
    const posts = <?= json_encode($postsByDate) ?>[date] || [];
    
    document.getElementById('bottomSheetTitle').textContent = 
        `Posts for ${new Date(date + 'T00:00:00').toLocaleDateString('en-US', { 
            weekday: 'long', 
            month: 'long', 
            day: 'numeric' 
        })}`;
    
    const postsList = document.getElementById('bottomSheetPostsList');
    
    if (posts.length === 0) {
        postsList.innerHTML = '<div class="text-center py-8 text-gray-500"><svg class="w-12 h-12 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg><p>No posts scheduled</p></div>';
    } else {
        postsList.innerHTML = posts.map(post => {
            const platforms = JSON.parse(post.platforms_json || '[]');
            const statusColors = {
                'draft': 'gray',
                'scheduled': 'blue',
                'published': 'green',
                'failed': 'red'
            };
            const statusColor = statusColors[post.status] || 'gray';
            
            return `
                <div class="bg-gray-800 rounded-lg p-4 border border-gray-700 mb-4">
                    <div class="h-1 bg-${statusColor}-500 rounded-t-lg -mx-4 -mt-4 mb-3"></div>
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center space-x-3">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-${statusColor}-900 text-${statusColor}-300">
                                ${post.status.charAt(0).toUpperCase() + post.status.slice(1)}
                            </span>
                            <span class="text-lg font-semibold text-white">
                                ${new Date(post.scheduled_at).toLocaleTimeString('en-US', { 
                                    hour: 'numeric', 
                                    minute: '2-digit' 
                                })}
                            </span>
                        </div>
                        <div class="flex space-x-2">
                            ${platforms.map(platform => 
                                `<div class="text-gray-400 w-5 h-5">${getPlatformIconSVG(platform)}</div>`
                            ).join('')}
                        </div>
                    </div>
                    
                    <p class="text-gray-200 mb-4 leading-relaxed">${post.content}</p>
                    
                    <div class="flex items-center justify-between text-sm text-gray-500">
                        <span>${post.content.length} characters</span>
                        <div class="flex items-center space-x-3">
                            <button 
                                onclick="editPost(${post.id})"
                                class="text-purple-400 hover:text-purple-300 font-medium touch-feedback"
                            >
                                Edit
                            </button>
                            <button 
                                onclick="deletePost(${post.id})"
                                class="text-red-400 hover:text-red-300 font-medium touch-feedback"
                            >
                                Delete
                            </button>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }
    
    const bottomSheet = document.getElementById('dayBottomSheet');
    bottomSheet.classList.remove('hidden');
    bottomSheet.classList.add('show');
}

function hideDayBottomSheet() {
    const bottomSheet = document.getElementById('dayBottomSheet');
    const content = document.getElementById('bottomSheetContent');
    
    content.style.transform = 'translateY(100%)';
    
    setTimeout(() => {
        bottomSheet.classList.add('hidden');
        bottomSheet.classList.remove('show');
        content.style.transform = '';
        selectedDate = null;
    }, 300);
}

// Desktop modal functions
function showDayPosts(date) {
    const posts = <?= json_encode($postsByDate) ?>[date] || [];
    
    document.getElementById('dayPostsTitle').textContent = 
        `Posts for ${new Date(date + 'T00:00:00').toLocaleDateString('en-US', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        })}`;
    
    const postsList = document.getElementById('dayPostsList');
    
    if (posts.length === 0) {
        postsList.innerHTML = '<p class="text-gray-500 text-center py-8">No posts scheduled for this date</p>';
    } else {
        postsList.innerHTML = posts.map(post => {
            const platforms = JSON.parse(post.platforms_json || '[]');
            const statusColors = {
                'draft': 'gray',
                'scheduled': 'blue',
                'published': 'green',
                'failed': 'red'
            };
            const statusColor = statusColors[post.status] || 'gray';
            
            return `
                <div class="bg-gray-800 rounded-lg p-4 border border-gray-700">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center space-x-3">
                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-${statusColor}-900 text-${statusColor}-300">
                                ${post.status.charAt(0).toUpperCase() + post.status.slice(1)}
                            </span>
                            <span class="text-sm text-gray-400">
                                ${new Date(post.scheduled_at).toLocaleTimeString('en-US', { 
                                    hour: 'numeric', 
                                    minute: '2-digit' 
                                })}
                            </span>
                        </div>
                        <div class="flex space-x-1">
                            ${platforms.map(platform => 
                                `<div class="text-gray-400 w-4 h-4">${getPlatformIconSVG(platform)}</div>`
                            ).join('')}
                        </div>
                    </div>
                    
                    <p class="text-gray-200 mb-3">${post.content}</p>
                    
                    <div class="flex items-center justify-between text-sm text-gray-500">
                        <span>${post.content.length} characters</span>
                        <button 
                            onclick="viewPost(${post.id})"
                            class="text-purple-400 hover:text-purple-300"
                        >
                            View Details
                        </button>
                    </div>
                </div>
            `;
        }).join('');
    }
    
    document.getElementById('dayPostsModal').classList.remove('hidden');
    document.getElementById('dayPostsModal').classList.add('flex');
}

function hideDayPostsModal() {
    document.getElementById('dayPostsModal').classList.add('hidden');
    document.getElementById('dayPostsModal').classList.remove('flex');
}

// Mini calendar functions
function showMiniCalendar() {
    addHapticFeedback();
    document.getElementById('miniCalendarModal').classList.remove('hidden');
    document.getElementById('miniCalendarModal').classList.add('flex');
}

function hideMiniCalendar() {
    document.getElementById('miniCalendarModal').classList.add('hidden');
    document.getElementById('miniCalendarModal').classList.remove('flex');
}

function jumpToMonth(month, year) {
    addHapticFeedback();
    window.location.href = `?month=${month}&year=${year}`;
}

// Swipe gesture setup
function setupSwipeGestures() {
    const agendaContainer = document.querySelector('.agenda-container');
    if (!agendaContainer) return;
    
    agendaContainer.addEventListener('touchstart', handleTouchStart, { passive: false });
    agendaContainer.addEventListener('touchmove', handleTouchMove, { passive: false });
    agendaContainer.addEventListener('touchend', handleTouchEnd, { passive: false });
}

function handleTouchStart(e) {
    swipeStartX = e.touches[0].clientX;
    swipeStartY = e.touches[0].clientY;
    isSwipingHorizontally = false;
    
    const postCard = e.target.closest('.swipeable');
    if (postCard) {
        postCard.style.transition = 'none';
    }
}

function handleTouchMove(e) {
    if (!swipeStartX || !swipeStartY) return;
    
    const currentX = e.touches[0].clientX;
    const currentY = e.touches[0].clientY;
    const deltaX = currentX - swipeStartX;
    const deltaY = currentY - swipeStartY;
    
    // Determine if this is a horizontal swipe
    if (Math.abs(deltaX) > Math.abs(deltaY) && Math.abs(deltaX) > 10 && !isSwipingHorizontally) {
        isSwipingHorizontally = true;
        const postCard = e.target.closest('.swipeable');
        if (postCard) {
            postCard.classList.add('swiping');
        }
    }
    
    if (isSwipingHorizontally) {
        e.preventDefault();
        const postCard = e.target.closest('.swipeable');
        if (postCard) {
            const maxSwipe = 100;
            const constrainedDelta = Math.max(-maxSwipe, Math.min(maxSwipe, deltaX));
            postCard.style.transform = `translateX(${constrainedDelta}px)`;
            
            // Show appropriate action based on swipe direction
            const leftAction = postCard.querySelector('.swipe-action-left');
            const rightAction = postCard.querySelector('.swipe-action-right');
            
            if (deltaX > 30) {
                leftAction.style.opacity = Math.min(1, (deltaX - 30) / 50);
                rightAction.style.opacity = 0;
            } else if (deltaX < -30) {
                rightAction.style.opacity = Math.min(1, (-deltaX - 30) / 50);
                leftAction.style.opacity = 0;
            } else {
                leftAction.style.opacity = 0;
                rightAction.style.opacity = 0;
            }
        }
    }
}

function handleTouchEnd(e) {
    if (!swipeStartX || !swipeStartY) return;
    
    const postCard = e.target.closest('.swipeable');
    if (postCard && isSwipingHorizontally) {
        const currentX = e.changedTouches[0].clientX;
        const deltaX = currentX - swipeStartX;
        
        postCard.style.transition = 'transform 0.3s ease';
        postCard.classList.remove('swiping');
        
        // Trigger action if swipe is significant enough
        if (deltaX > 60) {
            // Swipe right - edit
            addHapticFeedback('heavy');
            const postId = postCard.dataset.postId;
            editPost(postId);
        } else if (deltaX < -60) {
            // Swipe left - delete
            addHapticFeedback('heavy');
            const postId = postCard.dataset.postId;
            deletePost(postId);
        }
        
        // Reset position
        postCard.style.transform = 'translateX(0)';
        
        // Hide action overlays
        setTimeout(() => {
            const leftAction = postCard.querySelector('.swipe-action-left');
            const rightAction = postCard.querySelector('.swipe-action-right');
            if (leftAction) leftAction.style.opacity = 0;
            if (rightAction) rightAction.style.opacity = 0;
        }, 300);
    }
    
    swipeStartX = null;
    swipeStartY = null;
    isSwipingHorizontally = false;
}

// Pull to refresh setup
function setupPullToRefresh() {
    const agendaContainer = document.querySelector('.agenda-container');
    if (!agendaContainer) return;
    
    agendaContainer.addEventListener('touchstart', handlePullStart, { passive: false });
    agendaContainer.addEventListener('touchmove', handlePullMove, { passive: false });
    agendaContainer.addEventListener('touchend', handlePullEnd, { passive: false });
}

function handlePullStart(e) {
    if (e.target.closest('.agenda-container').scrollTop === 0) {
        pullStartY = e.touches[0].clientY;
    }
}

function handlePullMove(e) {
    if (!pullStartY) return;
    
    const agendaContainer = e.target.closest('.agenda-container');
    if (agendaContainer.scrollTop > 0) {
        pullStartY = null;
        return;
    }
    
    const currentY = e.touches[0].clientY;
    const deltaY = currentY - pullStartY;
    
    if (deltaY > 0 && deltaY < 100) {
        e.preventDefault();
        const indicator = document.getElementById('pullToRefreshIndicator');
        const pullProgress = Math.min(deltaY / 80, 1);
        
        indicator.style.transform = `translateY(${-100 + (pullProgress * 100)}%)`;
        indicator.style.opacity = pullProgress;
        
        if (deltaY > 60 && !isPullingToRefresh) {
            isPullingToRefresh = true;
            addHapticFeedback('medium');
        }
    }
}

function handlePullEnd(e) {
    if (!pullStartY) return;
    
    const indicator = document.getElementById('pullToRefreshIndicator');
    
    if (isPullingToRefresh) {
        // Trigger refresh
        addHapticFeedback('heavy');
        setTimeout(() => {
            window.location.reload();
        }, 500);
    } else {
        // Reset indicator
        indicator.style.transform = 'translateY(-100%)';
        indicator.style.opacity = 0;
    }
    
    pullStartY = null;
    isPullingToRefresh = false;
}

function addHapticFeedback(intensity = 'light') {
    if ('vibrate' in navigator) {
        const patterns = {
            light: 10,
            medium: 20,
            heavy: 50
        };
        navigator.vibrate(patterns[intensity] || 10);
    }
    
    // Visual feedback for non-haptic devices
    const activeElement = document.activeElement;
    if (activeElement && activeElement.classList.contains('touch-feedback')) {
        activeElement.classList.add('haptic-feedback');
        setTimeout(() => {
            activeElement.classList.remove('haptic-feedback');
        }, 100);
    }
}

function getPlatformIconSVG(platform) {
    const icons = {
        'instagram': '<svg class="w-full h-full" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zM5.838 12a6.162 6.162 0 1112.324 0 6.162 6.162 0 01-12.324 0zM12 16a4 4 0 110-8 4 4 0 010 8zm4.965-10.405a1.44 1.44 0 112.881.001 1.44 1.44 0 01-2.881-.001z"/></svg>',
        'facebook': '<svg class="w-full h-full" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
        'linkedin': '<svg class="w-full h-full" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>',
        'twitter': '<svg class="w-full h-full" fill="currentColor" viewBox="0 0 24 24"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/></svg>'
    };
    return icons[platform] || '<svg class="w-full h-full" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg>';
}

// Close modals on escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        hideDayPostsModal();
        hideMiniCalendar();
        hideDayBottomSheet();
    }
});

// Handle window resize
window.addEventListener('resize', function() {
    if (window.innerWidth >= 768 && currentView === 'agenda') {
        // Don't auto-switch on desktop, but ensure proper layout
        return;
    }
    
    if (window.innerWidth < 768 && currentView === 'calendar') {
        switchView('agenda');
    }
});

// Close bottom sheet on background click
document.getElementById('dayBottomSheet').addEventListener('click', function(e) {
    if (e.target === this) {
        hideDayBottomSheet();
    }
});
</script>

<?php renderFooter(); ?>