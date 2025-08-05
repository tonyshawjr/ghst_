/**
 * PDF Export Functionality
 * Handles PDF generation, download, and status checking for reports
 */

class PDFExporter {
    constructor(options = {}) {
        this.options = {
            baseUrl: '/api/reports/',
            pollingInterval: 2000, // 2 seconds
            maxPollingTime: 300000, // 5 minutes
            showProgress: true,
            showNotifications: true,
            ...options
        };
        
        this.activeJobs = new Map();
        this.init();
    }
    
    /**
     * Initialize PDF export functionality
     */
    init() {
        this.bindEvents();
        this.setupProgressModal();
        this.checkForActiveJobs();
    }
    
    /**
     * Bind click events to PDF export buttons
     */
    bindEvents() {
        // PDF export buttons
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-pdf-export]')) {
                e.preventDefault();
                const reportId = e.target.dataset.reportId || e.target.dataset.pdfExport;
                const options = this.parseButtonOptions(e.target);
                this.exportToPDF(reportId, options);
            }
            
            // PDF download buttons
            if (e.target.matches('[data-pdf-download]')) {
                e.preventDefault();
                const reportId = e.target.dataset.reportId || e.target.dataset.pdfDownload;
                this.downloadPDF(reportId);
            }
            
            // Background PDF export buttons
            if (e.target.matches('[data-pdf-background]')) {
                e.preventDefault();
                const reportId = e.target.dataset.reportId || e.target.dataset.pdfBackground;
                const priority = e.target.dataset.priority || 'normal';
                this.queuePDFGeneration(reportId, { priority });
            }
        });
        
        // Form submissions for PDF export
        document.addEventListener('submit', (e) => {
            if (e.target.matches('[data-pdf-form]')) {
                e.preventDefault();
                this.handleFormExport(e.target);
            }
        });
    }
    
    /**
     * Parse options from button data attributes
     */
    parseButtonOptions(button) {
        const options = {};
        
        // Parse PDF options
        if (button.dataset.pdfOptions) {
            try {
                Object.assign(options, JSON.parse(button.dataset.pdfOptions));
            } catch (e) {
                console.warn('Invalid PDF options JSON:', button.dataset.pdfOptions);
            }
        }
        
        // Individual option attributes
        if (button.dataset.orientation) options.orientation = button.dataset.orientation;
        if (button.dataset.pageSize) options.page_size = button.dataset.pageSize;
        if (button.dataset.priority) options.priority = button.dataset.priority;
        if (button.dataset.download) options.download = button.dataset.download === 'true';
        
        return options;
    }
    
    /**
     * Export report to PDF (immediate generation)
     */
    async exportToPDF(reportId, options = {}) {
        if (!reportId) {
            this.showError('Report ID is required');
            return;
        }
        
        const button = document.querySelector(`[data-pdf-export="${reportId}"], [data-report-id="${reportId}"]`);
        const originalText = button ? button.textContent : '';
        
        try {
            // Disable button and show loading state
            if (button) {
                button.disabled = true;
                button.textContent = 'Generating PDF...';
                button.classList.add('loading');
            }
            
            // Show progress modal for immediate generation
            if (this.options.showProgress) {
                this.showProgressModal(reportId, 'Generating PDF...', 0);
            }
            
            // Make API request
            const url = `${this.options.baseUrl}export-pdf.php`;
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    report_id: reportId,
                    download: options.download !== false,
                    pdf_options: options
                })
            });
            
            const result = await response.json();
            
            if (!response.ok) {
                throw new Error(result.error || `HTTP ${response.status}`);
            }
            
            if (result.success) {
                if (result.pdf_generated && result.download_url) {
                    // PDF is ready, download it
                    this.hideProgressModal();
                    
                    if (options.download !== false) {
                        window.location.href = result.download_url;
                    }
                    
                    if (this.options.showNotifications) {
                        this.showSuccess('PDF generated successfully!');
                    }
                    
                    // Update UI
                    this.updatePDFStatus(reportId, 'ready', result);
                } else {
                    throw new Error('PDF generation failed');
                }
            } else {
                throw new Error(result.error || 'PDF generation failed');
            }
            
        } catch (error) {
            console.error('PDF export error:', error);
            this.hideProgressModal();
            this.showError('Failed to generate PDF: ' + error.message);
        } finally {
            // Restore button state
            if (button) {
                button.disabled = false;
                button.textContent = originalText;
                button.classList.remove('loading');
            }
        }
    }
    
    /**
     * Queue PDF generation for background processing
     */
    async queuePDFGeneration(reportId, options = {}) {
        try {
            const url = `${this.options.baseUrl}background-pdf.php`;
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    report_id: reportId,
                    priority: options.priority || 'normal',
                    pdf_options: options
                })
            });
            
            const result = await response.json();
            
            if (!response.ok) {
                throw new Error(result.error || `HTTP ${response.status}`);
            }
            
            if (result.success) {
                if (result.status === 'completed') {
                    // High priority job completed immediately
                    this.showSuccess('PDF generated successfully!');
                    this.updatePDFStatus(reportId, 'ready', result);
                } else {
                    // Job queued for background processing
                    this.showInfo(`PDF generation queued (${result.estimated_completion})`);
                    this.startPolling(result.job_id, reportId);
                    this.updatePDFStatus(reportId, 'generating', result);
                }
            } else {
                throw new Error(result.error || 'Failed to queue PDF generation');
            }
            
        } catch (error) {
            console.error('PDF queue error:', error);
            this.showError('Failed to queue PDF generation: ' + error.message);
        }
    }
    
    /**
     * Download PDF directly
     */
    downloadPDF(reportId) {
        const downloadUrl = `${this.options.baseUrl}export-pdf.php?report_id=${reportId}&download=1`;
        window.location.href = downloadUrl;
    }
    
    /**
     * Start polling for job status
     */
    startPolling(jobId, reportId) {
        if (this.activeJobs.has(jobId)) {
            return; // Already polling this job
        }
        
        const pollData = {
            jobId,
            reportId,
            startTime: Date.now(),
            attempts: 0
        };
        
        this.activeJobs.set(jobId, pollData);
        this.pollJobStatus(jobId);
    }
    
    /**
     * Poll job status
     */
    async pollJobStatus(jobId) {
        const pollData = this.activeJobs.get(jobId);
        if (!pollData) return;
        
        const elapsed = Date.now() - pollData.startTime;
        if (elapsed > this.options.maxPollingTime) {
            this.activeJobs.delete(jobId);
            this.hideProgressModal();
            this.showError('PDF generation timed out');
            return;
        }
        
        try {
            const url = `${this.options.baseUrl}pdf-status.php?job_id=${jobId}`;
            const response = await fetch(url);
            const result = await response.json();
            
            if (!response.ok) {
                throw new Error(result.error || `HTTP ${response.status}`);
            }
            
            if (result.success) {
                this.updateJobProgress(jobId, result);
                
                if (result.status === 'completed') {
                    // Job completed successfully
                    this.activeJobs.delete(jobId);
                    this.hideProgressModal();
                    this.showSuccess('PDF ready for download!');
                    this.updatePDFStatus(pollData.reportId, 'ready', result);
                    
                    // Auto-download if requested
                    if (result.download_url) {
                        setTimeout(() => {
                            window.location.href = result.download_url;
                        }, 1000);
                    }
                    
                } else if (result.status === 'failed') {
                    // Job failed
                    this.activeJobs.delete(jobId);
                    this.hideProgressModal();
                    this.showError('PDF generation failed: ' + (result.error_message || 'Unknown error'));
                    this.updatePDFStatus(pollData.reportId, 'failed', result);
                    
                } else {
                    // Still processing, continue polling
                    pollData.attempts++;
                    setTimeout(() => this.pollJobStatus(jobId), this.options.pollingInterval);
                }
            } else {
                throw new Error(result.error || 'Failed to check job status');
            }
            
        } catch (error) {
            console.error('Polling error:', error);
            pollData.attempts++;
            
            if (pollData.attempts < 5) {
                // Retry with exponential backoff
                const delay = this.options.pollingInterval * Math.pow(2, pollData.attempts);
                setTimeout(() => this.pollJobStatus(jobId), delay);
            } else {
                // Too many failures, give up
                this.activeJobs.delete(jobId);
                this.hideProgressModal();
                this.showError('Failed to check PDF generation status');
            }
        }
    }
    
    /**
     * Update job progress in UI
     */
    updateJobProgress(jobId, status) {
        if (this.options.showProgress) {
            const progress = status.progress || 0;
            const message = this.getProgressMessage(status);
            this.updateProgressModal(message, progress);
        }
    }
    
    /**
     * Get progress message based on status
     */
    getProgressMessage(status) {
        switch (status.status) {
            case 'pending':
                return `Queued (Position: ${status.queue_position || 'unknown'})`;
            case 'processing':
                return `Generating PDF... (${status.elapsed_formatted || ''})`;
            case 'completed':
                return 'PDF ready!';
            case 'failed':
                return 'Generation failed';
            default:
                return 'Processing...';
        }
    }
    
    /**
     * Update PDF status in UI
     */
    updatePDFStatus(reportId, status, data = {}) {
        const elements = document.querySelectorAll(`[data-report-id="${reportId}"]`);
        
        elements.forEach(element => {
            // Update button states
            if (element.matches('button')) {
                switch (status) {
                    case 'ready':
                        element.classList.remove('btn-primary', 'btn-warning');
                        element.classList.add('btn-success');
                        element.textContent = 'Download PDF';
                        element.dataset.pdfDownload = reportId;
                        break;
                        
                    case 'generating':
                        element.classList.remove('btn-primary', 'btn-success');
                        element.classList.add('btn-warning');
                        element.textContent = 'Generating...';
                        element.disabled = true;
                        break;
                        
                    case 'failed':
                        element.classList.remove('btn-warning', 'btn-success');
                        element.classList.add('btn-primary');
                        element.textContent = 'Retry PDF';
                        element.disabled = false;
                        break;
                }
            }
            
            // Update status indicators
            const statusIndicator = element.querySelector('.pdf-status');
            if (statusIndicator) {
                statusIndicator.textContent = status.charAt(0).toUpperCase() + status.slice(1);
                statusIndicator.className = `pdf-status status-${status}`;
            }
            
            // Update file size display
            if (data.file_size_formatted) {
                const sizeElement = element.querySelector('.pdf-file-size');
                if (sizeElement) {
                    sizeElement.textContent = data.file_size_formatted;
                }
            }
        });
    }
    
    /**
     * Setup progress modal
     */
    setupProgressModal() {
        if (!this.options.showProgress) return;
        
        // Create modal HTML if it doesn't exist
        if (!document.getElementById('pdfProgressModal')) {
            const modalHTML = `
                <div id="pdfProgressModal" class="modal fade" tabindex="-1" role="dialog">
                    <div class="modal-dialog modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">PDF Generation</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body text-center">
                                <div class="spinner-border mb-3" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p id="pdfProgressMessage">Generating PDF...</p>
                                <div class="progress mb-3">
                                    <div id="pdfProgressBar" class="progress-bar" role="progressbar" 
                                         style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">
                                    </div>
                                </div>
                                <small id="pdfProgressDetails" class="text-muted"></small>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHTML);
        }
    }
    
    /**
     * Show progress modal
     */
    showProgressModal(reportId, message = 'Generating PDF...', progress = 0) {
        if (!this.options.showProgress) return;
        
        const modal = document.getElementById('pdfProgressModal');
        const messageEl = document.getElementById('pdfProgressMessage');
        const progressBar = document.getElementById('pdfProgressBar');
        const detailsEl = document.getElementById('pdfProgressDetails');
        
        if (modal && messageEl && progressBar) {
            messageEl.textContent = message;
            progressBar.style.width = progress + '%';
            progressBar.setAttribute('aria-valuenow', progress);
            
            if (detailsEl) {
                detailsEl.textContent = `Report ID: ${reportId}`;
            }
            
            // Show modal (Bootstrap 5)
            if (window.bootstrap) {
                const bsModal = new bootstrap.Modal(modal);
                bsModal.show();
            } else {
                modal.style.display = 'block';
                modal.classList.add('show');
            }
        }
    }
    
    /**
     * Update progress modal
     */
    updateProgressModal(message, progress) {
        const messageEl = document.getElementById('pdfProgressMessage');
        const progressBar = document.getElementById('pdfProgressBar');
        
        if (messageEl) messageEl.textContent = message;
        if (progressBar) {
            progressBar.style.width = progress + '%';
            progressBar.setAttribute('aria-valuenow', progress);
        }
    }
    
    /**
     * Hide progress modal
     */
    hideProgressModal() {
        const modal = document.getElementById('pdfProgressModal');
        if (modal) {
            // Hide modal (Bootstrap 5)
            if (window.bootstrap) {
                const bsModal = bootstrap.Modal.getInstance(modal);
                if (bsModal) bsModal.hide();
            } else {
                modal.style.display = 'none';
                modal.classList.remove('show');
            }
        }
    }
    
    /**
     * Handle form-based PDF export
     */
    async handleFormExport(form) {
        const formData = new FormData(form);
        const reportId = formData.get('report_id');
        const options = {};
        
        // Parse form options
        for (const [key, value] of formData.entries()) {
            if (key.startsWith('pdf_')) {
                options[key.substring(4)] = value;
            }
        }
        
        await this.exportToPDF(reportId, options);
    }
    
    /**
     * Check for active PDF generation jobs on page load
     */
    checkForActiveJobs() {
        // Look for elements indicating active PDF jobs
        const activeElements = document.querySelectorAll('[data-pdf-job-id]');
        
        activeElements.forEach(element => {
            const jobId = element.dataset.pdfJobId;
            const reportId = element.dataset.reportId;
            
            if (jobId && reportId) {
                this.startPolling(jobId, reportId);
            }
        });
    }
    
    /**
     * Show success notification
     */
    showSuccess(message) {
        if (!this.options.showNotifications) return;
        this.showNotification(message, 'success');
    }
    
    /**
     * Show error notification
     */
    showError(message) {
        if (!this.options.showNotifications) return;
        this.showNotification(message, 'error');
    }
    
    /**
     * Show info notification
     */
    showInfo(message) {
        if (!this.options.showNotifications) return;
        this.showNotification(message, 'info');
    }
    
    /**
     * Show notification (customizable)
     */
    showNotification(message, type = 'info') {
        // Try to use existing notification system
        if (window.showToast) {
            window.showToast(message, type);
        } else if (window.toastr) {
            window.toastr[type](message);
        } else if (window.Swal) {
            window.Swal.fire({
                text: message,
                icon: type === 'error' ? 'error' : type === 'success' ? 'success' : 'info',
                timer: 3000,
                showConfirmButton: false
            });
        } else {
            // Fallback to alert
            alert(message);
        }
    }
    
    /**
     * Get PDF generation statistics
     */
    async getStats(days = 7) {
        try {
            const url = `${this.options.baseUrl}pdf-stats.php?days=${days}`;
            const response = await fetch(url);
            const result = await response.json();
            
            if (response.ok && result.success) {
                return result.stats;
            } else {
                throw new Error(result.error || 'Failed to fetch stats');
            }
        } catch (error) {
            console.error('Stats fetch error:', error);
            return null;
        }
    }
    
    /**
     * Cancel active PDF job
     */
    async cancelJob(jobId) {
        try {
            const url = `${this.options.baseUrl}cancel-pdf.php`;
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ job_id: jobId })
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                this.activeJobs.delete(jobId);
                this.hideProgressModal();
                this.showInfo('PDF generation cancelled');
                return true;
            } else {
                throw new Error(result.error || 'Failed to cancel job');
            }
        } catch (error) {
            console.error('Cancel job error:', error);
            this.showError('Failed to cancel PDF generation');
            return false;
        }
    }
}

// Initialize PDF exporter when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.pdfExporter = new PDFExporter({
        showProgress: true,
        showNotifications: true,
        pollingInterval: 3000, // 3 seconds
        maxPollingTime: 600000 // 10 minutes
    });
});

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = PDFExporter;
}