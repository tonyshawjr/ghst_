/**
 * Report Sharing JavaScript Module
 * 
 * Handles shareable report link creation, management, and analytics
 * Integrates with the existing reports dashboard
 */

class ReportSharingManager {
    constructor() {
        this.apiBase = '/api/reports';
        this.currentReportId = null;
        this.shares = {};
        this.init();
    }
    
    init() {
        this.attachEventListeners();
        this.loadExistingShares();
    }
    
    attachEventListeners() {
        // Share button clicks
        document.addEventListener('click', (e) => {
            if (e.target.matches('.btn-share-report')) {
                const reportId = e.target.dataset.reportId;
                this.openShareModal(reportId);
            }
            
            if (e.target.matches('.btn-view-analytics')) {
                const shareId = e.target.dataset.shareId;
                this.viewShareAnalytics(shareId);
            }
            
            if (e.target.matches('.btn-revoke-share')) {
                const shareId = e.target.dataset.shareId;
                this.revokeShare(shareId);
            }
            
            if (e.target.matches('.btn-copy-link')) {
                const shareUrl = e.target.dataset.shareUrl;
                this.copyToClipboard(shareUrl);
            }
        });
        
        // Form submissions
        document.addEventListener('submit', (e) => {
            if (e.target.matches('#shareForm')) {
                e.preventDefault();
                this.createShare(e.target);
            }
            
            if (e.target.matches('#updateShareForm')) {
                e.preventDefault();
                this.updateShare(e.target);
            }
        });
        
        // Modal events
        $(document).on('shown.bs.modal', '#shareModal', () => {
            this.initializeShareForm();
        });
        
        $(document).on('hidden.bs.modal', '#shareModal', () => {
            this.resetShareForm();
        });
    }
    
    async openShareModal(reportId) {
        this.currentReportId = reportId;
        
        // Load existing shares for this report
        await this.loadReportShares(reportId);
        
        // Show modal
        $('#shareModal').modal('show');
    }
    
    async createShare(form) {
        const formData = new FormData(form);
        const data = {
            report_id: this.currentReportId,
            expires_in: formData.get('expires_in'),
            password: formData.get('password'),
            permissions: [],
            max_downloads: formData.get('max_downloads') || null,
            ip_restrictions: formData.get('ip_restrictions') || null,
            csrf_token: document.querySelector('meta[name="csrf-token"]')?.content
        };
        
        // Handle permissions
        if (formData.get('allow_view')) data.permissions.push('view');
        if (formData.get('allow_download')) data.permissions.push('download');
        if (formData.get('allow_analytics')) data.permissions.push('analytics');
        
        // Handle custom expiry
        if (data.expires_in === 'custom') {
            const customExpiry = formData.get('custom_expiry');
            if (customExpiry) {
                data.custom_expiry = new Date(customExpiry).getTime() / 1000;
            }
        }
        
        try {
            this.showLoading('Creating share link...');
            
            const response = await fetch(`${this.apiBase}/share.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showSuccess('Share link created successfully!');
                this.displayNewShare(result.data);
                await this.loadReportShares(this.currentReportId);
            } else {
                this.showError(result.error || 'Failed to create share link');
            }
        } catch (error) {
            console.error('Error creating share:', error);
            this.showError('Network error occurred');
        } finally {
            this.hideLoading();
        }
    }
    
    async revokeShare(shareId) {
        if (!confirm('Are you sure you want to revoke this share link? This action cannot be undone.')) {
            return;
        }
        
        try {
            const response = await fetch(`${this.apiBase}/revoke-share.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    share_id: shareId,
                    csrf_token: document.querySelector('meta[name="csrf-token"]')?.content
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showSuccess('Share link revoked successfully');
                await this.loadReportShares(this.currentReportId);
            } else {
                this.showError(result.error || 'Failed to revoke share link');
            }
        } catch (error) {
            console.error('Error revoking share:', error);
            this.showError('Network error occurred');
        }
    }
    
    async updateShare(form) {
        const formData = new FormData(form);
        const shareId = formData.get('share_id');
        
        const data = {
            share_id: shareId,
            csrf_token: document.querySelector('meta[name="csrf-token"]')?.content
        };
        
        // Only include changed fields
        const expires_in = formData.get('expires_in');
        if (expires_in) {
            data.expires_in = expires_in;
            if (expires_in === 'custom') {
                data.custom_expiry = new Date(formData.get('custom_expiry')).getTime() / 1000;
            }
        }
        
        const password = formData.get('password');
        if (password !== undefined) {
            data.password = password;
        }
        
        const maxDownloads = formData.get('max_downloads');
        if (maxDownloads) {
            data.max_downloads = parseInt(maxDownloads);
        }
        
        try {
            const response = await fetch(`${this.apiBase}/share-settings.php`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showSuccess('Share settings updated successfully');
                await this.loadReportShares(this.currentReportId);
            } else {
                this.showError(result.error || 'Failed to update share settings');
            }
        } catch (error) {
            console.error('Error updating share:', error);
            this.showError('Network error occurred');
        }
    }
    
    async viewShareAnalytics(shareId) {
        try {
            const response = await fetch(`${this.apiBase}/share-analytics.php?share_id=${shareId}`);
            const result = await response.json();
            
            if (result.success) {
                this.displayAnalyticsModal(result.data);
            } else {
                this.showError(result.error || 'Failed to load analytics');
            }
        } catch (error) {
            console.error('Error loading analytics:', error);
            this.showError('Network error occurred');
        }
    }
    
    async loadReportShares(reportId) {
        try {
            const response = await fetch(`${this.apiBase}/share-analytics.php?report_id=${reportId}`);
            const result = await response.json();
            
            if (result.success) {
                this.updateSharesList(result.data.analytics.shares || []);
            }
        } catch (error) {
            console.error('Error loading shares:', error);
        }
    }
    
    updateSharesList(shares) {
        const container = document.getElementById('existingShares');
        if (!container) return;
        
        if (shares.length === 0) {
            container.innerHTML = '<p class="text-muted">No shares created yet.</p>';
            return;
        }
        
        const html = shares.map(share => `
            <div class="share-item card mb-3" data-share-id="${share.share_id}">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h6 class="mb-1">
                                <i class="fas fa-link me-2"></i>
                                Share Link
                                ${share.has_password ? '<i class="fas fa-lock text-warning ms-2" title="Password Protected"></i>' : ''}
                                ${share.is_active ? '<span class="badge bg-success ms-2">Active</span>' : '<span class="badge bg-secondary ms-2">Inactive</span>'}
                            </h6>
                            <small class="text-muted">
                                Created: ${new Date(share.created_at).toLocaleDateString()}
                                ${share.expires_at ? ` | Expires: ${new Date(share.expires_at).toLocaleDateString()}` : ' | Never expires'}
                            </small>
                        </div>
                        <div class="col-md-3 text-center">
                            <div class="share-stats">
                                <span class="badge bg-primary me-2">
                                    <i class="fas fa-eye me-1"></i>${share.total_views} views
                                </span>
                                <span class="badge bg-success">
                                    <i class="fas fa-download me-1"></i>${share.total_downloads} downloads
                                </span>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-primary btn-copy-link" 
                                        data-share-url="${this.getShareUrl(share.share_token)}"
                                        title="Copy Link">
                                    <i class="fas fa-copy"></i>
                                </button>
                                <button type="button" class="btn btn-outline-info btn-view-analytics" 
                                        data-share-id="${share.share_id}"
                                        title="View Analytics">
                                    <i class="fas fa-chart-bar"></i>
                                </button>
                                <button type="button" class="btn btn-outline-warning btn-edit-share" 
                                        data-share-id="${share.share_id}"
                                        title="Edit Settings">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-revoke-share" 
                                        data-share-id="${share.share_id}"
                                        title="Revoke Access">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
        
        container.innerHTML = html;
    }
    
    displayNewShare(shareData) {
        const modal = `
            <div class="modal fade" id="shareCreatedModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-check-circle text-success me-2"></i>
                                Share Link Created
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-success">
                                <i class="fas fa-info-circle me-2"></i>
                                Your share link has been created successfully!
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Share URL:</label>
                                <div class="input-group">
                                    <input type="url" class="form-control" value="${shareData.share_url}" readonly id="newShareUrl">
                                    <button class="btn btn-outline-secondary" type="button" onclick="reportSharing.copyToClipboard('${shareData.share_url}')">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-body text-center">
                                            <h6>QR Code</h6>
                                            <img src="${shareData.qr_code_url}" alt="QR Code" class="img-fluid" style="max-width: 150px;">
                                            <br>
                                            <small class="text-muted">Scan to access</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6>Settings</h6>
                                            <ul class="list-unstyled mb-0">
                                                ${shareData.expires_at ? `<li><i class="fas fa-clock me-2"></i>Expires: ${new Date(shareData.expires_at).toLocaleDateString()}</li>` : '<li><i class="fas fa-infinity me-2"></i>Never expires</li>'}
                                                <li><i class="fas fa-${shareData.settings.has_password ? 'lock' : 'lock-open'} me-2"></i>${shareData.settings.has_password ? 'Password protected' : 'No password'}</li>
                                                <li><i class="fas fa-download me-2"></i>${shareData.settings.permissions.includes('download') ? 'Download allowed' : 'View only'}</li>
                                                ${shareData.settings.max_downloads ? `<li><i class="fas fa-sort-numeric-up me-2"></i>Max downloads: ${shareData.settings.max_downloads}</li>` : ''}
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-primary" onclick="reportSharing.copyToClipboard('${shareData.share_url}')">
                                <i class="fas fa-copy me-2"></i>Copy Link
                            </button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('shareCreatedModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add new modal
        document.body.insertAdjacentHTML('beforeend', modal);
        $('#shareCreatedModal').modal('show');
        
        // Auto-remove modal after closing
        $('#shareCreatedModal').on('hidden.bs.modal', function() {
            this.remove();
        });
    }
    
    displayAnalyticsModal(analyticsData) {
        const analytics = analyticsData.analytics;
        
        const modal = `
            <div class="modal fade" id="analyticsModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-chart-bar me-2"></i>
                                Share Analytics
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row mb-4">
                                <div class="col-md-3">
                                    <div class="card bg-primary text-white">
                                        <div class="card-body text-center">
                                            <h3>${analytics.total_views}</h3>
                                            <small>Total Views</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-success text-white">
                                        <div class="card-body text-center">
                                            <h3>${analytics.total_downloads}</h3>
                                            <small>Downloads</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-info text-white">
                                        <div class="card-body text-center">
                                            <h3>${analytics.geographic_stats.length}</h3>
                                            <small>Locations</small>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="card bg-warning text-white">
                                        <div class="card-body text-center">
                                            <h3>${analytics.access_stats.length}</h3>
                                            <small>Access Types</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            ${analytics.geographic_stats.length > 0 ? `
                                <div class="mb-4">
                                    <h6>Geographic Distribution</h6>
                                    <div class="table-responsive">
                                        <table class="table table-sm">
                                            <thead>
                                                <tr>
                                                    <th>Location</th>
                                                    <th>Accesses</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                ${analytics.geographic_stats.slice(0, 5).map(geo => `
                                                    <tr>
                                                        <td>${geo.city ? `${geo.city}, ` : ''}${geo.country || 'Unknown'}</td>
                                                        <td>${geo.count}</td>
                                                    </tr>
                                                `).join('')}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            ` : ''}
                            
                            ${analytics.timeline.length > 0 ? `
                                <div class="mb-4">
                                    <h6>Access Timeline (Last 30 Days)</h6>
                                    <canvas id="timelineChart" width="400" height="200"></canvas>
                                </div>
                            ` : ''}
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('analyticsModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add new modal
        document.body.insertAdjacentHTML('beforeend', modal);
        $('#analyticsModal').modal('show');
        
        // Initialize chart if timeline data exists
        if (analytics.timeline.length > 0) {
            $('#analyticsModal').on('shown.bs.modal', () => {
                this.createTimelineChart(analytics.timeline);
            });
        }
        
        // Auto-remove modal after closing
        $('#analyticsModal').on('hidden.bs.modal', function() {
            this.remove();
        });
    }
    
    createTimelineChart(timelineData) {
        const ctx = document.getElementById('timelineChart');
        if (!ctx) return;
        
        // Process timeline data
        const dates = [...new Set(timelineData.map(item => item.date))].sort();
        const viewData = dates.map(date => {
            const viewItem = timelineData.find(item => item.date === date && item.access_type === 'view');
            return viewItem ? viewItem.count : 0;
        });
        const downloadData = dates.map(date => {
            const downloadItem = timelineData.find(item => item.date === date && item.access_type === 'download');
            return downloadItem ? downloadItem.count : 0;
        });
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: dates.map(date => new Date(date).toLocaleDateString()),
                datasets: [
                    {
                        label: 'Views',
                        data: viewData,
                        borderColor: '#007bff',
                        backgroundColor: 'rgba(0, 123, 255, 0.1)',
                        tension: 0.1
                    },
                    {
                        label: 'Downloads',
                        data: downloadData,
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.1
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    }
                }
            }
        });
    }
    
    async copyToClipboard(text) {
        try {
            await navigator.clipboard.writeText(text);
            this.showSuccess('Link copied to clipboard!');
        } catch (err) {
            // Fallback for older browsers
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                this.showSuccess('Link copied to clipboard!');
            } catch (err) {
                this.showError('Failed to copy link');
            }
            
            document.body.removeChild(textArea);
        }
    }
    
    getShareUrl(token) {
        return `${window.location.origin}/shared/report.php?token=${token}`;
    }
    
    initializeShareForm() {
        // Set up form interactions
        const expirySelect = document.getElementById('expires_in');
        const customExpiryGroup = document.getElementById('customExpiryGroup');
        
        if (expirySelect && customExpiryGroup) {
            expirySelect.addEventListener('change', (e) => {
                customExpiryGroup.style.display = e.target.value === 'custom' ? 'block' : 'none';
            });
        }
        
        // Set minimum date for custom expiry
        const customExpiryInput = document.getElementById('custom_expiry');
        if (customExpiryInput) {
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            customExpiryInput.min = tomorrow.toISOString().slice(0, 16);
        }
    }
    
    resetShareForm() {
        const form = document.getElementById('shareForm');
        if (form) {
            form.reset();
            document.getElementById('customExpiryGroup').style.display = 'none';
        }
    }
    
    showLoading(message = 'Loading...') {
        // Implementation depends on your existing UI framework
        console.log('Loading:', message);
    }
    
    hideLoading() {
        // Implementation depends on your existing UI framework
        console.log('Loading complete');
    }
    
    showSuccess(message) {
        this.showNotification(message, 'success');
    }
    
    showError(message) {
        this.showNotification(message, 'danger');
    }
    
    showNotification(message, type = 'info') {
        // Create toast notification
        const toast = document.createElement('div');
        toast.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        toast.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(toast);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, 5000);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.reportSharing = new ReportSharingManager();
});

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ReportSharingManager;
}