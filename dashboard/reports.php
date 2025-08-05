<?php
/**
 * Reports Management Page
 * Generate, manage, and download social media reports
 */

require_once '../includes/auth_check.php';
require_once '../includes/functions.php';
require_once '../includes/Database.php';
require_once '../includes/ReportGenerator.php';
require_once '../includes/BrandingHelper.php';
require_once '../includes/EmailService.php';

$db = Database::getInstance();
$reportGenerator = new ReportGenerator();
$brandingHelper = BrandingHelper::getInstance();

// Get current client
$clientId = $_SESSION['current_client_id'] ?? null;
if (!$clientId) {
    header('Location: /dashboard/switch-client.php');
    exit;
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'generate_report':
                $options = [
                    'report_type' => $_POST['report_type'] ?? 'detailed_analytics',
                    'date_from' => $_POST['date_from'] ?? date('Y-m-01', strtotime('-1 month')),
                    'date_to' => $_POST['date_to'] ?? date('Y-m-t', strtotime('-1 month')),
                    'template_id' => $_POST['template_id'] ?? null,
                    'report_name' => $_POST['report_name'] ?? null,
                    'format' => $_POST['format'] ?? 'html',
                    'include_branding' => isset($_POST['include_branding'])
                ];
                
                $result = $reportGenerator->generateReport($clientId, $options);
                
                if ($result['success']) {
                    $message = 'Report generated successfully: ' . htmlspecialchars($result['report_name']);
                    $messageType = 'success';
                } else {
                    $message = 'Error generating report: ' . htmlspecialchars($result['error']);
                    $messageType = 'error';
                }
                break;
                
            case 'delete_report':
                $reportId = $_POST['report_id'] ?? null;
                if ($reportId && $reportGenerator->deleteReport($reportId)) {
                    $message = 'Report deleted successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Error deleting report.';
                    $messageType = 'error';
                }
                break;
                
            case 'create_share_link':
                $reportId = $_POST['report_id'] ?? null;
                $shareOptions = [
                    'password' => $_POST['share_password'] ?? null,
                    'allowed_downloads' => $_POST['allowed_downloads'] ?? null,
                    'expires_at' => $_POST['expires_at'] ?? null
                ];
                
                if ($reportId && $reportGenerator->createShareableLink($reportId, $shareOptions)) {
                    $message = 'Shareable link created successfully.';
                    $messageType = 'success';
                } else {
                    $message = 'Error creating shareable link.';
                    $messageType = 'error';
                }
                break;
                
            case 'send_email':
                $reportId = $_POST['report_id'] ?? null;
                $recipients = $_POST['recipients'] ?? [];
                $customMessage = $_POST['custom_message'] ?? '';
                $attachPdf = isset($_POST['attach_pdf']);
                
                // Parse recipients if it's a string
                if (is_string($recipients)) {
                    $recipients = array_filter(array_map('trim', explode(',', $recipients)));
                }
                
                if ($reportId && !empty($recipients)) {
                    try {
                        $emailService = EmailService::getInstance();
                        $results = $emailService->sendReport($reportId, $recipients, $customMessage, $attachPdf);
                        
                        $successCount = count(array_filter($results, function($r) { return $r['success']; }));
                        $totalCount = count($results);
                        
                        if ($successCount === $totalCount) {
                            $message = "Report sent successfully to {$successCount} recipient(s).";
                            $messageType = 'success';
                        } elseif ($successCount > 0) {
                            $failCount = $totalCount - $successCount;
                            $message = "Report sent to {$successCount} recipient(s). {$failCount} failed to send.";
                            $messageType = 'warning';
                        } else {
                            $message = 'Failed to send report to any recipients.';
                            $messageType = 'error';
                        }
                    } catch (Exception $e) {
                        $message = 'Error sending report: ' . $e->getMessage();
                        $messageType = 'error';
                    }
                } else {
                    $message = 'Report ID and at least one recipient are required.';
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Get filters
$filters = [
    'status' => $_GET['status'] ?? '',
    'report_type' => $_GET['report_type'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
];

// Get reports list
$reports = $reportGenerator->getReportsList($clientId, $filters);

// Get available templates
$templates = $reportGenerator->getReportTemplates($clientId);

// Get report types
$reportTypes = $reportGenerator->getReportTypes();

// Get client info for branding
$clientInfo = getCurrentClient();
$branding = $brandingHelper->getBranding($clientId);

$pageTitle = 'Reports & Analytics';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - ghst_</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?= $brandingHelper->getBrandingStyles($clientId) ?>
    <style>
        .report-card {
            transition: all 0.3s ease;
        }
        .report-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-completed {
            background-color: #dcfce7;
            color: #166534;
        }
        .status-generating {
            background-color: #fef3c7;
            color: #92400e;
        }
        .status-failed {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .modal {
            display: none;
        }
        .modal.active {
            display: flex;
        }
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
                    <p class="text-gray-600 mt-2">Generate professional branded reports for your clients</p>
                </div>
                <button onclick="openModal('generateModal')" class="btn-brand-primary px-6 py-3 rounded-lg font-semibold text-white shadow-lg hover:shadow-xl transition-all duration-200">
                    <i class="fas fa-plus mr-2"></i>Generate Report
                </button>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
            <div class="mb-6 p-4 rounded-lg <?= $messageType === 'success' ? 'bg-green-100 text-green-700 border border-green-200' : 'bg-red-100 text-red-700 border border-red-200' ?>">
                <div class="flex items-center">
                    <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?> mr-2"></i>
                    <?= $message ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Quick Stats -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Reports</p>
                        <p class="text-2xl font-bold text-gray-900"><?= count($reports) ?></p>
                    </div>
                    <div class="p-3 bg-blue-100 rounded-full">
                        <i class="fas fa-chart-bar text-blue-600"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">This Month</p>
                        <p class="text-2xl font-bold text-gray-900">
                            <?= count(array_filter($reports, function($r) { return date('Y-m', strtotime($r['created_at'])) === date('Y-m'); })) ?>
                        </p>
                    </div>
                    <div class="p-3 bg-green-100 rounded-full">
                        <i class="fas fa-calendar text-green-600"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Completed</p>
                        <p class="text-2xl font-bold text-gray-900">
                            <?= count(array_filter($reports, function($r) { return $r['status'] === 'completed'; })) ?>
                        </p>
                    </div>
                    <div class="p-3 bg-purple-100 rounded-full">
                        <i class="fas fa-check-circle text-purple-600"></i>
                    </div>
                </div>
            </div>
            
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">Total Downloads</p>
                        <p class="text-2xl font-bold text-gray-900">
                            <?= array_sum(array_column($reports, 'download_count')) ?>
                        </p>
                    </div>
                    <div class="p-3 bg-indigo-100 rounded-full">
                        <i class="fas fa-download text-indigo-600"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 mb-8">
            <form method="GET" class="flex flex-wrap gap-4 items-end">
                <div class="flex-1 min-w-48">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <option value="">All Statuses</option>
                        <option value="completed" <?= $filters['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="generating" <?= $filters['status'] === 'generating' ? 'selected' : '' ?>>Generating</option>
                        <option value="failed" <?= $filters['status'] === 'failed' ? 'selected' : '' ?>>Failed</option>
                    </select>
                </div>
                
                <div class="flex-1 min-w-48">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Report Type</label>
                    <select name="report_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                        <option value="">All Types</option>
                        <?php foreach ($reportTypes as $key => $name): ?>
                            <option value="<?= htmlspecialchars($key) ?>" <?= $filters['report_type'] === $key ? 'selected' : '' ?>>
                                <?= htmlspecialchars($name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex-1 min-w-48">
                    <label class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from']) ?>" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                </div>
                
                <div class="flex gap-2">
                    <button type="submit" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                        <i class="fas fa-filter mr-2"></i>Filter
                    </button>
                    <a href="/dashboard/reports.php" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors">
                        <i class="fas fa-times mr-2"></i>Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Reports List -->
        <div class="grid grid-cols-1 gap-6">
            <?php if (empty($reports)): ?>
                <div class="bg-white p-12 rounded-xl shadow-sm border border-gray-200 text-center">
                    <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-chart-line text-2xl text-gray-400"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">No reports yet</h3>
                    <p class="text-gray-600 mb-6">Generate your first report to see analytics insights for your clients.</p>
                    <button onclick="openModal('generateModal')" class="btn-brand-primary px-6 py-3 rounded-lg font-semibold text-white">
                        <i class="fas fa-plus mr-2"></i>Generate First Report
                    </button>
                </div>
            <?php else: ?>
                <?php foreach ($reports as $report): ?>
                    <div class="report-card bg-white p-6 rounded-xl shadow-sm border border-gray-200">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-2">
                                    <h3 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($report['report_name']) ?></h3>
                                    <span class="status-badge status-<?= htmlspecialchars($report['status']) ?>">
                                        <?= htmlspecialchars($report['status']) ?>
                                    </span>
                                </div>
                                
                                <div class="flex items-center gap-6 text-sm text-gray-600 mb-4">
                                    <span><i class="fas fa-chart-bar mr-1"></i><?= htmlspecialchars($reportTypes[$report['report_type']] ?? $report['report_type']) ?></span>
                                    <span><i class="fas fa-calendar mr-1"></i><?= date('M j, Y', strtotime($report['date_from'])) ?> - <?= date('M j, Y', strtotime($report['date_to'])) ?></span>
                                    <span><i class="fas fa-clock mr-1"></i>Generated <?= date('M j, Y g:i A', strtotime($report['created_at'])) ?></span>
                                    <?php if ($report['download_count'] > 0): ?>
                                        <span><i class="fas fa-download mr-1"></i><?= $report['download_count'] ?> downloads</span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($report['generated_by_name']): ?>
                                    <p class="text-sm text-gray-500">Generated by <?= htmlspecialchars($report['generated_by_name']) ?></p>
                                <?php endif; ?>
                                
                                <?php if ($report['status'] === 'failed' && $report['error_message']): ?>
                                    <div class="mt-3 p-3 bg-red-50 border border-red-200 rounded-lg">
                                        <p class="text-sm text-red-700">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>
                                            <?= htmlspecialchars($report['error_message']) ?>
                                        </p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="flex items-center gap-2 ml-4">
                                <?php if ($report['status'] === 'completed' && $report['file_path']): ?>
                                    <a href="/api/reports/download.php?id=<?= $report['id'] ?>" 
                                       class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                                        <i class="fas fa-download mr-1"></i>Download
                                    </a>
                                    <button onclick="previewReport(<?= $report['id'] ?>)" 
                                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                        <i class="fas fa-eye mr-1"></i>Preview
                                    </button>
                                    <button onclick="shareReport(<?= $report['id'] ?>)" 
                                            class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                                        <i class="fas fa-share mr-1"></i>Share
                                    </button>
                                    <button onclick="sendEmailReport(<?= $report['id'] ?>)" 
                                            class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                                        <i class="fas fa-envelope mr-1"></i>Email
                                    </button>
                                <?php endif; ?>
                                
                                <div class="relative group">
                                    <button class="px-3 py-2 text-gray-400 hover:text-gray-600 transition-colors">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-200 z-10">
                                        <div class="py-1">
                                            <?php if ($report['status'] === 'completed'): ?>
                                                <a href="/api/reports/preview.php?id=<?= $report['id'] ?>" target="_blank" 
                                                   class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                                    <i class="fas fa-external-link-alt mr-2"></i>Open in New Tab
                                                </a>
                                            <?php endif; ?>
                                            <button onclick="deleteReport(<?= $report['id'] ?>)" 
                                                    class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                                <i class="fas fa-trash mr-2"></i>Delete Report
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Generate Report Modal -->
    <div id="generateModal" class="modal fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50">
        <div class="bg-white rounded-xl p-8 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-gray-900">Generate New Report</h2>
                <button onclick="closeModal('generateModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form method="POST" class="space-y-6">
                <input type="hidden" name="action" value="generate_report">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Report Type</label>
                        <select name="report_type" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                            <?php foreach ($reportTypes as $key => $name): ?>
                                <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Template (Optional)</label>
                        <select name="template_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                            <option value="">Default Template</option>
                            <?php foreach ($templates as $template): ?>
                                <option value="<?= $template['id'] ?>"><?= htmlspecialchars($template['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">From Date</label>
                        <input type="date" name="date_from" value="<?= date('Y-m-01', strtotime('-1 month')) ?>" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">To Date</label>
                        <input type="date" name="date_to" value="<?= date('Y-m-t', strtotime('-1 month')) ?>" required 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Report Name (Optional)</label>
                    <input type="text" name="report_name" placeholder="Auto-generated if empty" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Format</label>
                        <select name="format" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                            <option value="html">HTML</option>
                            <option value="pdf">PDF (Coming Soon)</option>
                        </select>
                    </div>
                    
                    <div class="flex items-center mt-6">
                        <input type="checkbox" name="include_branding" id="include_branding" checked 
                               class="h-4 w-4 text-purple-600 focus:ring-purple-500 border-gray-300 rounded">
                        <label for="include_branding" class="ml-2 text-sm text-gray-700">Include client branding</label>
                    </div>
                </div>
                
                <div class="flex justify-end gap-4 pt-6 border-t border-gray-200">
                    <button type="button" onclick="closeModal('generateModal')" 
                            class="px-6 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="btn-brand-primary px-6 py-2 rounded-lg font-semibold text-white">
                        <i class="fas fa-chart-line mr-2"></i>Generate Report
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Send Email Modal -->
    <div id="emailModal" class="modal fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full m-4 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center p-6 border-b border-gray-200">
                <h3 class="text-xl font-semibold text-gray-900">Send Report via Email</h3>
                <button onclick="closeModal('emailModal')" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form method="POST" class="p-6 space-y-4" id="emailReportForm">
                <input type="hidden" name="action" value="send_email">
                <input type="hidden" name="report_id" id="emailReportId">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Recipients</label>
                    <textarea name="recipients" 
                              id="emailRecipients"
                              rows="3" 
                              placeholder="Enter email addresses separated by commas&#10;example@email.com, client@company.com"
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                              required></textarea>
                    <p class="text-xs text-gray-500 mt-1">Separate multiple email addresses with commas</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Personal Message (Optional)</label>
                    <textarea name="custom_message" 
                              rows="4" 
                              placeholder="Add a personal message to include with the report..."
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"></textarea>
                </div>
                
                <div class="flex items-center space-x-2">
                    <input type="checkbox" name="attach_pdf" id="attachPdf" checked 
                           class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                    <label for="attachPdf" class="text-sm text-gray-700">Attach PDF version</label>
                </div>
                
                <div class="bg-blue-50 p-4 rounded-lg">
                    <div class="flex items-start space-x-2">
                        <i class="fas fa-info-circle text-blue-600 mt-0.5"></i>
                        <div class="text-sm text-blue-800">
                            <p class="font-medium">What will be sent:</p>
                            <ul class="list-disc list-inside mt-1 space-y-1">
                                <li>Professional branded email with report highlights</li>
                                <li>PDF attachment (if selected)</li>
                                <li>Link to view full interactive report</li>
                                <li>Your custom message (if provided)</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-end gap-4 pt-4 border-t border-gray-200">
                    <button type="button" onclick="closeModal('emailModal')" 
                            class="px-6 py-2 text-gray-700 bg-gray-200 rounded-lg hover:bg-gray-300 transition-colors">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors flex items-center">
                        <i class="fas fa-paper-plane mr-2"></i>Send Email
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }
        
        function previewReport(reportId) {
            window.open('/api/reports/preview.php?id=' + reportId, '_blank');
        }
        
        function shareReport(reportId) {
            // Implementation for share functionality
            alert('Share functionality will be implemented');
        }
        
        function sendEmailReport(reportId) {
            // Set the report ID in the modal
            document.getElementById('emailReportId').value = reportId;
            
            // Clear previous values
            document.getElementById('emailRecipients').value = '';
            document.querySelector('textarea[name="custom_message"]').value = '';
            document.getElementById('attachPdf').checked = true;
            
            // Get client email from branding data as a default suggestion
            <?php if (!empty($branding['email'])): ?>
            const defaultEmail = '<?= addslashes($branding['email']) ?>';
            document.getElementById('emailRecipients').placeholder = 
                'Enter email addresses separated by commas\n' + defaultEmail + ', client@company.com';
            <?php endif; ?>
            
            // Open the modal
            openModal('emailModal');
        }
        
        function deleteReport(reportId) {
            if (confirm('Are you sure you want to delete this report? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_report">
                    <input type="hidden" name="report_id" value="${reportId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Auto-refresh page every 30 seconds if there are generating reports
        <?php if (count(array_filter($reports, function($r) { return $r['status'] === 'generating'; })) > 0): ?>
            setTimeout(() => {
                location.reload();
            }, 30000);
        <?php endif; ?>
    </script>
</body>
</html>