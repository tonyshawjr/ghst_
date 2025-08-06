<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/functions.php';
require_once '../includes/layout.php';

$auth = new Auth();
$auth->requireLogin();
requireClient();

$db = Database::getInstance();
$client = $auth->getCurrentClient();

$campaignId = intval($_GET['id'] ?? 0);
if (!$campaignId) {
    redirect('/dashboard/wrtr.php');
}

// Verify campaign belongs to client
$stmt = $db->prepare("SELECT * FROM strategy_campaigns WHERE id = ? AND client_id = ?");
$stmt->execute([$campaignId, $client['id']]);
$campaign = $stmt->fetch();

if (!$campaign) {
    redirect('/dashboard/wrtr.php');
}

// Handle POST request for creating share link
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        try {
            // Generate secure token
            $shareToken = generateRandomString(64);
            
            // Prepare share data
            $shareType = $_POST['share_type'] ?? 'view_only';
            $title = trim($_POST['title'] ?? '') ?: $campaign['title'] . ' - Shared Strategy';
            $description = trim($_POST['description'] ?? '');
            $password = trim($_POST['password'] ?? '');
            $passwordHash = !empty($password) ? password_hash($password, PASSWORD_ARGON2ID) : null;
            
            // Expiration settings
            $expiryDays = intval($_POST['expiry_days'] ?? 30);
            $expiresAt = $expiryDays > 0 ? date('Y-m-d H:i:s', strtotime("+{$expiryDays} days")) : null;
            
            // Access limits
            $maxViews = intval($_POST['max_views'] ?? 0) ?: null;
            $allowDownload = isset($_POST['allow_download']) ? 1 : 0;
            $allowAnalytics = isset($_POST['allow_analytics_view']) ? 1 : 0;
            $allowWeekExpansion = isset($_POST['allow_week_expansion']) ? 1 : 0;
            $showSensitiveData = isset($_POST['show_sensitive_data']) ? 1 : 0;
            
            // IP restrictions
            $ipWhitelist = null;
            if (!empty($_POST['ip_restrictions'])) {
                $ips = array_map('trim', explode("\n", $_POST['ip_restrictions']));
                $ips = array_filter($ips, 'filter_var', FILTER_VALIDATE_IP);
                $ipWhitelist = !empty($ips) ? json_encode($ips) : null;
            }
            
            // Insert share link
            $stmt = $db->prepare("
                INSERT INTO campaign_share_links (
                    campaign_id, share_token, share_type, title, description,
                    password_hash, expires_at, max_views, ip_whitelist,
                    allow_download, allow_analytics_view, allow_week_expansion,
                    show_sensitive_data, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $campaignId,
                $shareToken,
                $shareType,
                $title,
                $description,
                $passwordHash,
                $expiresAt,
                $maxViews,
                $ipWhitelist,
                $allowDownload,
                $allowAnalytics,
                $allowWeekExpansion,
                $showSensitiveData,
                $auth->getCurrentUser()['id']
            ]);
            
            $shareId = $db->lastInsertId();
            $shareUrl = BASE_URL . "/shared/campaign.php?token=" . $shareToken;
            
            $success = 'Share link created successfully!';
            
        } catch (Exception $e) {
            error_log("Share link creation error: " . $e->getMessage());
            $error = 'Failed to create share link. Please try again.';
        }
    }
}

// Get existing share links
$stmt = $db->prepare("
    SELECT csl.*, u.name as created_by_name,
           (SELECT COUNT(*) FROM campaign_share_access_logs WHERE share_link_id = csl.id) as total_views
    FROM campaign_share_links csl
    LEFT JOIN users u ON csl.created_by = u.id
    WHERE csl.campaign_id = ? AND csl.is_active = 1
    ORDER BY csl.created_at DESC
");
$stmt->execute([$campaignId]);
$existingLinks = $stmt->fetchAll();

$csrfToken = $auth->generateCSRFToken();
renderHeader('Share Campaign - ' . $campaign['title']);
?>

<div class="max-w-4xl mx-auto">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center mb-4">
            <a href="/dashboard/wrtr-campaign.php?id=<?= $campaignId ?>" class="text-purple-400 hover:text-purple-300 mr-4 touch-target">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <div>
                <h1 class="text-3xl font-bold text-white">Share Campaign</h1>
                <p class="text-gray-400"><?= sanitize($campaign['title']) ?></p>
            </div>
        </div>
    </div>

    <?php if (isset($success)): ?>
    <div class="bg-green-900/50 border border-green-500/50 rounded-lg p-4 mb-6">
        <div class="flex items-start">
            <svg class="w-5 h-5 text-green-400 mt-0.5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div class="flex-1">
                <p class="text-green-300 mb-2"><?= $success ?></p>
                <div class="bg-black rounded p-3">
                    <div class="flex items-center justify-between">
                        <code class="text-green-400 text-sm break-all mr-2"><?= $shareUrl ?></code>
                        <button onclick="copyToClipboard('<?= $shareUrl ?>')" class="px-2 py-1 bg-green-600 hover:bg-green-700 rounded text-xs transition-colors">
                            Copy
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
    <div class="bg-red-900/50 border border-red-500/50 rounded-lg p-4 mb-6">
        <div class="flex items-center">
            <svg class="w-5 h-5 text-red-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <p class="text-red-300"><?= $error ?></p>
        </div>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Create New Share Link -->
        <div class="bg-gray-900 rounded-lg border border-gray-800 p-6">
            <h3 class="text-xl font-semibold mb-6">Create Share Link</h3>
            
            <form method="POST" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                
                <!-- Share Type -->
                <div>
                    <label class="block text-sm font-medium mb-3">Share Type</label>
                    <div class="space-y-3">
                        <label class="flex items-center cursor-pointer">
                            <input type="radio" name="share_type" value="view_only" checked class="mr-3 text-purple-600 focus:ring-purple-500">
                            <div>
                                <div class="font-medium">View Only</div>
                                <div class="text-sm text-gray-400">Recipients can view the strategy but not download</div>
                            </div>
                        </label>
                        
                        <label class="flex items-center cursor-pointer">
                            <input type="radio" name="share_type" value="download_pdf" class="mr-3 text-purple-600 focus:ring-purple-500">
                            <div>
                                <div class="font-medium">View & Download</div>
                                <div class="text-sm text-gray-400">Recipients can view and download PDF export</div>
                            </div>
                        </label>
                        
                        <label class="flex items-center cursor-pointer">
                            <input type="radio" name="share_type" value="analytics_only" class="mr-3 text-purple-600 focus:ring-purple-500">
                            <div>
                                <div class="font-medium">Analytics Only</div>
                                <div class="text-sm text-gray-400">Recipients can only view performance metrics</div>
                            </div>
                        </label>
                    </div>
                </div>
                
                <!-- Title & Description -->
                <div>
                    <label class="block text-sm font-medium mb-2">Share Title</label>
                    <input 
                        type="text" 
                        name="title" 
                        placeholder="<?= sanitize($campaign['title']) ?> - Shared Strategy"
                        class="w-full px-3 py-2 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500"
                    >
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Description (Optional)</label>
                    <textarea 
                        name="description" 
                        rows="2" 
                        placeholder="Add a description for recipients..."
                        class="w-full px-3 py-2 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500 resize-none"
                    ></textarea>
                </div>
                
                <!-- Security Settings -->
                <div>
                    <label class="block text-sm font-medium mb-2">Password Protection (Optional)</label>
                    <input 
                        type="password" 
                        name="password" 
                        placeholder="Leave empty for no password"
                        class="w-full px-3 py-2 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500"
                    >
                    <p class="text-xs text-gray-500 mt-1">Recipients will need this password to access the strategy</p>
                </div>
                
                <!-- Expiration -->
                <div>
                    <label class="block text-sm font-medium mb-2">Link Expiration</label>
                    <select name="expiry_days" class="w-full px-3 py-2 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500">
                        <option value="7">7 days</option>
                        <option value="30" selected>30 days</option>
                        <option value="60">60 days</option>
                        <option value="90">90 days</option>
                        <option value="0">Never expires</option>
                    </select>
                </div>
                
                <!-- Access Limits -->
                <div>
                    <label class="block text-sm font-medium mb-2">Maximum Views (Optional)</label>
                    <input 
                        type="number" 
                        name="max_views" 
                        placeholder="Leave empty for unlimited"
                        min="1"
                        max="1000"
                        class="w-full px-3 py-2 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500"
                    >
                </div>
                
                <!-- Permissions -->
                <div>
                    <label class="block text-sm font-medium mb-3">Permissions</label>
                    <div class="space-y-2">
                        <label class="flex items-center">
                            <input type="checkbox" name="allow_download" class="mr-2 rounded border-gray-600 bg-gray-800 text-purple-600 focus:ring-purple-500">
                            <span class="text-sm">Allow PDF downloads</span>
                        </label>
                        
                        <label class="flex items-center">
                            <input type="checkbox" name="allow_analytics_view" checked class="mr-2 rounded border-gray-600 bg-gray-800 text-purple-600 focus:ring-purple-500">
                            <span class="text-sm">Show analytics data</span>
                        </label>
                        
                        <label class="flex items-center">
                            <input type="checkbox" name="allow_week_expansion" checked class="mr-2 rounded border-gray-600 bg-gray-800 text-purple-600 focus:ring-purple-500">
                            <span class="text-sm">Allow week expansion</span>
                        </label>
                        
                        <label class="flex items-center">
                            <input type="checkbox" name="show_sensitive_data" class="mr-2 rounded border-gray-600 bg-gray-800 text-purple-600 focus:ring-purple-500">
                            <span class="text-sm">Include sensitive data (costs, private notes)</span>
                        </label>
                    </div>
                </div>
                
                <!-- IP Restrictions -->
                <div>
                    <label class="block text-sm font-medium mb-2">IP Restrictions (Optional)</label>
                    <textarea 
                        name="ip_restrictions" 
                        rows="3" 
                        placeholder="Enter allowed IP addresses (one per line)&#10;192.168.1.100&#10;203.0.113.0"
                        class="w-full px-3 py-2 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500 resize-none text-sm font-mono"
                    ></textarea>
                    <p class="text-xs text-gray-500 mt-1">Leave empty to allow access from any IP address</p>
                </div>
                
                <button 
                    type="submit" 
                    class="w-full py-3 bg-purple-600 hover:bg-purple-700 rounded-lg font-medium transition-colors touch-target"
                >
                    Create Share Link
                </button>
            </form>
        </div>
        
        <!-- Existing Share Links -->
        <div class="bg-gray-900 rounded-lg border border-gray-800 p-6">
            <h3 class="text-xl font-semibold mb-6">Existing Share Links</h3>
            
            <?php if (empty($existingLinks)): ?>
            <div class="text-center py-8">
                <svg class="w-12 h-12 mx-auto mb-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.367 2.684 3 3 0 00-5.367-2.684z"></path>
                </svg>
                <p class="text-gray-400">No share links created yet</p>
            </div>
            <?php else: ?>
            
            <div class="space-y-4">
                <?php foreach ($existingLinks as $link): ?>
                <div class="bg-black rounded-lg p-4 border border-gray-800">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <h4 class="font-medium text-white"><?= sanitize($link['title']) ?></h4>
                            <div class="flex items-center space-x-4 text-sm text-gray-400 mt-1">
                                <span class="capitalize"><?= str_replace('_', ' ', $link['share_type']) ?></span>
                                <span><?= $link['total_views'] ?> views</span>
                                <?php if ($link['expires_at']): ?>
                                <span>Expires <?= formatRelativeTime($link['expires_at']) ?></span>
                                <?php else: ?>
                                <span>No expiration</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-2" x-data="{ showMenu: false }" @click.away="showMenu = false">
                            <button @click="showMenu = !showMenu" class="text-gray-400 hover:text-white touch-target">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path>
                                </svg>
                            </button>
                            
                            <div x-show="showMenu" x-transition class="absolute right-0 top-full mt-2 bg-gray-800 rounded-lg shadow-lg border border-gray-700 overflow-hidden z-10 min-w-32">
                                <button onclick="copyToClipboard('<?= BASE_URL ?>/shared/campaign.php?token=<?= $link['share_token'] ?>')" class="block w-full text-left px-3 py-2 hover:bg-gray-700 transition-colors text-sm">
                                    Copy Link
                                </button>
                                <button onclick="viewAnalytics(<?= $link['id'] ?>)" class="block w-full text-left px-3 py-2 hover:bg-gray-700 transition-colors text-sm">
                                    View Analytics
                                </button>
                                <button onclick="disableLink(<?= $link['id'] ?>)" class="block w-full text-left px-3 py-2 hover:bg-red-900 hover:text-red-300 transition-colors text-sm text-red-400">
                                    Disable Link
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Share URL -->
                    <div class="bg-gray-900 rounded p-3">
                        <div class="flex items-center justify-between">
                            <code class="text-purple-400 text-sm break-all mr-2"><?= BASE_URL ?>/shared/campaign.php?token=<?= $link['share_token'] ?></code>
                            <button onclick="copyToClipboard('<?= BASE_URL ?>/shared/campaign.php?token=<?= $link['share_token'] ?>')" class="px-2 py-1 bg-purple-600 hover:bg-purple-700 rounded text-xs transition-colors whitespace-nowrap">
                                Copy
                            </button>
                        </div>
                    </div>
                    
                    <!-- Share Features -->
                    <div class="flex items-center space-x-4 text-xs text-gray-500 mt-3">
                        <?php if ($link['password_hash']): ?>
                        <span class="flex items-center">
                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                            Password Protected
                        </span>
                        <?php endif; ?>
                        
                        <?php if ($link['allow_download']): ?>
                        <span>Download Enabled</span>
                        <?php endif; ?>
                        
                        <?php if ($link['max_views']): ?>
                        <span>Max <?= $link['max_views'] ?> views</span>
                        <?php endif; ?>
                        
                        <?php if ($link['ip_whitelist']): ?>
                        <span>IP Restricted</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        // Show success feedback
        const button = event.target;
        const originalText = button.textContent;
        button.textContent = 'Copied!';
        button.classList.add('bg-green-600', 'hover:bg-green-700');
        button.classList.remove('bg-purple-600', 'hover:bg-purple-700');
        
        setTimeout(() => {
            button.textContent = originalText;
            button.classList.remove('bg-green-600', 'hover:bg-green-700');
            button.classList.add('bg-purple-600', 'hover:bg-purple-700');
        }, 2000);
    }).catch(function() {
        alert('Failed to copy to clipboard');
    });
}

function viewAnalytics(linkId) {
    // Open analytics modal or navigate to analytics page
    window.open(`/dashboard/wrtr-share-analytics.php?link_id=${linkId}`, '_blank');
}

function disableLink(linkId) {
    if (confirm('Are you sure you want to disable this share link? It will no longer be accessible.')) {
        fetch('/api/wrtr/disable-share.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': '<?= $csrfToken ?>'
            },
            body: JSON.stringify({ link_id: linkId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Failed to disable share link: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while disabling the share link');
        });
    }
}

// Form enhancement
document.addEventListener('DOMContentLoaded', function() {
    const shareTypeRadios = document.querySelectorAll('input[name="share_type"]');
    const downloadCheckbox = document.querySelector('input[name="allow_download"]');
    
    shareTypeRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'download_pdf') {
                downloadCheckbox.checked = true;
            } else if (this.value === 'analytics_only') {
                downloadCheckbox.checked = false;
            }
        });
    });
});
</script>

<?php renderFooter(); ?>