<?php
/**
 * Reports Cleanup Script
 * Run this script via cron to clean up expired reports and maintain the system
 * 
 * Usage: php /path/to/scripts/reports_cleanup.php
 * Cron: 0 2 * * * /usr/bin/php /path/to/scripts/reports_cleanup.php >> /var/log/reports_cleanup.log 2>&1
 */

// Prevent web access
if (isset($_SERVER['HTTP_HOST'])) {
    die('This script can only be run from command line.');
}

require_once dirname(__DIR__) . '/includes/Database.php';

class ReportsCleanup {
    private $db;
    private $reportsDir;
    private $maxAge; // Days
    private $dryRun;
    
    public function __construct($dryRun = false) {
        $this->db = Database::getInstance();
        $this->reportsDir = dirname(__DIR__) . '/reports/';
        $this->maxAge = 90; // Keep reports for 90 days by default
        $this->dryRun = $dryRun;
        
        $this->log("Reports cleanup started" . ($dryRun ? " (DRY RUN)" : ""));
    }
    
    /**
     * Run all cleanup tasks
     */
    public function run() {
        try {
            $this->cleanupExpiredReports();
            $this->cleanupOrphanedFiles();
            $this->cleanupOldShareableLinks();
            $this->cleanupOldEmailLogs();
            $this->updateReportStatistics();
            
            $this->log("Cleanup completed successfully");
            
        } catch (Exception $e) {
            $this->log("ERROR: " . $e->getMessage(), true);
            exit(1);
        }
    }
    
    /**
     * Clean up expired and old reports
     */
    private function cleanupExpiredReports() {
        $this->log("Cleaning up expired reports...");
        
        // Get expired reports
        $stmt = $this->db->prepare("
            SELECT id, file_path, report_name, created_at, expires_at
            FROM generated_reports 
            WHERE (expires_at IS NOT NULL AND expires_at < NOW()) 
               OR (status = 'failed' AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY))
               OR (created_at < DATE_SUB(NOW(), INTERVAL ? DAY))
        ");
        $stmt->execute([$this->maxAge]);
        $expiredReports = $stmt->fetchAll();
        
        $deletedFiles = 0;
        $deletedRecords = 0;
        
        foreach ($expiredReports as $report) {
            // Delete physical file
            if ($report['file_path'] && file_exists($report['file_path'])) {
                if (!$this->dryRun) {
                    if (unlink($report['file_path'])) {
                        $deletedFiles++;
                        $this->log("Deleted file: " . $report['file_path']);
                    } else {
                        $this->log("WARNING: Could not delete file: " . $report['file_path']);
                    }
                } else {
                    $this->log("DRY RUN: Would delete file: " . $report['file_path']);
                    $deletedFiles++;
                }
            }
            
            // Delete database record
            if (!$this->dryRun) {
                $deleteStmt = $this->db->prepare("DELETE FROM generated_reports WHERE id = ?");
                if ($deleteStmt->execute([$report['id']])) {
                    $deletedRecords++;
                    $this->log("Deleted report record: {$report['report_name']} (ID: {$report['id']})");
                }
            } else {
                $this->log("DRY RUN: Would delete report: {$report['report_name']} (ID: {$report['id']})");
                $deletedRecords++;
            }
        }
        
        $this->log("Cleaned up {$deletedRecords} report records and {$deletedFiles} files");
    }
    
    /**
     * Clean up orphaned files (files without database records)
     */
    private function cleanupOrphanedFiles() {
        $this->log("Cleaning up orphaned files...");
        
        if (!is_dir($this->reportsDir)) {
            $this->log("Reports directory does not exist: " . $this->reportsDir);
            return;
        }
        
        // Get all report files from database
        $stmt = $this->db->prepare("SELECT DISTINCT file_path FROM generated_reports WHERE file_path IS NOT NULL");
        $stmt->execute();
        $dbFiles = array_column($stmt->fetchAll(), 'file_path');
        
        // Get all files in reports directory
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->reportsDir, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        $orphanedFiles = 0;
        foreach ($iterator as $file) {
            $filePath = $file->getRealPath();
            
            // Skip directories and system files
            if (!$file->isFile() || basename($filePath)[0] === '.') {
                continue;
            }
            
            // Check if file exists in database
            if (!in_array($filePath, $dbFiles)) {
                // Check if file is older than 1 day (to avoid deleting files currently being generated)
                if (time() - $file->getMTime() > 86400) {
                    if (!$this->dryRun) {
                        if (unlink($filePath)) {
                            $orphanedFiles++;
                            $this->log("Deleted orphaned file: " . $filePath);
                        } else {
                            $this->log("WARNING: Could not delete orphaned file: " . $filePath);
                        }
                    } else {
                        $this->log("DRY RUN: Would delete orphaned file: " . $filePath);
                        $orphanedFiles++;
                    }
                }
            }
        }
        
        $this->log("Cleaned up {$orphanedFiles} orphaned files");
    }
    
    /**
     * Clean up old shareable links
     */
    private function cleanupOldShareableLinks() {
        $this->log("Cleaning up old shareable links...");
        
        if (!$this->dryRun) {
            $stmt = $this->db->prepare("
                DELETE FROM shareable_reports 
                WHERE expires_at < NOW() 
                   OR created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
                   OR is_active = 0
            ");
            $stmt->execute();
            $deleted = $stmt->rowCount();
        } else {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count FROM shareable_reports 
                WHERE expires_at < NOW() 
                   OR created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
                   OR is_active = 0
            ");
            $stmt->execute();
            $deleted = $stmt->fetch()['count'];
        }
        
        $this->log("Cleaned up {$deleted} old shareable links");
    }
    
    /**
     * Clean up old email logs
     */
    private function cleanupOldEmailLogs() {
        $this->log("Cleaning up old email logs...");
        
        if (!$this->dryRun) {
            $stmt = $this->db->prepare("
                DELETE FROM report_email_logs 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
            ");
            $stmt->execute();
            $deleted = $stmt->rowCount();
        } else {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count FROM report_email_logs 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
            ");
            $stmt->execute();
            $deleted = $stmt->fetch()['count'];
        }
        
        $this->log("Cleaned up {$deleted} old email log entries");
    }
    
    /**
     * Update report statistics
     */
    private function updateReportStatistics() {
        $this->log("Updating report statistics...");
        
        // Get current stats
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_reports,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_reports,
                COUNT(CASE WHEN status = 'generating' THEN 1 END) as generating_reports,
                COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_reports,
                SUM(download_count) as total_downloads,
                SUM(file_size) as total_file_size,
                AVG(generation_time) as avg_generation_time
            FROM generated_reports
        ");
        $stmt->execute();
        $stats = $stmt->fetch();
        
        $this->log("Current statistics:");
        $this->log("  Total reports: " . number_format($stats['total_reports']));
        $this->log("  Completed: " . number_format($stats['completed_reports']));
        $this->log("  Generating: " . number_format($stats['generating_reports']));
        $this->log("  Failed: " . number_format($stats['failed_reports']));
        $this->log("  Total downloads: " . number_format($stats['total_downloads']));
        $this->log("  Total file size: " . $this->formatBytes($stats['total_file_size']));
        $this->log("  Avg generation time: " . round($stats['avg_generation_time'], 2) . "s");
        
        // Check for stuck reports (generating for more than 1 hour)
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as stuck_count FROM generated_reports 
            WHERE status = 'generating' AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute();
        $stuckCount = $stmt->fetch()['stuck_count'];
        
        if ($stuckCount > 0) {
            $this->log("WARNING: Found {$stuckCount} reports stuck in 'generating' status");
            
            if (!$this->dryRun) {
                // Mark stuck reports as failed
                $stmt = $this->db->prepare("
                    UPDATE generated_reports 
                    SET status = 'failed', error_message = 'Report generation timed out' 
                    WHERE status = 'generating' AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)
                ");
                $stmt->execute();
                $this->log("Marked {$stuckCount} stuck reports as failed");
            } else {
                $this->log("DRY RUN: Would mark {$stuckCount} stuck reports as failed");
            }
        }
    }
    
    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes) {
        if ($bytes === null) return 'N/A';
        
        $units = ['B', 'KB', 'MB', 'GB'];
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    /**
     * Log message with timestamp
     */
    private function log($message, $isError = false) {
        $timestamp = date('Y-m-d H:i:s');
        $prefix = $isError ? 'ERROR' : 'INFO';
        $logMessage = "[{$timestamp}] [{$prefix}] {$message}";
        
        echo $logMessage . PHP_EOL;
        
        // Also write to error log if it's an error
        if ($isError) {
            error_log($logMessage);
        }
    }
}

// Parse command line arguments
$dryRun = in_array('--dry-run', $argv) || in_array('-n', $argv);
$help = in_array('--help', $argv) || in_array('-h', $argv);

if ($help) {
    echo "Reports Cleanup Script\n";
    echo "Usage: php reports_cleanup.php [options]\n";
    echo "\nOptions:\n";
    echo "  -n, --dry-run    Show what would be deleted without actually deleting\n";
    echo "  -h, --help       Show this help message\n";
    echo "\nThis script cleans up:\n";
    echo "  - Expired report files and records\n";
    echo "  - Orphaned files without database records\n";
    echo "  - Old shareable links (30+ days)\n";
    echo "  - Old email logs (90+ days)\n";
    echo "  - Stuck report generation processes\n";
    exit(0);
}

// Run cleanup
$cleanup = new ReportsCleanup($dryRun);
$cleanup->run();

exit(0);
?>