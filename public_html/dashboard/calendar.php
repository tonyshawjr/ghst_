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

<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex justify-between items-center">
        <div>
            <h3 class="text-lg font-semibold text-gray-200">Content Calendar</h3>
            <p class="text-gray-400 text-sm mt-1">View and manage your scheduled posts</p>
        </div>
        
        <div class="flex items-center space-x-4">
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
            <div class="flex bg-gray-900 rounded-lg p-1">
                <button class="px-3 py-1 bg-purple-600 text-white rounded text-sm font-medium">
                    Month
                </button>
                <button class="px-3 py-1 text-gray-400 hover:text-white rounded text-sm font-medium transition-colors">
                    Week
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

    <!-- Calendar Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
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
            <div class="bg-gray-900 rounded-lg p-4 border border-gray-800">
                <div class="text-2xl font-bold text-<?= $stat['color'] ?>-400"><?= $stat['value'] ?></div>
                <div class="text-sm text-gray-400"><?= $stat['label'] ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Calendar Grid -->
    <div class="bg-gray-900 rounded-lg border border-gray-800 overflow-hidden">
        <!-- Calendar Header -->
        <div class="grid grid-cols-7 border-b border-gray-800">
            <?php 
            $dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            foreach ($dayNames as $dayName): 
            ?>
                <div class="p-4 text-center text-sm font-medium text-gray-400 border-r border-gray-800 last:border-r-0">
                    <?= $dayName ?>
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
                <div class="min-h-[120px] p-2 border-r border-gray-800 border-b border-gray-800 last:border-r-0 <?= $weekRow >= 5 ? 'last:border-b-0' : '' ?> <?= $day === null ? 'bg-gray-950' : ($day['isToday'] ? 'bg-purple-900/20' : 'bg-gray-900') ?>">
                    <?php if ($day !== null): ?>
                        <!-- Day Number -->
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium <?= $day['isToday'] ? 'text-purple-400' : ($day['isPast'] ? 'text-gray-500' : 'text-gray-300') ?>">
                                <?= $day['day'] ?>
                            </span>
                            <?php if ($day['isToday']): ?>
                                <span class="w-2 h-2 bg-purple-500 rounded-full"></span>
                            <?php endif; ?>
                        </div>

                        <!-- Posts for this day -->
                        <div class="space-y-1">
                            <?php 
                            $displayPosts = array_slice($day['posts'], 0, 3); // Show max 3 posts
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
                                    class="p-2 bg-<?= $statusColor ?>-900/50 border border-<?= $statusColor ?>-700 rounded text-xs cursor-pointer hover:bg-<?= $statusColor ?>-900/70 transition-colors"
                                    onclick="viewPost(<?= $post['id'] ?>)"
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
                                    <button 
                                        onclick="showDayPosts('<?= $day['date'] ?>')"
                                        class="text-gray-400 hover:text-white text-[10px] font-medium"
                                    >
                                        +<?= $remainingCount ?> more
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Add Post Button (for future dates) -->
                        <?php if (!$day['isPast']): ?>
                            <button 
                                onclick="createPostForDate('<?= $day['date'] ?>')"
                                class="w-full mt-1 p-1 text-gray-500 hover:text-purple-400 hover:bg-purple-900/20 rounded text-[10px] font-medium transition-colors opacity-0 hover:opacity-100 group-hover:opacity-100"
                                title="Add post for this date"
                            >
                                + Add Post
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Legend -->
    <div class="flex items-center justify-center space-x-6 text-sm">
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

<!-- Day Posts Modal -->
<div id="dayPostsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-gray-900 rounded-lg border border-gray-800 p-6 max-w-2xl w-full mx-4 max-h-[80vh] overflow-y-auto">
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
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.calendar-day:hover .add-post-btn {
    opacity: 1;
}
</style>

<script>
function viewPost(postId) {
    // TODO: Implement post detail view
    alert('Post detail view not yet implemented. Post ID: ' + postId);
}

function createPostForDate(date) {
    // Redirect to posts page with pre-filled date
    const dateObj = new Date(date + 'T12:00:00');
    const localDateTime = new Date(dateObj.getTime() - dateObj.getTimezoneOffset() * 60000)
        .toISOString().slice(0, 16);
    
    window.location.href = `/dashboard/posts.php?action=new&date=${localDateTime}`;
}

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

function getPlatformIconSVG(platform) {
    const icons = {
        'instagram': '<svg class="w-full h-full" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zM5.838 12a6.162 6.162 0 1112.324 0 6.162 6.162 0 01-12.324 0zM12 16a4 4 0 110-8 4 4 0 010 8zm4.965-10.405a1.44 1.44 0 112.881.001 1.44 1.44 0 01-2.881-.001z"/></svg>',
        'facebook': '<svg class="w-full h-full" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
        'linkedin': '<svg class="w-full h-full" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>',
        'twitter': '<svg class="w-full h-full" fill="currentColor" viewBox="0 0 24 24"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/></svg>'
    };
    return icons[platform] || '<svg class="w-full h-full" fill="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/></svg>';
}

// Close modal on escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        hideDayPostsModal();
    }
});
</script>

<?php renderFooter(); ?>