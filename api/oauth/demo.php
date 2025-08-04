<?php
require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Auth.php';
require_once '../../includes/functions.php';

session_start();

$auth = new Auth();
$auth->requireLogin();
requireClient();

$platform = $_GET['platform'] ?? '';

if (!DEMO_MODE) {
    $_SESSION['error'] = 'Demo mode is disabled';
    header('Location: ' . APP_URL . '/dashboard/accounts.php');
    exit;
}

if (!$platform) {
    $_SESSION['error'] = 'No platform specified';
    header('Location: ' . APP_URL . '/dashboard/accounts.php');
    exit;
}

$platformNames = [
    'instagram' => 'Instagram',
    'facebook' => 'Facebook', 
    'twitter' => 'Twitter',
    'linkedin' => 'LinkedIn'
];

$platformName = $platformNames[$platform] ?? 'Unknown';

// Simulate the OAuth flow with a demo login page
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connect to <?= $platformName ?> - Demo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'JetBrains Mono', monospace; }
    </style>
</head>
<body class="h-full bg-gray-100">
    <div class="min-h-full flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div>
                <div class="mx-auto h-12 w-12 flex items-center justify-center rounded-full bg-<?= $platform === 'instagram' ? 'pink' : 'blue' ?>-100">
                    <svg class="h-6 w-6 text-<?= $platform === 'instagram' ? 'pink' : 'blue' ?>-600" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                    </svg>
                </div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                    Connect to <?= $platformName ?>
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    <span class="font-medium text-indigo-600">Demo Mode</span> - This simulates the OAuth flow
                </p>
            </div>
            
            <div class="bg-white py-8 px-4 shadow sm:rounded-lg sm:px-10">
                <div class="space-y-6">
                    <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-yellow-800">Demo Mode Active</h3>
                                <div class="mt-2 text-sm text-yellow-700">
                                    <p>This is a simulation of what the real <?= $platformName ?> login would look like. In production, you'd see <?= $platformName ?>'s actual login page.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Authorize ghst_ to access your <?= $platformName ?> account</h3>
                        <div class="space-y-3 text-sm text-gray-600">
                            <div class="flex items-center">
                                <svg class="h-4 w-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Post content to your <?= $platformName ?> account
                            </div>
                            <div class="flex items-center">
                                <svg class="h-4 w-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Schedule posts for future publishing
                            </div>
                            <div class="flex items-center">
                                <svg class="h-4 w-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                View your account information
                            </div>
                        </div>
                    </div>

                    <div class="flex space-x-4">
                        <button 
                            onclick="demoConnect()" 
                            class="flex-1 flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-<?= $platform === 'instagram' ? 'pink' : 'blue' ?>-600 hover:bg-<?= $platform === 'instagram' ? 'pink' : 'blue' ?>-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-<?= $platform === 'instagram' ? 'pink' : 'blue' ?>-500"
                        >
                            Authorize App (Demo)
                        </button>
                        <button 
                            onclick="window.location.href='<?= APP_URL ?>/dashboard/accounts.php'" 
                            class="flex-1 flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function demoConnect() {
        // Show loading state
        event.target.disabled = true;
        event.target.textContent = 'Connecting...';
        
        // Simulate API delay
        setTimeout(() => {
            window.location.href = '<?= APP_URL ?>/api/oauth/demo-callback.php?platform=<?= $platform ?>';
        }, 1500);
    }
    </script>
</body>
</html>