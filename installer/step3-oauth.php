<h2 class="text-2xl font-bold text-gray-200 mb-6 text-center">
    <span class="text-purple-500">Step 3:</span> Social Media Setup
</h2>

<p class="text-gray-400 text-center mb-8">
    Connect your app to social media platforms. You can skip this and set it up later in Settings.
</p>

<form method="POST" class="space-y-8">
    <!-- Facebook/Instagram -->
    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <div class="flex items-center space-x-3 mb-4">
            <div class="text-blue-500">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-200">Facebook & Instagram</h3>
        </div>
        
        <div class="space-y-4">
            <div class="bg-blue-900/20 border border-blue-700 rounded-lg p-4 mb-4">
                <h4 class="text-blue-300 font-medium mb-2">Setup Instructions:</h4>
                <ol class="text-blue-200 text-sm space-y-1 list-decimal list-inside">
                    <li>Go to <a href="https://developers.facebook.com" target="_blank" class="underline hover:text-blue-100">developers.facebook.com</a></li>
                    <li>Create a new app → Choose "Consumer" type</li>
                    <li>Add "Facebook Login" and "Instagram Basic Display" products</li>
                    <li>In Settings → Basic, find your App ID and App Secret</li>
                    <li>In Facebook Login → Settings, add this redirect URL:</li>
                </ol>
                <div class="mt-2 p-2 bg-gray-800 rounded text-xs font-mono text-gray-300 break-all">
                    <?= htmlspecialchars($installData['app_url'] ?? 'https://yourdomain.com') ?>/api/oauth/callback/facebook.php
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Facebook App ID</label>
                    <input 
                        type="text" 
                        name="facebook_client_id" 
                        value="<?= $_POST['facebook_client_id'] ?? '' ?>"
                        placeholder="Your Facebook App ID"
                        class="w-full px-3 py-2 bg-gray-900 border border-gray-600 rounded-lg text-gray-200 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Facebook App Secret</label>
                    <input 
                        type="password" 
                        name="facebook_client_secret" 
                        value="<?= $_POST['facebook_client_secret'] ?? '' ?>"
                        placeholder="Your Facebook App Secret"
                        class="w-full px-3 py-2 bg-gray-900 border border-gray-600 rounded-lg text-gray-200 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                </div>
            </div>
        </div>
    </div>
    
    <!-- Twitter -->
    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <div class="flex items-center space-x-3 mb-4">
            <div class="text-blue-400">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-200">Twitter / X</h3>
        </div>
        
        <div class="space-y-4">
            <div class="bg-blue-900/20 border border-blue-700 rounded-lg p-4 mb-4">
                <h4 class="text-blue-300 font-medium mb-2">Setup Instructions:</h4>
                <ol class="text-blue-200 text-sm space-y-1 list-decimal list-inside">
                    <li>Go to <a href="https://developer.twitter.com" target="_blank" class="underline hover:text-blue-100">developer.twitter.com</a></li>
                    <li>Apply for developer access (if needed)</li>
                    <li>Create a new project and app</li>
                    <li>In app settings, enable OAuth 2.0</li>
                    <li>Add this redirect URL in OAuth settings:</li>
                </ol>
                <div class="mt-2 p-2 bg-gray-800 rounded text-xs font-mono text-gray-300 break-all">
                    <?= htmlspecialchars($installData['app_url'] ?? 'https://yourdomain.com') ?>/api/oauth/callback/twitter.php
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Twitter API Key</label>
                    <input 
                        type="text" 
                        name="twitter_client_id" 
                        value="<?= $_POST['twitter_client_id'] ?? '' ?>"
                        placeholder="Your Twitter API Key"
                        class="w-full px-3 py-2 bg-gray-900 border border-gray-600 rounded-lg text-gray-200 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Twitter API Secret</label>
                    <input 
                        type="password" 
                        name="twitter_client_secret" 
                        value="<?= $_POST['twitter_client_secret'] ?? '' ?>"
                        placeholder="Your Twitter API Secret"
                        class="w-full px-3 py-2 bg-gray-900 border border-gray-600 rounded-lg text-gray-200 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                </div>
            </div>
        </div>
    </div>
    
    <!-- LinkedIn -->
    <div class="bg-gray-800 rounded-lg p-6 border border-gray-700">
        <div class="flex items-center space-x-3 mb-4">
            <div class="text-blue-600">
                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                </svg>
            </div>
            <h3 class="text-lg font-semibold text-gray-200">LinkedIn</h3>
        </div>
        
        <div class="space-y-4">
            <div class="bg-blue-900/20 border border-blue-700 rounded-lg p-4 mb-4">
                <h4 class="text-blue-300 font-medium mb-2">Setup Instructions:</h4>
                <ol class="text-blue-200 text-sm space-y-1 list-decimal list-inside">
                    <li>Go to <a href="https://www.linkedin.com/developers" target="_blank" class="underline hover:text-blue-100">linkedin.com/developers</a></li>
                    <li>Create a new LinkedIn app</li>
                    <li>Select "Sign In with LinkedIn" product</li>
                    <li>In Auth settings, add this redirect URL:</li>
                </ol>
                <div class="mt-2 p-2 bg-gray-800 rounded text-xs font-mono text-gray-300 break-all">
                    <?= htmlspecialchars($installData['app_url'] ?? 'https://yourdomain.com') ?>/api/oauth/callback/linkedin.php
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">LinkedIn Client ID</label>
                    <input 
                        type="text" 
                        name="linkedin_client_id" 
                        value="<?= $_POST['linkedin_client_id'] ?? '' ?>"
                        placeholder="Your LinkedIn Client ID"
                        class="w-full px-3 py-2 bg-gray-900 border border-gray-600 rounded-lg text-gray-200 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">LinkedIn Client Secret</label>
                    <input 
                        type="password" 
                        name="linkedin_client_secret" 
                        value="<?= $_POST['linkedin_client_secret'] ?? '' ?>"
                        placeholder="Your LinkedIn Client Secret"
                        class="w-full px-3 py-2 bg-gray-900 border border-gray-600 rounded-lg text-gray-200 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                    >
                </div>
            </div>
        </div>
    </div>
    
    <div class="bg-yellow-900/20 border border-yellow-700 rounded-lg p-4">
        <div class="flex items-start space-x-3">
            <svg class="w-5 h-5 text-yellow-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div>
                <h4 class="text-yellow-300 font-medium">Optional Setup</h4>
                <p class="text-yellow-200 text-sm mt-1">
                    You can skip this step and configure OAuth later in the Settings page. 
                    The app will still work, but users won't be able to connect real accounts until OAuth is set up.
                </p>
            </div>
        </div>
    </div>
    
    <div class="flex justify-center space-x-4">
        <button 
            type="submit" 
            name="skip_oauth"
            value="1"
            class="px-6 py-3 bg-gray-700 hover:bg-gray-600 rounded-lg text-white font-medium transition-colors"
        >
            Skip for Now
        </button>
        
        <button 
            type="submit" 
            class="px-8 py-3 bg-purple-600 hover:bg-purple-700 rounded-lg text-white font-medium transition-colors flex items-center space-x-2"
        >
            <span>Save OAuth Settings</span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
        </button>
    </div>
</form>