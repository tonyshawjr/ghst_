<?php
/**
 * ghst_ Installation Script
 * Run this once to set up your database
 */

// Check if already installed
if (file_exists('config.php')) {
    die('Installation already completed. Delete config.php to reinstall.');
}

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step == 1) {
        // Test database connection
        $host = $_POST['db_host'] ?? 'localhost';
        $name = $_POST['db_name'] ?? '';
        $user = $_POST['db_user'] ?? '';
        $pass = $_POST['db_pass'] ?? '';
        
        try {
            $dsn = "mysql:host=$host;charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Create database if it doesn't exist
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$name` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$name`");
            
            // Store in session for next step
            session_start();
            $_SESSION['install'] = [
                'db_host' => $host,
                'db_name' => $name,
                'db_user' => $user,
                'db_pass' => $pass
            ];
            
            header('Location: install.php?step=2');
            exit;
        } catch (PDOException $e) {
            $error = 'Database connection failed: ' . $e->getMessage();
        }
    } elseif ($step == 2) {
        session_start();
        if (!isset($_SESSION['install'])) {
            header('Location: install.php');
            exit;
        }
        
        $install = $_SESSION['install'];
        
        // Import schema
        try {
            $dsn = "mysql:host={$install['db_host']};dbname={$install['db_name']};charset=utf8mb4";
            $pdo = new PDO($dsn, $install['db_user'], $install['db_pass']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Read and execute schema
            $schema = file_get_contents('db/schema.sql');
            $pdo->exec($schema);
            
            $_SESSION['install']['schema_imported'] = true;
            header('Location: install.php?step=3');
            exit;
        } catch (Exception $e) {
            $error = 'Schema import failed: ' . $e->getMessage();
        }
    } elseif ($step == 3) {
        session_start();
        if (!isset($_SESSION['install']) || !$_SESSION['install']['schema_imported']) {
            header('Location: install.php');
            exit;
        }
        
        $install = $_SESSION['install'];
        
        // Create admin user
        $email = $_POST['admin_email'] ?? '';
        $pass = $_POST['admin_pass'] ?? '';
        $name = $_POST['admin_name'] ?? '';
        $appUrl = $_POST['app_url'] ?? '';
        
        if (empty($email) || empty($pass) || empty($name) || empty($appUrl)) {
            $error = 'All fields are required';
        } else {
            try {
                $dsn = "mysql:host={$install['db_host']};dbname={$install['db_name']};charset=utf8mb4";
                $pdo = new PDO($dsn, $install['db_user'], $install['db_pass']);
                
                // Create admin user
                $hash = password_hash($pass, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, name) VALUES (?, ?, ?)");
                $stmt->execute([$email, $hash, $name]);
                
                // Generate config file
                $configTemplate = file_get_contents('config.example.php');
                $config = str_replace([
                    'your_database_host',
                    'your_database_name',
                    'your_database_user',
                    'your_database_password',
                    'https://yourdomain.com',
                    'your-random-salt-here',
                    'your-32-character-encryption-key',
                    'your-cron-secret-key'
                ], [
                    $install['db_host'],
                    $install['db_name'],
                    $install['db_user'],
                    $install['db_pass'],
                    rtrim($appUrl, '/'),
                    bin2hex(random_bytes(16)),
                    bin2hex(random_bytes(16)),
                    bin2hex(random_bytes(16))
                ], $configTemplate);
                
                file_put_contents('config.php', $config);
                
                // Clear session
                session_destroy();
                
                $success = 'Installation completed successfully!';
            } catch (Exception $e) {
                $error = 'Setup failed: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ghst_ Installation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'JetBrains Mono', monospace; }
    </style>
</head>
<body class="bg-black text-white min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-lg">
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold mb-2">
                <span class="text-purple-500">*</span> ghst_
            </h1>
            <p class="text-gray-400">Installation Wizard</p>
        </div>
        
        <!-- Progress -->
        <div class="flex items-center justify-center mb-8">
            <div class="flex items-center space-x-4">
                <div class="w-8 h-8 rounded-full <?= $step >= 1 ? 'bg-purple-600' : 'bg-gray-700' ?> flex items-center justify-center">1</div>
                <div class="w-16 h-1 <?= $step >= 2 ? 'bg-purple-600' : 'bg-gray-700' ?>"></div>
                <div class="w-8 h-8 rounded-full <?= $step >= 2 ? 'bg-purple-600' : 'bg-gray-700' ?> flex items-center justify-center">2</div>
                <div class="w-16 h-1 <?= $step >= 3 ? 'bg-purple-600' : 'bg-gray-700' ?>"></div>
                <div class="w-8 h-8 rounded-full <?= $step >= 3 ? 'bg-purple-600' : 'bg-gray-700' ?> flex items-center justify-center">3</div>
            </div>
        </div>
        
        <div class="bg-gray-900 rounded-lg p-8 border border-gray-800">
            <?php if ($error): ?>
                <div class="bg-red-900/20 border border-red-500 text-red-400 px-4 py-3 rounded mb-6">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="bg-green-900/20 border border-green-500 text-green-400 px-4 py-3 rounded mb-6">
                    <?= htmlspecialchars($success) ?>
                </div>
                <div class="text-center">
                    <a href="/login.php" class="inline-block px-6 py-3 bg-purple-600 hover:bg-purple-700 rounded-lg font-medium transition-colors">
                        Go to Login
                    </a>
                </div>
            <?php elseif ($step == 1): ?>
                <h2 class="text-xl font-bold mb-6">Step 1: Database Configuration</h2>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Database Host</label>
                        <input type="text" name="db_host" value="localhost" required class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Database Name</label>
                        <input type="text" name="db_name" required class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Database User</label>
                        <input type="text" name="db_user" required class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Database Password</label>
                        <input type="password" name="db_pass" class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500">
                    </div>
                    <button type="submit" class="w-full py-3 bg-purple-600 hover:bg-purple-700 rounded-lg font-medium transition-colors">
                        Test Connection & Continue
                    </button>
                </form>
            <?php elseif ($step == 2): ?>
                <h2 class="text-xl font-bold mb-6">Step 2: Import Database Schema</h2>
                <p class="text-gray-400 mb-6">Click below to import the database schema. This will create all necessary tables.</p>
                <form method="POST">
                    <button type="submit" class="w-full py-3 bg-purple-600 hover:bg-purple-700 rounded-lg font-medium transition-colors">
                        Import Schema
                    </button>
                </form>
            <?php elseif ($step == 3): ?>
                <h2 class="text-xl font-bold mb-6">Step 3: Create Admin Account</h2>
                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-2">Application URL</label>
                        <input type="url" name="app_url" placeholder="https://yourdomain.com" required class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Admin Name</label>
                        <input type="text" name="admin_name" required class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Admin Email</label>
                        <input type="email" name="admin_email" required class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-2">Admin Password</label>
                        <input type="password" name="admin_pass" required minlength="8" class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500">
                        <p class="text-xs text-gray-500 mt-1">Minimum 8 characters</p>
                    </div>
                    <button type="submit" class="w-full py-3 bg-purple-600 hover:bg-purple-700 rounded-lg font-medium transition-colors">
                        Complete Installation
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>