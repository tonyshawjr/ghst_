<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/functions.php';
require_once '../includes/layout.php';

$auth = new Auth();
$auth->requireLogin();
requireClient();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token';
    } else {
        $platform = $_POST['platform'] ?? '';
        $clientId = trim($_POST['client_id'] ?? '');
        $clientSecret = trim($_POST['client_secret'] ?? '');
        
        if (!$platform || !$clientId || !$clientSecret) {
            $error = 'All fields are required';
        } else {
            // Update config.php with the new credentials
            $configPath = ROOT_PATH . '/config.php';
            $configContent = file_get_contents($configPath);
            
            switch ($platform) {
                case 'facebook':
                    $configContent = preg_replace(
                        "/define\('FB_APP_ID', '[^']*'\);/",
                        "define('FB_APP_ID', '{$clientId}');",
                        $configContent
                    );
                    $configContent = preg_replace(
                        "/define\('FB_APP_SECRET', '[^']*'\);/",
                        "define('FB_APP_SECRET', '{$clientSecret}');",
                        $configContent
                    );
                    break;
                case 'twitter':
                    $configContent = preg_replace(
                        "/define\('TWITTER_API_KEY', '[^']*'\);/",
                        "define('TWITTER_API_KEY', '{$clientId}');",
                        $configContent
                    );
                    $configContent = preg_replace(
                        "/define\('TWITTER_API_SECRET', '[^']*'\);/",
                        "define('TWITTER_API_SECRET', '{$clientSecret}');",
                        $configContent
                    );
                    break;
                case 'linkedin':
                    $configContent = preg_replace(
                        "/define\('LINKEDIN_CLIENT_ID', '[^']*'\);/",
                        "define('LINKEDIN_CLIENT_ID', '{$clientId}');",
                        $configContent
                    );
                    $configContent = preg_replace(
                        "/define\('LINKEDIN_CLIENT_SECRET', '[^']*'\);/",
                        "define('LINKEDIN_CLIENT_SECRET', '{$clientSecret}');",
                        $configContent
                    );
                    break;
            }
            
            if (file_put_contents($configPath, $configContent)) {
                $success = ucfirst($platform) . ' credentials saved successfully! You can now connect accounts.';
            } else {
                $error = 'Failed to save credentials. Check file permissions.';
            }
        }
    }
}

$platforms = [
    'facebook' => [
        'name' => 'Facebook/Instagram',
        'setup_url' => 'https://developers.facebook.com',
        'instructions' => [
            'Go to Facebook Developers and create a new app',
            'Choose "Consumer" app type',
            'Add "Facebook Login" product to your app',
            'In Settings > Basic, copy your App ID and App Secret',
            'In Facebook Login > Settings, add this redirect URL:'
        ],
        'redirect_url' => APP_URL . '/api/oauth/callback/facebook.php',
        'current_id' => FB_APP_ID,
        'current_secret' => FB_APP_SECRET
    ],
    'twitter' => [
        'name' => 'Twitter/X',
        'setup_url' => 'https://developer.twitter.com',
        'instructions' => [
            'Apply for Twitter Developer access (if needed)',
            'Create a new project and app in the Developer Portal',
            'In your app settings, enable OAuth 2.0',
            'Copy your API Key and API Secret Key',
            'Add this redirect URL in OAuth settings:'
        ],
        'redirect_url' => APP_URL . '/api/oauth/callback/twitter.php',
        'current_id' => TWITTER_API_KEY,
        'current_secret' => TWITTER_API_SECRET
    ],
    'linkedin' => [
        'name' => 'LinkedIn',
        'setup_url' => 'https://www.linkedin.com/developers',
        'instructions' => [
            'Create a new LinkedIn app',
            'Select "Sign In with LinkedIn" product',
            'Copy your Client ID and Client Secret',
            'In Auth settings, add this redirect URL:'
        ],
        'redirect_url' => APP_URL . '/api/oauth/callback/linkedin.php',
        'current_id' => LINKEDIN_CLIENT_ID,
        'current_secret' => LINKEDIN_CLIENT_SECRET
    ]
];

renderHeader('OAuth Setup - Social Media Integration');
?>

<div class="max-w-4xl mx-auto space-y-8">
    <!-- Header -->
    <div class="text-center">
        <h2 class="text-2xl font-bold text-gray-200 mb-2">Social Media OAuth Setup</h2>
        <p class="text-gray-400">Configure your social media app credentials for seamless account connections</p>
    </div>

    <!-- Instructions -->
    <div class="bg-blue-900/20 border border-blue-700 rounded-lg p-6">
        <div class="flex items-start space-x-3">
            <svg class="w-6 h-6 text-blue-400 mt-1 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div>
                <h3 class="text-blue-300 font-semibold mb-2">One-Time Setup Required</h3>
                <p class="text-blue-200 text-sm leading-relaxed">
                    To connect social media accounts, you need to register your app with each platform first. 
                    This is a one-time setup that enables secure connections. Once configured, users can connect 
                    their accounts with a single click.
                </p>
            </div>
        </div>
    </div>

    <?php if (isset($success)): ?>
        <div class="bg-green-900/50 border border-green-700 rounded-lg p-4">
            <div class="flex items-center space-x-2">
                <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span class="text-green-200"><?= sanitize($success) ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="bg-red-900/50 border border-red-700 rounded-lg p-4">
            <div class="flex items-center space-x-2">
                <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01"></path>
                </svg>
                <span class="text-red-200"><?= sanitize($error) ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Platform Setup Cards -->
    <div class="grid gap-8">
        <?php foreach ($platforms as $key => $platform): ?>
            <div class="bg-gray-900 rounded-lg border border-gray-800 overflow-hidden">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <div class="flex items-center space-x-3">
                            <div class="text-purple-500">
                                <?= getPlatformIcon($key) ?>
                            </div>
                            <div>
                                <h3 class="text-xl font-semibold text-gray-200"><?= $platform['name'] ?></h3>
                                <p class="text-gray-400 text-sm">Configure OAuth credentials</p>
                            </div>
                        </div>
                        
                        <?php 
                        $isConfigured = $platform['current_id'] !== 'your_facebook_app_id' && 
                                       $platform['current_id'] !== 'your_twitter_api_key' && 
                                       $platform['current_id'] !== 'your_linkedin_client_id';
                        ?>
                        
                        <?php if ($isConfigured): ?>
                            <div class="flex items-center space-x-2">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-900 text-green-300">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    Configured
                                </span>
                            </div>
                        <?php else: ?>
                            <div class="flex items-center space-x-2">
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-yellow-900 text-yellow-300">
                                    <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01"></path>
                                    </svg>
                                    Setup Needed
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="grid md:grid-cols-2 gap-6">
                        <!-- Instructions -->
                        <div>
                            <h4 class="font-medium text-gray-300 mb-3">Setup Instructions</h4>
                            <ol class="space-y-2 text-sm text-gray-400">
                                <?php foreach ($platform['instructions'] as $index => $instruction): ?>
                                    <li class="flex items-start space-x-2">
                                        <span class="flex-shrink-0 w-5 h-5 bg-purple-600 text-white text-xs rounded-full flex items-center justify-center font-medium">
                                            <?= $index + 1 ?>
                                        </span>
                                        <span><?= $instruction ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                            
                            <div class="mt-4 p-3 bg-gray-800 rounded border text-xs font-mono text-gray-300 break-all">
                                <?= $platform['redirect_url'] ?>
                            </div>
                            
                            <div class="mt-4">
                                <a href="<?= $platform['setup_url'] ?>" 
                                   target="_blank" 
                                   class="inline-flex items-center px-4 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg text-white text-sm font-medium transition-colors">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                    </svg>
                                    Open <?= $platform['name'] ?> Developer Console
                                </a>
                            </div>
                        </div>

                        <!-- Configuration Form -->
                        <div>
                            <h4 class="font-medium text-gray-300 mb-3">Enter Your Credentials</h4>
                            <form method="POST" class="space-y-4">
                                <input type="hidden" name="csrf_token" value="<?= $auth->generateCSRFToken() ?>">
                                <input type="hidden" name="platform" value="<?= $key ?>">
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-2">
                                        <?= $key === 'twitter' ? 'API Key' : 'Client ID' ?>
                                    </label>
                                    <input 
                                        type="text" 
                                        name="client_id" 
                                        value="<?= $isConfigured ? '••••••••••••••••' : '' ?>"
                                        placeholder="Enter your <?= $key === 'twitter' ? 'API Key' : 'Client ID' ?>"
                                        class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                        <?= $isConfigured ? '' : 'required' ?>
                                    >
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-300 mb-2">
                                        <?= $key === 'twitter' ? 'API Secret Key' : 'Client Secret' ?>
                                    </label>
                                    <input 
                                        type="password" 
                                        name="client_secret" 
                                        value="<?= $isConfigured ? '••••••••••••••••' : '' ?>"
                                        placeholder="Enter your <?= $key === 'twitter' ? 'API Secret Key' : 'Client Secret' ?>"
                                        class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                        <?= $isConfigured ? '' : 'required' ?>
                                    >
                                </div>
                                
                                <button 
                                    type="submit" 
                                    class="w-full px-4 py-2 bg-green-600 hover:bg-green-700 rounded-lg text-white font-medium transition-colors"
                                >
                                    <?= $isConfigured ? 'Update' : 'Save' ?> <?= $platform['name'] ?> Credentials
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Next Steps -->
    <div class="bg-gray-900 rounded-lg border border-gray-800 p-6">
        <h3 class="text-lg font-semibold text-gray-200 mb-4">After Setup</h3>
        <div class="grid md:grid-cols-2 gap-6">
            <div>
                <h4 class="font-medium text-gray-300 mb-2">For Users</h4>
                <p class="text-gray-400 text-sm">
                    Once OAuth is configured, users can simply go to the Accounts page and click 
                    "Connect Account" to link their social media accounts with a single click.
                </p>
            </div>
            <div>
                <h4 class="font-medium text-gray-300 mb-2">For Administrators</h4>
                <p class="text-gray-400 text-sm">
                    You can update these credentials anytime if needed. The app will automatically 
                    use the new credentials for future connections.
                </p>
            </div>
        </div>
        
        <div class="mt-6 pt-4 border-t border-gray-800">
            <a href="/dashboard/accounts.php" 
               class="inline-flex items-center px-4 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg text-white font-medium transition-colors">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                </svg>
                Go to Accounts Page
            </a>
        </div>
    </div>
</div>

<?php renderFooter(); ?>