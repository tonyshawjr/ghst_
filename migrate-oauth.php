<?php
/**
 * OAuth Database Migration Script
 * Updates the database schema to support OAuth implementation
 */

require_once 'config.php';
require_once 'includes/Database.php';

try {
    $db = Database::getInstance();
    echo "🔄 Starting OAuth database migration...\n\n";
    
    // Check if migration is needed
    $stmt = $db->prepare("SHOW COLUMNS FROM accounts LIKE 'platform_user_id'");
    $stmt->execute();
    if ($stmt->fetch()) {
        echo "✅ Migration already applied. Database is up to date.\n";
        exit(0);
    }
    
    // Add new columns to accounts table
    echo "1. Adding new columns to accounts table...\n";
    $db->exec("ALTER TABLE `accounts` 
        ADD COLUMN `platform_user_id` varchar(255) AFTER `platform`,
        ADD COLUMN `display_name` varchar(255) AFTER `username`,
        ADD COLUMN `token_expires_at` datetime AFTER `expires_at`,
        ADD COLUMN `account_data` json AFTER `token_expires_at`");
    echo "✅ Added: platform_user_id, display_name, token_expires_at, account_data\n\n";
    
    // Update existing data
    echo "2. Migrating existing data...\n";
    $db->exec("UPDATE `accounts` SET 
        `platform_user_id` = COALESCE(`account_id`, `username`),
        `display_name` = `username`,
        `token_expires_at` = `expires_at`");
    echo "✅ Migrated existing account data\n\n";
    
    // Check if logs table needs level column
    $stmt = $db->prepare("SHOW COLUMNS FROM logs LIKE 'level'");
    $stmt->execute();
    if (!$stmt->fetch()) {
        echo "3. Updating logs table for OAuth operations...\n";
        $db->exec("ALTER TABLE `logs` 
            ADD COLUMN `level` enum('debug','info','warning','error') AFTER `action`,
            ADD COLUMN `context` json AFTER `details`");
        
        $db->exec("UPDATE `logs` SET `level` = 'info' WHERE `level` IS NULL");
        echo "✅ Updated logs table with level and context columns\n\n";
    } else {
        echo "3. Logs table already has level column, skipping...\n\n";
    }
    
    // Create indexes
    echo "4. Creating OAuth-related indexes...\n";
    try {
        $db->exec("CREATE INDEX idx_accounts_platform_user ON accounts(platform, platform_user_id)");
        echo "✅ Created idx_accounts_platform_user\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "⚠️  idx_accounts_platform_user already exists\n";
        } else {
            throw $e;
        }
    }
    
    try {
        $db->exec("CREATE INDEX idx_accounts_token_expiry ON accounts(token_expires_at, is_active)");
        echo "✅ Created idx_accounts_token_expiry\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "⚠️  idx_accounts_token_expiry already exists\n";
        } else {
            throw $e;
        }
    }
    
    try {
        $db->exec("CREATE INDEX idx_logs_level ON logs(level, created_at)");
        echo "✅ Created idx_logs_level\n";
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "⚠️  idx_logs_level already exists\n";
        } else {
            throw $e;
        }
    }
    
    echo "\n🎉 OAuth database migration completed successfully!\n";
    echo "\n✅ Your database is now ready for OAuth implementation:\n";
    echo "   • OAuth tokens and user data can be stored\n";
    echo "   • Token expiration tracking is enabled\n";
    echo "   • Enhanced logging for OAuth operations\n";
    echo "   • Optimized indexes for OAuth queries\n\n";
    
    echo "🚀 Next steps:\n";
    echo "   1. Configure OAuth credentials in /dashboard/oauth-setup.php\n";
    echo "   2. Test OAuth flows at /dashboard/accounts.php\n";
    echo "   3. Set up token refresh cron job\n\n";
    
} catch (Exception $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    echo "   Please check your database connection and try again.\n";
    exit(1);
}