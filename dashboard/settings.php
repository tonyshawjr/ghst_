<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/functions.php';
require_once '../includes/layout.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$user = $auth->getCurrentUser();
$client = $auth->getCurrentClient();
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_profile':
                $name = $_POST['name'] ?? '';
                $email = $_POST['email'] ?? '';
                
                if (empty($name) || empty($email)) {
                    $error = 'Name and email are required.';
                } elseif (!validateEmail($email)) {
                    $error = 'Invalid email address.';
                } else {
                    $stmt = $db->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
                    if ($stmt->execute([$name, $email, $user['id']])) {
                        $_SESSION['user_name'] = $name;
                        $_SESSION['user_email'] = $email;
                        $message = 'Profile updated successfully.';
                        $user = $auth->getCurrentUser();
                    } else {
                        $error = 'Failed to update profile.';
                    }
                }
                break;
                
            case 'change_password':
                $currentPass = $_POST['current_password'] ?? '';
                $newPass = $_POST['new_password'] ?? '';
                $confirmPass = $_POST['confirm_password'] ?? '';
                
                if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
                    $error = 'All password fields are required.';
                } elseif (!password_verify($currentPass, $user['password_hash'])) {
                    $error = 'Current password is incorrect.';
                } elseif ($newPass !== $confirmPass) {
                    $error = 'New passwords do not match.';
                } elseif (strlen($newPass) < 6) {
                    $error = 'New password must be at least 6 characters.';
                } else {
                    $hashedPass = password_hash($newPass, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    if ($stmt->execute([$hashedPass, $user['id']])) {
                        $message = 'Password changed successfully.';
                    } else {
                        $error = 'Failed to change password.';
                    }
                }
                break;
                
            case 'update_client':
                if (!$client) break;
                
                $clientName = $_POST['client_name'] ?? '';
                $timezone = $_POST['timezone'] ?? 'UTC';
                $notes = $_POST['notes'] ?? '';
                
                if (empty($clientName)) {
                    $error = 'Client name is required.';
                } else {
                    $stmt = $db->prepare("UPDATE clients SET name = ?, timezone = ?, notes = ? WHERE id = ?");
                    if ($stmt->execute([$clientName, $timezone, $notes, $client['id']])) {
                        $_SESSION['client_name'] = $clientName;
                        $_SESSION['client_timezone'] = $timezone;
                        $message = 'Client settings updated successfully.';
                        $client = $auth->getCurrentClient();
                    } else {
                        $error = 'Failed to update client settings.';
                    }
                }
                break;
                
            case 'reset_installation':
                $confirmText = $_POST['confirm_text'] ?? '';
                
                if ($confirmText !== 'DELETE ALL DATA') {
                    $error = 'You must type "DELETE ALL DATA" exactly to confirm.';
                } else {
                    try {
                        // Disable foreign key checks
                        $db->getConnection()->exec("SET FOREIGN_KEY_CHECKS = 0");
                        
                        // Drop all database tables (in order to avoid foreign key constraints)
                        $tables = ['retry_queue', 'user_actions', 'logs', 'posts', 'media', 'accounts', 'brands', 'sessions', 'platform_limits', 'clients', 'users'];
                        foreach ($tables as $table) {
                            $db->getConnection()->exec("DROP TABLE IF EXISTS `$table`");
                        }
                        
                        // Re-enable foreign key checks
                        $db->getConnection()->exec("SET FOREIGN_KEY_CHECKS = 1");
                        
                        // Delete config file
                        if (file_exists(ROOT_PATH . '/config.php')) {
                            unlink(ROOT_PATH . '/config.php');
                        }
                        
                        // Clear session
                        session_destroy();
                        
                        // Redirect to installer
                        header('Location: /installer.php');
                        exit;
                        
                    } catch (Exception $e) {
                        $error = 'Failed to reset installation: ' . $e->getMessage();
                    }
                }
                break;
        }
    }
}


// Get timezone list
$timezones = timezone_identifiers_list();

renderHeader('Settings');
?>

<div class="space-y-8">
    <!-- Success/Error Messages -->
    <?php if ($message): ?>
        <div class="bg-green-900/50 border border-green-700 rounded-lg p-4">
            <div class="flex items-center space-x-2">
                <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span class="text-green-200"><?= sanitize($message) ?></span>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-900/50 border border-red-700 rounded-lg p-4">
            <div class="flex items-center space-x-2">
                <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01"></path>
                </svg>
                <span class="text-red-200"><?= sanitize($error) ?></span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Settings Tabs -->
    <div class="border-b border-gray-800">
        <nav class="-mb-px flex space-x-8">
            <button onclick="showTab('profile')" id="tab-profile" class="py-2 px-1 border-b-2 border-purple-500 font-medium text-sm text-purple-400">
                Profile
            </button>
            <button onclick="showTab('system')" id="tab-system" class="py-2 px-1 border-b-2 border-transparent font-medium text-sm text-gray-400 hover:text-gray-300 hover:border-gray-300">
                System Info
            </button>
            <button onclick="showTab('reset')" id="tab-reset" class="py-2 px-1 border-b-2 border-transparent font-medium text-sm text-red-400 hover:text-red-300 hover:border-red-300">
                Reset & Reinstall
            </button>
        </nav>
    </div>

    <!-- Profile Tab -->
    <div id="content-profile" class="space-y-8">
        <!-- Profile Settings -->
        <div class="bg-gray-900 rounded-lg border border-gray-800 p-6">
            <h3 class="text-lg font-semibold text-gray-200 mb-6">Profile Information</h3>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $auth->generateCSRFToken() ?>">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Name</label>
                        <input 
                            type="text" 
                            name="name" 
                            value="<?= sanitize($user['name'] ?? '') ?>"
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                            required
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Email</label>
                        <input 
                            type="email" 
                            name="email" 
                            value="<?= sanitize($user['email']) ?>"
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                            required
                        >
                    </div>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg text-white font-medium transition-colors">
                        Update Profile
                    </button>
                </div>
            </form>
        </div>

        <!-- Password Change -->
        <div class="bg-gray-900 rounded-lg border border-gray-800 p-6">
            <h3 class="text-lg font-semibold text-gray-200 mb-6">Change Password</h3>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $auth->generateCSRFToken() ?>">
                <input type="hidden" name="action" value="change_password">
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Current Password</label>
                    <input 
                        type="password" 
                        name="current_password" 
                        class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                        required
                    >
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">New Password</label>
                        <input 
                            type="password" 
                            name="new_password" 
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                            required
                            minlength="6"
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Confirm New Password</label>
                        <input 
                            type="password" 
                            name="confirm_password" 
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                            required
                            minlength="6"
                        >
                    </div>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg text-white font-medium transition-colors">
                        Change Password
                    </button>
                </div>
            </form>
        </div>

        <?php if ($client): ?>
        <!-- Client Settings -->
        <div class="bg-gray-900 rounded-lg border border-gray-800 p-6">
            <h3 class="text-lg font-semibold text-gray-200 mb-6">Client Settings</h3>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $auth->generateCSRFToken() ?>">
                <input type="hidden" name="action" value="update_client">
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Client Name</label>
                    <input 
                        type="text" 
                        name="client_name" 
                        value="<?= sanitize($client['name']) ?>"
                        class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                        required
                    >
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Timezone</label>
                    <select 
                        name="timezone"
                        class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                    >
                        <?php foreach ($timezones as $tz): ?>
                        <option value="<?= $tz ?>" <?= ($client['timezone'] ?? 'UTC') === $tz ? 'selected' : '' ?>>
                            <?= $tz ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Notes</label>
                    <textarea 
                        name="notes" 
                        rows="3"
                        class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                    ><?= sanitize($client['notes'] ?? '') ?></textarea>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg text-white font-medium transition-colors">
                        Update Client Settings
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>


    <!-- System Info Tab -->
    <div id="content-system" class="space-y-8 hidden">
        <div class="bg-gray-900 rounded-lg border border-gray-800 p-6">
            <h3 class="text-lg font-semibold text-gray-200 mb-6">System Information</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-medium text-gray-300 mb-3">Application</h4>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-400">App Name:</span>
                            <span class="text-gray-200"><?= APP_NAME ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">App URL:</span>
                            <span class="text-gray-200"><?= APP_URL ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Environment:</span>
                            <span class="text-gray-200"><?= ENVIRONMENT ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Demo Mode:</span>
                            <span class="text-gray-200"><?= DEMO_MODE ? 'Enabled' : 'Disabled' ?></span>
                        </div>
                    </div>
                </div>
                
                <div>
                    <h4 class="font-medium text-gray-300 mb-3">Database</h4>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-400">Host:</span>
                            <span class="text-gray-200"><?= DB_HOST ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Database:</span>
                            <span class="text-gray-200"><?= DB_NAME ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-400">User:</span>
                            <span class="text-gray-200"><?= DB_USER ?></span>
                        </div>
                        <?php if (defined('DB_PORT')): ?>
                        <div class="flex justify-between">
                            <span class="text-gray-400">Port:</span>
                            <span class="text-gray-200"><?= DB_PORT ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reset Tab -->
    <div id="content-reset" class="space-y-8 hidden">
        <div class="bg-red-900/20 border border-red-700 rounded-lg p-6">
            <div class="flex items-start space-x-3">
                <svg class="w-6 h-6 text-red-400 mt-1 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L5.268 15.5c-.77.833.192 2.5 1.732 2.5z"></path>
                </svg>
                <div>
                    <h3 class="text-red-300 font-semibold mb-2">⚠️ Danger Zone</h3>
                    <p class="text-red-200 text-sm leading-relaxed">
                        This will completely reset your installation and delete ALL data permanently. 
                        Use this if you want to start fresh or reinstall the application.
                    </p>
                </div>
            </div>
        </div>

        <div class="bg-gray-900 rounded-lg border border-gray-800 p-6">
            <h3 class="text-lg font-semibold text-gray-200 mb-6">Reset & Reinstall</h3>
            
            <div class="space-y-6">
                <div class="bg-yellow-900/20 border border-yellow-700 rounded-lg p-4">
                    <h4 class="text-yellow-300 font-medium mb-2">What will be deleted:</h4>
                    <ul class="text-yellow-200 text-sm space-y-1 list-disc list-inside">
                        <li>All user accounts and admin settings</li>
                        <li>All clients and their data</li>
                        <li>All connected social media accounts</li>
                        <li>All scheduled and published posts</li>
                        <li>All uploaded media files</li>
                        <li>All system logs and analytics</li>
                        <li>OAuth configuration settings</li>
                        <li>Database tables and configuration files</li>
                    </ul>
                </div>
                
                <div class="bg-green-900/20 border border-green-700 rounded-lg p-4">
                    <h4 class="text-green-300 font-medium mb-2">What happens after reset:</h4>
                    <ul class="text-green-200 text-sm space-y-1 list-disc list-inside">
                        <li>You'll be redirected to the installer</li>
                        <li>You can set up the database again</li>
                        <li>Create a new admin account</li>
                        <li>Configure OAuth settings from scratch</li>
                        <li>Start fresh with a clean installation</li>
                    </ul>
                </div>
                
                <form method="POST" onsubmit="return confirmReset()" class="space-y-4">
                    <input type="hidden" name="csrf_token" value="<?= $auth->generateCSRFToken() ?>">
                    <input type="hidden" name="action" value="reset_installation">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">
                            Type <code class="bg-gray-800 px-2 py-1 rounded text-red-300">DELETE ALL DATA</code> to confirm:
                        </label>
                        <input 
                            type="text" 
                            name="confirm_text" 
                            placeholder="Type exactly: DELETE ALL DATA"
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent"
                            required
                            autocomplete="off"
                        >
                    </div>
                    
                    <div class="flex justify-end space-x-4">
                        <button 
                            type="button"
                            onclick="showTab('system')"
                            class="px-6 py-2 bg-gray-700 hover:bg-gray-600 rounded-lg text-white font-medium transition-colors"
                        >
                            Cancel
                        </button>
                        
                        <button 
                            type="submit" 
                            class="px-6 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-white font-medium transition-colors flex items-center space-x-2"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            <span>Reset & Reinstall</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function showTab(tabName) {
    // Hide all content divs
    document.querySelectorAll('[id^="content-"]').forEach(div => div.classList.add('hidden'));
    
    // Remove active class from all tabs
    document.querySelectorAll('[id^="tab-"]').forEach(tab => {
        tab.classList.remove('border-purple-500', 'text-purple-400', 'border-red-500', 'text-red-400');
        tab.classList.add('border-transparent');
        if (tab.id === 'tab-reset') {
            tab.classList.add('text-red-400');
        } else {
            tab.classList.add('text-gray-400');
        }
    });
    
    // Show selected content
    document.getElementById('content-' + tabName).classList.remove('hidden');
    
    // Add active class to selected tab
    const activeTab = document.getElementById('tab-' + tabName);
    activeTab.classList.remove('border-transparent', 'text-gray-400', 'text-red-400');
    if (tabName === 'reset') {
        activeTab.classList.add('border-red-500', 'text-red-400');
    } else {
        activeTab.classList.add('border-purple-500', 'text-purple-400');
    }
}

function confirmReset() {
    return confirm(
        '🚨 FINAL WARNING 🚨\n\n' +
        'This will PERMANENTLY DELETE ALL DATA:\n' +
        '• All users, clients, and accounts\n' +
        '• All posts and media\n' +
        '• All settings and configuration\n\n' +
        'This action CANNOT be undone!\n\n' +
        'Are you absolutely sure you want to continue?'
    );
}
</script>

<?php renderFooter(); ?>