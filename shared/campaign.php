<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/functions.php';

// Get share token from URL
$shareToken = $_GET['token'] ?? '';
if (empty($shareToken)) {
    http_response_code(404);
    die('Share link not found');
}

$db = Database::getInstance();

// Get share link details
$stmt = $db->prepare("
    SELECT csl.*, sc.title as campaign_title, sc.description as campaign_description,
           sc.total_weeks, sc.start_date, sc.status as campaign_status,
           c.name as client_name
    FROM campaign_share_links csl
    JOIN strategy_campaigns sc ON csl.campaign_id = sc.id
    JOIN clients c ON sc.client_id = c.id
    WHERE csl.share_token = ? AND csl.is_active = 1
");
$stmt->execute([$shareToken]);
$shareLink = $stmt->fetch();

if (!$shareLink) {
    http_response_code(404);
    die('Share link not found or has been disabled');
}

// Check if link has expired
if ($shareLink['expires_at'] && strtotime($shareLink['expires_at']) < time()) {
    http_response_code(410);
    die('This share link has expired');
}

// Check view limits
if ($shareLink['max_views'] && $shareLink['view_count'] >= $shareLink['max_views']) {
    http_response_code(429);
    die('This share link has reached its view limit');
}

// Check IP restrictions
if ($shareLink['ip_whitelist']) {
    $allowedIPs = json_decode($shareLink['ip_whitelist'], true);
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
    
    if (!in_array($clientIP, $allowedIPs)) {
        http_response_code(403);
        die('Access denied from your IP address');
    }
}

// Handle password protection
$passwordRequired = !empty($shareLink['password_hash']);
$passwordValid = false;
$passwordError = false;

if ($passwordRequired) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $enteredPassword = $_POST['password'] ?? '';
        if (!empty($enteredPassword) && password_verify($enteredPassword, $shareLink['password_hash'])) {
            $passwordValid = true;
            // Set session to remember password for this share link
            session_start();
            $_SESSION['share_password_' . $shareLink['id']] = true;
        } else {
            $passwordError = true;
        }
    } else {
        session_start();
        $passwordValid = isset($_SESSION['share_password_' . $shareLink['id']]);
    }
    
    if (!$passwordValid) {
        // Show password form
        ?>
        <!DOCTYPE html>
        <html lang="en" class="bg-black">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Password Required - <?= sanitize($shareLink['title']) ?></title>
            <script src="https://cdn.tailwindcss.com"></script>
            <script>
                tailwind.config = {
                    darkMode: 'class',
                    theme: {
                        extend: {
                            fontFamily: {
                                mono: ['JetBrains Mono', 'monospace']
                            }
                        }
                    }
                }
            </script>
            <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
        </head>
        <body class="bg-black text-white font-mono min-h-screen flex items-center justify-center">
            <div class="max-w-md w-full mx-auto p-6">
                <div class="text-center mb-8">
                    <div class="w-16 h-16 bg-purple-600 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                    </div>
                    <h1 class="text-2xl font-bold mb-2">Password Required</h1>
                    <p class="text-gray-400">Enter the password to view this strategy</p>
                </div>
                
                <form method="POST" class="space-y-4">
                    <div>
                        <input 
                            type="password" 
                            name="password" 
                            placeholder="Enter password"
                            required
                            autofocus
                            class="w-full px-4 py-3 bg-gray-900 border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500 text-white"
                        >
                    </div>
                    
                    <?php if ($passwordError): ?>
                    <div class="text-red-400 text-sm">
                        Incorrect password. Please try again.
                    </div>
                    <?php endif; ?>
                    
                    <button 
                        type="submit" 
                        class="w-full py-3 bg-purple-600 hover:bg-purple-700 rounded-lg font-medium transition-colors"
                    >
                        Access Strategy
                    </button>
                </form>
                
                <div class="text-center mt-8">
                    <p class="text-gray-500 text-sm">Powered by ghst_wrtr</p>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// Log access
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$referrer = $_SERVER['HTTP_REFERER'] ?? '';
$clientIP = $_SERVER['REMOTE_ADDR'] ?? '';

// Detect device type
$deviceType = 'desktop';
if (preg_match('/(Mobile|Android|iPhone|iPad)/', $userAgent)) {
    $deviceType = preg_match('/(iPad)/', $userAgent) ? 'tablet' : 'mobile';
}

// Log the access
$stmt = $db->prepare("
    INSERT INTO campaign_share_access_logs (
        share_link_id, access_type, ip_address, user_agent, referrer,
        device_type, success, created_at
    ) VALUES (?, 'view', ?, ?, ?, ?, 1, NOW())
");
$stmt->execute([
    $shareLink['id'],
    $clientIP,
    $userAgent,
    $referrer,
    $deviceType
]);

// Update view count
$stmt = $db->prepare("
    UPDATE campaign_share_links 
    SET view_count = view_count + 1, last_accessed = NOW() 
    WHERE id = ?
");
$stmt->execute([$shareLink['id']]);

// Get campaign data based on permissions
$campaignId = $shareLink['campaign_id'];

// Get weeks if allowed
$weeks = [];
if ($shareLink['allow_week_expansion']) {
    $stmt = $db->prepare("
        SELECT cw.*, 
               COUNT(cwp.id) as posts_count,
               COUNT(CASE WHEN cwp.status = 'published' THEN 1 END) as published_count
        FROM campaign_weeks cw
        LEFT JOIN campaign_week_posts cwp ON cw.id = cwp.campaign_week_id
        WHERE cw.campaign_id = ?
        GROUP BY cw.id
        ORDER BY cw.week_number ASC
    ");
    $stmt->execute([$campaignId]);
    $weeks = $stmt->fetchAll();
}

// Get analytics if allowed
$analytics = null;
if ($shareLink['allow_analytics_view']) {
    $stmt = $db->prepare("
        SELECT 
            SUM(total_posts) as total_posts,
            AVG(avg_engagement_rate) as avg_engagement_rate,
            SUM(total_engagement) as total_engagement,
            SUM(total_impressions) as total_impressions
        FROM campaign_analytics 
        WHERE campaign_id = ? AND week_number IS NULL
    ");
    $stmt->execute([$campaignId]);
    $analytics = $stmt->fetch();
}

// Get campaign overview data
$campaignOverview = null;
if (!$shareLink['show_sensitive_data']) {
    // Get public campaign data
    $stmt = $db->prepare("
        SELECT cg.primary_goal, cg.target_audience,
               cvt.brand_voice, cvt.writing_style,
               ct.campaign_type
        FROM campaign_goals cg
        LEFT JOIN campaign_voice_tone cvt ON cg.campaign_id = cvt.campaign_id
        LEFT JOIN campaign_types ct ON cg.campaign_id = ct.campaign_id
        WHERE cg.campaign_id = ?
    ");
    $stmt->execute([$campaignId]);
    $campaignOverview = $stmt->fetch();
}

?>
<!DOCTYPE html>
<html lang="en" class="bg-black">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($shareLink['title']) ?></title>
    <meta name="description" content="<?= sanitize($shareLink['description'] ?: 'AI-generated social media strategy') ?>">
    
    <!-- Prevent indexing of shared campaigns -->
    <meta name="robots" content="noindex, nofollow">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        mono: ['JetBrains Mono', 'monospace']
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
</head>
<body class="bg-black text-white font-mono">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="border-b border-gray-800 bg-gray-900/50 backdrop-blur">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center h-16">
                    <div class="flex items-center space-x-4">
                        <div class="w-8 h-8 bg-purple-600 rounded-full flex items-center justify-center">
                            <span class="text-white font-bold text-sm">g</span>
                        </div>
                        <div>
                            <h1 class="text-lg font-semibold"><?= sanitize($shareLink['title']) ?></h1>
                            <p class="text-sm text-gray-400"><?= sanitize($shareLink['client_name']) ?></p>
                        </div>
                    </div>
                    
                    <div class="flex items-center space-x-4">
                        <?php if ($shareLink['allow_download']): ?>
                        <a 
                            href="/api/wrtr/export-shared.php?token=<?= urlencode($shareToken) ?>&format=pdf" 
                            class="px-4 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg text-sm font-medium transition-colors"
                        >
                            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Download PDF
                        </a>
                        <?php endif; ?>
                        
                        <div class="text-sm text-gray-500">
                            Read-only view
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Description -->
            <?php if (!empty($shareLink['description'])): ?>
            <div class="mb-8">
                <div class="bg-purple-900/20 border border-purple-500/30 rounded-lg p-4">
                    <p class="text-purple-200"><?= sanitize($shareLink['description']) ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Campaign Stats -->
            <?php if ($analytics || $weeks): ?>
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                <div class="bg-gray-900 rounded-lg p-4 border border-gray-800">
                    <div class="text-xs text-gray-400">Campaign Status</div>
                    <div class="text-lg font-bold capitalize <?= $shareLink['campaign_status'] === 'active' ? 'text-green-400' : 'text-gray-300' ?>">
                        <?= $shareLink['campaign_status'] ?>
                    </div>
                </div>
                <div class="bg-gray-900 rounded-lg p-4 border border-gray-800">
                    <div class="text-xs text-gray-400">Total Weeks</div>
                    <div class="text-lg font-bold text-white"><?= $shareLink['total_weeks'] ?></div>
                </div>
                <?php if ($analytics): ?>
                <div class="bg-gray-900 rounded-lg p-4 border border-gray-800">
                    <div class="text-xs text-gray-400">Total Posts</div>
                    <div class="text-lg font-bold text-white"><?= $analytics['total_posts'] ?? 'TBD' ?></div>
                </div>
                <div class="bg-gray-900 rounded-lg p-4 border border-gray-800">
                    <div class="text-xs text-gray-400">Avg Engagement</div>
                    <div class="text-lg font-bold text-blue-400"><?= number_format($analytics['avg_engagement_rate'] ?? 0, 1) ?>%</div>
                </div>
                <?php else: ?>
                <div class="bg-gray-900 rounded-lg p-4 border border-gray-800">
                    <div class="text-xs text-gray-400">Weeks Planned</div>
                    <div class="text-lg font-bold text-white"><?= count($weeks) ?></div>
                </div>
                <div class="bg-gray-900 rounded-lg p-4 border border-gray-800">
                    <div class="text-xs text-gray-400">Start Date</div>
                    <div class="text-lg font-bold text-white"><?= $shareLink['start_date'] ? formatDate($shareLink['start_date']) : 'TBD' ?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Campaign Overview -->
            <?php if ($campaignOverview): ?>
            <div class="bg-gray-900 rounded-lg p-6 border border-gray-800 mb-8">
                <h3 class="text-lg font-semibold mb-4">Campaign Overview</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div>
                        <label class="text-sm text-gray-400">Primary Goal</label>
                        <p class="text-white capitalize"><?= str_replace('_', ' ', $campaignOverview['primary_goal']) ?></p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-400">Brand Voice</label>
                        <p class="text-white capitalize"><?= $campaignOverview['brand_voice'] ?></p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-400">Campaign Type</label>
                        <p class="text-white capitalize"><?= str_replace('_', ' ', $campaignOverview['campaign_type']) ?></p>
                    </div>
                </div>
                <?php if (!empty($campaignOverview['target_audience'])): ?>
                <div class="mt-4">
                    <label class="text-sm text-gray-400">Target Audience</label>
                    <p class="text-white"><?= sanitize($campaignOverview['target_audience']) ?></p>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Weekly Strategy -->
            <?php if (!empty($weeks)): ?>
            <div class="space-y-4">
                <h3 class="text-lg font-semibold">Strategy Overview</h3>
                
                <div class="space-y-3" x-data="{ openWeek: null }">
                    <?php foreach ($weeks as $week): ?>
                    <div class="bg-gray-900 rounded-lg border border-gray-800 overflow-hidden">
                        <!-- Week Header -->
                        <div 
                            class="p-4 cursor-pointer hover:bg-gray-800 transition-colors"
                            @click="openWeek = openWeek === <?= $week['week_number'] ?> ? null : <?= $week['week_number'] ?>"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-4">
                                    <div class="text-lg font-semibold text-purple-400">
                                        Week <?= $week['week_number'] ?>
                                    </div>
                                    <div class="text-white font-medium">
                                        <?= sanitize($week['week_theme'] ?: "Week {$week['week_number']} Strategy") ?>
                                    </div>
                                    <div class="text-sm text-gray-400">
                                        <?= formatDate($week['week_start_date']) ?> - <?= formatDate($week['week_end_date']) ?>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-4">
                                    <div class="text-sm text-gray-400"><?= $week['posts_count'] ?> posts</div>
                                    <svg 
                                        class="w-5 h-5 text-gray-400 transform transition-transform"
                                        :class="openWeek === <?= $week['week_number'] ?> ? 'rotate-180' : ''"
                                        fill="none" 
                                        stroke="currentColor" 
                                        viewBox="0 0 24 24"
                                    >
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Week Content -->
                        <div x-show="openWeek === <?= $week['week_number'] ?>" x-collapse>
                            <div class="border-t border-gray-800 p-6">
                                <div id="week-<?= $week['week_number'] ?>-content" class="space-y-6">
                                    <div class="text-center py-8">
                                        <div class="animate-spin w-8 h-8 border-4 border-purple-600 border-t-transparent rounded-full mx-auto"></div>
                                        <p class="text-gray-400 mt-2">Loading week details...</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="text-center py-16 bg-gray-900 rounded-lg border border-gray-800">
                <svg class="w-16 h-16 mx-auto mb-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <h4 class="text-xl font-semibold mb-2">Strategy Overview</h4>
                <p class="text-gray-400">This <?= $shareLink['total_weeks'] ?>-week campaign strategy is currently being finalized.</p>
            </div>
            <?php endif; ?>
        </main>

        <!-- Footer -->
        <footer class="border-t border-gray-800 bg-gray-900/50 backdrop-blur mt-16">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div class="flex justify-between items-center">
                    <div class="text-sm text-gray-500">
                        Generated by <span class="text-purple-400 font-medium">ghst_wrtr</span> AI Strategy Engine
                    </div>
                    <div class="text-sm text-gray-500">
                        Shared on <?= formatDate($shareLink['created_at']) ?>
                    </div>
                </div>
            </div>
        </footer>
    </div>

    <script>
    // Load week content when expanded
    document.addEventListener('click', (e) => {
        const weekHeader = e.target.closest('[\\@click*="openWeek"]');
        if (weekHeader) {
            const weekNumber = parseInt(weekHeader.getAttribute('@click').match(/\d+/)[0]);
            setTimeout(() => {
                const content = document.querySelector(`#week-${weekNumber}-content`);
                if (content && !content.hasAttribute('data-loaded')) {
                    loadWeekContent(weekNumber);
                }
            }, 100);
        }
    });

    function loadWeekContent(weekNumber) {
        fetch(`/api/wrtr/get-shared-week.php?token=<?= urlencode($shareToken) ?>&week_number=${weekNumber}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.querySelector(`#week-${weekNumber}-content`).innerHTML = renderWeekContent(data.week);
                    document.querySelector(`#week-${weekNumber}-content`).setAttribute('data-loaded', 'true');
                } else {
                    document.querySelector(`#week-${weekNumber}-content`).innerHTML = '<p class="text-red-400">Failed to load week content</p>';
                }
            })
            .catch(error => {
                console.error('Error loading week content:', error);
                document.querySelector(`#week-${weekNumber}-content`).innerHTML = '<p class="text-red-400">Failed to load week content</p>';
            });
    }

    function renderWeekContent(week) {
        let html = `
            <div class="mb-6">
                <h4 class="text-lg font-semibold mb-2">${week.week_theme}</h4>
                <div class="flex items-center space-x-4 text-sm text-gray-400">
                    <span>Week ${week.week_number}</span>
                    <span>${week.week_start_date} - ${week.week_end_date}</span>
                </div>
            </div>
        `;

        if (week.objectives && week.objectives.length > 0) {
            html += `
                <div class="mb-6">
                    <h5 class="font-medium mb-3">Week Objectives</h5>
                    <ul class="space-y-1 text-gray-300">
                        ${week.objectives.map(obj => `<li class="flex items-start"><span class="text-purple-400 mr-2">•</span>${obj}</li>`).join('')}
                    </ul>
                </div>
            `;
        }

        if (week.key_messages && week.key_messages.length > 0) {
            html += `
                <div class="mb-6">
                    <h5 class="font-medium mb-3">Key Messages</h5>
                    <ul class="space-y-1 text-gray-300">
                        ${week.key_messages.map(msg => `<li class="flex items-start"><span class="text-purple-400 mr-2">•</span>${msg}</li>`).join('')}
                    </ul>
                </div>
            `;
        }

        if (week.posts && week.posts.length > 0) {
            html += `
                <div>
                    <h5 class="font-medium mb-4">Content Posts (${week.posts.length})</h5>
                    <div class="space-y-4">
                        ${week.posts.map((post, index) => `
                            <div class="bg-black rounded-lg p-4 border border-gray-800">
                                <div class="flex items-center space-x-3 mb-3">
                                    <div class="text-sm px-2 py-1 bg-gray-800 rounded capitalize">${post.platform}</div>
                                    <div class="text-sm text-gray-400 capitalize">${post.post_type}</div>
                                    ${post.content_pillar ? `<div class="text-sm text-purple-400">${post.content_pillar}</div>` : ''}
                                </div>
                                <div class="mb-3">
                                    <p class="text-white whitespace-pre-wrap">${post.content}</p>
                                </div>
                                ${post.hashtags ? `
                                    <div class="text-sm text-blue-400">
                                        ${post.hashtags}
                                    </div>
                                ` : ''}
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }

        return html;
    }
    </script>
</body>
</html>