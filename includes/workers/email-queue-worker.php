<?php
/**
 * Email Queue Worker
 * Background worker for processing email queue
 * This script should be run via cron job or process manager
 */

// Prevent running from web browser
if (php_sapi_name() !== 'cli') {
    die('This script can only be run from command line');
}

// Set memory limit and execution time for batch processing
ini_set('memory_limit', '256M');
ini_set('max_execution_time', 0); // No time limit for CLI

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../EmailService.php';

/**
 * Email Queue Worker Class
 */
class EmailQueueWorker {
    private $db;
    private $emailService;
    private $running = false;
    private $processedCount = 0;
    private $failedCount = 0;
    private $startTime;
    private $logFile;
    
    // Configuration
    private $batchSize = 50;
    private $delayBetweenEmails = 100000; // 0.1 second in microseconds
    private $delayBetweenBatches = 1000000; // 1 second in microseconds
    private $maxRunTime = 3600; // 1 hour max run time
    private $lockFile;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->emailService = EmailService::getInstance();
        $this->startTime = time();
        $this->logFile = LOG_PATH . '/email-worker-' . date('Y-m-d') . '.log';
        $this->lockFile = ROOT_PATH . '/email-worker.lock';
        
        // Ensure log directory exists
        if (!is_dir(LOG_PATH)) {
            mkdir(LOG_PATH, 0755, true);
        }
        
        // Set up signal handlers for graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleShutdown']);
            pcntl_signal(SIGINT, [$this, 'handleShutdown']);
        }
    }
    
    /**
     * Start the worker
     */
    public function start($maxBatches = null) {
        if (!$this->acquireLock()) {
            $this->log('Another worker instance is already running. Exiting.');
            return false;
        }
        
        $this->running = true;
        $batchCount = 0;
        
        $this->log('Email queue worker started');
        $this->log("Configuration: batch_size={$this->batchSize}, max_batches=" . ($maxBatches ?: 'unlimited'));
        
        try {
            while ($this->running) {
                // Check for shutdown signals
                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }
                
                // Check max run time
                if ((time() - $this->startTime) > $this->maxRunTime) {
                    $this->log('Max run time reached. Shutting down gracefully.');
                    break;
                }
                
                // Process a batch of emails
                $result = $this->processBatch();
                
                if ($result['processed'] > 0) {
                    $this->processedCount += $result['processed'];
                    $this->failedCount += $result['failed'];
                    $this->log("Batch processed: {$result['processed']} sent, {$result['failed']} failed");
                } else {
                    // No emails to process, wait and check again
                    $this->log('No emails in queue. Sleeping...');
                    sleep(30); // Wait 30 seconds before checking again
                }
                
                $batchCount++;
                
                // Check if we've reached max batches
                if ($maxBatches && $batchCount >= $maxBatches) {
                    $this->log("Processed {$maxBatches} batches. Shutting down.");
                    break;
                }
                
                // Delay between batches
                if ($result['processed'] > 0) {
                    usleep($this->delayBetweenBatches);
                }
            }
            
        } catch (Exception $e) {
            $this->log('Worker error: ' . $e->getMessage());
            $this->log('Stack trace: ' . $e->getTraceAsString());
        } finally {
            $this->cleanup();
        }
        
        return true;
    }
    
    /**
     * Process a batch of emails
     */
    private function processBatch() {
        try {
            $result = $this->emailService->processQueue($this->batchSize);
            return $result;
        } catch (Exception $e) {
            $this->log('Batch processing error: ' . $e->getMessage());
            return ['processed' => 0, 'failed' => 0, 'total' => 0];
        }
    }
    
    /**
     * Handle shutdown signals
     */
    public function handleShutdown($signal) {
        $this->log("Received shutdown signal ({$signal}). Stopping gracefully...");
        $this->running = false;
    }
    
    /**
     * Acquire lock to prevent multiple instances
     */
    private function acquireLock() {
        if (file_exists($this->lockFile)) {
            $pid = file_get_contents($this->lockFile);
            
            // Check if the process is still running
            if ($this->isProcessRunning($pid)) {
                return false;
            } else {
                // Stale lock file, remove it
                unlink($this->lockFile);
            }
        }
        
        // Create lock file with current PID
        file_put_contents($this->lockFile, getmypid());
        return true;
    }
    
    /**
     * Check if a process is running
     */
    private function isProcessRunning($pid) {
        if (!$pid) return false;
        
        // On Unix systems
        if (function_exists('posix_kill')) {
            return posix_kill($pid, 0);
        }
        
        // Fallback method
        $result = shell_exec("ps -p {$pid}");
        return strpos($result, $pid) !== false;
    }
    
    /**
     * Release lock and cleanup
     */
    private function cleanup() {
        $runtime = time() - $this->startTime;
        $this->log("Worker finished. Runtime: {$runtime}s, Processed: {$this->processedCount}, Failed: {$this->failedCount}");
        
        // Remove lock file
        if (file_exists($this->lockFile)) {
            unlink($this->lockFile);
        }
        
        // Update worker statistics
        $this->updateWorkerStats($runtime);
    }
    
    /**
     * Update worker statistics
     */
    private function updateWorkerStats($runtime) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO email_worker_stats 
                (started_at, finished_at, runtime_seconds, emails_processed, emails_failed, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                date('Y-m-d H:i:s', $this->startTime),
                date('Y-m-d H:i:s'),
                $runtime,
                $this->processedCount,
                $this->failedCount
            ]);
        } catch (Exception $e) {
            $this->log('Failed to update worker stats: ' . $e->getMessage());
        }
    }
    
    /**
     * Log message with timestamp
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}" . PHP_EOL;
        
        // Write to log file
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
        
        // Also output to console if running in CLI
        if (php_sapi_name() === 'cli') {
            echo $logMessage;
        }
    }
    
    /**
     * Get queue statistics
     */
    public function getQueueStats() {
        try {
            $stmt = $this->db->query("
                SELECT 
                    status,
                    COUNT(*) as count,
                    MIN(created_at) as oldest,
                    MAX(created_at) as newest
                FROM email_queue 
                GROUP BY status
            ");
            
            $stats = [];
            while ($row = $stmt->fetch()) {
                $stats[$row['status']] = $row;
            }
            
            return $stats;
        } catch (Exception $e) {
            $this->log('Failed to get queue stats: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Clean up old completed emails from queue
     */
    public function cleanupOldEmails($days = 30) {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM email_queue 
                WHERE status IN ('sent', 'delivered', 'failed') 
                AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->execute([$days]);
            
            $deletedCount = $stmt->rowCount();
            $this->log("Cleaned up {$deletedCount} old email records");
            
            return $deletedCount;
        } catch (Exception $e) {
            $this->log('Cleanup failed: ' . $e->getMessage());
            return 0;
        }
    }
}

// CLI command handling
if ($argc > 1) {
    $command = $argv[1];
    $worker = new EmailQueueWorker();
    
    switch ($command) {
        case 'start':
            $maxBatches = isset($argv[2]) ? (int)$argv[2] : null;
            $worker->start($maxBatches);
            break;
            
        case 'stats':
            $stats = $worker->getQueueStats();
            echo "Email Queue Statistics:\n";
            echo str_repeat('-', 40) . "\n";
            foreach ($stats as $status => $data) {
                echo sprintf("%-15s: %5d emails (oldest: %s)\n", 
                    ucfirst($status), 
                    $data['count'], 
                    $data['oldest']
                );
            }
            break;
            
        case 'cleanup':
            $days = isset($argv[2]) ? (int)$argv[2] : 30;
            $deleted = $worker->cleanupOldEmails($days);
            echo "Cleaned up {$deleted} old email records\n";
            break;
            
        case 'help':
        default:
            echo "Email Queue Worker\n";
            echo "Usage: php email-queue-worker.php <command> [options]\n\n";
            echo "Commands:\n";
            echo "  start [batches]  Start processing email queue (optional: limit to N batches)\n";
            echo "  stats            Show queue statistics\n";
            echo "  cleanup [days]   Clean up old emails (default: 30 days)\n";
            echo "  help             Show this help message\n\n";
            echo "Examples:\n";
            echo "  php email-queue-worker.php start        # Process emails continuously\n";
            echo "  php email-queue-worker.php start 5      # Process 5 batches and exit\n";
            echo "  php email-queue-worker.php stats        # Show queue statistics\n";
            echo "  php email-queue-worker.php cleanup 60   # Clean up emails older than 60 days\n";
            break;
    }
} else {
    echo "No command specified. Use 'help' for usage information.\n";
}
?>