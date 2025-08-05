<?php
/**
 * ghst_ Complete Setup Installer
 * Handles database, admin account, and OAuth setup
 */

session_start();

// Check if already installed
if (file_exists('config.php') && !isset($_GET['force'])) {
    die('
    <div style="font-family: monospace; padding: 50px; text-align: center;">
        <h2>🎉 Installation Already Complete!</h2>
        <p>ghst_ is already installed on this server.</p>
        <a href="/login.php" style="background: #8B5CF6; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">Go to Login</a>
        <br><br>
        <small><a href="?force=1">Force Reinstall</a></small>
    </div>');
}

$step = (int)($_GET['step'] ?? 1);
$error = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 1: // Database setup
            handleDatabaseSetup();
            break;
        case 2: // Admin account
            handleAdminSetup();
            break;
        case 3: // OAuth setup
            handleOAuthSetup();
            break;
    }
}

function handleDatabaseSetup() {
    global $error;
    
    $host = trim($_POST['db_host'] ?? 'localhost');
    $name = trim($_POST['db_name'] ?? '');
    $user = trim($_POST['db_user'] ?? '');
    $pass = $_POST['db_pass'] ?? '';
    $port = (int)($_POST['db_port'] ?? 3306);
    
    if (!$name || !$user) {
        $error = 'Database name and username are required';
        return;
    }
    
    try {
        // Test connection
        $dsn = "mysql:host=$host;port=$port;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `$name`");
        
        // Import schema
        $schema = file_get_contents('db/schema.sql');
        
        // Remove comments and split by semicolons
        $schema = preg_replace('/--.*?\n/', "\n", $schema);
        $schema = preg_replace('/\/\*.*?\*\//s', '', $schema);
        
        // Split into individual statements
        $statements = array_filter(array_map('trim', explode(';', $schema)));
        
        $totalStatements = count($statements);
        $completed = 0;
        
        foreach ($statements as $statement) {
            if (empty($statement) || strpos($statement, '--') === 0) {
                continue;
            }
            
            // Skip delimiter changes
            if (stripos($statement, 'DELIMITER') === 0) {
                continue;
            }
            
            try {
                $pdo->exec($statement);
                $completed++;
            } catch (PDOException $e) {
                // Ignore duplicate entry errors for default data
                if (strpos($e->getMessage(), 'Duplicate entry') === false) {
                    throw $e;
                }
            }
        }
        
        // Store database info
        $_SESSION['install'] = [
            'db_host' => $host,
            'db_name' => $name,
            'db_user' => $user,
            'db_pass' => $pass,
            'db_port' => $port
        ];
        
        header('Location: installer.php?step=2');
        exit;
        
    } catch (PDOException $e) {
        $error = 'Database setup failed: ' . $e->getMessage();
    }
}

function handleAdminSetup() {
    global $error;
    
    if (!isset($_SESSION['install'])) {
        header('Location: installer.php');
        exit;
    }
    
    $email = trim($_POST['admin_email'] ?? '');
    $password = $_POST['admin_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $appUrl = trim($_POST['app_url'] ?? '');
    
    if (!$email || !$password || !$appUrl) {
        $error = 'All fields are required';
        return;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address';
        return;
    }
    
    if ($password !== $confirmPassword) {
        $error = 'Passwords do not match';
        return;
    }
    
    if (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters';
        return;
    }
    
    try {
        // Connect to database
        $db = $_SESSION['install'];
        $dsn = "mysql:host={$db['db_host']};port={$db['db_port']};dbname={$db['db_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $db['db_user'], $db['db_pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create admin user
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, name) VALUES (?, ?, ?)");
        $stmt->execute([$email, $hashedPassword, 'Admin']);
        
        // Store admin info
        $_SESSION['install']['admin_email'] = $email;
        $_SESSION['install']['app_url'] = rtrim($appUrl, '/');
        
        header('Location: installer.php?step=3');
        exit;
        
    } catch (PDOException $e) {
        $error = 'Failed to create admin account: ' . $e->getMessage();
    }
}

function handleOAuthSetup() {
    global $error;
    
    if (!isset($_SESSION['install'])) {
        header('Location: installer.php');
        exit;
    }
    
    // Skip if user chose to skip OAuth
    if (isset($_POST['skip_oauth'])) {
        createConfigFile();
        return;
    }
    
    // Validate OAuth credentials
    $platforms = ['facebook', 'twitter', 'linkedin'];
    $oauthData = [];
    
    foreach ($platforms as $platform) {
        $clientId = trim($_POST[$platform . '_client_id'] ?? '');
        $clientSecret = trim($_POST[$platform . '_client_secret'] ?? '');
        
        if ($clientId && $clientSecret) {
            $oauthData[$platform] = [
                'client_id' => $clientId,
                'client_secret' => $clientSecret
            ];
        }
    }
    
    $_SESSION['install']['oauth'] = $oauthData;
    createConfigFile();
}

function createConfigFile() {
    $install = $_SESSION['install'];
    
    // Generate secure keys
    $passwordSalt = bin2hex(random_bytes(32));
    $encryptionKey = bin2hex(random_bytes(16));
    $cronSecret = bin2hex(random_bytes(16));
    
    // OAuth credentials
    $oauth = $install['oauth'] ?? [];
    
    $fbAppId = $oauth['facebook']['client_id'] ?? 'your_facebook_app_id';
    $fbAppSecret = $oauth['facebook']['client_secret'] ?? 'your_facebook_app_secret';
    $twitterKey = $oauth['twitter']['client_id'] ?? 'your_twitter_api_key';
    $twitterSecret = $oauth['twitter']['client_secret'] ?? 'your_twitter_api_secret';
    $linkedinId = $oauth['linkedin']['client_id'] ?? 'your_linkedin_client_id';
    $linkedinSecret = $oauth['linkedin']['client_secret'] ?? 'your_linkedin_client_secret';
    
    $configContent = "<?php
/**
 * ghst_ Configuration File
 * Generated by installer on " . date('Y-m-d H:i:s') . "
 */

// Environment
define('ENVIRONMENT', 'production');
define('DEMO_MODE', false); // Set to true for testing without real OAuth

// Database Configuration
define('DB_HOST', '{$install['db_host']}');
define('DB_NAME', '{$install['db_name']}');
define('DB_USER', '{$install['db_user']}');
define('DB_PASS', '{$install['db_pass']}');
define('DB_CHARSET', 'utf8mb4');
define('DB_PORT', {$install['db_port']});

// Application Settings
define('APP_NAME', 'ghst_');
define('APP_URL', '{$install['app_url']}');
define('APP_TIMEZONE', 'UTC');

// Security
define('SESSION_NAME', 'ghst_session');
define('CSRF_TOKEN_NAME', 'ghst_token');
define('PASSWORD_SALT', '{$passwordSalt}');
define('ENCRYPTION_KEY', '{$encryptionKey}');

// Paths
define('ROOT_PATH', dirname(__FILE__));
define('UPLOADS_PATH', ROOT_PATH . '/uploads');
define('INCLUDES_PATH', ROOT_PATH . '/includes');
define('MEDIA_PATH', ROOT_PATH . '/uploads/media');

// Upload Settings
define('MAX_UPLOAD_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_VIDEO_TYPES', ['mp4', 'mov', 'avi', 'webm']);

// OAuth Settings (Social Media Platforms)
define('OAUTH_REDIRECT_BASE', APP_URL . '/api/oauth/callback/');

// Facebook/Instagram
define('FB_APP_ID', '{$fbAppId}');
define('FB_APP_SECRET', '{$fbAppSecret}');
define('FB_API_VERSION', 'v18.0');

// LinkedIn
define('LINKEDIN_CLIENT_ID', '{$linkedinId}');
define('LINKEDIN_CLIENT_SECRET', '{$linkedinSecret}');

// Twitter/X
define('TWITTER_API_KEY', '{$twitterKey}');
define('TWITTER_API_SECRET', '{$twitterSecret}');
define('TWITTER_BEARER_TOKEN', 'your_twitter_bearer_token');

// Google (for OAuth login)
define('GOOGLE_CLIENT_ID', 'your_google_client_id');
define('GOOGLE_CLIENT_SECRET', 'your_google_client_secret');

// Cron Settings
define('CRON_SECRET', '{$cronSecret}');
define('POST_BATCH_SIZE', 10);
define('RETRY_DELAY_MINUTES', 30);

// Email Settings (for notifications)
define('SMTP_HOST', 'mail.yourdomain.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'noreply@yourdomain.com');
define('SMTP_PASS', 'your_email_password');
define('SMTP_FROM_NAME', 'ghst_');
define('SMTP_FROM_EMAIL', 'noreply@yourdomain.com');

// Logging
define('LOG_ERRORS', true);
define('LOG_PATH', ROOT_PATH . '/logs');
define('LOG_LEVEL', 'debug');

// Performance
define('CACHE_ENABLED', false);
define('CACHE_PATH', ROOT_PATH . '/cache');
define('CACHE_TTL', 3600);

// Debug (disable in production!)
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Session Configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 for HTTPS
ini_set('session.cookie_samesite', 'Lax');

// Timezone
date_default_timezone_set(APP_TIMEZONE);
";
    
    if (file_put_contents('config.php', $configContent)) {
        // Clean up session
        unset($_SESSION['install']);
        header('Location: installer.php?step=4');
        exit;
    } else {
        $error = 'Failed to create config.php. Check file permissions.';
    }
}

// Get current step data for display
$installData = $_SESSION['install'] ?? [];
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ghst_ Setup - Step <?= $step ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'JetBrains Mono', monospace; }
        .glow { text-shadow: 0 0 10px rgba(139, 92, 246, 0.5); }
    </style>
</head>
<body class="h-full bg-black text-white">
    <div class="min-h-full flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-2xl w-full space-y-8">
            <!-- Header -->
            <div class="text-center">
                <h1 class="text-4xl font-bold glow">
                    <span class="text-purple-500">*</span> ghst_ Setup
                </h1>
                <p class="mt-2 text-gray-400">Social Media Scheduling Platform</p>
                
                <!-- Progress Bar -->
                <div class="mt-8 flex justify-center">
                    <div class="flex items-center space-x-4">
                        <?php for ($i = 1; $i <= 4; $i++): ?>
                            <div class="flex items-center">
                                <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium
                                    <?= $i <= $step ? 'bg-purple-600 text-white' : 'bg-gray-800 text-gray-400' ?>">
                                    <?= $i ?>
                                </div>
                                <?php if ($i < 4): ?>
                                    <div class="w-12 h-0.5 ml-4 <?= $i < $step ? 'bg-purple-600' : 'bg-gray-800' ?>"></div>
                                <?php endif; ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-900/50 border border-red-700 rounded-lg p-4">
                    <div class="flex items-center space-x-2">
                        <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01"></path>
                        </svg>
                        <span class="text-red-200"><?= htmlspecialchars($error) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="bg-green-900/50 border border-green-700 rounded-lg p-4">
                    <div class="flex items-center space-x-2">
                        <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span class="text-green-200"><?= htmlspecialchars($success) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Step Content -->
            <div class="bg-gray-900 rounded-lg border border-gray-800 p-8">
                <?php 
                switch ($step) {
                    case 1: include 'installer/step1-database.php'; break;
                    case 2: include 'installer/step2-admin.php'; break;
                    case 3: include 'installer/step3-oauth.php'; break;
                    case 4: include 'installer/step4-complete.php'; break;
                    default: include 'installer/step1-database.php';
                }
                ?>
            </div>
        </div>
    </div>
</body>
</html>