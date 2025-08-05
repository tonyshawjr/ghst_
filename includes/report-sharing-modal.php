<?php
/**
 * Report Sharing Modal Include
 * 
 * HTML modal for creating and managing shareable report links
 * Include this file in pages that need report sharing functionality
 */

// Ensure Auth is available for CSRF token
if (!isset($auth)) {
    require_once __DIR__ . '/Auth.php';
    $auth = new Auth();
}
?>

<!-- Report Sharing Modal -->
<div class="modal fade share-modal" id="shareModal" tabindex="-1" aria-labelledby="shareModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="shareModalLabel">
                    <i class="fas fa-share-alt me-2"></i>
                    Share Report
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body">
                <!-- Create New Share Form -->
                <div class="share-form-section">
                    <h6>
                        <i class="fas fa-plus-circle"></i>
                        Create New Share Link
                    </h6>
                    
                    <form id="shareForm">
                        <input type="hidden" name="csrf_token" value="<?php echo $auth->generateCSRFToken(); ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="expires_in" class="form-label">
                                    <i class="fas fa-clock me-1"></i>
                                    Expiration
                                </label>
                                <select class="form-select" id="expires_in" name="expires_in">
                                    <option value="24h">24 Hours</option>
                                    <option value="7d" selected>7 Days</option>
                                    <option value="30d">30 Days</option>
                                    <option value="90d">90 Days</option>
                                    <option value="never">Never</option>
                                    <option value="custom">Custom Date</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="max_downloads" class="form-label">
                                    <i class="fas fa-download me-1"></i>
                                    Download Limit
                                </label>
                                <input type="number" class="form-control" id="max_downloads" name="max_downloads" 
                                       placeholder="Unlimited" min="1" max="1000">
                                <small class="form-text text-muted">Leave empty for unlimited downloads</small>
                            </div>
                        </div>
                        
                        <!-- Custom Expiry Date Input -->
                        <div id="customExpiryGroup">
                            <label for="custom_expiry" class="form-label">
                                <i class="fas fa-calendar-alt me-1"></i>
                                Custom Expiry Date & Time
                            </label>
                            <input type="datetime-local" class="form-control" id="custom_expiry" name="custom_expiry">
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">
                                    <i class="fas fa-lock me-1"></i>
                                    Password Protection
                                </label>
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Leave empty for no password">
                                <small class="form-text text-muted">Optional password to protect access</small>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="ip_restrictions" class="form-label">
                                    <i class="fas fa-globe me-1"></i>
                                    IP Restrictions
                                </label>
                                <input type="text" class="form-control" id="ip_restrictions" name="ip_restrictions" 
                                       placeholder="192.168.1.1, 10.0.0.0/24">
                                <small class="form-text text-muted">Comma-separated IPs or CIDR ranges</small>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-user-shield me-1"></i>
                                Permissions
                            </label>
                            <div class="share-permissions">
                                <div class="permission-option">
                                    <input type="checkbox" id="allow_view" name="allow_view" checked disabled>
                                    <label for="allow_view" class="mb-0">
                                        <strong>View Report</strong>
                                        <br>
                                        <small class="text-muted">Always allowed</small>
                                    </label>
                                </div>
                                <div class="permission-option">
                                    <input type="checkbox" id="allow_download" name="allow_download" checked>
                                    <label for="allow_download" class="mb-0">
                                        <strong>Download PDF</strong>
                                        <br>
                                        <small class="text-muted">Allow PDF download</small>
                                    </label>
                                </div>
                                <div class="permission-option">
                                    <input type="checkbox" id="allow_analytics" name="allow_analytics">
                                    <label for="allow_analytics" class="mb-0">
                                        <strong>View Analytics</strong>
                                        <br>
                                        <small class="text-muted">Show access stats</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-link me-2"></i>
                                Create Share Link
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Existing Shares List -->
                <div class="share-form-section">
                    <h6>
                        <i class="fas fa-list"></i>
                        Existing Share Links
                    </h6>
                    
                    <div id="existingShares">
                        <div class="text-center py-3">
                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="text-muted mt-2 mb-0">Loading existing shares...</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Share Settings Update Modal -->
<div class="modal fade" id="shareSettingsModal" tabindex="-1" aria-labelledby="shareSettingsModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="shareSettingsModalLabel">
                    <i class="fas fa-cog me-2"></i>
                    Update Share Settings
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body">
                <form id="updateShareForm">
                    <input type="hidden" name="share_id" id="update_share_id">
                    <input type="hidden" name="csrf_token" value="<?php echo $auth->generateCSRFToken(); ?>">
                    
                    <div class="mb-3">
                        <label for="update_expires_in" class="form-label">
                            <i class="fas fa-clock me-1"></i>
                            Expiration
                        </label>
                        <select class="form-select" id="update_expires_in" name="expires_in">
                            <option value="">No change</option>
                            <option value="24h">24 Hours</option>
                            <option value="7d">7 Days</option>
                            <option value="30d">30 Days</option>
                            <option value="90d">90 Days</option>
                            <option value="never">Never</option>
                            <option value="custom">Custom Date</option>
                        </select>
                    </div>
                    
                    <div id="updateCustomExpiryGroup" style="display: none;">
                        <div class="mb-3">
                            <label for="update_custom_expiry" class="form-label">
                                <i class="fas fa-calendar-alt me-1"></i>
                                Custom Expiry Date & Time
                            </label>
                            <input type="datetime-local" class="form-control" id="update_custom_expiry" name="custom_expiry">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="update_password" class="form-label">
                            <i class="fas fa-lock me-1"></i>
                            Password Protection
                        </label>
                        <input type="password" class="form-control" id="update_password" name="password" 
                               placeholder="Leave empty to keep current, enter new to change">
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="remove_password" name="remove_password">
                            <label class="form-check-label" for="remove_password">
                                Remove password protection
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="update_max_downloads" class="form-label">
                            <i class="fas fa-download me-1"></i>
                            Download Limit
                        </label>
                        <input type="number" class="form-control" id="update_max_downloads" name="max_downloads" 
                               placeholder="Leave empty to keep current" min="1" max="1000">
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="remove_download_limit" name="remove_download_limit">
                            <label class="form-check-label" for="remove_download_limit">
                                Remove download limit
                            </label>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>
                            Update Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Include required CSS and JS -->
<link rel="stylesheet" href="/assets/css/report-sharing.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="/assets/js/report-sharing.js"></script>

<script>
// Initialize permission option interactions
document.addEventListener('DOMContentLoaded', function() {
    // Permission checkbox styling
    const permissionOptions = document.querySelectorAll('.permission-option');
    permissionOptions.forEach(option => {
        const checkbox = option.querySelector('input[type="checkbox"]');
        const updateCheckedState = () => {
            if (checkbox.checked) {
                option.classList.add('checked');
            } else {
                option.classList.remove('checked');
            }
        };
        
        checkbox.addEventListener('change', updateCheckedState);
        option.addEventListener('click', (e) => {
            if (e.target !== checkbox && !checkbox.disabled) {
                checkbox.checked = !checkbox.checked;
                updateCheckedState();
            }
        });
        
        // Initial state
        updateCheckedState();
    });
    
    // Update form interactions
    const updateExpirySelect = document.getElementById('update_expires_in');
    const updateCustomExpiryGroup = document.getElementById('updateCustomExpiryGroup');
    
    if (updateExpirySelect && updateCustomExpiryGroup) {
        updateExpirySelect.addEventListener('change', (e) => {
            updateCustomExpiryGroup.style.display = e.target.value === 'custom' ? 'block' : 'none';
        });
    }
    
    // Remove password checkbox interaction
    const removePasswordCheck = document.getElementById('remove_password');
    const updatePasswordInput = document.getElementById('update_password');
    
    if (removePasswordCheck && updatePasswordInput) {
        removePasswordCheck.addEventListener('change', (e) => {
            if (e.target.checked) {
                updatePasswordInput.disabled = true;
                updatePasswordInput.value = '';
                updatePasswordInput.placeholder = 'Password will be removed';
            } else {
                updatePasswordInput.disabled = false;
                updatePasswordInput.placeholder = 'Leave empty to keep current, enter new to change';
            }
        });
    }
    
    // Remove download limit checkbox interaction
    const removeDownloadLimitCheck = document.getElementById('remove_download_limit');
    const updateMaxDownloadsInput = document.getElementById('update_max_downloads');
    
    if (removeDownloadLimitCheck && updateMaxDownloadsInput) {
        removeDownloadLimitCheck.addEventListener('change', (e) => {
            if (e.target.checked) {
                updateMaxDownloadsInput.disabled = true;
                updateMaxDownloadsInput.value = '';
                updateMaxDownloadsInput.placeholder = 'Download limit will be removed';
            } else {
                updateMaxDownloadsInput.disabled = false;
                updateMaxDownloadsInput.placeholder = 'Leave empty to keep current';
            }
        });
    }
    
    // Edit share button handler
    document.addEventListener('click', (e) => {
        if (e.target.matches('.btn-edit-share')) {
            const shareId = e.target.dataset.shareId;
            document.getElementById('update_share_id').value = shareId;
            $('#shareSettingsModal').modal('show');
        }
    });
});
</script>

<style>
/* Additional inline styles for better integration */
.share-modal .modal-body {
    max-height: 70vh;
    overflow-y: auto;
}

.permission-option label {
    cursor: pointer;
    user-select: none;
}

.permission-option input[type="checkbox"]:disabled + label {
    opacity: 0.7;
    cursor: not-allowed;
}

/* Loading state for shares list */
#existingShares .spinner-border {
    width: 1.5rem;
    height: 1.5rem;
}

/* Mobile responsive adjustments */
@media (max-width: 576px) {
    .share-modal .modal-dialog {
        margin: 0.5rem;
    }
    
    .share-form-section {
        padding: 1rem;
    }
    
    .permission-option {
        padding: 0.5rem;
    }
}
</style>