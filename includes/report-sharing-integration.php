<?php
/**
 * Report Sharing Integration
 * 
 * Include this file in your reports dashboard to add sharing functionality
 * This script provides JavaScript functions and modal integration
 */

// Ensure we have authentication and required services
if (!isset($auth)) {
    require_once __DIR__ . '/Auth.php';
    $auth = new Auth();
}

if (!isset($sharingService)) {
    require_once __DIR__ . '/ReportSharingService.php';
    $sharingService = new ReportSharingService();
}
?>

<!-- Include the sharing modal -->
<?php include __DIR__ . '/report-sharing-modal.php'; ?>

<!-- CSRF Token Meta Tag -->
<meta name="csrf-token" content="<?php echo $auth->generateCSRFToken(); ?>">

<!-- Additional JavaScript for integration -->
<script>
/**
 * Enhanced share report function for integration
 */
function shareReport(reportId) {
    if (window.reportSharing) {
        window.reportSharing.openShareModal(reportId);
    } else {
        console.error('Report sharing module not loaded');
        // Fallback to simple modal or alert
        alert('Share functionality is loading. Please try again in a moment.');
    }
}

/**
 * Preview report function (if not already defined)
 */
function previewReport(reportId) {
    const previewUrl = `/api/reports/preview.php?id=${reportId}`;
    window.open(previewUrl, '_blank', 'width=1200,height=800,scrollbars=yes,resizable=yes');
}

/**
 * Send email report function (if not already defined)
 */
function sendEmailReport(reportId) {
    // You can implement email modal here or redirect to email page
    console.log('Send email for report:', reportId);
}

/**
 * Delete report function (if not already defined)
 */
function deleteReport(reportId) {
    if (confirm('Are you sure you want to delete this report? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_report">
            <input type="hidden" name="report_id" value="${reportId}">
            <input type="hidden" name="csrf_token" value="${document.querySelector('meta[name="csrf-token"]').content}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

/**
 * Enhanced modal functions for compatibility
 */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Add share icons to existing reports if they don't have them
    const reportCards = document.querySelectorAll('.report-card');
    reportCards.forEach(card => {
        // Find the report ID from existing buttons
        const downloadBtn = card.querySelector('a[href*="/api/reports/download.php"]');
        if (downloadBtn) {
            const urlParams = new URLSearchParams(downloadBtn.href.split('?')[1]);
            const reportId = urlParams.get('id');
            
            // Check if share button already exists
            const existingShareBtn = card.querySelector(`button[onclick*="shareReport(${reportId})"]`);
            if (!existingShareBtn && reportId) {
                // Add share button if it doesn't exist
                const actionsContainer = card.querySelector('.flex.items-center.gap-2');
                if (actionsContainer) {
                    const shareBtn = document.createElement('button');
                    shareBtn.onclick = () => shareReport(reportId);
                    shareBtn.className = 'btn-share-report px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors';
                    shareBtn.setAttribute('data-report-id', reportId);
                    shareBtn.innerHTML = '<i class="fas fa-share mr-1"></i>Share';
                    
                    // Insert before the menu button (ellipsis)
                    const menuBtn = actionsContainer.querySelector('.relative.group');
                    if (menuBtn) {
                        actionsContainer.insertBefore(shareBtn, menuBtn);
                    } else {
                        actionsContainer.appendChild(shareBtn);
                    }
                }
            }
        }
    });
    
    // Close modals when clicking outside
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal') && e.target.classList.contains('active')) {
            const modalId = e.target.id;
            closeModal(modalId);
        }
    });
    
    // Close modals with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const activeModals = document.querySelectorAll('.modal.active');
            activeModals.forEach(modal => {
                closeModal(modal.id);
            });
        }
    });
});

// Add notification system if not already present
if (!window.showNotification) {
    window.showNotification = function(message, type = 'info') {
        // Create notification element
        const notification = document.createElement('div');
        notification.className = `fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg transition-all duration-300 transform translate-x-full`;
        
        // Set colors based on type
        switch (type) {
            case 'success':
                notification.className += ' bg-green-100 text-green-800 border border-green-200';
                break;
            case 'error':
            case 'danger':
                notification.className += ' bg-red-100 text-red-800 border border-red-200';
                break;
            case 'warning':
                notification.className += ' bg-yellow-100 text-yellow-800 border border-yellow-200';
                break;
            default:
                notification.className += ' bg-blue-100 text-blue-800 border border-blue-200';
        }
        
        notification.innerHTML = `
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' || type === 'danger' ? 'exclamation-circle' : type === 'warning' ? 'exclamation-triangle' : 'info-circle'} mr-2"></i>
                    <span>${message}</span>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        // Animate in
        setTimeout(() => {
            notification.classList.remove('translate-x-full');
        }, 10);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            notification.classList.add('translate-x-full');
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 300);
        }, 5000);
    };
}

// Load analytics data for shares (if needed)
async function loadShareAnalytics(reportId) {
    try {
        const response = await fetch(`/api/reports/share-analytics.php?report_id=${reportId}`);
        const result = await response.json();
        
        if (result.success) {
            return result.data.analytics;
        }
    } catch (error) {
        console.error('Error loading share analytics:', error);
    }
    
    return null;
}

// Add quick share functionality
function quickShare(reportId, options = {}) {
    // Quick share with default settings
    const defaultOptions = {
        expires_in: '7d',
        permissions: ['view', 'download'],
        ...options
    };
    
    // Create share via API
    fetch('/api/reports/share.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            report_id: reportId,
            ...defaultOptions,
            csrf_token: document.querySelector('meta[name="csrf-token"]')?.content
        })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            // Copy link to clipboard
            navigator.clipboard.writeText(result.data.share_url).then(() => {
                showNotification('Share link created and copied to clipboard!', 'success');
            }).catch(() => {
                showNotification(`Share link created: ${result.data.share_url}`, 'success');
            });
        } else {
            showNotification(result.error || 'Failed to create share link', 'error');
        }
    })
    .catch(error => {
        console.error('Error creating quick share:', error);
        showNotification('Network error occurred', 'error');
    });
}

// Bulk operations for reports
function bulkShareReports(reportIds, options = {}) {
    if (!Array.isArray(reportIds) || reportIds.length === 0) {
        showNotification('No reports selected', 'warning');
        return;
    }
    
    const promises = reportIds.map(reportId => 
        fetch('/api/reports/share.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                report_id: reportId,
                ...options,
                csrf_token: document.querySelector('meta[name="csrf-token"]')?.content
            })
        }).then(response => response.json())
    );
    
    Promise.all(promises).then(results => {
        const successful = results.filter(r => r.success).length;
        const failed = results.length - successful;
        
        if (failed === 0) {
            showNotification(`${successful} share links created successfully!`, 'success');
        } else {
            showNotification(`${successful} successful, ${failed} failed`, 'warning');
        }
    }).catch(error => {
        console.error('Error in bulk share:', error);
        showNotification('Bulk share operation failed', 'error');
    });
}

// Export functions for global use
window.shareReport = shareReport;
window.quickShare = quickShare;
window.bulkShareReports = bulkShareReports;
window.loadShareAnalytics = loadShareAnalytics;

console.log('Report sharing integration loaded successfully');
</script>

<!-- Additional CSS for better integration -->
<style>
/* Ensure sharing buttons integrate well with existing styles */
.btn-share-report {
    position: relative;
    overflow: hidden;
}

.btn-share-report:hover {
    transform: translateY(-1px);
}

.btn-share-report::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
    transition: left 0.5s;
}

.btn-share-report:hover::before {
    left: 100%;
}

/* Notification system integration */
.notification-container {
    position: fixed;
    top: 1rem;
    right: 1rem;
    z-index: 9999;
    pointer-events: none;
}

.notification-container > * {
    pointer-events: auto;
    margin-bottom: 0.5rem;
}

/* Modal backdrop compatibility */
.modal.active {
    display: flex !important;
}

/* Mobile responsive adjustments */
@media (max-width: 640px) {
    .btn-share-report {
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
    }
    
    .btn-share-report i {
        margin-right: 0.25rem;
    }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .notification-container div {
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
    }
}
</style>

<?php
// Add any server-side initialization if needed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'quick_share') {
    $reportId = $_POST['report_id'] ?? null;
    $options = [
        'expires_in' => $_POST['expires_in'] ?? ReportSharingService::EXPIRY_7D,
        'permissions' => ['view', 'download']
    ];
    
    if ($reportId) {
        try {
            $result = $sharingService->createShareLink($reportId, $options);
            echo json_encode(['success' => true, 'data' => $result]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit();
    }
}
?>

<!-- Debug information (remove in production) -->
<?php if (defined('ENVIRONMENT') && ENVIRONMENT === 'development'): ?>
<script>
console.log('Report Sharing Integration Debug Info:', {
    hasAuth: <?php echo isset($auth) ? 'true' : 'false'; ?>,
    hasSharingService: <?php echo isset($sharingService) ? 'true' : 'false'; ?>,
    csrfToken: '<?php echo $auth->generateCSRFToken(); ?>'
});
</script>
<?php endif; ?>