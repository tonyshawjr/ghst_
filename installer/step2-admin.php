<h2 class="text-2xl font-bold text-gray-200 mb-6 text-center">
    <span class="text-purple-500">Step 2:</span> Admin Account
</h2>

<p class="text-gray-400 text-center mb-8">
    Create your administrator account and set your app URL.
</p>

<form method="POST" class="space-y-6">
    <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">App URL</label>
        <input 
            type="url" 
            name="app_url" 
            value="<?= $_POST['app_url'] ?? 'https://' . ($_SERVER['HTTP_HOST'] ?? 'yourdomain.com') ?>"
            placeholder="https://yourdomain.com"
            class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
            required
        >
        <p class="text-xs text-gray-500 mt-1">The full URL where your app is installed (no trailing slash)</p>
    </div>
    
    <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">Admin Email</label>
        <input 
            type="email" 
            name="admin_email" 
            value="<?= $_POST['admin_email'] ?? '' ?>"
            placeholder="admin@yourdomain.com"
            class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
            required
        >
        <p class="text-xs text-gray-500 mt-1">You'll use this email to log in</p>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Admin Password</label>
            <input 
                type="password" 
                name="admin_password" 
                placeholder="Choose a strong password"
                class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                required
                minlength="6"
            >
            <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Confirm Password</label>
            <input 
                type="password" 
                name="confirm_password" 
                placeholder="Confirm your password"
                class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                required
                minlength="6"
            >
        </div>
    </div>
    
    <div class="bg-green-900/20 border border-green-700 rounded-lg p-4">
        <div class="flex items-start space-x-3">
            <svg class="w-5 h-5 text-green-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <div>
                <h4 class="text-green-300 font-medium">Database Connected!</h4>
                <p class="text-green-200 text-sm mt-1">
                    ✅ Connected to: <strong><?= htmlspecialchars($installData['db_name'] ?? '') ?></strong><br>
                    ✅ All database tables created successfully
                </p>
            </div>
        </div>
    </div>
    
    <div class="flex justify-center">
        <button 
            type="submit" 
            class="px-8 py-3 bg-purple-600 hover:bg-purple-700 rounded-lg text-white font-medium transition-colors flex items-center space-x-2"
        >
            <span>Create Admin Account</span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
        </button>
    </div>
</form>