<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/functions.php';

$auth = new Auth();
$error = '';

if ($auth->isLoggedIn()) {
    redirect('/dashboard/');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!$auth->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } elseif (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } elseif (!validateEmail($email)) {
        $error = 'Please enter a valid email address.';
    } else {
        if ($auth->login($email, $password)) {
            redirect('/dashboard/');
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

$csrfToken = $auth->generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ghst_ - Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'JetBrains Mono', monospace; }
        .glow { text-shadow: 0 0 10px rgba(139, 92, 246, 0.5); }
        .typing-effect {
            overflow: hidden;
            border-right: .15em solid #8b5cf6;
            white-space: pre;
            animation: typing 3.5s steps(40, end), blink-caret .75s step-end infinite;
        }
        @keyframes typing {
            from { width: 0 }
            to { width: 100% }
        }
        @keyframes blink-caret {
            from, to { border-color: transparent }
            50% { border-color: #8b5cf6; }
        }
    </style>
</head>
<body class="h-full bg-black text-white">
    <div class="flex h-full">
        <!-- Left Panel -->
        <div class="hidden lg:flex lg:w-1/2 bg-gradient-to-br from-gray-900 to-black p-12 flex-col justify-between">
            <div>
                <h1 class="text-5xl font-bold mb-8">
                    <span class="text-purple-500">*</span> ghst_
                </h1>
                <p class="text-gray-400 mb-12">Multi-client social media scheduling for the modern web.</p>
                
                <div class="bg-gray-900 rounded-lg p-6 border border-gray-800">
                    <p class="text-sm text-gray-500 mb-2"># Schedule a post via API</p>
                    <pre class="text-sm text-green-400"><code>curl -X POST https://api.ghst.app/v1/posts \
  -H "Authorization: Bearer YOUR_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "content": "Launching something big 🚀",
    "platforms": ["instagram", "twitter"],
    "scheduled_at": "2025-01-01T12:00:00Z"
  }'</code></pre>
                </div>
            </div>
            
            <div class="space-y-4">
                <p class="text-sm text-gray-500">Built for agencies. Powered by automation.</p>
                <div class="flex space-x-4 text-gray-600">
                    <span class="text-xs">v1.0.0</span>
                    <span class="text-xs">•</span>
                    <span class="text-xs">MIT License</span>
                </div>
            </div>
        </div>
        
        <!-- Right Panel -->
        <div class="flex-1 flex items-center justify-center p-8 bg-black">
            <div class="w-full max-w-md">
                <div class="text-center mb-8 lg:hidden">
                    <h1 class="text-4xl font-bold">
                        <span class="text-purple-500">*</span> ghst_
                    </h1>
                </div>
                
                <div class="bg-gray-900 rounded-lg p-8 border border-gray-800">
                    <h2 class="text-2xl font-bold mb-6">Sign in</h2>
                    
                    <?php if ($error): ?>
                        <div class="bg-red-900/20 border border-red-500 text-red-400 px-4 py-3 rounded mb-6">
                            <?= sanitize($error) ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        
                        <div>
                            <label for="email" class="block text-sm font-medium mb-2">Email</label>
                            <input 
                                type="email" 
                                id="email" 
                                name="email" 
                                required
                                class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500 transition-colors"
                                placeholder="admin@ghst.app"
                            >
                        </div>
                        
                        <div>
                            <label for="password" class="block text-sm font-medium mb-2">Password</label>
                            <input 
                                type="password" 
                                id="password" 
                                name="password" 
                                required
                                class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500 transition-colors"
                                placeholder="••••••••"
                            >
                        </div>
                        
                        <div class="flex items-center justify-between">
                            <label class="flex items-center">
                                <input type="checkbox" class="mr-2 bg-black border-gray-700">
                                <span class="text-sm text-gray-400">Remember me</span>
                            </label>
                            <a href="#" class="text-sm text-purple-500 hover:text-purple-400">Forgot password?</a>
                        </div>
                        
                        <button 
                            type="submit"
                            class="w-full py-3 bg-purple-600 hover:bg-purple-700 rounded-lg font-medium transition-colors"
                        >
                            Sign in
                        </button>
                    </form>
                    
                    <div class="mt-6 text-center">
                        <div class="relative">
                            <div class="absolute inset-0 flex items-center">
                                <div class="w-full border-t border-gray-800"></div>
                            </div>
                            <div class="relative flex justify-center text-sm">
                                <span class="px-2 bg-gray-900 text-gray-500">Or continue with</span>
                            </div>
                        </div>
                        
                        <button 
                            type="button"
                            class="mt-4 w-full py-3 bg-gray-800 hover:bg-gray-700 rounded-lg font-medium transition-colors flex items-center justify-center space-x-2"
                            onclick="alert('Google OAuth coming soon!')"
                        >
                            <svg class="w-5 h-5" viewBox="0 0 24 24">
                                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                            </svg>
                            <span>Google</span>
                        </button>
                    </div>
                    
                    <p class="mt-6 text-center text-sm text-gray-500">
                        Admin access only. Contact support for access.
                    </p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>