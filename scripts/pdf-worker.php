<?php
/**
 * PDF Generation Background Worker
 * Processes queued PDF generation jobs
 * 
 * Usage: php pdf-worker.php [--daemon] [--max-jobs=10] [--sleep=30]
 */

// Set memory limit for PDF generation
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300); // 5 minutes per job

require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/ReportGenerator.php';
require_once __DIR__ . '/../includes/PDFGenerator.php';
require_once __DIR__ . '/../includes/BrandingHelper.php';

class PDFWorker {
    private $db;
    private $daemon = false;
    private $maxJobs = 10;
    private $sleepInterval = 30;
    private $running = true;
    private $currentJobId = null;
    
    public function __construct($options = []) {
        $this->db = Database::getInstance();
        $this->daemon = $options['daemon'] ?? false;
        $this->maxJobs = (int)($options['max_jobs'] ?? 10);
        $this->sleepInterval = (int)($options['sleep'] ?? 30);
        
        // Set signal handlers for graceful shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGTERM, [$this, 'handleShutdown']);
            pcntl_signal(SIGINT, [$this, 'handleShutdown']);
        }
        
        $this->log("PDF Worker started (daemon: " . ($this->daemon ? 'yes' : 'no') . ", max_jobs: {$this->maxJobs})");
    }
    
    /**
     * Start processing jobs
     */
    public function run() {
        $jobsProcessed = 0;
        
        do {
            try {
                // Check for signals (if available)
                if (function_exists('pcntl_signal_dispatch')) {
                    pcntl_signal_dispatch();
                }
                
                if (!$this->running) {
                    $this->log("Shutdown signal received, stopping worker");
                    break;
                }
                
                // Get next job from queue
                $job = $this->getNextJob();
                
                if ($job) {
                    $this->currentJobId = $job['id'];
                    $this->processJob($job);
                    $jobsProcessed++;
                    
                    // Check if we've reached max jobs limit
                    if (!$this->daemon && $jobsProcessed >= $this->maxJobs) {
                        $this->log("Reached maximum jobs limit ({$this->maxJobs}), stopping");
                        break;
                    }
                } else {
                    // No jobs available
                    if (!$this->daemon) {
                        $this->log("No jobs in queue, exiting");
                        break;
                    }
                    
                    // Sleep before checking again
                    $this->log("No jobs in queue, sleeping for {$this->sleepInterval} seconds");
                    sleep($this->sleepInterval);
                }
                
            } catch (Exception $e) {
                $this->log("Worker error: " . $e->getMessage(), 'ERROR');
                
                // Mark current job as failed if one is being processed
                if ($this->currentJobId) {
                    $this->markJobFailed($this->currentJobId, $e->getMessage());
                    $this->currentJobId = null;
                }
                
                // Sleep before continuing
                sleep(5);
            }
            
        } while ($this->daemon && $this->running);
        
        $this->log("PDF Worker stopped (processed {$jobsProcessed} jobs)");
    }
    
    /**
     * Get next job from queue
     */
    private function getNextJob() {
        $stmt = $this->db->prepare("
            SELECT pq.*, gr.file_path, gr.report_name, gr.client_id
            FROM pdf_generation_queue pq
            JOIN generated_reports gr ON pq.report_id = gr.id
            WHERE pq.status = 'pending'
            ORDER BY 
                CASE pq.priority 
                    WHEN 'high' THEN 1 
                    WHEN 'normal' THEN 2 
                    WHEN 'low' THEN 3 
                END,
                pq.created_at ASC
            LIMIT 1
            FOR UPDATE
        ");
        
        $stmt->execute();
        $job = $stmt->fetch();
        
        if ($job) {
            // Mark job as processing
            $stmt = $this->db->prepare("
                UPDATE pdf_generation_queue 
                SET status = 'processing', started_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$job['id']]);
            
            $this->log("Picked up job {$job['id']} (Report: {$job['report_name']}, Priority: {$job['priority']})");
        }
        
        return $job;
    }
    
    /**
     * Process a PDF generation job
     */
    private function processJob($job) {
        $startTime = microtime(true);
        
        try {
            $this->log("Processing job {$job['id']} - Report ID: {$job['report_id']}");
            
            // Validate report file exists
            if (!$job['file_path'] || !file_exists($job['file_path'])) {
                throw new Exception('Report file not found: ' . $job['file_path']);
            }
            
            // Get branding information
            $brandingHelper = BrandingHelper::getInstance();
            $branding = $brandingHelper->getBranding($job['client_id']);
            
            // Parse PDF options
            $pdfOptions = json_decode($job['pdf_options'], true) ?: [];
            $defaultOptions = [
                'orientation' => 'portrait',
                'page_size' => 'A4',
                'margin_top' => 20,
                'margin_bottom' => 20,
                'margin_left' => 15,
                'margin_right' => 15,
                'default_font' => 'helvetica',
                'default_font_size' => 11
            ];
            
            $pdfOptions = array_merge($defaultOptions, $pdfOptions);
            
            // Initialize PDF generator
            $pdfGenerator = new PDFGenerator('auto', $pdfOptions);
            
            // Create PDF output directory
            $pdfDir = $_SERVER['DOCUMENT_ROOT'] . '/reports/pdf/';
            if (!is_dir($pdfDir)) {
                mkdir($pdfDir, 0755, true);
            }
            
            // Generate PDF filename
            $pdfFileName = 'report_' . $job['report_id'] . '_' . date('Y-m-d_H-i-s') . '.pdf';
            $pdfPath = $pdfDir . $pdfFileName;
            
            // Read HTML content
            $htmlContent = file_get_contents($job['file_path']);
            if ($htmlContent === false) {
                throw new Exception('Failed to read HTML report file');
            }
            
            $this->log("Generating PDF for job {$job['id']}...");
            
            // Generate PDF
            $success = $pdfGenerator->generatePDF($htmlContent, $pdfPath, $branding);
            
            if (!$success || !file_exists($pdfPath)) {
                throw new Exception('PDF generation failed - no output file created');
            }
            
            $fileSize = filesize($pdfPath);
            if ($fileSize === 0) {
                throw new Exception('PDF generation failed - empty file created');
            }
            
            // Cache the PDF
            $cachedPath = $pdfGenerator->cachePDF($job['report_id'], $pdfPath);
            $finalPath = $cachedPath ?: $pdfPath;
            
            $processingTime = microtime(true) - $startTime;
            
            // Update job as completed
            $stmt = $this->db->prepare("
                UPDATE pdf_generation_queue 
                SET status = 'completed', completed_at = NOW(), 
                    pdf_path = ?, processing_time = ?, file_size = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $finalPath,
                round($processingTime, 2),
                $fileSize,
                $job['id']
            ]);
            
            // Update report record
            $stmt = $this->db->prepare("
                UPDATE generated_reports 
                SET pdf_generated = 1, pdf_generated_at = NOW(), pdf_file_size = ?
                WHERE id = ?
            ");
            $stmt->execute([$fileSize, $job['report_id']]);
            
            $this->log("Job {$job['id']} completed successfully (Processing time: " . round($processingTime, 2) . "s, File size: " . $this->formatFileSize($fileSize) . ")");
            
        } catch (Exception $e) {
            $this->log("Job {$job['id']} failed: " . $e->getMessage(), 'ERROR');
            $this->markJobFailed($job['id'], $e->getMessage());
        }
        
        $this->currentJobId = null;
    }
    
    /**
     * Mark job as failed
     */
    private function markJobFailed($jobId, $errorMessage) {
        $stmt = $this->db->prepare("
            UPDATE pdf_generation_queue 
            SET status = 'failed', completed_at = NOW(), error_message = ?
            WHERE id = ?
        ");
        $stmt->execute([$errorMessage, $jobId]);
    }
    
    /**
     * Handle shutdown signals
     */
    public function handleShutdown($signal) {
        $this->log("Received signal {$signal}, initiating graceful shutdown");
        $this->running = false;
        
        // If currently processing a job, mark it as pending again
        if ($this->currentJobId) {
            $stmt = $this->db->prepare("
                UPDATE pdf_generation_queue 
                SET status = 'pending', started_at = NULL
                WHERE id = ? AND status = 'processing'
            ");
            $stmt->execute([$this->currentJobId]);
            $this->log("Reset job {$this->currentJobId} to pending status");
        }
    }
    
    /**
     * Log message with timestamp
     */
    private function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}" . PHP_EOL;
        
        // Output to console
        echo $logMessage;
        
        // Also log to file
        $logFile = __DIR__ . '/../logs/pdf-worker.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Format file size
     */
    private function formatFileSize($bytes) {
        $units = array('B', 'KB', 'MB', 'GB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    /**
     * Clean up old completed/failed jobs
     */
    public function cleanup($olderThanDays = 7) {
        $stmt = $this->db->prepare("
            DELETE FROM pdf_generation_queue 
            WHERE status IN ('completed', 'failed') 
            AND completed_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$olderThanDays]);
        
        $deletedCount = $stmt->rowCount();
        if ($deletedCount > 0) {
            $this->log("Cleaned up {$deletedCount} old jobs");
        }
        
        return $deletedCount;
    }
}

// Parse command line arguments
$options = [];
$arguments = array_slice($argv, 1);

foreach ($arguments as $arg) {
    if ($arg === '--daemon') {
        $options['daemon'] = true;
    } elseif (strpos($arg, '--max-jobs=') === 0) {
        $options['max_jobs'] = (int)substr($arg, 11);
    } elseif (strpos($arg, '--sleep=') === 0) {
        $options['sleep'] = (int)substr($arg, 8);
    } elseif ($arg === '--cleanup') {
        // Run cleanup and exit
        $worker = new PDFWorker();
        $deleted = $worker->cleanup();
        echo "Cleaned up {$deleted} old jobs\n";
        exit(0);
    } elseif ($arg === '--help' || $arg === '-h') {
        echo "PDF Worker - Background PDF Generation\n\n";
        echo "Usage: php pdf-worker.php [options]\n\n";
        echo "Options:\n";
        echo "  --daemon          Run as daemon (continuous processing)\n";
        echo "  --max-jobs=N      Maximum jobs to process before exiting (default: 10)\n";
        echo "  --sleep=N         Sleep interval in seconds between queue checks (default: 30)\n";
        echo "  --cleanup         Clean up old completed/failed jobs and exit\n";
        echo "  --help, -h        Show this help message\n\n";
        echo "Examples:\n";
        echo "  php pdf-worker.php                    Process up to 10 jobs and exit\n";
        echo "  php pdf-worker.php --daemon           Run continuously as daemon\n";
        echo "  php pdf-worker.php --max-jobs=5       Process up to 5 jobs and exit\n";
        echo "  php pdf-worker.php --cleanup          Clean up old jobs\n";
        exit(0);
    }
}

// Create and run worker
try {
    $worker = new PDFWorker($options);
    $worker->run();
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}
?>