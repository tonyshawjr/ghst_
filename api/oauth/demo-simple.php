<?php
require_once '../../config.php';

$platform = $_GET['platform'] ?? 'instagram';

$platformNames = [
    'instagram' => 'Instagram',
    'facebook' => 'Facebook', 
    'twitter' => 'Twitter',
    'linkedin' => 'LinkedIn'
];

$platformName = $platformNames[$platform] ?? 'Unknown';
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
                    <div class="bg-green-50 border border-green-200 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-green-800">Demo Mode Working!</h3>
                                <div class="mt-2 text-sm text-green-700">
                                    <p>This shows you what the real <?= $platformName ?> login would look like. The actual OAuth setup can be done later when you're ready to go live.</p>
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
                            ✅ Authorize App (Demo)
                        </button>
                        <button 
                            onclick="window.location.href='<?= APP_URL ?>/dashboard/accounts.php'" 
                            class="flex-1 flex justify-center py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                        >
                            Cancel
                        </button>
                    </div>
                    
                    <div class="text-center">
                        <p class="text-xs text-gray-500">
                            In production, this would be <?= $platformName ?>'s actual login page
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function demoConnect() {
        // Show loading state
        event.target.disabled = true;
        event.target.innerHTML = '⏳ Connecting...';
        
        // Simulate API delay
        setTimeout(() => {
            // For now, just show success and go back
            alert('✅ Demo account connected successfully!\n\nIn a real app, this would save the account to your database.');
            window.location.href = '<?= APP_URL ?>/dashboard/accounts.php';
        }, 1500);
    }
    </script>
</body>
</html>