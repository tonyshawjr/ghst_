<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/functions.php';
require_once '../includes/layout.php';
require_once '../includes/CampaignStrategyEngine.php';

$auth = new Auth();
$auth->requireLogin();
requireClient();

$db = Database::getInstance();
$client = $auth->getCurrentClient();

$campaignId = intval($_GET['id'] ?? 0);
if (!$campaignId) {
    redirect('/dashboard/wrtr.php');
}

// Verify campaign belongs to client
$stmt = $db->prepare("SELECT * FROM strategy_campaigns WHERE id = ? AND client_id = ?");
$stmt->execute([$campaignId, $client['id']]);
$campaign = $stmt->fetch();

if (!$campaign) {
    redirect('/dashboard/wrtr.php');
}

// Get campaign overview data
$strategyEngine = new CampaignStrategyEngine($client['id'], $campaignId);

// Get campaign goals, offers, voice/tone, and other wizard data
$stmt = $db->prepare("
    SELECT cg.primary_goal, cg.target_audience, cg.success_metrics,
           co.primary_offer, co.offer_type, co.call_to_action,
           cvt.brand_voice, cvt.writing_style, cvt.tone_attributes,
           ct.campaign_type, ct.content_pillars, ct.posting_frequency
    FROM campaign_goals cg
    LEFT JOIN campaign_offers co ON cg.campaign_id = co.campaign_id
    LEFT JOIN campaign_voice_tone cvt ON cg.campaign_id = cvt.campaign_id
    LEFT JOIN campaign_types ct ON cg.campaign_id = ct.campaign_id
    WHERE cg.campaign_id = ?
");
$stmt->execute([$campaignId]);
$campaignData = $stmt->fetch();

// Get all weeks for the campaign
$stmt = $db->prepare("
    SELECT cw.*, 
           COUNT(cwp.id) as posts_count,
           COUNT(CASE WHEN cwp.status = 'scheduled' THEN 1 END) as scheduled_count,
           COUNT(CASE WHEN cwp.status = 'published' THEN 1 END) as published_count
    FROM campaign_weeks cw
    LEFT JOIN campaign_week_posts cwp ON cw.id = cwp.campaign_week_id
    WHERE cw.campaign_id = ?
    GROUP BY cw.id
    ORDER BY cw.week_number ASC
");
$stmt->execute([$campaignId]);
$weeks = $stmt->fetchAll();

// Get campaign analytics summary
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

$csrfToken = $auth->generateCSRFToken();
renderHeader($campaign['title'] . ' - Campaign Details');
?>

<div class="max-w-7xl mx-auto">
    <!-- Campaign Header -->
    <div class="mb-8">
        <div class="flex items-center mb-4">
            <a href="/dashboard/wrtr.php" class="text-purple-400 hover:text-purple-300 mr-4 touch-target">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <div class="flex-1">
                <h1 class="text-3xl font-bold text-white"><?= sanitize($campaign['title']) ?></h1>
                <p class="text-gray-400 mt-1"><?= sanitize($campaign['description'] ?? 'AI-Generated Campaign Strategy') ?></p>
            </div>
        </div>

        <!-- Campaign Stats -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-6">
            <div class="bg-gray-900 rounded-lg p-4 border border-gray-800">
                <div class="text-xs text-gray-400">Status</div>
                <div class="text-lg font-bold capitalize <?= $campaign['status'] === 'active' ? 'text-green-400' : 'text-gray-300' ?>">
                    <?= $campaign['status'] ?>
                </div>
            </div>
            <div class="bg-gray-900 rounded-lg p-4 border border-gray-800">
                <div class="text-xs text-gray-400">Weeks Planned</div>
                <div class="text-lg font-bold text-white"><?= count($weeks) ?>/<?= $campaign['total_weeks'] ?></div>
            </div>
            <div class="bg-gray-900 rounded-lg p-4 border border-gray-800">
                <div class="text-xs text-gray-400">Total Posts</div>
                <div class="text-lg font-bold text-white"><?= array_sum(array_column($weeks, 'posts_count')) ?></div>
            </div>
            <div class="bg-gray-900 rounded-lg p-4 border border-gray-800">
                <div class="text-xs text-gray-400">Scheduled</div>
                <div class="text-lg font-bold text-blue-400"><?= array_sum(array_column($weeks, 'scheduled_count')) ?></div>
            </div>
            <div class="bg-gray-900 rounded-lg p-4 border border-gray-800">
                <div class="text-xs text-gray-400">Published</div>
                <div class="text-lg font-bold text-green-400"><?= array_sum(array_column($weeks, 'published_count')) ?></div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-wrap gap-3">
            <button 
                onclick="uploadAnalytics()" 
                class="px-4 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg font-medium transition-colors touch-target"
            >
                <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                </svg>
                Upload Analytics
            </button>
            <button 
                onclick="exportPDF()" 
                class="px-4 py-2 bg-gray-800 hover:bg-gray-700 rounded-lg font-medium transition-colors touch-target"
            >
                <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                Export PDF
            </button>
            <button 
                onclick="shareStrategy()" 
                class="px-4 py-2 bg-gray-800 hover:bg-gray-700 rounded-lg font-medium transition-colors touch-target"
            >
                <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.367 2.684 3 3 0 00-5.367-2.684z"></path>
                </svg>
                Share
            </button>
        </div>
    </div>

    <!-- Campaign Overview -->
    <?php if ($campaignData): ?>
    <div class="bg-gray-900 rounded-lg p-6 border border-gray-800 mb-8">
        <h3 class="text-lg font-semibold mb-4">Campaign Overview</h3>
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="space-y-4">
                <div>
                    <label class="text-sm text-gray-400">Primary Goal</label>
                    <p class="text-white capitalize"><?= str_replace('_', ' ', $campaignData['primary_goal']) ?></p>
                </div>
                <div>
                    <label class="text-sm text-gray-400">Brand Voice</label>
                    <p class="text-white capitalize"><?= $campaignData['brand_voice'] ?></p>
                </div>
                <div>
                    <label class="text-sm text-gray-400">Campaign Type</label>
                    <p class="text-white capitalize"><?= str_replace('_', ' ', $campaignData['campaign_type']) ?></p>
                </div>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="text-sm text-gray-400">Target Audience</label>
                    <p class="text-white"><?= sanitize($campaignData['target_audience']) ?></p>
                </div>
                <div>
                    <label class="text-sm text-gray-400">Primary Offer</label>
                    <p class="text-white"><?= sanitize($campaignData['primary_offer']) ?></p>
                </div>
                <div>
                    <label class="text-sm text-gray-400">Writing Style</label>
                    <p class="text-white capitalize"><?= $campaignData['writing_style'] ?></p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Weekly Strategy -->
    <div class="space-y-4">
        <h3 class="text-lg font-semibold">12-Week Strategy</h3>
        
        <?php if (empty($weeks)): ?>
        <!-- No weeks generated yet -->
        <div class="text-center py-16 bg-gray-900 rounded-lg border border-gray-800">
            <svg class="w-16 h-16 mx-auto mb-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
            </svg>
            <h4 class="text-xl font-semibold mb-4">Strategy Not Generated Yet</h4>
            <p class="text-gray-400 mb-6">This campaign was created but the AI strategy hasn't been generated.</p>
            <button 
                onclick="generateStrategy()" 
                class="px-6 py-3 bg-purple-600 hover:bg-purple-700 rounded-lg font-medium transition-colors touch-target"
            >
                Generate 12-Week Strategy
            </button>
        </div>
        <?php else: ?>
        
        <!-- Week Accordion -->
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
                                <?= sanitize($week['week_theme'] ?? "Week {$week['week_number']} Strategy") ?>
                            </div>
                            <div class="text-sm text-gray-400">
                                <?= formatDate($week['week_start_date']) ?> - <?= formatDate($week['week_end_date']) ?>
                            </div>
                        </div>
                        <div class="flex items-center space-x-4">
                            <div class="flex items-center space-x-2">
                                <span class="text-sm text-gray-400"><?= $week['posts_count'] ?> posts</span>
                                <div class="w-2 h-2 rounded-full <?= $week['status'] === 'completed' ? 'bg-green-500' : ($week['status'] === 'in_progress' ? 'bg-yellow-500' : 'bg-gray-500') ?>"></div>
                            </div>
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

                <!-- Week Content (Expandable) -->
                <div x-show="openWeek === <?= $week['week_number'] ?>" x-collapse>
                    <div class="border-t border-gray-800 p-6">
                        <div id="week-<?= $week['week_number'] ?>-content" class="space-y-6">
                            <!-- Loading placeholder - will be replaced by AJAX -->
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
        <?php endif; ?>
    </div>
</div>

<!-- Analytics Upload Modal -->
<div id="analyticsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-gray-900 rounded-lg border border-gray-800 w-full max-w-lg">
        <div class="p-6 border-b border-gray-800">
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-semibold">Upload Analytics Data</h3>
                <button onclick="closeAnalyticsModal()" class="text-gray-400 hover:text-white touch-target">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
        
        <form id="analyticsForm" class="p-6">
            <div class="mb-4">
                <label class="block text-sm font-medium mb-2">Analytics File</label>
                <div class="border-2 border-dashed border-gray-700 rounded-lg p-6 text-center">
                    <input type="file" id="analyticsFile" accept=".json,.csv,.xlsx" class="hidden">
                    <button type="button" onclick="document.getElementById('analyticsFile').click()" class="text-purple-400 hover:text-purple-300">
                        <svg class="w-12 h-12 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        Click to upload or drag and drop
                    </button>
                    <p class="text-sm text-gray-400">JSON, CSV, or Excel files</p>
                </div>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeAnalyticsModal()" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 rounded-lg transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg transition-colors">
                    Upload & Analyze
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Regenerate Week Modal -->
<div id="regenerateModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4">
    <div class="bg-gray-900 rounded-lg border border-gray-800 w-full max-w-lg">
        <div class="p-6 border-b border-gray-800">
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-semibold">Regenerate Week</h3>
                <button onclick="closeRegenerateModal()" class="text-gray-400 hover:text-white touch-target">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
        </div>
        
        <form id="regenerateForm" class="p-6">
            <input type="hidden" id="regenerateWeekNumber" value="">
            
            <div class="mb-4">
                <label class="block text-sm font-medium mb-2">Reason for Regeneration</label>
                <select id="regenerateReason" class="w-full px-3 py-2 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500">
                    <option value="performance">Poor Performance</option>
                    <option value="feedback">User Feedback</option>
                    <option value="date_change">Date Changes</option>
                    <option value="strategy_pivot">Strategy Pivot</option>
                    <option value="user_request">General Request</option>
                </select>
            </div>
            
            <div class="mb-6">
                <label class="block text-sm font-medium mb-2">Additional Feedback</label>
                <textarea 
                    id="regenerateFeedback" 
                    rows="3" 
                    placeholder="Describe what you'd like to change or improve..."
                    class="w-full px-3 py-2 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500 resize-none"
                ></textarea>
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="closeRegenerateModal()" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 rounded-lg transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg transition-colors">
                    Regenerate Week
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let currentCampaignId = <?= $campaignId ?>;
let currentWeekData = {};

// Load week content when expanded
document.addEventListener('alpine:init', () => {
    Alpine.data('weekAccordion', () => ({
        openWeek: null,
        
        loadWeekContent(weekNumber) {
            if (this.openWeek === weekNumber && !document.querySelector(`#week-${weekNumber}-content`).hasAttribute('data-loaded')) {
                fetch(`/api/wrtr/get-week.php?campaign_id=${currentCampaignId}&week_number=${weekNumber}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            document.querySelector(`#week-${weekNumber}-content`).innerHTML = this.renderWeekContent(data.week);
                            document.querySelector(`#week-${weekNumber}-content`).setAttribute('data-loaded', 'true');
                        }
                    })
                    .catch(error => {
                        console.error('Error loading week content:', error);
                        document.querySelector(`#week-${weekNumber}-content`).innerHTML = '<p class="text-red-400">Failed to load week content</p>';
                    });
            }
        },

        renderWeekContent(week) {
            let html = `
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <h4 class="text-lg font-semibold mb-2">${week.week_theme}</h4>
                        <div class="flex items-center space-x-4 text-sm text-gray-400">
                            <span>Week ${week.week_number}</span>
                            <span>${week.week_start_date} - ${week.week_end_date}</span>
                            <span class="capitalize">${week.status}</span>
                        </div>
                    </div>
                    <div class="flex space-x-2">
                        <button onclick="regenerateWeek(${week.week_number})" class="px-3 py-1 bg-gray-800 hover:bg-gray-700 rounded text-sm transition-colors">
                            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Regenerate
                        </button>
                    </div>
                </div>

                <!-- Week Objectives -->
                ${week.objectives && week.objectives.length > 0 ? `
                <div class="mb-6">
                    <h5 class="font-medium mb-3">Week Objectives</h5>
                    <ul class="space-y-1">
                        ${week.objectives.map(obj => `<li class="flex items-start"><span class="text-purple-400 mr-2">•</span>${obj}</li>`).join('')}
                    </ul>
                </div>
                ` : ''}

                <!-- Key Messages -->
                ${week.key_messages && week.key_messages.length > 0 ? `
                <div class="mb-6">
                    <h5 class="font-medium mb-3">Key Messages</h5>
                    <ul class="space-y-1">
                        ${week.key_messages.map(msg => `<li class="flex items-start"><span class="text-purple-400 mr-2">•</span>${msg}</li>`).join('')}
                    </ul>
                </div>
                ` : ''}

                <!-- Posts -->
                <div>
                    <div class="flex justify-between items-center mb-4">
                        <h5 class="font-medium">Content Posts (${week.posts.length})</h5>
                        <button onclick="pushAllToScheduler(${week.week_number})" class="px-3 py-1 bg-purple-600 hover:bg-purple-700 rounded text-sm transition-colors">
                            Push All to Scheduler
                        </button>
                    </div>
                    <div class="space-y-4">
                        ${week.posts.map((post, index) => `
                            <div class="bg-black rounded-lg p-4 border border-gray-800">
                                <div class="flex justify-between items-start mb-3">
                                    <div class="flex items-center space-x-3">
                                        <div class="text-sm px-2 py-1 bg-gray-800 rounded capitalize">${post.platform}</div>
                                        <div class="text-sm text-gray-400 capitalize">${post.post_type}</div>
                                        ${post.content_pillar ? `<div class="text-sm text-purple-400">${post.content_pillar}</div>` : ''}
                                    </div>
                                    <div class="flex space-x-2">
                                        ${post.status !== 'scheduled' && post.status !== 'published' ? `
                                            <button onclick="pushToScheduler(${week.week_number}, ${post.id})" class="text-sm text-purple-400 hover:text-purple-300">
                                                Schedule
                                            </button>
                                        ` : ''}
                                        <span class="text-sm text-gray-400 capitalize">${post.status}</span>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <p class="text-white whitespace-pre-wrap">${post.content}</p>
                                </div>
                                ${post.hashtags ? `
                                    <div class="mb-2">
                                        <span class="text-sm text-blue-400">${post.hashtags}</span>
                                    </div>
                                ` : ''}
                                ${post.call_to_action ? `
                                    <div class="text-sm">
                                        <span class="text-gray-400">CTA:</span> 
                                        <span class="text-green-400">${post.call_to_action}</span>
                                    </div>
                                ` : ''}
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
            return html;
        }
    }))
});

// When week is opened, load content
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
    fetch(`/api/wrtr/get-week.php?campaign_id=${currentCampaignId}&week_number=${weekNumber}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.querySelector(`#week-${weekNumber}-content`).innerHTML = renderWeekContent(data.week);
                document.querySelector(`#week-${weekNumber}-content`).setAttribute('data-loaded', 'true');
            }
        })
        .catch(error => {
            console.error('Error loading week content:', error);
            document.querySelector(`#week-${weekNumber}-content`).innerHTML = '<p class="text-red-400">Failed to load week content</p>';
        });
}

function renderWeekContent(week) {
    return `
        <div class="flex justify-between items-start mb-6">
            <div>
                <h4 class="text-lg font-semibold mb-2">${week.week_theme}</h4>
                <div class="flex items-center space-x-4 text-sm text-gray-400">
                    <span>Week ${week.week_number}</span>
                    <span>${week.week_start_date} - ${week.week_end_date}</span>
                    <span class="capitalize">${week.status}</span>
                </div>
            </div>
            <div class="flex space-x-2">
                <button onclick="regenerateWeek(${week.week_number})" class="px-3 py-1 bg-gray-800 hover:bg-gray-700 rounded text-sm transition-colors">
                    <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Regenerate
                </button>
            </div>
        </div>

        ${week.objectives && week.objectives.length > 0 ? `
        <div class="mb-6">
            <h5 class="font-medium mb-3">Week Objectives</h5>
            <ul class="space-y-1 text-gray-300">
                ${week.objectives.map(obj => `<li class="flex items-start"><span class="text-purple-400 mr-2">•</span>${obj}</li>`).join('')}
            </ul>
        </div>
        ` : ''}

        ${week.key_messages && week.key_messages.length > 0 ? `
        <div class="mb-6">
            <h5 class="font-medium mb-3">Key Messages</h5>
            <ul class="space-y-1 text-gray-300">
                ${week.key_messages.map(msg => `<li class="flex items-start"><span class="text-purple-400 mr-2">•</span>${msg}</li>`).join('')}
            </ul>
        </div>
        ` : ''}

        <div>
            <div class="flex justify-between items-center mb-4">
                <h5 class="font-medium">Content Posts (${week.posts.length})</h5>
                <button onclick="pushAllToScheduler(${week.week_number})" class="px-3 py-1 bg-purple-600 hover:bg-purple-700 rounded text-sm transition-colors">
                    Push All to Scheduler
                </button>
            </div>
            <div class="space-y-4">
                ${week.posts.map((post, index) => `
                    <div class="bg-black rounded-lg p-4 border border-gray-800">
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex items-center space-x-3">
                                <div class="text-sm px-2 py-1 bg-gray-800 rounded capitalize">${post.platform}</div>
                                <div class="text-sm text-gray-400 capitalize">${post.post_type}</div>
                                ${post.content_pillar ? `<div class="text-sm text-purple-400">${post.content_pillar}</div>` : ''}
                            </div>
                            <div class="flex space-x-2">
                                ${post.status !== 'scheduled' && post.status !== 'published' ? `
                                    <button onclick="pushToScheduler(${week.week_number}, ${post.id})" class="text-sm text-purple-400 hover:text-purple-300">
                                        Schedule
                                    </button>
                                ` : ''}
                                <span class="text-sm text-gray-400 capitalize">${post.status}</span>
                            </div>
                        </div>
                        <div class="mb-3">
                            <p class="text-white whitespace-pre-wrap">${post.content}</p>
                        </div>
                        ${post.hashtags ? `
                            <div class="mb-2">
                                <span class="text-sm text-blue-400">${post.hashtags}</span>
                            </div>
                        ` : ''}
                        ${post.call_to_action ? `
                            <div class="text-sm">
                                <span class="text-gray-400">CTA:</span> 
                                <span class="text-green-400">${post.call_to_action}</span>
                            </div>
                        ` : ''}
                    </div>
                `).join('')}
            </div>
        </div>
    `;
}

// Modal Functions
function uploadAnalytics() {
    document.getElementById('analyticsModal').classList.remove('hidden');
    document.getElementById('analyticsModal').classList.add('flex');
}

function closeAnalyticsModal() {
    document.getElementById('analyticsModal').classList.add('hidden');
    document.getElementById('analyticsModal').classList.remove('flex');
}

function shareStrategy() {
    window.location.href = `/dashboard/wrtr-share.php?id=${currentCampaignId}`;
}

function exportPDF() {
    window.location.href = `/dashboard/wrtr-export.php?id=${currentCampaignId}&format=pdf`;
}

function generateStrategy() {
    // Show loading state
    const button = event.target;
    button.disabled = true;
    button.innerHTML = 'Generating...';
    
    fetch('/dashboard/wrtr-generate.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?= $csrfToken ?>'
        },
        body: JSON.stringify({ 
            campaign_id: currentCampaignId,
            action: 'generate_full_strategy'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Failed to generate strategy: ' + (data.error || 'Unknown error'));
            button.disabled = false;
            button.innerHTML = 'Generate 12-Week Strategy';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while generating the strategy');
        button.disabled = false;
        button.innerHTML = 'Generate 12-Week Strategy';
    });
}

function regenerateWeek(weekNumber) {
    document.getElementById('regenerateWeekNumber').value = weekNumber;
    document.getElementById('regenerateModal').classList.remove('hidden');
    document.getElementById('regenerateModal').classList.add('flex');
}

function closeRegenerateModal() {
    document.getElementById('regenerateModal').classList.add('hidden');
    document.getElementById('regenerateModal').classList.remove('flex');
}

function pushToScheduler(weekNumber, postId) {
    fetch('/dashboard/wrtr-scheduler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?= $csrfToken ?>'
        },
        body: JSON.stringify({
            campaign_id: currentCampaignId,
            post_id: postId,
            action: 'schedule_post'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Refresh the week content
            document.querySelector(`#week-${weekNumber}-content`).removeAttribute('data-loaded');
            loadWeekContent(weekNumber);
        } else {
            alert('Failed to schedule post: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while scheduling the post');
    });
}

function pushAllToScheduler(weekNumber) {
    fetch('/dashboard/wrtr-scheduler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?= $csrfToken ?>'
        },
        body: JSON.stringify({
            campaign_id: currentCampaignId,
            week_number: weekNumber,
            action: 'schedule_week'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Refresh the week content
            document.querySelector(`#week-${weekNumber}-content`).removeAttribute('data-loaded');
            loadWeekContent(weekNumber);
            alert(`Scheduled ${data.scheduled_count} posts successfully`);
        } else {
            alert('Failed to schedule posts: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while scheduling the posts');
    });
}

// Form handlers
document.getElementById('analyticsForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const file = document.getElementById('analyticsFile').files[0];
    if (!file) {
        alert('Please select a file');
        return;
    }
    
    const formData = new FormData();
    formData.append('analytics_file', file);
    formData.append('campaign_id', currentCampaignId);
    formData.append('csrf_token', '<?= $csrfToken ?>');
    
    fetch('/api/wrtr/upload-analytics.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeAnalyticsModal();
            alert('Analytics uploaded and strategy evolved successfully!');
            location.reload();
        } else {
            alert('Failed to upload analytics: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while uploading analytics');
    });
});

document.getElementById('regenerateForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const weekNumber = document.getElementById('regenerateWeekNumber').value;
    const reason = document.getElementById('regenerateReason').value;
    const feedback = document.getElementById('regenerateFeedback').value;
    
    fetch('/dashboard/wrtr-regenerate.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?= $csrfToken ?>'
        },
        body: JSON.stringify({
            campaign_id: currentCampaignId,
            week_number: parseInt(weekNumber),
            regeneration_reason: reason,
            user_feedback: feedback
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeRegenerateModal();
            // Refresh the week content
            document.querySelector(`#week-${weekNumber}-content`).removeAttribute('data-loaded');
            loadWeekContent(parseInt(weekNumber));
        } else {
            alert('Failed to regenerate week: ' + (data.error || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while regenerating the week');
    });
});

// Close modals on escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeAnalyticsModal();
        closeRegenerateModal();
    }
});
</script>

<?php renderFooter(); ?>