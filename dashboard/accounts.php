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

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(['success' => false, 'error' => 'Invalid CSRF token'], 400);
    }
    
    if ($action === 'remove' && isset($_POST['account_id'])) {
        $stmt = $db->prepare("UPDATE accounts SET is_active = 0 WHERE id = ? AND client_id = ?");
        $stmt->execute([$_POST['account_id'], $client['id']]);
        
        jsonResponse(['success' => true, 'message' => 'Account removed successfully']);
    }
}

// Get connected accounts
$stmt = $db->prepare("
    SELECT * FROM accounts 
    WHERE client_id = ? AND is_active = 1 
    ORDER BY platform, created_at DESC
");
$stmt->execute([$client['id']]);
$accounts = $stmt->fetchAll();

// Group accounts by platform
$accountsByPlatform = [];
foreach ($accounts as $account) {
    $accountsByPlatform[$account['platform']][] = $account;
}

// Check OAuth configuration status
function isOAuthConfigured($platform) {
    switch ($platform) {
        case 'facebook':
        case 'instagram':
            return FB_APP_ID !== 'your_facebook_app_id' && FB_APP_SECRET !== 'your_facebook_app_secret';
        case 'twitter':
            return TWITTER_API_KEY !== 'your_twitter_api_key' && TWITTER_API_SECRET !== 'your_twitter_api_secret';
        case 'linkedin':
            return LINKEDIN_CLIENT_ID !== 'your_linkedin_client_id' && LINKEDIN_CLIENT_SECRET !== 'your_linkedin_client_secret';
        default:
            return false;
    }
}

$availablePlatforms = [
    'instagram' => [
        'name' => 'Instagram',
        'color' => 'pink',
        'description' => 'Share photos, videos, and stories',
        'configured' => isOAuthConfigured('instagram'),
    ],
    'facebook' => [
        'name' => 'Facebook',
        'color' => 'blue',
        'description' => 'Connect with your audience on Facebook',
        'configured' => isOAuthConfigured('facebook'),
    ],
    'linkedin' => [
        'name' => 'LinkedIn',
        'color' => 'blue',
        'description' => 'Professional networking and content',
        'configured' => isOAuthConfigured('linkedin'),
    ],
    'twitter' => [
        'name' => 'Twitter',
        'color' => 'blue',
        'description' => 'Share thoughts and engage in conversations',
        'configured' => isOAuthConfigured('twitter'),
    ],
];

renderHeader('Social Media Accounts');
?>

<div class="space-y-8">
    <!-- Check if OAuth needs setup -->
    <?php 
    $unconfiguredPlatforms = array_filter($availablePlatforms, function($platform) {
        return !$platform['configured'];
    });
    ?>
    
    <?php if (!empty($unconfiguredPlatforms)): ?>
        <div class="bg-yellow-900/20 border border-yellow-700 rounded-lg p-6">
            <div class="flex items-start space-x-3">
                <svg class="w-6 h-6 text-yellow-400 mt-1 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L5.268 15.5c-.77.833.192 2.5 1.732 2.5z"></path>
                </svg>
                <div class="flex-1">
                    <h3 class="text-yellow-300 font-semibold mb-2">OAuth Setup Required</h3>
                    <p class="text-yellow-200 text-sm mb-4">
                        Some platforms need OAuth configuration before users can connect accounts. 
                        <?php if (DEMO_MODE): ?>
                            <strong>Demo mode is enabled</strong> - you can try connecting without setup, but posts won't actually publish.
                        <?php else: ?>
                            This is a one-time admin setup.
                        <?php endif ?>
                    </p>
                    <div class="flex flex-wrap gap-2 mb-4">
                        <?php foreach ($unconfiguredPlatforms as $key => $platform): ?>
                            <span class="inline-flex items-center px-2 py-1 rounded text-xs bg-yellow-800 text-yellow-200">
                                <?= $platform['name'] ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <a href="/dashboard/oauth-setup.php" 
                       class="inline-flex items-center px-4 py-2 bg-yellow-600 hover:bg-yellow-700 rounded-lg text-white text-sm font-medium transition-colors">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Setup OAuth Credentials
                    </a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Page Actions -->
    <div class="flex justify-between items-center">
        <div>
            <h3 class="text-lg font-semibold text-gray-200">Connected Accounts</h3>
            <p class="text-gray-400 text-sm mt-1">Manage your social media platform connections</p>
        </div>
        <button 
            onclick="showConnectModal()" 
            class="px-4 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg text-white font-medium transition-colors"
        >
            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Connect Account
        </button>
    </div>

    <!-- Connected Accounts -->
    <?php if (empty($accounts)): ?>
        <div class="bg-gray-900 rounded-lg border border-gray-800 p-12 text-center">
            <svg class="w-16 h-16 mx-auto text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
            </svg>
            <h3 class="text-xl font-semibold text-gray-300 mb-2">No Accounts Connected</h3>
            <p class="text-gray-500 mb-6">Connect your social media accounts to start scheduling posts</p>
            <button 
                onclick="showConnectModal()" 
                class="px-6 py-3 bg-purple-600 hover:bg-purple-700 rounded-lg text-white font-medium transition-colors"
            >
                Connect Your First Account
            </button>
        </div>
    <?php else: ?>
        <div class="grid gap-6">
            <?php foreach ($accountsByPlatform as $platform => $platformAccounts): ?>
                <div class="bg-gray-900 rounded-lg border border-gray-800 overflow-hidden">
                    <div class="p-6 border-b border-gray-800">
                        <div class="flex items-center space-x-3">
                            <div class="text-<?= $availablePlatforms[$platform]['color'] ?>-500">
                                <?= getPlatformIcon($platform) ?>
                            </div>
                            <div>
                                <h4 class="text-lg font-semibold"><?= $availablePlatforms[$platform]['name'] ?></h4>
                                <p class="text-gray-400 text-sm"><?= count($platformAccounts) ?> account(s) connected</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="divide-y divide-gray-800">
                        <?php foreach ($platformAccounts as $account): ?>
                            <?php
                            // Get account info if available
                            $accountInfo = null;
                            $tokenWarning = null;
                            try {
                                $platformObj = Platform::create($platform);
                                $platformObj->loadAccount($account['id']);
                                $accountInfo = $platformObj->getAccountInfo();
                                $tokenWarning = $platformObj->getTokenExpiryWarning();
                            } catch (Exception $e) {
                                $accountInfo = ['error' => $e->getMessage()];
                            }
                            ?>
                            <div class="p-6">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 bg-<?= $availablePlatforms[$platform]['color'] ?>-600 rounded-full flex items-center justify-center">
                                                <span class="text-white font-medium text-sm">
                                                    <?= strtoupper(substr($account['platform_username'] ?: $platform, 0, 1)) ?>
                                                </span>
                                            </div>
                                            <div>
                                                <h5 class="font-medium">
                                                    <?= sanitize($account['platform_username'] ?: 'Connected Account') ?>
                                                </h5>
                                                <div class="flex items-center space-x-4 text-sm text-gray-400">
                                                    <span>Connected <?= getRelativeTime($account['created_at']) ?></span>
                                                    <?php if ($accountInfo && !isset($accountInfo['error'])): ?>
                                                        <?php if (isset($accountInfo['followers'])): ?>
                                                            <span><?= number_format($accountInfo['followers']) ?> followers</span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <?php if ($tokenWarning): ?>
                                            <div class="mt-3 p-3 bg-yellow-900/50 border border-yellow-700 rounded-lg">
                                                <div class="flex items-center space-x-2">
                                                    <svg class="w-4 h-4 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L5.268 15.5c-.77.833.192 2.5 1.732 2.5z"></path>
                                                    </svg>
                                                    <span class="text-yellow-200 text-sm"><?= sanitize($tokenWarning) ?></span>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (isset($accountInfo['error'])): ?>
                                            <div class="mt-3 p-3 bg-red-900/50 border border-red-700 rounded-lg">
                                                <div class="flex items-center space-x-2">
                                                    <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                    </svg>
                                                    <span class="text-red-200 text-sm">Connection issue: <?= sanitize($accountInfo['error']) ?></span>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="flex items-center space-x-2">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-900 text-green-300">
                                            Active
                                        </span>
                                        <button 
                                            onclick="removeAccount(<?= $account['id'] ?>, '<?= sanitize($account['platform_username'] ?: $platform) ?>')"
                                            class="p-2 text-gray-400 hover:text-red-400 transition-colors"
                                            title="Remove account"
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
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Connect Account Modal -->
<div id="connectModal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-gray-900 rounded-lg border border-gray-800 p-6 max-w-md w-full mx-4">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-semibold">Connect Account</h3>
            <button onclick="hideConnectModal()" class="text-gray-400 hover:text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        
        <div class="space-y-3">
            <?php foreach ($availablePlatforms as $platformKey => $platform): ?>
                <button 
                    onclick="connectPlatform('<?= $platformKey ?>')"
                    class="w-full flex items-center justify-between p-4 bg-gray-800 hover:bg-gray-700 rounded-lg transition-colors text-left"
                >
                    <div class="flex items-center space-x-3">
                        <div class="text-<?= $platform['color'] ?>-500">
                            <?= getPlatformIcon($platformKey) ?>
                        </div>
                        <div>
                            <div class="font-medium"><?= $platform['name'] ?></div>
                            <div class="text-sm text-gray-400"><?= $platform['description'] ?></div>
                        </div>
                    </div>
                    <div class="flex items-center space-x-2">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </div>
                </button>
            <?php endforeach; ?>
        </div>
        
        <p class="text-xs text-gray-500 mt-4">
            Click any platform to instantly connect your account
        </p>
    </div>
</div>

<script>
function showConnectModal() {
    document.getElementById('connectModal').classList.remove('hidden');
    document.getElementById('connectModal').classList.add('flex');
}

function hideConnectModal() {
    document.getElementById('connectModal').classList.add('hidden');
    document.getElementById('connectModal').classList.remove('flex');
}

function connectPlatform(platform) {
    // Simple direct connection - just add the account
    const button = event.target.closest('button');
    const originalText = button.innerHTML;
    
    // Show loading
    button.innerHTML = '<svg class="animate-spin h-4 w-4 inline mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Connecting...';
    button.disabled = true;
    
    // Make request to connect
    fetch('/api/oauth/quick-connect.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'platform=' + platform + '&csrf_token=<?= $auth->generateCSRFToken() ?>'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            hideConnectModal();
            location.reload(); // Refresh to show new account
        } else {
            alert('Error: ' + (data.error || 'Failed to connect account'));
            button.innerHTML = originalText;
            button.disabled = false;
        }
    })
    .catch(error => {
        alert('Connection failed');
        button.innerHTML = originalText;
        button.disabled = false;
    });
}

function removeAccount(accountId, accountName) {
    if (!confirm(`Are you sure you want to remove the account "${accountName}"?\n\nThis action cannot be undone.`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'remove');
    formData.append('account_id', accountId);
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
            alert('Error: ' + (data.error || 'Failed to remove account'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to remove account');
    });
}

// Close modal on escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        hideConnectModal();
    }
});
</script>

<?php renderFooter(); ?>