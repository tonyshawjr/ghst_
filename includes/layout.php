<?php
/**
 * Dashboard Layout Functions
 */

function renderHeader($title = 'Dashboard') {
    global $auth;
    $user = $auth->getCurrentUser();
    $client = $auth->getCurrentClient();
    $csrfToken = $auth->generateCSRFToken();
    ?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($title) ?> - ghst_</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'JetBrains Mono', monospace; }
        .glow { text-shadow: 0 0 10px rgba(139, 92, 246, 0.5); }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #111; }
        ::-webkit-scrollbar-thumb { background: #4b5563; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #6b7280; }
    </style>
</head>
<body class="h-full bg-black text-white">
    <div class="flex h-full" x-data="{ sidebarOpen: false, clientDropdownOpen: false }">
        <!-- Sidebar -->
        <aside class="w-64 bg-gray-900 border-r border-gray-800 flex flex-col">
            <!-- Logo -->
            <div class="p-6 border-b border-gray-800">
                <h1 class="text-2xl font-bold">
                    <span class="text-purple-500">*</span> ghst_
                </h1>
            </div>
            
            <!-- Client Selector -->
            <?php if ($client): 
                // Get all clients for dropdown
                $db = Database::getInstance();
                $stmt = $db->prepare("SELECT * FROM clients WHERE is_active = 1 ORDER BY name");
                $stmt->execute();
                $allClients = $stmt->fetchAll();
            ?>
            <div class="p-4 border-b border-gray-800">
                <div class="relative" @click.away="clientDropdownOpen = false">
                    <button 
                        @click="clientDropdownOpen = !clientDropdownOpen"
                        class="w-full px-4 py-2 bg-gray-800 hover:bg-gray-700 rounded-lg text-left flex items-center justify-between transition-colors"
                    >
                        <span class="truncate"><?= sanitize($client['name']) ?></span>
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    
                    <div 
                        x-show="clientDropdownOpen" 
                        x-transition
                        class="absolute top-full left-0 right-0 mt-2 bg-gray-800 rounded-lg shadow-lg border border-gray-700 overflow-hidden z-50 max-h-64 overflow-y-auto"
                    >
                        <?php foreach ($allClients as $c): ?>
                            <?php if ($c['id'] != $client['id']): ?>
                                <form method="POST" action="/dashboard/quick-switch.php" class="block">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="client_id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="w-full text-left px-4 py-2 hover:bg-gray-700 transition-colors flex items-center justify-between">
                                        <span><?= sanitize($c['name']) ?></span>
                                    </button>
                                </form>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        
                        <div class="border-t border-gray-700 mt-1 pt-1">
                            <button 
                                onclick="showAddClientModal()"
                                class="w-full text-left px-4 py-2 hover:bg-gray-700 transition-colors flex items-center space-x-2 text-purple-400"
                            >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                <span>Add New Client</span>
                            </button>
                            <a href="/dashboard/switch-client.php" class="block px-4 py-2 hover:bg-gray-700 transition-colors flex items-center space-x-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                </svg>
                                <span>Manage Clients</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Navigation -->
            <nav class="flex-1 p-4 space-y-1">
                <?php
                $currentPage = basename($_SERVER['PHP_SELF']);
                $navItems = [
                    ['url' => '/dashboard/', 'label' => 'Dashboard', 'icon' => 'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
                    ['url' => '/dashboard/posts.php', 'label' => 'Posts', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                    ['url' => '/dashboard/accounts.php', 'label' => 'Accounts', 'icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z'],
                    ['url' => '/dashboard/media.php', 'label' => 'Media', 'icon' => 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z'],
                    ['url' => '/dashboard/calendar.php', 'label' => 'Calendar', 'icon' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
                    ['url' => '/dashboard/analytics.php', 'label' => 'Analytics', 'icon' => 'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
                    ['url' => '/dashboard/settings.php', 'label' => 'Settings', 'icon' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z']
                ];
                
                foreach ($navItems as $item):
                    $isActive = $currentPage === basename($item['url']);
                ?>
                <a 
                    href="<?= $item['url'] ?>" 
                    class="flex items-center space-x-3 px-4 py-2 rounded-lg transition-colors <?= $isActive ? 'bg-purple-600 text-white' : 'text-gray-400 hover:bg-gray-800 hover:text-white' ?>"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $item['icon'] ?>"></path>
                    </svg>
                    <span><?= $item['label'] ?></span>
                </a>
                <?php endforeach; ?>
            </nav>
            
            <!-- User Menu -->
            <div class="p-4 border-t border-gray-800">
                <div class="flex items-center space-x-3 mb-3">
                    <div class="w-8 h-8 bg-purple-600 rounded-full flex items-center justify-center">
                        <span class="text-sm font-medium"><?= strtoupper(substr($user['name'], 0, 1)) ?></span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium truncate"><?= sanitize($user['name']) ?></p>
                        <p class="text-xs text-gray-500 truncate"><?= sanitize($user['email']) ?></p>
                    </div>
                </div>
                <a href="/logout.php" class="block w-full px-4 py-2 bg-gray-800 hover:bg-gray-700 rounded-lg text-center text-sm transition-colors">
                    Logout
                </a>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main class="flex-1 overflow-y-auto">
            <!-- Page Header -->
            <header class="bg-gray-900 border-b border-gray-800 px-8 py-6">
                <h2 class="text-2xl font-bold"><?= sanitize($title) ?></h2>
            </header>
            
            <!-- Page Content -->
            <div class="p-8">
    <?php
}

function renderFooter() {
    global $auth;
    $csrfToken = $auth->generateCSRFToken();
    ?>
            </div>
        </main>
    </div>
    
    <!-- Add Client Modal -->
    <div id="addClientModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
        <div class="bg-gray-900 rounded-lg max-w-md w-full p-6 border border-gray-800">
            <h3 class="text-xl font-semibold mb-4">Add New Client</h3>
            
            <form method="POST" action="/dashboard/quick-switch.php">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="create">
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Client Name</label>
                        <input 
                            type="text" 
                            name="client_name" 
                            required
                            placeholder="e.g., ABC Company"
                            class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500"
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">Timezone</label>
                        <select 
                            name="timezone"
                            class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500"
                        >
                            <option value="America/New_York">Eastern Time (New York)</option>
                            <option value="America/Chicago">Central Time (Chicago)</option>
                            <option value="America/Denver">Mountain Time (Denver)</option>
                            <option value="America/Los_Angeles">Pacific Time (Los Angeles)</option>
                            <option value="Europe/London">UK (London)</option>
                            <option value="Europe/Paris">Central Europe (Paris)</option>
                            <option value="Asia/Tokyo">Japan (Tokyo)</option>
                            <option value="Australia/Sydney">Australia (Sydney)</option>
                            <option value="UTC">UTC</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">Notes (optional)</label>
                        <textarea 
                            name="notes" 
                            rows="3"
                            placeholder="Any additional information..."
                            class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500"
                        ></textarea>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end space-x-3">
                    <button 
                        type="button"
                        onclick="hideAddClientModal()"
                        class="px-4 py-2 bg-gray-800 hover:bg-gray-700 rounded-lg transition-colors"
                    >
                        Cancel
                    </button>
                    <button 
                        type="submit"
                        class="px-4 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg font-medium transition-colors"
                    >
                        Create Client
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function showAddClientModal() {
            document.getElementById('addClientModal').classList.remove('hidden');
        }
        
        function hideAddClientModal() {
            document.getElementById('addClientModal').classList.add('hidden');
        }
        
        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideAddClientModal();
            }
        });
        
        // Close modal on backdrop click
        document.getElementById('addClientModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                hideAddClientModal();
            }
        });
    </script>
</body>
</html>
    <?php
}

function requireClient() {
    global $auth;
    if (!$auth->getCurrentClient()) {
        redirect('/dashboard/switch-client.php');
    }
}