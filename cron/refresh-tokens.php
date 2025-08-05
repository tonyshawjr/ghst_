<?php
/**
 * Token Refresh Cron Job
 * Automatically refreshes OAuth tokens that are about to expire
 * 
 * Add to crontab:
 * 0 6,12,18,0 * * * /usr/bin/php /path/to/ghst_/cron/refresh-tokens.php
 */

require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/OAuth.php';
require_once '../includes/functions.php';

// Only run from command line or with proper cron secret
if (php_sapi_name() !== 'cli' && (!isset($_GET['secret']) || $_GET['secret'] !== CRON_SECRET)) {
    http_response_code(403);
    die('Access denied');
}

$startTime = time();
$refreshed = 0;
$failed = 0;
$errors = [];

try {
    $db = Database::getInstance();
    $oauth = new OAuth();
    
    // Find accounts with tokens that expire within 24 hours and have refresh tokens
    $stmt = $db->prepare("
        SELECT id, platform, platform_user_id, username, display_name, token_expires_at 
        FROM accounts 
        WHERE is_active = 1 
          AND token_expires_at IS NOT NULL 
          AND token_expires_at <= DATE_ADD(NOW(), INTERVAL 24 HOUR)
          AND refresh_token IS NOT NULL 
          AND refresh_token != ''
        ORDER BY token_expires_at ASC
    ");
    $stmt->execute();
    $accounts = $stmt->fetchAll();
    
    if (empty($accounts)) {
        logMessage("No tokens need refreshing");
        exit(0);
    }
    
    logMessage("Found " . count($accounts) . " accounts with expiring tokens");
    
    foreach ($accounts as $account) {
        try {
            logMessage("Refreshing token for {$account['platform']} account: {$account['display_name']} ({$account['id']})");
            
            if ($oauth->refreshToken($account['id'])) {
                $refreshed++;
                logMessage("✅ Successfully refreshed token for account {$account['id']}");
            } else {
                $failed++;
                $error = "Failed to refresh token for account {$account['id']} ({$account['platform']}: {$account['display_name']})";
                $errors[] = $error;
                logMessage("❌ " . $error);
            }
            
        } catch (Exception $e) {
            $failed++;
            $error = "Exception refreshing token for account {$account['id']}: " . $e->getMessage();
            $errors[] = $error;
            logMessage("❌ " . $error);
        }
        
        // Small delay to be nice to APIs
        usleep(100000); // 0.1 seconds
    }
    
    $duration = time() - $startTime;
    $summary = "Token refresh completed in {$duration}s. Refreshed: {$refreshed}, Failed: {$failed}";
    logMessage($summary);
    
    // Log summary to database
    if ($refreshed > 0 || $failed > 0) {
        $stmt = $db->prepare("
            INSERT INTO logs (level, message, context, created_at) 
            VALUES ('info', ?, ?, NOW())
        ");
        $stmt->execute([
            'Token refresh cron completed',
            json_encode([
                'refreshed' => $refreshed,
                'failed' => $failed,
                'duration' => $duration,
                'errors' => $errors
            ])
        ]);
    }
    
} catch (Exception $e) {
    logMessage("❌ Fatal error in token refresh cron: " . $e->getMessage());
    
    // Try to log to database
    try {
        $stmt = $db->prepare("
            INSERT INTO logs (level, message, context, created_at) 
            VALUES ('error', ?, ?, NOW())
        ");
        $stmt->execute([
            'Token refresh cron failed',
            json_encode(['error' => $e->getMessage()])
        ]);
    } catch (Exception $logError) {
        // Can't log to database, just continue
    }
    
    exit(1);
}

function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[{$timestamp}] {$message}" . PHP_EOL;
    
    // Log to file if possible
    if (defined('LOG_PATH') && is_dir(LOG_PATH)) {
        file_put_contents(LOG_PATH . '/token-refresh.log', $logLine, FILE_APPEND | LOCK_EX);
    }
    
    // Also output to console if running from CLI
    if (php_sapi_name() === 'cli') {
        echo $logLine;
    }
}