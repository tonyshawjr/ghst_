<?php
/**
 * Email Management Dashboard
 * Manage email queue, statistics, and delivery tracking
 */

require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
require_once '../includes/Database.php';
require_once '../includes/EmailService.php';

$db = Database::getInstance();
$emailService = EmailService::getInstance();

// Get current client
$clientId = $_SESSION['current_client_id'] ?? null;
$userId = $_SESSION['user_id'] ?? null;

// Handle actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'retry_email':
            $queueId = $_POST['queue_id'] ?? null;
            if ($queueId) {
                try {
                    $stmt = $db->prepare("
                        UPDATE email_queue 
                        SET status = 'pending', retry_count = 0, next_retry_at = NULL, last_error = NULL
                        WHERE id = ?
                    ");
                    if ($stmt->execute([$queueId])) {
                        $message = 'Email queued for retry.';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to retry email.';
                        $messageType = 'error';
                    }
                } catch (Exception $e) {
                    $message = 'Error: ' . $e->getMessage();
                    $messageType = 'error';
                }
            }
            break;
            
        case 'cancel_email':
            $queueId = $_POST['queue_id'] ?? null;
            if ($queueId) {
                try {
                    $stmt = $db->prepare("DELETE FROM email_queue WHERE id = ? AND status IN ('pending', 'failed')");
                    if ($stmt->execute([$queueId]) && $stmt->rowCount() > 0) {
                        $message = 'Email cancelled successfully.';
                        $messageType = 'success';
                    } else {
                        $message = 'Failed to cancel email or email already sent.';
                        $messageType = 'error';
                    }
                } catch (Exception $e) {
                    $message = 'Error: ' . $e->getMessage();
                    $messageType = 'error';
                }
            }
            break;
            
        case 'process_queue':
            try {
                $result = $emailService->processQueue(10); // Process 10 emails
                $message = "Processed {$result['processed']} emails. {$result['failed']} failed.";
                $messageType = $result['processed'] > 0 ? 'success' : 'info';
            } catch (Exception $e) {
                $message = 'Error processing queue: ' . $e->getMessage();
                $messageType = 'error';
            }
            break;
    }
}

// Get email statistics
$stats = [];
try {
    $statsResult = $emailService->getEmailStats(date('Y-m-d', strtotime('-30 days')), date('Y-m-d'));
    $stats = $statsResult ?: [];
} catch (Exception $e) {
    error_log('Failed to get email stats: ' . $e->getMessage());
}

// Get recent email queue items
$queueItems = [];
try {
    $stmt = $db->prepare("
        SELECT eq.*, eal.user_id
        FROM email_queue eq
        LEFT JOIN email_activity_log eal ON eal.id = (
            SELECT eal2.id FROM email_activity_log eal2 
            WHERE JSON_EXTRACT(eal2.details, '$.results[*].queue_id') LIKE CONCAT('%', eq.id, '%')
            ORDER BY eal2.created_at DESC
            LIMIT 1
        )
        WHERE eal.user_id = ? OR eq.id IN (
            SELECT CAST(JSON_UNQUOTE(JSON_EXTRACT(eal3.details, '$.results[*].queue_id')) AS UNSIGNED)
            FROM email_activity_log eal3 
            WHERE eal3.user_id = ?
        )
        ORDER BY eq.created_at DESC 
        LIMIT 50
    ");
    $stmt->execute([$userId, $userId]);
    $queueItems = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Failed to get queue items: ' . $e->getMessage());
}

// Get recent email tracking
$recentEmails = [];
try {
    $stmt = $db->prepare("
        SELECT et.*, eal.user_id
        FROM email_tracking et
        LEFT JOIN email_activity_log eal ON eal.id = (
            SELECT eal2.id FROM email_activity_log eal2 
            WHERE JSON_EXTRACT(eal2.details, '$.results[*].tracking_id') LIKE CONCAT('%', et.tracking_id, '%')
            AND eal2.user_id = ?
            LIMIT 1
        )
        WHERE eal.user_id = ?
        ORDER BY et.created_at DESC 
        LIMIT 20
    ");
    $stmt->execute([$userId, $userId]);
    $recentEmails = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Failed to get recent emails: ' . $e->getMessage());
}

$pageTitle = 'Email Management';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - ghst_</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-pending { background-color: #fef3c7; color: #92400e; }
        .status-sending { background-color: #dbeafe; color: #1e40af; }
        .status-sent { background-color: #dcfce7; color: #166534; }
        .status-delivered { background-color: #dcfce7; color: #166534; }
        .status-opened { background-color: #e0e7ff; color: #3730a3; }
        .status-clicked { background-color: #fce7f3; color: #be185d; }
        .status-failed { background-color: #fee2e2; color: #991b1b; }
        .status-bounced { background-color: #fed7aa; color: #c2410c; }
    </style>
</head>
<body class="bg-gray-50">
    <?php include '../includes/dashboard_nav.php'; ?>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="mb-8">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900"><?= htmlspecialchars($pageTitle) ?></h1>
                    <p class="text-gray-600 mt-2">Monitor email delivery and manage email queue</p>
                </div>
                <div class="flex gap-3">
                    <a href="/dashboard/settings.php#email" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                        <i class="fas fa-cog mr-2"></i>Email Settings
                    </a>
                    <button onclick="processQueue()" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-sync mr-2"></i>Process Queue
                    </button>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-lg <?= $messageType === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : ($messageType === 'warning' ? 'bg-yellow-100 text-yellow-700 border border-yellow-200' : 'bg-red-100 text-red-700 border border-red-200') ?>">
                <div class="flex items-center">
                    <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : ($messageType === 'warning' ? 'fa-exclamation-triangle' : 'fa-exclamation-circle') ?> mr-2"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Email Statistics -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Sent</p>
                        <p class="text-2xl font-bold text-gray-900"><?= number_format($stats['total_sent'] ?? 0) ?></p>
                    </div>
                    <div class="p-3 bg-blue-100 rounded-full">
                        <i class="fas fa-paper-plane text-blue-600"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Delivery Rate</p>
                        <p class="text-2xl font-bold text-green-600"><?= round($stats['delivery_rate'] ?? 0, 1) ?>%</p>
                    </div>
                    <div class="p-3 bg-green-100 rounded-full">
                        <i class="fas fa-check-circle text-green-600"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Open Rate</p>
                        <p class="text-2xl font-bold text-purple-600"><?= round($stats['open_rate'] ?? 0, 1) ?>%</p>
                    </div>
                    <div class="p-3 bg-purple-100 rounded-full">
                        <i class="fas fa-envelope-open text-purple-600"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Click Rate</p>
                        <p class="text-2xl font-bold text-indigo-600"><?= round($stats['click_rate'] ?? 0, 1) ?>%</p>
                    </div>
                    <div class="p-3 bg-indigo-100 rounded-full">
                        <i class="fas fa-mouse-pointer text-indigo-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="border-b border-gray-200">
                <nav class="flex space-x-8 px-6">
                    <button onclick="showTab('queue')" id="tab-queue" class="py-4 px-1 border-b-2 border-blue-500 font-medium text-sm text-blue-600">
                        Email Queue
                    </button>
                    <button onclick="showTab('tracking')" id="tab-tracking" class="py-4 px-1 border-b-2 border-transparent font-medium text-sm text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        Email Tracking
                    </button>
                </nav>
            </div>

            <!-- Email Queue Tab -->
            <div id="content-queue" class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">Email Queue</h3>
                    <span class="text-sm text-gray-500"><?= count($queueItems) ?> items</span>
                </div>

                <?php if (empty($queueItems)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-inbox text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">No emails in queue</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($queueItems as $item): ?>
                            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-3 mb-2">
                                            <span class="status-badge status-<?= strtolower($item['status']) ?>">
                                                <?= ucfirst($item['status']) ?>
                                            </span>
                                            <span class="text-sm text-gray-600">
                                                <?= htmlspecialchars($item['recipient_email']) ?>
                                            </span>
                                        </div>
                                        <h4 class="font-medium text-gray-900"><?= htmlspecialchars($item['subject']) ?></h4>
                                        <div class="flex items-center space-x-4 mt-2 text-sm text-gray-500">
                                            <span><i class="fas fa-clock mr-1"></i><?= date('M j, Y g:i A', strtotime($item['created_at'])) ?></span>
                                            <?php if ($item['retry_count'] > 0): ?>
                                                <span><i class="fas fa-redo mr-1"></i>Retries: <?= $item['retry_count'] ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($item['last_error'])): ?>
                                            <div class="mt-2 p-2 bg-red-50 rounded text-sm text-red-700">
                                                <i class="fas fa-exclamation-triangle mr-1"></i>
                                                <?= htmlspecialchars($item['last_error']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="flex items-center space-x-2 ml-4">
                                        <?php if (in_array($item['status'], ['pending', 'failed'])): ?>
                                            <button onclick="retryEmail(<?= $item['id'] ?>)" 
                                                    class="px-3 py-1 bg-blue-600 text-white rounded text-sm hover:bg-blue-700 transition-colors">
                                                <i class="fas fa-redo mr-1"></i>Retry
                                            </button>
                                            <button onclick="cancelEmail(<?= $item['id'] ?>)" 
                                                    class="px-3 py-1 bg-red-600 text-white rounded text-sm hover:bg-red-700 transition-colors">
                                                <i class="fas fa-times mr-1"></i>Cancel
                                            </button>
                                        <?php endif; ?>
                                        <button onclick="viewEmailDetails('<?= $item['tracking_id'] ?>')" 
                                                class="px-3 py-1 bg-gray-600 text-white rounded text-sm hover:bg-gray-700 transition-colors">
                                            <i class="fas fa-eye mr-1"></i>Details
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Email Tracking Tab -->
            <div id="content-tracking" class="p-6 hidden">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-semibold text-gray-900">Recent Email Activity</h3>
                    <span class="text-sm text-gray-500"><?= count($recentEmails) ?> emails</span>
                </div>

                <?php if (empty($recentEmails)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-chart-line text-4xl text-gray-300 mb-4"></i>
                        <p class="text-gray-500">No email tracking data available</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($recentEmails as $email): ?>
                            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-3 mb-2">
                                            <span class="status-badge status-<?= strtolower($email['status']) ?>">
                                                <?= ucfirst($email['status']) ?>
                                            </span>
                                            <span class="text-sm text-gray-600">
                                                <?= htmlspecialchars($email['recipient_email']) ?>
                                            </span>
                                        </div>
                                        <h4 class="font-medium text-gray-900"><?= htmlspecialchars($email['subject']) ?></h4>
                                        <div class="flex items-center space-x-4 mt-2 text-sm text-gray-500">
                                            <span><i class="fas fa-paper-plane mr-1"></i><?= date('M j, Y g:i A', strtotime($email['created_at'])) ?></span>
                                            <?php if ($email['opened_at']): ?>
                                                <span class="text-green-600"><i class="fas fa-envelope-open mr-1"></i>Opened <?= date('M j, g:i A', strtotime($email['opened_at'])) ?></span>
                                            <?php endif; ?>
                                            <?php if ($email['clicked_at']): ?>
                                                <span class="text-purple-600"><i class="fas fa-mouse-pointer mr-1"></i>Clicked <?= date('M j, g:i A', strtotime($email['clicked_at'])) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex items-center space-x-4 mt-1 text-sm text-gray-500">
                                            <span>Opens: <?= $email['open_count'] ?></span>
                                            <span>Clicks: <?= $email['click_count'] ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center space-x-2 ml-4">
                                        <button onclick="viewEmailDetails('<?= $email['tracking_id'] ?>')" 
                                                class="px-3 py-1 bg-gray-600 text-white rounded text-sm hover:bg-gray-700 transition-colors">
                                            <i class="fas fa-chart-line mr-1"></i>Analytics
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        function showTab(tabName) {
            // Hide all content divs
            document.querySelectorAll('[id^="content-"]').forEach(div => div.classList.add('hidden'));
            
            // Remove active class from all tabs
            document.querySelectorAll('[id^="tab-"]').forEach(tab => {
                tab.classList.remove('border-blue-500', 'text-blue-600');
                tab.classList.add('border-transparent', 'text-gray-500');
            });
            
            // Show selected content
            document.getElementById('content-' + tabName).classList.remove('hidden');
            
            // Add active class to selected tab
            const activeTab = document.getElementById('tab-' + tabName);
            activeTab.classList.remove('border-transparent', 'text-gray-500');
            activeTab.classList.add('border-blue-500', 'text-blue-600');
        }

        function processQueue() {
            if (confirm('Process pending emails in the queue now?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="process_queue">';
                document.body.appendChild(form);
                form.submit();
            }
        }

        function retryEmail(queueId) {
            if (confirm('Retry sending this email?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="retry_email">
                    <input type="hidden" name="queue_id" value="${queueId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function cancelEmail(queueId) {
            if (confirm('Cancel this email? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="cancel_email">
                    <input type="hidden" name="queue_id" value="${queueId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function viewEmailDetails(trackingId) {
            // Open email analytics in a new window/modal
            const url = '/api/email/tracking.php?stats=1&tracking_id=' + encodeURIComponent(trackingId);
            window.open(url, '_blank', 'width=800,height=600');
        }

        // Auto-refresh every 30 seconds
        setInterval(() => {
            if (document.getElementById('content-queue').classList.contains('hidden') === false) {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>