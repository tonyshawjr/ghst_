<?php
require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/layout.php';

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
                } elseif ($newPass !== $confirmPass) {
                    $error = 'New passwords do not match.';
                } elseif (strlen($newPass) < 8) {
                    $error = 'Password must be at least 8 characters.';
                } else {
                    // Verify current password
                    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    $userData = $stmt->fetch();
                    
                    if (!password_verify($currentPass, $userData['password_hash'])) {
                        $error = 'Current password is incorrect.';
                    } else {
                        $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                        $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                        if ($stmt->execute([$newHash, $user['id']])) {
                            $message = 'Password changed successfully.';
                        } else {
                            $error = 'Failed to change password.';
                        }
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
        }
    }
}

$csrfToken = $auth->generateCSRFToken();

// Get timezone list
$timezones = timezone_identifiers_list();

renderHeader('Settings');
?>

<?php if ($message): ?>
<div class="bg-green-900/20 border border-green-500 text-green-400 px-4 py-3 rounded mb-6">
    <?= sanitize($message) ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="bg-red-900/20 border border-red-500 text-red-400 px-4 py-3 rounded mb-6">
    <?= sanitize($error) ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Profile Settings -->
    <div class="bg-gray-900 rounded-lg border border-gray-800">
        <div class="p-6 border-b border-gray-800">
            <h3 class="text-lg font-semibold">Profile Settings</h3>
        </div>
        <div class="p-6">
            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="update_profile">
                
                <div>
                    <label class="block text-sm font-medium mb-2">Name</label>
                    <input 
                        type="text" 
                        name="name" 
                        value="<?= sanitize($user['name']) ?>"
                        required
                        class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500"
                    >
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Email</label>
                    <input 
                        type="email" 
                        name="email" 
                        value="<?= sanitize($user['email']) ?>"
                        required
                        class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500"
                    >
                </div>
                
                <button type="submit" class="w-full py-3 bg-purple-600 hover:bg-purple-700 rounded-lg font-medium transition-colors">
                    Update Profile
                </button>
            </form>
        </div>
    </div>
    
    <!-- Password Change -->
    <div class="bg-gray-900 rounded-lg border border-gray-800">
        <div class="p-6 border-b border-gray-800">
            <h3 class="text-lg font-semibold">Change Password</h3>
        </div>
        <div class="p-6">
            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="change_password">
                
                <div>
                    <label class="block text-sm font-medium mb-2">Current Password</label>
                    <input 
                        type="password" 
                        name="current_password" 
                        required
                        class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500"
                    >
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">New Password</label>
                    <input 
                        type="password" 
                        name="new_password" 
                        required
                        minlength="8"
                        class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500"
                    >
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Confirm New Password</label>
                    <input 
                        type="password" 
                        name="confirm_password" 
                        required
                        minlength="8"
                        class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500"
                    >
                </div>
                
                <button type="submit" class="w-full py-3 bg-purple-600 hover:bg-purple-700 rounded-lg font-medium transition-colors">
                    Change Password
                </button>
            </form>
        </div>
    </div>
    
    <?php if ($client): ?>
    <!-- Client Settings -->
    <div class="bg-gray-900 rounded-lg border border-gray-800">
        <div class="p-6 border-b border-gray-800">
            <h3 class="text-lg font-semibold">Client Settings</h3>
        </div>
        <div class="p-6">
            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="update_client">
                
                <div>
                    <label class="block text-sm font-medium mb-2">Client Name</label>
                    <input 
                        type="text" 
                        name="client_name" 
                        value="<?= sanitize($client['name']) ?>"
                        required
                        class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500"
                    >
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Timezone</label>
                    <select 
                        name="timezone"
                        class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500"
                    >
                        <?php foreach ($timezones as $tz): ?>
                        <option value="<?= $tz ?>" <?= $client['timezone'] === $tz ? 'selected' : '' ?>>
                            <?= $tz ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Notes</label>
                    <textarea 
                        name="notes" 
                        rows="3"
                        class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500"
                    ><?= sanitize($client['notes'] ?? '') ?></textarea>
                </div>
                
                <button type="submit" class="w-full py-3 bg-purple-600 hover:bg-purple-700 rounded-lg font-medium transition-colors">
                    Update Client Settings
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- System Information -->
    <div class="bg-gray-900 rounded-lg border border-gray-800">
        <div class="p-6 border-b border-gray-800">
            <h3 class="text-lg font-semibold">System Information</h3>
        </div>
        <div class="p-6 space-y-3">
            <div class="flex justify-between">
                <span class="text-gray-400">Version</span>
                <span>1.0.0</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-400">PHP Version</span>
                <span><?= phpversion() ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-400">Server Time</span>
                <span><?= date('Y-m-d H:i:s') ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-400">Your Timezone</span>
                <span><?= $client ? $client['timezone'] : 'UTC' ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-400">Max Upload Size</span>
                <span><?= ini_get('upload_max_filesize') ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Danger Zone -->
<div class="mt-8 bg-red-900/10 rounded-lg border border-red-900 p-6">
    <h3 class="text-lg font-semibold text-red-400 mb-4">Danger Zone</h3>
    <p class="text-sm text-gray-400 mb-4">These actions are irreversible. Please be certain.</p>
    <div class="flex items-center space-x-4">
        <button 
            onclick="alert('Account deletion coming soon')"
            class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm font-medium transition-colors"
        >
            Delete Account
        </button>
        <?php if ($client): ?>
        <button 
            onclick="alert('Client deletion coming soon')"
            class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg text-sm font-medium transition-colors"
        >
            Delete Client
        </button>
        <?php endif; ?>
    </div>
</div>

<?php renderFooter(); ?>