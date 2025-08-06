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

// Get existing campaigns
$stmt = $db->prepare("
    SELECT * FROM ai_campaigns 
    WHERE client_id = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$client['id']]);
$existingCampaigns = $stmt->fetchAll();

// Campaign status stats
$stmt = $db->prepare("
    SELECT 
        status,
        COUNT(*) as count 
    FROM ai_campaigns 
    WHERE client_id = ? 
    GROUP BY status
");
$stmt->execute([$client['id']]);
$statusStats = [];
while ($row = $stmt->fetch()) {
    $statusStats[$row['status']] = $row['count'];
}

// Handle POST request for new campaign creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_campaign') {
    if (!$auth->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        // Validate and sanitize campaign data
        $campaignData = [
            'name' => trim($_POST['campaign_name'] ?? ''),
            'client_name' => trim($_POST['client_name'] ?? ''),
            'goal' => $_POST['goal'] ?? '',
            'offer_details' => trim($_POST['offer_details'] ?? ''),
            'target_audience' => trim($_POST['target_audience'] ?? ''),
            'brand_voice' => $_POST['brand_voice'] ?? '',
            'writing_style' => $_POST['writing_style'] ?? '',
            'personality_traits' => json_encode($_POST['personality_traits'] ?? []),
            'campaign_type' => $_POST['campaign_type'] ?? '',
            'frequency' => $_POST['frequency'] ?? '',
            'duration' => intval($_POST['duration'] ?? 0),
            'start_date' => $_POST['start_date'] ?? '',
            'key_dates' => trim($_POST['key_dates'] ?? ''),
            'ai_provider' => $_POST['ai_provider'] ?? 'claude',
            'analytics_data' => $_POST['analytics_data'] ?? '',
            'additional_context' => trim($_POST['additional_context'] ?? '')
        ];
        
        // Insert campaign into database
        try {
            $stmt = $db->prepare("
                INSERT INTO ai_campaigns (
                    client_id, name, client_name, goal, offer_details, target_audience,
                    brand_voice, writing_style, personality_traits, campaign_type,
                    frequency, duration, start_date, key_dates, ai_provider,
                    analytics_data, additional_context, status, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
            ");
            
            $stmt->execute([
                $client['id'],
                $campaignData['name'],
                $campaignData['client_name'],
                $campaignData['goal'],
                $campaignData['offer_details'],
                $campaignData['target_audience'],
                $campaignData['brand_voice'],
                $campaignData['writing_style'],
                $campaignData['personality_traits'],
                $campaignData['campaign_type'],
                $campaignData['frequency'],
                $campaignData['duration'],
                $campaignData['start_date'],
                $campaignData['key_dates'],
                $campaignData['ai_provider'],
                $campaignData['analytics_data'],
                $campaignData['additional_context']
            ]);
            
            $campaignId = $db->lastInsertId();
            $success = 'Campaign created successfully!';
            
            // Redirect to prevent form resubmission
            header('Location: /dashboard/wrtr.php?created=' . $campaignId);
            exit;
            
        } catch (Exception $e) {
            $error = 'Failed to create campaign. Please try again.';
        }
    }
}

$csrfToken = $auth->generateCSRFToken();
renderHeader('ghst_wrtr: AI Strategy Engine');
?>

<div class="max-w-7xl mx-auto">
    <!-- Page Header -->
    <div class="mb-8">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between">
            <div class="mb-4 lg:mb-0">
                <h1 class="text-3xl lg:text-4xl font-bold mb-2">
                    ghst_wrtr: Strategy Engine<sup class="text-purple-400 text-lg ml-1">AI</sup>
                </h1>
                <p class="text-gray-400">Create data-driven social media strategies with AI-powered insights</p>
            </div>
            
            <button 
                onclick="showNewCampaignWizard()" 
                class="px-6 py-3 bg-purple-600 hover:bg-purple-700 rounded-lg font-medium transition-colors flex items-center space-x-2 touch-target"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                <span>New Campaign</span>
            </button>
        </div>
    </div>

    <!-- Campaign Stats -->
    <?php if (!empty($existingCampaigns)): ?>
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 lg:gap-6 mb-8">
        <div class="bg-gray-900 rounded-lg p-4 lg:p-6 border border-gray-800">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-400 text-xs lg:text-sm">Total Campaigns</p>
                    <p class="text-2xl lg:text-3xl font-bold mt-1"><?= count($existingCampaigns) ?></p>
                </div>
                <svg class="w-6 h-6 lg:w-8 lg:h-8 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
            </div>
        </div>
        
        <div class="bg-gray-900 rounded-lg p-4 lg:p-6 border border-gray-800">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-400 text-xs lg:text-sm">Active</p>
                    <p class="text-2xl lg:text-3xl font-bold mt-1"><?= $statusStats['active'] ?? 0 ?></p>
                </div>
                <svg class="w-6 h-6 lg:w-8 lg:h-8 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
        
        <div class="bg-gray-900 rounded-lg p-4 lg:p-6 border border-gray-800">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-400 text-xs lg:text-sm">Completed</p>
                    <p class="text-2xl lg:text-3xl font-bold mt-1"><?= $statusStats['completed'] ?? 0 ?></p>
                </div>
                <svg class="w-6 h-6 lg:w-8 lg:h-8 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
        
        <div class="bg-gray-900 rounded-lg p-4 lg:p-6 border border-gray-800">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-gray-400 text-xs lg:text-sm">This Month</p>
                    <p class="text-2xl lg:text-3xl font-bold mt-1"><?= count(array_filter($existingCampaigns, function($c) { return date('Y-m', strtotime($c['created_at'])) === date('Y-m'); })) ?></p>
                </div>
                <svg class="w-6 h-6 lg:w-8 lg:h-8 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Campaign List -->
    <?php if (empty($existingCampaigns)): ?>
    <!-- Empty State -->
    <div class="text-center py-16">
        <div class="max-w-md mx-auto">
            <svg class="w-20 h-20 mx-auto mb-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
            </svg>
            
            <h2 class="text-2xl font-bold text-white mb-4">Welcome to ghst_wrtr</h2>
            <p class="text-gray-400 mb-8">Create your first AI-powered social media strategy campaign to get started.</p>
            
            <div class="bg-gray-900 rounded-xl p-6 mb-8 text-left">
                <h3 class="font-semibold mb-4 text-center">What You'll Get:</h3>
                <div class="space-y-4">
                    <div class="flex items-start space-x-3">
                        <div class="w-8 h-8 bg-purple-600 rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0">AI</div>
                        <div>
                            <h4 class="font-medium">AI-Generated Content Strategy</h4>
                            <p class="text-sm text-gray-400">Get personalized content recommendations based on your goals</p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="w-8 h-8 bg-purple-600 rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0">📊</div>
                        <div>
                            <h4 class="font-medium">Data-Driven Insights</h4>
                            <p class="text-sm text-gray-400">Upload analytics to get tailored recommendations</p>
                        </div>
                    </div>
                    <div class="flex items-start space-x-3">
                        <div class="w-8 h-8 bg-purple-600 rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0">⚡</div>
                        <div>
                            <h4 class="font-medium">Automated Workflows</h4>
                            <p class="text-sm text-gray-400">Set frequency and duration for ongoing strategy updates</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <button 
                onclick="showNewCampaignWizard()" 
                class="inline-flex items-center px-8 py-4 bg-purple-600 hover:bg-purple-700 rounded-lg font-medium transition-colors touch-target"
            >
                <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
                Create Your First Campaign
            </button>
        </div>
    </div>
    <?php else: ?>
    <!-- Campaign Cards -->
    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
        <?php foreach ($existingCampaigns as $campaign): 
            $statusColor = $campaign['status'] === 'active' ? 'bg-green-900 text-green-300' : 
                          ($campaign['status'] === 'completed' ? 'bg-blue-900 text-blue-300' : 
                          ($campaign['status'] === 'paused' ? 'bg-yellow-900 text-yellow-300' : 'bg-gray-900 text-gray-300'));
            
            $weeksCompleted = max(0, floor((time() - strtotime($campaign['created_at'])) / (7 * 24 * 60 * 60)));
            $totalWeeks = $campaign['duration'] ?: 'Ongoing';
        ?>
        <div class="bg-gray-900 rounded-lg border border-gray-800 p-6 hover:border-gray-700 transition-colors touch-feedback">
            <div class="flex items-start justify-between mb-4">
                <div class="flex-1">
                    <h3 class="text-lg font-semibold mb-1 truncate"><?= sanitize($campaign['name']) ?></h3>
                    <p class="text-sm text-gray-400 truncate"><?= sanitize($campaign['client_name']) ?></p>
                </div>
                <div class="ml-3 flex items-center space-x-2">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $statusColor ?>">
                        <?= ucfirst($campaign['status']) ?>
                    </span>
                    <div class="relative" x-data="{ showMenu: false }" @click.away="showMenu = false">
                        <button @click="showMenu = !showMenu" class="p-1 text-gray-400 hover:text-white transition-colors touch-target">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                            </svg>
                        </button>
                        
                        <div x-show="showMenu" x-transition class="absolute right-0 top-full mt-2 bg-gray-800 rounded-lg shadow-lg border border-gray-700 overflow-hidden z-50 min-w-40">
                            <a href="/dashboard/wrtr-campaign.php?id=<?= $campaign['id'] ?>" class="block px-4 py-2 hover:bg-gray-700 transition-colors text-sm">
                                <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                                View/Edit
                            </a>
                            <a href="/dashboard/wrtr-export.php?id=<?= $campaign['id'] ?>" class="block px-4 py-2 hover:bg-gray-700 transition-colors text-sm">
                                <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                Export PDF
                            </a>
                            <a href="/dashboard/wrtr-share.php?id=<?= $campaign['id'] ?>" class="block px-4 py-2 hover:bg-gray-700 transition-colors text-sm">
                                <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.367 2.684 3 3 0 00-5.367-2.684z"></path>
                                </svg>
                                Share
                            </a>
                            <div class="border-t border-gray-700">
                                <button onclick="deleteCampaign(<?= $campaign['id'] ?>)" class="block w-full text-left px-4 py-2 hover:bg-red-900 hover:text-red-300 transition-colors text-sm text-red-400">
                                    <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                    Delete
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="space-y-3">
                <div class="flex items-center text-sm">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                    <span class="text-gray-400">Goal:</span>
                    <span class="ml-2 capitalize"><?= sanitize($campaign['goal']) ?></span>
                </div>
                
                <div class="flex items-center text-sm">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="text-gray-400">Progress:</span>
                    <span class="ml-2"><?= $weeksCompleted ?> / <?= $totalWeeks ?> weeks</span>
                </div>
                
                <div class="flex items-center text-sm">
                    <svg class="w-4 h-4 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <span class="text-gray-400">Updated:</span>
                    <span class="ml-2"><?= getRelativeTime($campaign['updated_at'] ?: $campaign['created_at']) ?></span>
                </div>
                
                <div class="flex items-center justify-between pt-3 border-t border-gray-800">
                    <div class="flex items-center space-x-1 text-xs text-gray-500">
                        <div class="w-3 h-3 bg-purple-600 rounded-full flex items-center justify-center">
                            <span class="text-white text-xs">AI</span>
                        </div>
                        <span><?= ucfirst($campaign['ai_provider']) ?></span>
                    </div>
                    
                    <div class="flex items-center space-x-1 text-xs text-gray-500">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span><?= ucfirst($campaign['frequency']) ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<!-- New Campaign Wizard Modal -->
<div id="campaignWizardModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50 p-4" 
     x-data="{ 
         currentStep: 1, 
         totalSteps: 5,
         formData: {
             campaign_name: '',
             client_name: '',
             goal: '',
             offer_details: '',
             target_audience: '',
             brand_voice: '',
             writing_style: '',
             personality_traits: [],
             campaign_type: '',
             frequency: '',
             duration: '',
             start_date: '',
             key_dates: '',
             ai_provider: 'claude',
             analytics_data: '',
             additional_context: ''
         }
     }">
    <div class="bg-gray-900 rounded-lg border border-gray-800 w-full max-w-2xl max-h-[90vh] overflow-y-auto">
        <!-- Modal Header -->
        <div class="p-6 border-b border-gray-800">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-semibold">Create New Campaign</h3>
                    <p class="text-sm text-gray-400 mt-1">Step <span x-text="currentStep"></span> of <span x-text="totalSteps"></span></p>
                </div>
                <button onclick="hideCampaignWizard()" class="text-gray-400 hover:text-white touch-target">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            
            <!-- Progress Bar -->
            <div class="mt-4">
                <div class="w-full bg-gray-800 rounded-full h-2">
                    <div class="bg-purple-600 h-2 rounded-full transition-all duration-300" 
                         :style="`width: ${(currentStep / totalSteps) * 100}%`"></div>
                </div>
            </div>
        </div>
        
        <!-- Form -->
        <form method="POST" action="/dashboard/wrtr.php" class="p-6">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="create_campaign">
            
            <!-- Step 1: Campaign Basics -->
            <div x-show="currentStep === 1" class="space-y-6">
                <h4 class="text-lg font-semibold text-purple-400">Campaign Basics</h4>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium mb-2">Campaign Name</label>
                        <input 
                            type="text" 
                            name="campaign_name" 
                            x-model="formData.campaign_name"
                            required
                            placeholder="e.g., Spring Product Launch"
                            class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500 text-base"
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">Client/Brand Name</label>
                        <input 
                            type="text" 
                            name="client_name" 
                            x-model="formData.client_name"
                            required
                            placeholder="e.g., TechCorp"
                            class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500 text-base"
                        >
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Primary Goal</label>
                    <select 
                        name="goal" 
                        x-model="formData.goal"
                        required
                        class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500 text-base"
                    >
                        <option value="">Select a goal...</option>
                        <option value="brand_awareness">Brand Awareness</option>
                        <option value="lead_generation">Lead Generation</option>
                        <option value="sales_conversion">Sales Conversion</option>
                        <option value="engagement">Engagement</option>
                        <option value="community_building">Community Building</option>
                        <option value="thought_leadership">Thought Leadership</option>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Offer/Product Details</label>
                    <textarea 
                        name="offer_details" 
                        x-model="formData.offer_details"
                        rows="3"
                        placeholder="Describe your product, service, or offer in detail..."
                        class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500 text-base resize-none"
                    ></textarea>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Target Audience</label>
                    <textarea 
                        name="target_audience" 
                        x-model="formData.target_audience"
                        rows="3"
                        placeholder="Describe your ideal customer, demographics, interests, pain points..."
                        class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500 text-base resize-none"
                    ></textarea>
                </div>
            </div>
            
            <!-- Step 2: Voice & Tone -->
            <div x-show="currentStep === 2" class="space-y-6">
                <h4 class="text-lg font-semibold text-purple-400">Voice & Tone</h4>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium mb-2">Brand Voice</label>
                        <select 
                            name="brand_voice" 
                            x-model="formData.brand_voice"
                            required
                            class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500 text-base"
                        >
                            <option value="">Select brand voice...</option>
                            <option value="professional">Professional</option>
                            <option value="friendly">Friendly</option>
                            <option value="casual">Casual</option>
                            <option value="authoritative">Authoritative</option>
                            <option value="playful">Playful</option>
                            <option value="sophisticated">Sophisticated</option>
                            <option value="inspiring">Inspiring</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">Writing Style</label>
                        <select 
                            name="writing_style" 
                            x-model="formData.writing_style"
                            required
                            class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500 text-base"
                        >
                            <option value="">Select writing style...</option>
                            <option value="conversational">Conversational</option>
                            <option value="educational">Educational</option>
                            <option value="storytelling">Storytelling</option>
                            <option value="direct">Direct</option>
                            <option value="humorous">Humorous</option>
                            <option value="emotional">Emotional</option>
                            <option value="data_driven">Data-Driven</option>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-3">Personality Traits (Select all that apply)</label>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                        <?php 
                        $traits = ['innovative', 'trustworthy', 'energetic', 'caring', 'bold', 'authentic', 'expert', 'approachable', 'creative', 'reliable', 'passionate', 'inclusive'];
                        foreach ($traits as $trait): 
                        ?>
                        <label class="flex items-center space-x-2 cursor-pointer">
                            <input 
                                type="checkbox" 
                                name="personality_traits[]" 
                                value="<?= $trait ?>"
                                class="rounded border-gray-600 bg-gray-800 text-purple-600 focus:ring-purple-500"
                            >
                            <span class="text-sm capitalize"><?= str_replace('_', ' ', $trait) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <!-- Step 3: Campaign Settings -->
            <div x-show="currentStep === 3" class="space-y-6">
                <h4 class="text-lg font-semibold text-purple-400">Campaign Settings</h4>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium mb-2">Campaign Type</label>
                        <select 
                            name="campaign_type" 
                            x-model="formData.campaign_type"
                            required
                            class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500 text-base"
                        >
                            <option value="">Select campaign type...</option>
                            <option value="product_launch">Product Launch</option>
                            <option value="seasonal">Seasonal Campaign</option>
                            <option value="evergreen">Evergreen Content</option>
                            <option value="event_promotion">Event Promotion</option>
                            <option value="brand_building">Brand Building</option>
                            <option value="crisis_management">Crisis Management</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">Update Frequency</label>
                        <select 
                            name="frequency" 
                            x-model="formData.frequency"
                            required
                            class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500 text-base"
                        >
                            <option value="">Select frequency...</option>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="bi_weekly">Bi-weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="one_time">One-time</option>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium mb-2">Duration (weeks)</label>
                        <input 
                            type="number" 
                            name="duration" 
                            x-model="formData.duration"
                            min="1"
                            max="52"
                            placeholder="e.g., 8"
                            class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500 text-base"
                        >
                        <p class="text-xs text-gray-500 mt-1">Leave empty for ongoing campaigns</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">Start Date</label>
                        <input 
                            type="date" 
                            name="start_date" 
                            x-model="formData.start_date"
                            class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500 text-base"
                        >
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Key Dates & Milestones</label>
                    <textarea 
                        name="key_dates" 
                        x-model="formData.key_dates"
                        rows="3"
                        placeholder="List important dates, deadlines, events, or milestones..."
                        class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500 text-base resize-none"
                    ></textarea>
                </div>
            </div>
            
            <!-- Step 4: AI Configuration -->
            <div x-show="currentStep === 4" class="space-y-6">
                <h4 class="text-lg font-semibold text-purple-400">AI Configuration</h4>
                
                <div>
                    <label class="block text-sm font-medium mb-3">AI Provider</label>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label class="cursor-pointer">
                            <input 
                                type="radio" 
                                name="ai_provider" 
                                value="claude" 
                                x-model="formData.ai_provider"
                                class="sr-only"
                            >
                            <div class="p-4 border border-gray-700 rounded-lg transition-colors" 
                                 :class="formData.ai_provider === 'claude' ? 'border-purple-500 bg-purple-900/20' : 'hover:border-gray-600'">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 bg-purple-600 rounded-full flex items-center justify-center">
                                        <span class="text-white text-sm font-bold">C</span>
                                    </div>
                                    <div>
                                        <h5 class="font-medium">Claude (Anthropic)</h5>
                                        <p class="text-sm text-gray-400">Advanced reasoning and analysis</p>
                                    </div>
                                </div>
                            </div>
                        </label>
                        
                        <label class="cursor-pointer">
                            <input 
                                type="radio" 
                                name="ai_provider" 
                                value="chatgpt" 
                                x-model="formData.ai_provider"
                                class="sr-only"
                            >
                            <div class="p-4 border border-gray-700 rounded-lg transition-colors" 
                                 :class="formData.ai_provider === 'chatgpt' ? 'border-green-500 bg-green-900/20' : 'hover:border-gray-600'">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 bg-green-600 rounded-full flex items-center justify-center">
                                        <span class="text-white text-sm font-bold">G</span>
                                    </div>
                                    <div>
                                        <h5 class="font-medium">ChatGPT (OpenAI)</h5>
                                        <p class="text-sm text-gray-400">Creative content generation</p>
                                    </div>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Analytics Data Upload (Optional)</label>
                    <div class="border-2 border-dashed border-gray-700 rounded-lg p-6 text-center hover:border-gray-600 transition-colors">
                        <svg class="w-12 h-12 mx-auto mb-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        <p class="text-sm text-gray-400 mb-2">Upload your analytics data for more targeted recommendations</p>
                        <p class="text-xs text-gray-500">Supports CSV, Excel, or paste text data</p>
                        <input type="file" name="analytics_file" accept=".csv,.xlsx,.xls,.txt" class="hidden" id="analyticsFile">
                        <textarea 
                            name="analytics_data" 
                            x-model="formData.analytics_data"
                            rows="4"
                            placeholder="Or paste your analytics data here..."
                            class="w-full mt-3 px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500 text-base resize-none"
                        ></textarea>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Additional Context</label>
                    <textarea 
                        name="additional_context" 
                        x-model="formData.additional_context"
                        rows="3"
                        placeholder="Any additional information that would help the AI understand your needs..."
                        class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500 text-base resize-none"
                    ></textarea>
                </div>
            </div>
            
            <!-- Step 5: Review & Generate -->
            <div x-show="currentStep === 5" class="space-y-6">
                <h4 class="text-lg font-semibold text-purple-400">Review & Generate</h4>
                
                <div class="bg-gray-800 rounded-lg p-6">
                    <h5 class="font-semibold mb-4">Campaign Summary</h5>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Campaign Name:</span>
                            <span x-text="formData.campaign_name || 'Not specified'"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Client:</span>
                            <span x-text="formData.client_name || 'Not specified'"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Goal:</span>
                            <span x-text="formData.goal ? formData.goal.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) : 'Not specified'"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Campaign Type:</span>
                            <span x-text="formData.campaign_type ? formData.campaign_type.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) : 'Not specified'"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Frequency:</span>
                            <span x-text="formData.frequency ? formData.frequency.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase()) : 'Not specified'"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">AI Provider:</span>
                            <span x-text="formData.ai_provider === 'claude' ? 'Claude (Anthropic)' : 'ChatGPT (OpenAI)'"></span>
                        </div>
                    </div>
                </div>
                
                <div class="bg-purple-900/20 border border-purple-500/30 rounded-lg p-4">
                    <div class="flex items-start space-x-3">
                        <svg class="w-6 h-6 text-purple-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div>
                            <h6 class="font-medium text-purple-300 mb-1">Ready to Generate Your Strategy</h6>
                            <p class="text-sm text-purple-200">Your AI-powered strategy will be generated based on the information provided. You can edit and refine it after creation.</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Navigation Buttons -->
            <div class="mt-8 flex flex-col lg:flex-row justify-between space-y-3 lg:space-y-0 lg:space-x-3">
                <button 
                    type="button"
                    @click="currentStep > 1 ? currentStep-- : null"
                    x-show="currentStep > 1"
                    class="px-6 py-3 bg-gray-800 hover:bg-gray-700 rounded-lg font-medium transition-colors touch-target"
                >
                    Previous
                </button>
                
                <div class="flex space-x-3 lg:ml-auto">
                    <button 
                        type="button"
                        onclick="hideCampaignWizard()"
                        class="px-6 py-3 bg-gray-800 hover:bg-gray-700 rounded-lg font-medium transition-colors touch-target order-2 lg:order-1"
                    >
                        Cancel
                    </button>
                    
                    <button 
                        type="button"
                        @click="currentStep < totalSteps ? currentStep++ : null"
                        x-show="currentStep < totalSteps"
                        class="px-6 py-3 bg-purple-600 hover:bg-purple-700 rounded-lg font-medium transition-colors touch-target order-1 lg:order-2"
                    >
                        Next
                    </button>
                    
                    <button 
                        type="submit"
                        x-show="currentStep === totalSteps"
                        class="px-8 py-3 bg-purple-600 hover:bg-purple-700 rounded-lg font-medium transition-colors touch-target order-1 lg:order-2"
                    >
                        Generate Campaign
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function showNewCampaignWizard() {
    triggerHapticPattern('medium');
    document.getElementById('campaignWizardModal').classList.remove('hidden');
    document.getElementById('campaignWizardModal').classList.add('flex');
}

function hideCampaignWizard() {
    document.getElementById('campaignWizardModal').classList.add('hidden');
    document.getElementById('campaignWizardModal').classList.remove('flex');
}

function deleteCampaign(campaignId) {
    if (confirm('Are you sure you want to delete this campaign? This action cannot be undone.')) {
        triggerHapticPattern('error');
        
        fetch('/dashboard/wrtr-delete.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?= $csrfToken ?>'
            },
            body: JSON.stringify({ id: campaignId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed to delete campaign. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        });
    }
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        hideCampaignWizard();
    }
});

// Close modal on backdrop click
document.getElementById('campaignWizardModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        hideCampaignWizard();
    }
});

// Form validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.querySelector('#campaignWizardModal form');
    if (form) {
        form.addEventListener('submit', function(e) {
            // Add loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = `
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white inline" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Generating Campaign...
            `;
            
            triggerHapticPattern('success');
        });
    }
});
</script>

<?php renderFooter(); ?>