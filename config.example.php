<?php
/**
 * ghst_ Configuration File
 * Copy this file to config.php and update with your settings
 */

// Environment
define('ENVIRONMENT', 'development'); // development, production

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_database_name');
define('DB_USER', 'your_database_user');
define('DB_PASS', 'your_database_password');
define('DB_CHARSET', 'utf8mb4');

// Application Settings
define('APP_NAME', 'ghst_');
define('APP_URL', 'https://yourdomain.com');
define('APP_TIMEZONE', 'UTC');

// Security
define('SESSION_NAME', 'ghst_session');
define('CSRF_TOKEN_NAME', 'ghst_token');
define('PASSWORD_SALT', 'your-random-salt-here'); // Change this!
define('ENCRYPTION_KEY', 'your-32-character-encryption-key'); // 32 chars

// Paths
define('ROOT_PATH', dirname(__FILE__));
define('PUBLIC_PATH', ROOT_PATH . '/public_html');
define('UPLOADS_PATH', PUBLIC_PATH . '/uploads');
define('INCLUDES_PATH', ROOT_PATH . '/includes');

// Upload Settings
define('MAX_UPLOAD_SIZE', 50 * 1024 * 1024); // 50MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_VIDEO_TYPES', ['mp4', 'mov', 'avi', 'webm']);

// OAuth Settings (Social Media Platforms)
define('OAUTH_REDIRECT_BASE', APP_URL . '/api/oauth/callback/');

// Facebook/Instagram
define('FB_APP_ID', 'your_facebook_app_id');
define('FB_APP_SECRET', 'your_facebook_app_secret');
define('FB_API_VERSION', 'v18.0');

// LinkedIn
define('LINKEDIN_CLIENT_ID', 'your_linkedin_client_id');
define('LINKEDIN_CLIENT_SECRET', 'your_linkedin_client_secret');

// Twitter/X
define('TWITTER_API_KEY', 'your_twitter_api_key');
define('TWITTER_API_SECRET', 'your_twitter_api_secret');
define('TWITTER_BEARER_TOKEN', 'your_twitter_bearer_token');

// Google (for OAuth login)
define('GOOGLE_CLIENT_ID', 'your_google_client_id');
define('GOOGLE_CLIENT_SECRET', 'your_google_client_secret');

// Cron Settings
define('CRON_SECRET', 'your-cron-secret-key'); // Protect cron endpoint
define('POST_BATCH_SIZE', 10); // Posts to process per cron run
define('RETRY_DELAY_MINUTES', 30); // Minutes before retrying failed posts

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
define('LOG_LEVEL', 'debug'); // debug, info, warning, error

// Performance
define('CACHE_ENABLED', false);
define('CACHE_PATH', ROOT_PATH . '/cache');
define('CACHE_TTL', 3600); // 1 hour

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
ini_set('session.cookie_secure', 1); // Enable for HTTPS
ini_set('session.cookie_samesite', 'Strict');

// Timezone
date_default_timezone_set(APP_TIMEZONE);