<?php
/**
 * Public Report Viewer
 * 
 * Public-facing page for viewing shared reports without authentication
 * Supports client branding, mobile responsive design, and access controls
 */

// Include core files
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/ReportSharingService.php';
require_once '../includes/BrandingHelper.php';

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');

$sharingService = new ReportSharingService();
$errors = [];
$share = null;
$passwordRequired = false;
$accessGranted = false;

// Get share token from URL
$token = $_GET['token'] ?? '';
if (empty($token)) {
    $errors[] = 'Invalid share link';
} else {
    // Validate token format
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        $errors[] = 'Invalid share token format';
    } else {
        // Get share information
        $share = $sharingService->getShareByToken($token);
        
        if (!$share) {
            $errors[] = 'Share link not found or has expired';
        } else {
            // Check IP restrictions
            $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
            if (!$sharingService->isIPAllowed($share['id'], $clientIP)) {
                $errors[] = 'Access denied from your location';
            } else {
                // Check if password is required
                $passwordRequired = !empty($share['password_hash']);
                
                if ($passwordRequired) {
                    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
                        if ($sharingService->validateSharePassword($share['id'], $_POST['password'])) {
                            $accessGranted = true;
                            $_SESSION['share_' . $share['id'] . '_authenticated'] = true;
                        } else {
                            $errors[] = 'Incorrect password';
                        }
                    } elseif (isset($_SESSION['share_' . $share['id'] . '_authenticated'])) {
                        $accessGranted = true;
                    }
                } else {
                    $accessGranted = true;
                }
                
                // Record view if access granted
                if ($accessGranted) {
                    $sharingService->recordShareAccess($share['id'], 'view');
                }
            }
        }
    }
}

// Load branding if share exists
$branding = null;
if ($share && $share['client_id']) {
    try {
        $brandingHelper = new BrandingHelper();
        $branding = $brandingHelper->getClientBranding($share['client_id']);
    } catch (Exception $e) {
        error_log("Failed to load branding: " . $e->getMessage());
    }
}

// Handle download request
if ($accessGranted && isset($_GET['download']) && $_GET['download'] === '1') {
    $permissions = $share['access_settings']['permissions'] ?? [];
    
    if (in_array('download', $permissions)) {
        // Record download
        $sharingService->recordShareAccess($share['id'], 'download');
        
        // Serve file
        $filePath = ROOT_PATH . '/' . $share['file_path'];
        if (file_exists($filePath)) {
            $fileName = $share['report_name'] . '.pdf';
            
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: no-cache, must-revalidate');
            
            readfile($filePath);
            exit();
        } else {
            $errors[] = 'Report file not found';
        }
    } else {
        $errors[] = 'Download not permitted for this share';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $share ? htmlspecialchars($share['report_name']) : 'Shared Report'; ?> - <?php echo APP_NAME; ?></title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?php echo APP_URL; ?>/assets/favicon.ico">
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: <?php echo $branding['primary_color'] ?? '#007bff'; ?>;
            --secondary-color: <?php echo $branding['secondary_color'] ?? '#6c757d'; ?>;
            --accent-color: <?php echo $branding['accent_color'] ?? '#28a745'; ?>;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .report-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .report-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .report-header {
            background: var(--primary-color);
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
        }
        
        .client-logo {
            max-height: 60px;
            margin-bottom: 1rem;
        }
        
        .report-title {
            font-size: 1.8rem;
            font-weight: 300;
            margin-bottom: 0.5rem;
        }
        
        .report-meta {
            opacity: 0.9;
            font-size: 0.9rem;
        }
        
        .report-content {
            padding: 2rem;
        }
        
        .password-form {
            max-width: 400px;
            margin: 2rem auto;
            text-align: center;
        }
        
        .password-input {
            position: relative;
            margin-bottom: 1rem;
        }
        
        .password-input input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .password-input input:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.75rem 2rem;
            font-weight: 500;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        .btn-success {
            background-color: var(--accent-color);
            border-color: var(--accent-color);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .report-preview {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 2rem;
            background: #f8f9fa;
        }
        
        .preview-header {
            background: #e9ecef;
            padding: 1rem;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: between;
            align-items: center;
        }
        
        .preview-content {
            padding: 2rem;
            text-align: center;
            min-height: 300px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        .report-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .info-card {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
        }
        
        .info-card i {
            font-size: 2rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .info-card h5 {
            color: #495057;
            margin-bottom: 0.5rem;
        }
        
        .info-card p {
            margin: 0;
            color: #6c757d;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 2rem;
        }
        
        .powered-by {
            text-align: center;
            margin-top: 3rem;
            padding: 1rem;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .powered-by a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        @media (max-width: 768px) {
            .report-container {
                margin: 1rem auto;
                padding: 0 0.5rem;
            }
            
            .report-header {
                padding: 1.5rem 1rem;
            }
            
            .report-title {
                font-size: 1.4rem;
            }
            
            .report-content {
                padding: 1.5rem 1rem;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 300px;
            }
        }
        
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .security-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255,255,255,0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="report-container">
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php foreach ($errors as $error): ?>
                    <div><?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            </div>
            
            <div class="text-center mt-4">
                <a href="<?php echo APP_URL; ?>" class="btn btn-primary">
                    <i class="fas fa-home me-2"></i>Go to Homepage
                </a>
            </div>
        <?php elseif ($passwordRequired && !$accessGranted): ?>
            <div class="report-card">
                <div class="report-header">
                    <?php if ($branding && $branding['logo_url']): ?>
                        <img src="<?php echo htmlspecialchars($branding['logo_url']); ?>" alt="Logo" class="client-logo">
                    <?php endif; ?>
                    
                    <div class="security-badge">
                        <i class="fas fa-lock"></i>
                        Protected
                    </div>
                    
                    <h1 class="report-title">
                        <i class="fas fa-lock me-2"></i>
                        Password Required
                    </h1>
                    <p class="report-meta">This report is password protected</p>
                </div>
                
                <div class="report-content">
                    <form method="POST" class="password-form">
                        <div class="password-input">
                            <input type="password" name="password" placeholder="Enter password" required autofocus>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-unlock me-2"></i>Access Report
                        </button>
                    </form>
                    
                    <?php if (in_array('Incorrect password', $errors)): ?>
                        <div class="alert alert-warning mt-3" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Incorrect password. Please try again.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif ($accessGranted && $share): ?>
            <div class="report-card">
                <div class="report-header">
                    <?php if ($branding && $branding['logo_url']): ?>
                        <img src="<?php echo htmlspecialchars($branding['logo_url']); ?>" alt="<?php echo htmlspecialchars($branding['company_name'] ?? 'Client Logo'); ?>" class="client-logo">
                    <?php endif; ?>
                    
                    <?php if ($share['password_hash']): ?>
                        <div class="security-badge">
                            <i class="fas fa-shield-alt"></i>
                            Secure
                        </div>
                    <?php endif; ?>
                    
                    <h1 class="report-title"><?php echo htmlspecialchars($share['report_name']); ?></h1>
                    <p class="report-meta">
                        <i class="fas fa-building me-1"></i>
                        <?php echo htmlspecialchars($share['client_name']); ?>
                        <?php if ($share['expires_at']): ?>
                            <span class="ms-3">
                                <i class="fas fa-clock me-1"></i>
                                Expires: <?php echo date('M j, Y g:i A', strtotime($share['expires_at'])); ?>
                            </span>
                        <?php endif; ?>
                    </p>
                </div>
                
                <div class="report-content">
                    <div class="report-info">
                        <div class="info-card">
                            <i class="fas fa-chart-line"></i>
                            <h5>Report Type</h5>
                            <p><?php echo ucfirst(str_replace('_', ' ', $share['report_type'])); ?></p>
                        </div>
                        
                        <div class="info-card">
                            <i class="fas fa-file-pdf"></i>
                            <h5>Format</h5>
                            <p>PDF Document</p>
                        </div>
                        
                        <div class="info-card">
                            <i class="fas fa-calendar"></i>
                            <h5>Generated</h5>
                            <p><?php echo date('M j, Y', strtotime($share['created_at'])); ?></p>
                        </div>
                        
                        <?php if ($share['expires_at']): ?>
                        <div class="info-card">
                            <i class="fas fa-hourglass-half"></i>
                            <h5>Access Until</h5>
                            <p><?php echo date('M j, Y', strtotime($share['expires_at'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="report-preview">
                        <div class="preview-header">
                            <span><i class="fas fa-file-pdf me-2"></i><?php echo htmlspecialchars($share['report_name']); ?>.pdf</span>
                        </div>
                        <div class="preview-content">
                            <i class="fas fa-file-pdf" style="font-size: 4rem; color: #dc3545; margin-bottom: 1rem;"></i>
                            <h4>PDF Report Preview</h4>
                            <p class="text-muted">Click the download button below to access the full report</p>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <?php 
                        $permissions = $share['access_settings']['permissions'] ?? [];
                        if (in_array('download', $permissions)): 
                        ?>
                            <a href="?token=<?php echo htmlspecialchars($token); ?>&download=1" 
                               class="btn btn-success btn-lg" 
                               id="downloadBtn">
                                <i class="fas fa-download me-2"></i>Download Report
                            </a>
                        <?php endif; ?>
                        
                        <button type="button" class="btn btn-outline-primary btn-lg" onclick="shareReport()">
                            <i class="fas fa-share me-2"></i>Share Link
                        </button>
                    </div>
                    
                    <?php if ($share['allowed_downloads'] && $share['download_count']): ?>
                        <div class="alert alert-info mt-3" role="alert">
                            <i class="fas fa-info-circle me-2"></i>
                            Downloads: <?php echo $share['download_count']; ?> / <?php echo $share['allowed_downloads']; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($branding && $branding['company_name']): ?>
                <div class="powered-by">
                    Report powered by <?php echo htmlspecialchars($branding['company_name']); ?>
                    <br>
                    <small>Generated with <a href="<?php echo APP_URL; ?>" target="_blank"><?php echo APP_NAME; ?></a></small>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Download button loading state
        document.getElementById('downloadBtn')?.addEventListener('click', function(e) {
            const btn = e.target;
            const originalHTML = btn.innerHTML;
            btn.innerHTML = '<span class="loading-spinner me-2"></span>Preparing Download...';
            btn.disabled = true;
            
            // Re-enable after 3 seconds
            setTimeout(() => {
                btn.innerHTML = originalHTML;
                btn.disabled = false;
            }, 3000);
        });
        
        // Share functionality
        function shareReport() {
            const url = window.location.href;
            
            if (navigator.share) {
                navigator.share({
                    title: '<?php echo addslashes($share['report_name'] ?? 'Shared Report'); ?>',
                    text: 'Check out this report',
                    url: url
                });
            } else if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(() => {
                    showToast('Link copied to clipboard!', 'success');
                });
            } else {
                // Fallback
                const tempInput = document.createElement('input');
                tempInput.value = url;
                document.body.appendChild(tempInput);
                tempInput.select();
                document.execCommand('copy');
                document.body.removeChild(tempInput);
                showToast('Link copied to clipboard!', 'success');
            }
        }
        
        // Simple toast notification
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `alert alert-${type} position-fixed`;
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            toast.innerHTML = `
                <i class="fas fa-check-circle me-2"></i>
                ${message}
                <button type="button" class="btn-close float-end" onclick="this.parentElement.remove()"></button>
            `;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, 5000);
        }
        
        // Auto-focus password field
        document.querySelector('input[name="password"]')?.focus();
        
        // Handle form submission with loading state
        document.querySelector('form')?.addEventListener('submit', function(e) {
            const submitBtn = e.target.querySelector('button[type="submit"]');
            if (submitBtn) {
                const originalHTML = submitBtn.innerHTML;
                submitBtn.innerHTML = '<span class="loading-spinner me-2"></span>Verifying...';
                submitBtn.disabled = true;
            }
        });
    </script>
</body>
</html>