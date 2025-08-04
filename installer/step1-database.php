<h2 class="text-2xl font-bold text-gray-200 mb-6 text-center">
    <span class="text-purple-500">Step 1:</span> Database Setup
</h2>

<p class="text-gray-400 text-center mb-8">
    Connect to your MySQL database. ghst_ will create the database and tables automatically.
</p>

<form method="POST" class="space-y-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Database Host</label>
            <input 
                type="text" 
                name="db_host" 
                value="<?= $_POST['db_host'] ?? 'localhost' ?>"
                placeholder="localhost or 127.0.0.1"
                class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                required
            >
            <p class="text-xs text-gray-500 mt-1">Usually 'localhost' or '127.0.0.1'</p>
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Database Port</label>
            <input 
                type="number" 
                name="db_port" 
                value="<?= $_POST['db_port'] ?? '3306' ?>"
                placeholder="3306"
                class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                required
            >
            <p class="text-xs text-gray-500 mt-1">Usually 3306</p>
        </div>
    </div>
    
    <div>
        <label class="block text-sm font-medium text-gray-300 mb-2">Database Name</label>
        <input 
            type="text" 
            name="db_name" 
            value="<?= $_POST['db_name'] ?? 'ghst' ?>"
            placeholder="ghst"
            class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
            required
        >
        <p class="text-xs text-gray-500 mt-1">Database will be created if it doesn't exist</p>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Database Username</label>
            <input 
                type="text" 
                name="db_user" 
                value="<?= $_POST['db_user'] ?? 'root' ?>"
                placeholder="root"
                class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                required
            >
        </div>
        
        <div>
            <label class="block text-sm font-medium text-gray-300 mb-2">Database Password</label>
            <input 
                type="password" 
                name="db_pass" 
                value="<?= $_POST['db_pass'] ?? '' ?>"
                placeholder="Enter password"
                class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
            >
            <p class="text-xs text-gray-500 mt-1">Leave blank if no password</p>
        </div>
    </div>
    
    <div class="bg-blue-900/20 border border-blue-700 rounded-lg p-4">
        <div class="flex items-start space-x-3">
            <svg class="w-5 h-5 text-blue-400 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div>
                <h4 class="text-blue-300 font-medium">Database Info</h4>
                <p class="text-blue-200 text-sm mt-1">
                    ghst_ will automatically create the database and all required tables. 
                    Make sure your MySQL user has CREATE and INSERT permissions.
                </p>
            </div>
        </div>
    </div>
    
    <div class="flex justify-center">
        <button 
            type="submit" 
            class="px-8 py-3 bg-purple-600 hover:bg-purple-700 rounded-lg text-white font-medium transition-colors flex items-center space-x-2"
        >
            <span>Test Connection & Create Database</span>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
            </svg>
        </button>
    </div>
</form>