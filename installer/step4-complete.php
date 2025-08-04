<div class="text-center space-y-8">
    <div class="mx-auto w-20 h-20 bg-green-500 rounded-full flex items-center justify-center">
        <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
        </svg>
    </div>
    
    <div>
        <h2 class="text-3xl font-bold text-gray-200 mb-4">
            🎉 Installation Complete!
        </h2>
        <p class="text-xl text-gray-400 mb-8">
            ghst_ is now ready to schedule your social media posts
        </p>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-left">
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
            <h3 class="text-lg font-semibold text-green-400 mb-3">✅ What's Installed</h3>
            <ul class="space-y-2 text-gray-300 text-sm">
                <li>• Database created with all tables</li>
                <li>• Admin account configured</li>
                <li>• Configuration file generated</li>
                <li>• OAuth endpoints ready</li>
                <li>• Security settings applied</li>
                <li>• Cron job configured for automation</li>
            </ul>
        </div>
        
        <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
            <h3 class="text-lg font-semibold text-blue-400 mb-3">🚀 Next Steps</h3>
            <ul class="space-y-2 text-gray-300 text-sm">
                <li>• Log in with your admin account</li>
                <li>• Create your first client</li>
                <li>• Connect social media accounts</li>
                <li>• Start scheduling posts!</li>
                <li>• Set up cron job for automation</li>
                <li>• Configure email settings (optional)</li>
            </ul>
        </div>
    </div>
    
    <div class="bg-purple-900/20 border border-purple-700 rounded-lg p-6">
        <h3 class="text-lg font-semibold text-purple-300 mb-3">📋 Important Information</h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-left">
            <div>
                <h4 class="font-medium text-gray-300 mb-2">Admin Login</h4>
                <p class="text-gray-400 text-sm mb-1">Email: <code class="text-purple-300"><?= htmlspecialchars($installData['admin_email'] ?? '') ?></code></p>
                <p class="text-gray-400 text-sm">Password: The one you chose during setup</p>
            </div>
            
            <div>
                <h4 class="font-medium text-gray-300 mb-2">App URL</h4>
                <p class="text-gray-400 text-sm">
                    <a href="<?= htmlspecialchars($installData['app_url'] ?? '') ?>" class="text-purple-300 hover:text-purple-200 underline" target="_blank">
                        <?= htmlspecialchars($installData['app_url'] ?? '') ?>
                    </a>
                </p>
            </div>
        </div>
    </div>
    
    <div class="bg-yellow-900/20 border border-yellow-700 rounded-lg p-4">
        <div class="flex items-start space-x-3">
            <svg class="w-5 h-5 text-yellow-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L5.268 15.5c-.77.833.192 2.5 1.732 2.5z"></path>
            </svg>
            <div class="text-left">
                <h4 class="text-yellow-300 font-medium">Security Note</h4>
                <p class="text-yellow-200 text-sm mt-1">
                    For security, delete the <code>installer.php</code> file and <code>installer/</code> folder after setup is complete.
                </p>
            </div>
        </div>
    </div>
    
    <div class="bg-blue-900/20 border border-blue-700 rounded-lg p-4">
        <div class="flex items-start space-x-3">
            <svg class="w-5 h-5 text-blue-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div class="text-left">
                <h4 class="text-blue-300 font-medium">Cron Job Setup</h4>
                <p class="text-blue-200 text-sm mt-1">
                    To automate post publishing, add this cron job in cPanel:
                </p>
                <div class="mt-2 p-2 bg-gray-800 rounded text-xs font-mono text-gray-300">
                    */5 * * * * /usr/bin/php <?= htmlspecialchars(dirname(__FILE__)) ?>/cron.php
                </div>
                <p class="text-blue-200 text-xs mt-1">This runs every 5 minutes to publish scheduled posts</p>
            </div>
        </div>
    </div>
    
    <div class="flex justify-center space-x-4">
        <a 
            href="<?= htmlspecialchars($installData['app_url'] ?? '') ?>/login.php" 
            class="px-8 py-3 bg-purple-600 hover:bg-purple-700 rounded-lg text-white font-medium transition-colors flex items-center space-x-2"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
            </svg>
            <span>Login to ghst_</span>
        </a>
        
        <a 
            href="<?= htmlspecialchars($installData['app_url'] ?? '') ?>/dashboard/settings.php" 
            class="px-6 py-3 bg-blue-600 hover:bg-blue-700 rounded-lg text-white font-medium transition-colors flex items-center space-x-2"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
            </svg>
            <span>Settings</span>
        </a>
    </div>
</div>