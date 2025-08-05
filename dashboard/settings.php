<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/functions.php';
require_once '../includes/Branding.php';
require_once '../includes/layout.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$user = $auth->getCurrentUser();
$client = $auth->getCurrentClient();
$branding = new Branding();
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
                
            case 'update_ai_settings':
                if (!$client) break;
                
                $claudeApiKey = $_POST['claude_api_key'] ?? '';
                $claudeModel = $_POST['claude_model'] ?? '';
                $openaiApiKey = $_POST['openai_api_key'] ?? '';
                $openaiModel = $_POST['openai_model'] ?? '';
                
                // Update both API keys
                $stmt = $db->prepare("UPDATE clients SET claude_api_key = ?, claude_model = ?, openai_api_key = ?, openai_model = ? WHERE id = ?");
                if ($stmt->execute([$claudeApiKey, $claudeModel, $openaiApiKey, $openaiModel, $client['id']])) {
                    $message = 'AI settings updated successfully.';
                    $client = $auth->getCurrentClient();
                } else {
                    $error = 'Failed to update AI settings.';
                }
                break;
                
            case 'update_email_settings':
                $emailSettings = [
                    'email_provider' => $_POST['email_provider'] ?? 'smtp',
                    'email_smtp_host' => $_POST['email_smtp_host'] ?? '',
                    'email_smtp_port' => $_POST['email_smtp_port'] ?? '587',
                    'email_smtp_user' => $_POST['email_smtp_user'] ?? '',
                    'email_smtp_pass' => $_POST['email_smtp_pass'] ?? '',
                    'email_smtp_encryption' => $_POST['email_smtp_encryption'] ?? 'tls',
                    'email_from_name' => $_POST['email_from_name'] ?? '',
                    'email_from_email' => $_POST['email_from_email'] ?? '',
                    'email_reply_to' => $_POST['email_reply_to'] ?? '',
                    'email_tracking_enabled' => isset($_POST['email_tracking_enabled']) ? '1' : '0',
                    'email_queue_enabled' => isset($_POST['email_queue_enabled']) ? '1' : '0',
                    'email_max_retries' => $_POST['email_max_retries'] ?? '3',
                    'email_retry_delay' => $_POST['email_retry_delay'] ?? '300',
                    'email_signature' => $_POST['email_signature'] ?? '',
                    'email_sendgrid_api_key' => $_POST['email_sendgrid_api_key'] ?? '',
                ];
                
                try {
                    $db->beginTransaction();
                    
                    foreach ($emailSettings as $key => $value) {
                        // Skip empty passwords unless explicitly being updated
                        if (in_array($key, ['email_smtp_pass', 'email_sendgrid_api_key']) && empty($value)) {
                            continue;
                        }
                        
                        $stmt = $db->prepare("
                            INSERT INTO settings (setting_key, setting_value, user_id) 
                            VALUES (?, ?, ?)
                            ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()
                        ");
                        $stmt->execute([$key, $value, $user['id'], $value]);
                    }
                    
                    $db->commit();
                    $message = 'Email settings updated successfully.';
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = 'Failed to update email settings: ' . $e->getMessage();
                }
                break;
                
            case 'update_branding':
                if (!$client) break;
                
                $brandingData = [
                    'business_name' => $_POST['business_name'] ?? '',
                    'tagline' => $_POST['tagline'] ?? '',
                    'website' => $_POST['website'] ?? '',
                    'email' => $_POST['branding_email'] ?? '',
                    'phone' => $_POST['phone'] ?? '',
                    'primary_color' => $_POST['primary_color'] ?? '#8B5CF6',
                    'secondary_color' => $_POST['secondary_color'] ?? '#A855F7',
                    'accent_color' => $_POST['accent_color'] ?? '#10B981',
                    'email_signature' => $_POST['email_signature'] ?? '',
                    'report_header' => $_POST['report_header'] ?? '',
                    'report_footer' => $_POST['report_footer'] ?? ''
                ];
                
                $logoFile = $_FILES['logo'] ?? null;
                $result = $branding->saveBrandingSettings($client['id'], $brandingData, $logoFile);
                
                if ($result['success']) {
                    $message = $result['message'];
                } else {
                    $error = $result['error'];
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
                        $tables = ['ai_suggestions', 'ai_usage_logs', 'post_reactions', 'post_metrics', 'analytics', 'notifications', 'webhook_logs', 'retry_queue', 'user_actions', 'logs', 'posts', 'media', 'accounts', 'brands', 'sessions', 'platform_limits', 'clients', 'users'];
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

// Get current email settings
$emailSettings = [];
try {
    $stmt = $db->prepare("
        SELECT setting_key, setting_value 
        FROM settings 
        WHERE setting_key LIKE 'email_%' AND (user_id = ? OR user_id IS NULL)
        ORDER BY user_id DESC
    ");
    $stmt->execute([$user['id']]);
    $settings = $stmt->fetchAll();
    
    foreach ($settings as $setting) {
        if (!isset($emailSettings[$setting['setting_key']])) {
            $emailSettings[$setting['setting_key']] = $setting['setting_value'];
        }
    }
    
    // Set defaults if not found
    $emailDefaults = [
        'email_provider' => 'smtp',
        'email_smtp_host' => '',
        'email_smtp_port' => '587',
        'email_smtp_user' => '',
        'email_smtp_pass' => '',
        'email_smtp_encryption' => 'tls',
        'email_from_name' => 'ghst_',
        'email_from_email' => '',
        'email_reply_to' => '',
        'email_tracking_enabled' => '1',
        'email_queue_enabled' => '1',
        'email_max_retries' => '3',
        'email_retry_delay' => '300',
        'email_signature' => '',
        'email_sendgrid_api_key' => ''
    ];
    
    foreach ($emailDefaults as $key => $default) {
        if (!isset($emailSettings[$key])) {
            $emailSettings[$key] = $default;
        }
    }
} catch (Exception $e) {
    error_log('Failed to load email settings: ' . $e->getMessage());
}

renderHeader('Settings');
?>

<!-- Include Branding Assets -->
<link rel="stylesheet" href="/assets/css/branding.css">

<?php
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
            <?php if ($client): ?>
            <button onclick="showTab('branding')" id="tab-branding" class="py-2 px-1 border-b-2 border-transparent font-medium text-sm text-gray-400 hover:text-gray-300 hover:border-gray-300">
                Branding
            </button>
            
            <button onclick="showTab('email')" id="tab-email" class="py-2 px-1 border-b-2 border-transparent font-medium text-sm text-gray-400 hover:text-gray-300 hover:border-gray-300">
                Email Settings
            </button>
            <?php endif; ?>
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
        
        <!-- AI Settings -->
        <div class="bg-gray-900 rounded-lg border border-gray-800 p-6">
            <h3 class="text-lg font-semibold text-gray-200 mb-6">AI Content Suggestions</h3>
            <p class="text-sm text-gray-400 mb-4">Configure API keys for both providers. You can choose which one to use when generating content.</p>
            
            <form method="POST" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?= $auth->generateCSRFToken() ?>">
                <input type="hidden" name="action" value="update_ai_settings">
                
                <!-- Claude Settings -->
                <div class="border border-gray-700 rounded-lg p-4 space-y-4">
                    <h4 class="font-medium text-purple-400">Claude (Anthropic)</h4>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">API Key</label>
                        <input 
                            type="password" 
                            name="claude_api_key" 
                            value="<?= !empty($client['claude_api_key']) ? str_repeat('*', 20) : '' ?>"
                            placeholder="Enter your Claude API key"
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Model</label>
                        <select 
                            name="claude_model"
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                        >
                            <option value="claude-3-5-sonnet-20241022" <?= ($client['claude_model'] ?? '') === 'claude-3-5-sonnet-20241022' ? 'selected' : '' ?>>Claude 3.5 Sonnet</option>
                            <option value="claude-3-5-haiku-20241022" <?= ($client['claude_model'] ?? '') === 'claude-3-5-haiku-20241022' ? 'selected' : '' ?>>Claude 3.5 Haiku</option>
                            <option value="claude-3-opus-20240229" <?= ($client['claude_model'] ?? '') === 'claude-3-opus-20240229' ? 'selected' : '' ?>>Claude 3 Opus</option>
                        </select>
                    </div>
                    
                    <p class="text-xs text-gray-500">
                        Get your API key: <a href="https://console.anthropic.com/api-keys" target="_blank" class="text-purple-400 hover:text-purple-300">console.anthropic.com</a>
                    </p>
                </div>
                
                <!-- OpenAI Settings -->
                <div class="border border-gray-700 rounded-lg p-4 space-y-4">
                    <h4 class="font-medium text-green-400">ChatGPT (OpenAI)</h4>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">API Key</label>
                        <input 
                            type="password" 
                            name="openai_api_key" 
                            value="<?= !empty($client['openai_api_key']) ? str_repeat('*', 20) : '' ?>"
                            placeholder="Enter your OpenAI API key"
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Model</label>
                        <select 
                            name="openai_model"
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                        >
                            <option value="gpt-4o" <?= ($client['openai_model'] ?? '') === 'gpt-4o' ? 'selected' : '' ?>>GPT-4o</option>
                            <option value="gpt-4-turbo" <?= ($client['openai_model'] ?? '') === 'gpt-4-turbo' ? 'selected' : '' ?>>GPT-4 Turbo</option>
                            <option value="gpt-3.5-turbo" <?= ($client['openai_model'] ?? '') === 'gpt-3.5-turbo' ? 'selected' : '' ?>>GPT-3.5 Turbo</option>
                        </select>
                    </div>
                    
                    <p class="text-xs text-gray-500">
                        Get your API key: <a href="https://platform.openai.com/api-keys" target="_blank" class="text-green-400 hover:text-green-300">platform.openai.com</a>
                    </p>
                </div>
                
                <div class="text-xs text-gray-500">
                    <p>Your API keys are encrypted and stored securely. Leave blank to keep existing keys.</p>
                </div>
                
                <div class="flex justify-end">
                    <button type="submit" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg text-white font-medium transition-colors">
                        Update AI Settings
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($client): ?>
    <!-- Branding Tab -->
    <div id="content-branding" class="space-y-8 hidden">
        <?php 
        $clientBranding = $branding->getBrandingSettings($client['id']);
        ?>
        
        <!-- Business Information -->
        <div class="branding-card">
            <h3 class="text-lg font-semibold text-gray-200 mb-6">Business Information</h3>
            
            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?= $auth->generateCSRFToken() ?>">
                <input type="hidden" name="action" value="update_branding">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Business Name</label>
                        <input 
                            type="text" 
                            name="business_name" 
                            value="<?= sanitize($clientBranding['business_name']) ?>"
                            placeholder="Your Business Name"
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Website</label>
                        <input 
                            type="url" 
                            name="website" 
                            value="<?= sanitize($clientBranding['website']) ?>"
                            placeholder="https://yourwebsite.com"
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                        >
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Tagline</label>
                    <input 
                        type="text" 
                        name="tagline" 
                        value="<?= sanitize($clientBranding['tagline']) ?>"
                        placeholder="Your business tagline or slogan"
                        maxlength="500"
                        class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                    >
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Contact Email</label>
                        <input 
                            type="email" 
                            name="branding_email" 
                            value="<?= sanitize($clientBranding['email']) ?>"
                            placeholder="contact@yourbusiness.com"
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                        >
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Phone Number</label>
                        <input 
                            type="tel" 
                            name="phone" 
                            value="<?= sanitize($clientBranding['phone']) ?>"
                            placeholder="+1 (555) 123-4567"
                            class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                        >
                    </div>
                </div>
        </div>
        
        <!-- Logo Upload -->
        <div class="branding-card">
            <h3 class="text-lg font-semibold text-gray-200 mb-6">Logo</h3>
            
            <div class="space-y-4">
                <div class="flex items-start space-x-6">
                    <div class="flex-shrink-0">
                        <div id="logo-preview" class="logo-upload-area logo-preview-container w-32 h-32 bg-gray-800 border-2 border-dashed border-gray-600 rounded-lg flex items-center justify-center overflow-hidden">
                            <?php if ($clientBranding['logo_path']): ?>
                                <img src="<?= sanitize($clientBranding['logo_path']) ?>" alt="Current Logo" class="w-full h-full object-contain">
                            <?php else: ?>
                                <div class="text-center">
                                    <svg class="mx-auto h-8 w-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                    <p class="text-xs text-gray-500 mt-1">No Logo</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-300 mb-2">Upload New Logo</label>
                        <div class="relative">
                            <input 
                                type="file" 
                                name="logo" 
                                accept="image/*"
                                onchange="previewLogo(this)"
                                class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-medium file:bg-purple-600 file:text-white hover:file:bg-purple-700"
                            >
                        </div>
                        <p class="text-xs text-gray-500 mt-2">
                            Supported formats: JPG, PNG, GIF, SVG, WebP. Maximum size: 5MB.<br>
                            Recommended size: 200x200px for best results.
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Brand Colors -->
        <div class="branding-card">
            <h3 class="text-lg font-semibold text-gray-200 mb-6">Brand Colors</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Primary Color</label>
                    <div class="color-picker-container flex items-center space-x-3">
                        <input 
                            type="color" 
                            name="primary_color" 
                            value="<?= sanitize($clientBranding['primary_color']) ?>"
                            class="w-12 h-10 bg-gray-800 border border-gray-700 rounded cursor-pointer"
                        >
                        <input 
                            type="text" 
                            value="<?= sanitize($clientBranding['primary_color']) ?>"
                            class="flex-1 px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                            pattern="^#[0-9A-Fa-f]{6}$"
                        >
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Main brand color for buttons and highlights</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Secondary Color</label>
                    <div class="color-picker-container flex items-center space-x-3">
                        <input 
                            type="color" 
                            name="secondary_color" 
                            value="<?= sanitize($clientBranding['secondary_color']) ?>"
                            class="w-12 h-10 bg-gray-800 border border-gray-700 rounded cursor-pointer"
                        >
                        <input 
                            type="text" 
                            value="<?= sanitize($clientBranding['secondary_color']) ?>"
                            class="flex-1 px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                            pattern="^#[0-9A-Fa-f]{6}$"
                        >
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Secondary brand color for gradients</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Accent Color</label>
                    <div class="color-picker-container flex items-center space-x-3">
                        <input 
                            type="color" 
                            name="accent_color" 
                            value="<?= sanitize($clientBranding['accent_color']) ?>"
                            class="w-12 h-10 bg-gray-800 border border-gray-700 rounded cursor-pointer"
                        >
                        <input 
                            type="text" 
                            value="<?= sanitize($clientBranding['accent_color']) ?>"
                            class="flex-1 px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                            pattern="^#[0-9A-Fa-f]{6}$"
                        >
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Accent color for success states and CTAs</p>
                </div>
            </div>
            
            <div class="mt-6 p-4 bg-gray-800 rounded-lg">
                <h4 class="text-sm font-medium text-gray-300 mb-3">Color Preview</h4>
                <div class="flex space-x-4">
                    <div class="flex-1 h-12 rounded color-preview-bar" style="background: <?= sanitize($clientBranding['primary_color']) ?>"></div>
                    <div class="flex-1 h-12 rounded color-preview-bar" style="background: <?= sanitize($clientBranding['secondary_color']) ?>"></div>
                    <div class="flex-1 h-12 rounded color-preview-bar" style="background: <?= sanitize($clientBranding['accent_color']) ?>"></div>
                </div>
            </div>
        </div>
        
        <!-- Content Templates -->
        <div class="branding-card">
            <h3 class="text-lg font-semibold text-gray-200 mb-6">Content Templates</h3>
            
            <div class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Email Signature</label>
                    <textarea 
                        name="email_signature" 
                        rows="4"
                        placeholder="Best regards,&#10;Your Name&#10;Your Business Name&#10;Phone: +1 (555) 123-4567&#10;Email: contact@yourbusiness.com"
                        class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                    ><?= sanitize($clientBranding['email_signature']) ?></textarea>
                    <p class="text-xs text-gray-500 mt-1">This signature will be added to email communications</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Report Header</label>
                    <textarea 
                        name="report_header" 
                        rows="3"
                        placeholder="Social Media Performance Report&#10;Prepared by: Your Business Name&#10;Period: [Date Range]"
                        class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                    ><?= sanitize($clientBranding['report_header']) ?></textarea>
                    <p class="text-xs text-gray-500 mt-1">Header text for client reports</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Report Footer</label>
                    <textarea 
                        name="report_footer" 
                        rows="3"
                        placeholder="Thank you for choosing our services!&#10;For questions, contact us at: contact@yourbusiness.com&#10;Visit us: https://yourwebsite.com"
                        class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                    ><?= sanitize($clientBranding['report_footer']) ?></textarea>
                    <p class="text-xs text-gray-500 mt-1">Footer text for client reports</p>
                </div>
            </div>
        </div>
        
        <!-- Save Button -->
        <div class="flex justify-end">
            <button type="submit" class="px-6 py-3 bg-purple-600 hover:bg-purple-700 rounded-lg text-white font-medium transition-colors flex items-center space-x-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                <span>Save Branding Settings</span>
            </button>
        </div>
        </form>
    </div>
    
    <!-- Email Settings Tab -->
    <div id="content-email" class="space-y-8 hidden">
        <div class="bg-gray-900 rounded-lg border border-gray-800 p-6">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h3 class="text-lg font-semibold text-gray-200">Email Configuration</h3>
                    <p class="text-sm text-gray-400 mt-1">Configure email settings for report delivery and notifications</p>
                </div>
                <button type="button" onclick="testEmailConfiguration()" class="px-3 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg text-white text-sm font-medium transition-colors">
                    Test Configuration
                </button>
            </div>
            
            <form method="POST" class="space-y-6" id="emailSettingsForm">
                <input type="hidden" name="csrf_token" value="<?= $auth->generateCSRFToken() ?>">
                <input type="hidden" name="action" value="update_email_settings">
                
                <!-- Email Provider Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-300 mb-2">Email Provider</label>
                    <select name="email_provider" id="email_provider" onchange="toggleProviderSettings()" class="w-full px-3 py-2 bg-gray-800 border border-gray-700 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <option value="smtp" <?= ($emailSettings['email_provider'] ?? '') === 'smtp' ? 'selected' : '' ?>>SMTP</option>
                        <option value="sendgrid" <?= ($emailSettings['email_provider'] ?? '') === 'sendgrid' ? 'selected' : '' ?>>SendGrid</option>
                        <option value="ses" <?= ($emailSettings['email_provider'] ?? '') === 'ses' ? 'selected' : '' ?>>Amazon SES (Coming Soon)</option>
                    </select>
                </div>
                
                <!-- SMTP Settings -->
                <div id="smtp-settings" class="space-y-4 p-4 bg-gray-800 rounded-lg">
                    <h4 class="font-medium text-purple-400">SMTP Configuration</h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">SMTP Host</label>
                            <input type="text" name="email_smtp_host" value="<?= sanitize($emailSettings['email_smtp_host'] ?? '') ?>" placeholder="mail.yourdomain.com" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">SMTP Port</label>
                            <select name="email_smtp_port" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                <option value="25" <?= ($emailSettings['email_smtp_port'] ?? '') === '25' ? 'selected' : '' ?>>25 (Standard)</option>
                                <option value="587" <?= ($emailSettings['email_smtp_port'] ?? '') === '587' ? 'selected' : '' ?>>587 (Submission)</option>
                                <option value="465" <?= ($emailSettings['email_smtp_port'] ?? '') === '465' ? 'selected' : '' ?>>465 (SMTPS)</option>
                                <option value="2525" <?= ($emailSettings['email_smtp_port'] ?? '') === '2525' ? 'selected' : '' ?>>2525 (Alternative)</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Username</label>
                            <input type="text" name="email_smtp_user" value="<?= sanitize($emailSettings['email_smtp_user'] ?? '') ?>" placeholder="username@yourdomain.com" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Password</label>
                            <input type="password" name="email_smtp_pass" value="" placeholder="Leave blank to keep current" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">Encryption</label>
                            <select name="email_smtp_encryption" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                <option value="none" <?= ($emailSettings['email_smtp_encryption'] ?? '') === 'none' ? 'selected' : '' ?>>None</option>
                                <option value="tls" <?= ($emailSettings['email_smtp_encryption'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS</option>
                                <option value="ssl" <?= ($emailSettings['email_smtp_encryption'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- SendGrid Settings -->
                <div id="sendgrid-settings" class="space-y-4 p-4 bg-gray-800 rounded-lg hidden">
                    <h4 class="font-medium text-purple-400">SendGrid Configuration</h4>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">SendGrid API Key</label>
                        <input type="password" name="email_sendgrid_api_key" value="" placeholder="SG.xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <p class="text-xs text-gray-500 mt-1">Get your API key from <a href="https://app.sendgrid.com/settings/api_keys" target="_blank" class="text-purple-400 hover:text-purple-300">SendGrid Dashboard</a></p>
                    </div>
                </div>
                
                <!-- From Settings -->
                <div class="space-y-4 p-4 bg-gray-800 rounded-lg">
                    <h4 class="font-medium text-purple-400">Sender Information</h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">From Name</label>
                            <input type="text" name="email_from_name" value="<?= sanitize($emailSettings['email_from_name'] ?? '') ?>" placeholder="Your Business Name" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-300 mb-2">From Email</label>
                            <input type="email" name="email_from_email" value="<?= sanitize($emailSettings['email_from_email'] ?? '') ?>" placeholder="noreply@yourdomain.com" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-300 mb-2">Reply-To Email</label>
                            <input type="email" name="email_reply_to" value="<?= sanitize($emailSettings['email_reply_to'] ?? '') ?>" placeholder="support@yourdomain.com" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            <p class="text-xs text-gray-500 mt-1">Email address for client replies (optional)</p>
                        </div>
                    </div>
                </div>
                
                <!-- Email Features -->
                <div class="space-y-4 p-4 bg-gray-800 rounded-lg">
                    <h4 class="font-medium text-purple-400">Email Features</h4>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <label class="flex items-center space-x-3">
                                <input type="checkbox" name="email_tracking_enabled" <?= ($emailSettings['email_tracking_enabled'] ?? '1') === '1' ? 'checked' : '' ?> class="rounded border-gray-600 text-purple-600 shadow-sm focus:border-purple-300 focus:ring focus:ring-purple-200 focus:ring-opacity-50">
                                <div>
                                    <span class="text-sm font-medium text-gray-300">Email Tracking</span>
                                    <p class="text-xs text-gray-500">Track email opens and clicks</p>
                                </div>
                            </label>
                            
                            <label class="flex items-center space-x-3">
                                <input type="checkbox" name="email_queue_enabled" <?= ($emailSettings['email_queue_enabled'] ?? '1') === '1' ? 'checked' : '' ?> class="rounded border-gray-600 text-purple-600 shadow-sm focus:border-purple-300 focus:ring focus:ring-purple-200 focus:ring-opacity-50">
                                <div>
                                    <span class="text-sm font-medium text-gray-300">Email Queue</span>
                                    <p class="text-xs text-gray-500">Queue emails for background processing</p>
                                </div>
                            </label>
                        </div>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Max Retries</label>
                                <select name="email_max_retries" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                    <option value="1" <?= ($emailSettings['email_max_retries'] ?? '') === '1' ? 'selected' : '' ?>>1</option>
                                    <option value="3" <?= ($emailSettings['email_max_retries'] ?? '') === '3' ? 'selected' : '' ?>>3</option>
                                    <option value="5" <?= ($emailSettings['email_max_retries'] ?? '') === '5' ? 'selected' : '' ?>>5</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-300 mb-2">Retry Delay (seconds)</label>
                                <select name="email_retry_delay" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                    <option value="60" <?= ($emailSettings['email_retry_delay'] ?? '') === '60' ? 'selected' : '' ?>>1 minute</option>
                                    <option value="300" <?= ($emailSettings['email_retry_delay'] ?? '') === '300' ? 'selected' : '' ?>>5 minutes</option>
                                    <option value="900" <?= ($emailSettings['email_retry_delay'] ?? '') === '900' ? 'selected' : '' ?>>15 minutes</option>
                                    <option value="1800" <?= ($emailSettings['email_retry_delay'] ?? '') === '1800' ? 'selected' : '' ?>>30 minutes</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Email Signature -->
                <div class="space-y-4 p-4 bg-gray-800 rounded-lg">
                    <h4 class="font-medium text-purple-400">Email Signature</h4>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-2">Signature Text</label>
                        <textarea name="email_signature" rows="4" placeholder="Best regards,&#10;Your Name&#10;Your Business Name&#10;Phone: (123) 456-7890&#10;Email: you@yourbusiness.com" class="w-full px-3 py-2 bg-gray-700 border border-gray-600 rounded-lg text-gray-200 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent"><?= sanitize($emailSettings['email_signature'] ?? '') ?></textarea>
                        <p class="text-xs text-gray-500 mt-1">This signature will be automatically added to all outgoing emails</p>
                    </div>
                </div>
                
                <!-- Save Button -->
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="testEmailConfiguration()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg text-white font-medium transition-colors">
                        Test Configuration
                    </button>
                    <button type="submit" class="px-6 py-3 bg-purple-600 hover:bg-purple-700 rounded-lg text-white font-medium transition-colors flex items-center space-x-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span>Save Email Settings</span>
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Email Statistics -->
        <div class="bg-gray-900 rounded-lg border border-gray-800 p-6">
            <h3 class="text-lg font-semibold text-gray-200 mb-6">Email Statistics</h3>
            
            <div id="email-stats-loading" class="text-center py-8">
                <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-purple-500"></div>
                <p class="text-gray-400 mt-2">Loading email statistics...</p>
            </div>
            
            <div id="email-stats-content" class="hidden">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-purple-400" id="total-sent">0</div>
                        <div class="text-sm text-gray-400">Total Sent</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-400" id="delivery-rate">0%</div>
                        <div class="text-sm text-gray-400">Delivery Rate</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-400" id="open-rate">0%</div>
                        <div class="text-sm text-gray-400">Open Rate</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-yellow-400" id="click-rate">0%</div>
                        <div class="text-sm text-gray-400">Click Rate</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

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

// Logo preview function
function previewLogo(input) {
    const file = input.files[0];
    const preview = document.getElementById('logo-preview');
    
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = `<img src="${e.target.result}" alt="Logo Preview" class="w-full h-full object-contain">`;
        };
        reader.readAsDataURL(file);
    }
}

// Color picker sync
document.addEventListener('DOMContentLoaded', function() {
    const colorInputs = document.querySelectorAll('input[type="color"]');
    colorInputs.forEach(colorInput => {
        const textInput = colorInput.nextElementSibling;
        
        colorInput.addEventListener('change', function() {
            textInput.value = this.value;
            updateColorPreview();
        });
        
        textInput.addEventListener('input', function() {
            if (/^#[0-9A-F]{6}$/i.test(this.value)) {
                colorInput.value = this.value;
                updateColorPreview();
            }
        });
    });
    
    function updateColorPreview() {
        const primaryColor = document.querySelector('input[name="primary_color"]')?.value;
        const secondaryColor = document.querySelector('input[name="secondary_color"]')?.value;
        const accentColor = document.querySelector('input[name="accent_color"]')?.value;
        
        const previewBars = document.querySelectorAll('.color-preview-bar');
        if (previewBars.length >= 3) {
            if (primaryColor) previewBars[0].style.background = primaryColor;
            if (secondaryColor) previewBars[1].style.background = secondaryColor;
            if (accentColor) previewBars[2].style.background = accentColor;
        }
    }
});

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

// Email Settings Functions
function toggleProviderSettings() {
    const provider = document.getElementById('email_provider').value;
    const smtpSettings = document.getElementById('smtp-settings');
    const sendgridSettings = document.getElementById('sendgrid-settings');
    
    // Hide all provider settings
    smtpSettings.classList.add('hidden');
    sendgridSettings.classList.add('hidden');
    
    // Show selected provider settings
    if (provider === 'smtp') {
        smtpSettings.classList.remove('hidden');
    } else if (provider === 'sendgrid') {
        sendgridSettings.classList.remove('hidden');
    }
}

function testEmailConfiguration() {
    const testEmail = prompt('Enter email address to send test email to:');
    if (!testEmail) return;
    
    // Validate email format
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(testEmail)) {
        alert('Please enter a valid email address.');
        return;
    }
    
    // Show loading state
    const testBtn = event.target;
    const originalText = testBtn.innerHTML;
    testBtn.innerHTML = '<div class="inline-block animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>Sending...';
    testBtn.disabled = true;
    
    // Get current form data
    const formData = new FormData(document.getElementById('emailSettingsForm'));
    
    // Prepare test configuration
    const testConfig = {
        provider: formData.get('email_provider'),
        from_email: formData.get('email_from_email'),
        from_name: formData.get('email_from_name'),
        smtp_host: formData.get('email_smtp_host'),
        smtp_port: formData.get('email_smtp_port'),
        smtp_user: formData.get('email_smtp_user'),
        smtp_pass: formData.get('email_smtp_pass'),
        smtp_encryption: formData.get('email_smtp_encryption'),
        sendgrid_api_key: formData.get('email_sendgrid_api_key')
    };
    
    // Send test email
    fetch('/api/email/test.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            test_email: testEmail,
            config: testConfig
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Test email sent successfully!\n\nPlease check your inbox and spam folder.');
        } else {
            alert('❌ Test email failed:\n\n' + (data.message || 'Unknown error'));
            console.error('Test results:', data.test_results);
        }
    })
    .catch(error => {
        console.error('Test error:', error);
        alert('❌ Test email failed due to network error. Please try again.');
    })
    .finally(() => {
        // Restore button state
        testBtn.innerHTML = originalText;
        testBtn.disabled = false;
    });
}

function loadEmailStats() {
    fetch('/api/email/stats.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('total-sent').textContent = data.stats.total_sent || 0;
                document.getElementById('delivery-rate').textContent = (data.stats.delivery_rate || 0).toFixed(1) + '%';
                document.getElementById('open-rate').textContent = (data.stats.open_rate || 0).toFixed(1) + '%';
                document.getElementById('click-rate').textContent = (data.stats.click_rate || 0).toFixed(1) + '%';
                
                document.getElementById('email-stats-loading').classList.add('hidden');
                document.getElementById('email-stats-content').classList.remove('hidden');
            }
        })
        .catch(error => {
            console.error('Failed to load email stats:', error);
            document.getElementById('email-stats-loading').innerHTML = '<p class="text-red-400">Failed to load statistics</p>';
        });
}

// Initialize email settings when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Toggle provider settings on page load
    toggleProviderSettings();
    
    // Load email statistics when email tab is shown
    const emailTab = document.getElementById('tab-email');
    if (emailTab) {
        emailTab.addEventListener('click', function() {
            setTimeout(loadEmailStats, 100); // Small delay to ensure tab content is visible
        });
    }
});

</script>

<!-- Include Branding JavaScript -->
<script src="/assets/js/branding.js"></script>

<?php renderFooter(); ?>