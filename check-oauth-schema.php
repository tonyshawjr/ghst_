<?php
/**
 * Check OAuth Schema Compatibility
 * Verifies if the database schema supports OAuth implementation
 */

require_once 'config.php';
require_once 'includes/Database.php';

try {
    $db = Database::getInstance();
    echo "🔍 Checking OAuth schema compatibility...\n\n";
    
    // Check accounts table structure
    $stmt = $db->prepare("DESCRIBE accounts");
    $stmt->execute();
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $requiredColumns = [
        'platform_user_id',
        'display_name', 
        'token_expires_at',
        'account_data'
    ];
    
    $missingColumns = array_diff($requiredColumns, $columns);
    
    if (empty($missingColumns)) {
        echo "✅ Accounts table schema is OAuth-ready\n";
        $schemaReady = true;
    } else {
        echo "⚠️  Accounts table missing OAuth columns: " . implode(', ', $missingColumns) . "\n";
        $schemaReady = false;
    }
    
    // Check logs table
    $stmt = $db->prepare("DESCRIBE logs");
    $stmt->execute();
    $logColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (in_array('level', $logColumns) && in_array('context', $logColumns)) {
        echo "✅ Logs table supports OAuth logging\n";
    } else {
        echo "⚠️  Logs table missing OAuth logging columns\n";
        $schemaReady = false;
    }
    
    echo "\n";
    
    if ($schemaReady) {
        echo "🎉 Database schema is ready for OAuth!\n";
        echo "✅ You can now test OAuth flows at /dashboard/accounts.php\n\n";
        
        // Test OAuth implementation
        echo "🧪 Testing OAuth system...\n";
        require_once 'includes/OAuth.php';
        $oauth = new OAuth();
        echo "✅ OAuth class loads successfully\n";
        
    } else {
        echo "❌ Database schema needs updating for OAuth support\n\n";
        echo "📋 Required SQL commands:\n";
        echo "```sql\n";
        if (in_array('platform_user_id', $missingColumns)) {
            echo "ALTER TABLE accounts ADD COLUMN platform_user_id varchar(255) AFTER platform;\n";
        }
        if (in_array('display_name', $missingColumns)) {
            echo "ALTER TABLE accounts ADD COLUMN display_name varchar(255) AFTER username;\n";
        }
        if (in_array('token_expires_at', $missingColumns)) {
            echo "ALTER TABLE accounts ADD COLUMN token_expires_at datetime AFTER expires_at;\n";
        }
        if (in_array('account_data', $missingColumns)) {
            echo "ALTER TABLE accounts ADD COLUMN account_data json AFTER token_expires_at;\n";
        }
        if (!in_array('level', $logColumns)) {
            echo "ALTER TABLE logs ADD COLUMN level enum('debug','info','warning','error') AFTER action;\n";
        }
        if (!in_array('context', $logColumns)) {
            echo "ALTER TABLE logs ADD COLUMN context json AFTER details;\n";
        }
        echo "```\n\n";
        echo "💡 You can run these commands manually in your database admin tool\n";
        echo "   or use phpMyAdmin/Adminer to execute them.\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
}