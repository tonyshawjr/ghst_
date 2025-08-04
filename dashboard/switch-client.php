<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/functions.php';
require_once '../includes/layout.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$currentClient = $auth->getCurrentClient();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $action = $_POST['action'] ?? 'switch';
        
        if ($action === 'switch') {
            // Handle client switch
            $clientId = $_POST['client_id'] ?? '';
            
            if (empty($clientId)) {
                $error = 'Please select a client.';
            } else {
                if ($auth->setCurrentClient($clientId)) {
                    redirect('/dashboard/');
                } else {
                    $error = 'Failed to switch client. Please try again.';
                }
            }
        } elseif ($action === 'create') {
            // Handle new client creation
            $clientName = trim($_POST['client_name'] ?? '');
            $timezone = $_POST['timezone'] ?? 'UTC';
            $notes = trim($_POST['notes'] ?? '');
            
            if (empty($clientName)) {
                $error = 'Client name is required.';
            } else {
                $stmt = $db->prepare("INSERT INTO clients (name, timezone, notes) VALUES (?, ?, ?)");
                if ($stmt->execute([$clientName, $timezone, $notes])) {
                    $newClientId = $db->lastInsertId();
                    if ($auth->setCurrentClient($newClientId)) {
                        redirect('/dashboard/');
                    } else {
                        $error = 'Client created but failed to switch. Please try again.';
                    }
                } else {
                    $error = 'Failed to create client. Please try again.';
                }
            }
        } elseif ($action === 'delete') {
            // Handle client deletion
            $clientId = $_POST['delete_client_id'] ?? '';
            
            if (empty($clientId)) {
                $error = 'Invalid client selected for deletion.';
            } else {
                // Check if this is the current client
                if ($currentClient && $currentClient['id'] == $clientId) {
                    $error = 'Cannot delete the currently active client. Please switch to another client first.';
                } else {
                    // Check if client has data
                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM posts WHERE client_id = ?");
                    $stmt->execute([$clientId]);
                    $postCount = $stmt->fetch()['count'];
                    
                    $stmt = $db->prepare("SELECT COUNT(*) as count FROM media WHERE client_id = ?");
                    $stmt->execute([$clientId]);
                    $mediaCount = $stmt->fetch()['count'];
                    
                    if ($postCount > 0 || $mediaCount > 0) {
                        // Soft delete - just mark as inactive
                        $stmt = $db->prepare("UPDATE clients SET is_active = 0 WHERE id = ?");
                        if ($stmt->execute([$clientId])) {
                            $success = 'Client archived successfully.';
                        } else {
                            $error = 'Failed to archive client. Please try again.';
                        }
                    } else {
                        // Hard delete if no data
                        $stmt = $db->prepare("DELETE FROM clients WHERE id = ?");
                        if ($stmt->execute([$clientId])) {
                            $success = 'Client deleted successfully.';
                        } else {
                            $error = 'Failed to delete client. Please try again.';
                        }
                    }
                }
            }
        }
    }
}

// Get all clients
$stmt = $db->prepare("SELECT * FROM clients WHERE is_active = 1 ORDER BY name");
$stmt->execute();
$clients = $stmt->fetchAll();

$csrfToken = $auth->generateCSRFToken();

// Don't show sidebar for this page
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Select Client - ghst_</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'JetBrains Mono', monospace; }
    </style>
</head>
<body class="h-full bg-black text-white">
    <div class="min-h-full flex items-center justify-center p-4">
        <div class="w-full max-w-2xl">
            <div class="text-center mb-8">
                <h1 class="text-4xl font-bold mb-2">
                    <span class="text-purple-500">*</span> ghst_
                </h1>
                <p class="text-gray-400">Select a client to manage</p>
            </div>
            
            <?php if (isset($error)): ?>
                <div class="bg-red-900/20 border border-red-500 text-red-400 px-4 py-3 rounded mb-6">
                    <?= sanitize($error) ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($success)): ?>
                <div class="bg-green-900/20 border border-green-500 text-green-400 px-4 py-3 rounded mb-6">
                    <?= sanitize($success) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="clientForm">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="switch" id="formAction">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <?php foreach ($clients as $client): ?>
                    <label class="relative cursor-pointer">
                        <input 
                            type="radio" 
                            name="client_id" 
                            value="<?= $client['id'] ?>"
                            class="sr-only peer"
                            <?= $currentClient && $currentClient['id'] == $client['id'] ? 'checked' : '' ?>
                        >
                        <div class="bg-gray-900 border-2 border-gray-800 rounded-lg p-6 transition-all peer-checked:border-purple-500 peer-checked:bg-purple-900/20 hover:border-gray-700">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h3 class="text-lg font-semibold"><?= sanitize($client['name']) ?></h3>
                                    <?php if ($client['notes']): ?>
                                        <p class="text-sm text-gray-400 mt-1"><?= sanitize($client['notes']) ?></p>
                                    <?php endif; ?>
                                    <p class="text-xs text-gray-500 mt-2">Timezone: <?= sanitize($client['timezone']) ?></p>
                                </div>
                                <div class="flex items-start space-x-2 ml-4">
                                    <?php if ($currentClient && $currentClient['id'] == $client['id']): ?>
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-purple-900 text-purple-300">
                                            Current
                                        </span>
                                    <?php else: ?>
                                        <button 
                                            type="button"
                                            onclick="confirmDelete(<?= $client['id'] ?>, '<?= addslashes($client['name']) ?>')"
                                            class="p-1 text-red-400 hover:text-red-300 hover:bg-red-900/20 rounded transition-colors"
                                            title="Delete client"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </label>
                    <?php endforeach; ?>
                    
                    <!-- Add New Client Option -->
                    <div 
                        onclick="showAddClientForm()"
                        class="bg-gray-900 border-2 border-dashed border-gray-800 rounded-lg p-6 flex items-center justify-center cursor-pointer hover:border-purple-500 transition-colors"
                    >
                        <div class="text-center">
                            <svg class="mx-auto h-12 w-12 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            <p class="mt-2 text-sm text-gray-500">Add New Client</p>
                            <p class="text-xs text-gray-600 mt-1">Click to create</p>
                        </div>
                    </div>
                </div>
                
                <div class="mt-8 flex justify-center space-x-4">
                    <button 
                        type="submit"
                        class="px-6 py-3 bg-purple-600 hover:bg-purple-700 rounded-lg font-medium transition-colors"
                    >
                        Continue to Dashboard
                    </button>
                    <?php if ($currentClient): ?>
                        <a 
                            href="/dashboard/"
                            class="px-6 py-3 bg-gray-800 hover:bg-gray-700 rounded-lg font-medium transition-colors"
                        >
                            Cancel
                        </a>
                    <?php endif; ?>
                </div>
            </form>
            
            <!-- Add Client Modal -->
            <div id="addClientModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
                <div class="bg-gray-900 rounded-lg max-w-md w-full p-6 border border-gray-800">
                    <h3 class="text-xl font-semibold mb-4">Add New Client</h3>
                    
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium mb-2">Client Name</label>
                                <input 
                                    type="text" 
                                    name="client_name" 
                                    required
                                    placeholder="e.g., ABC Company"
                                    class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500"
                                >
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium mb-2">Timezone</label>
                                <select 
                                    name="timezone"
                                    class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500"
                                >
                                    <option value="America/New_York">Eastern Time (New York)</option>
                                    <option value="America/Chicago">Central Time (Chicago)</option>
                                    <option value="America/Denver">Mountain Time (Denver)</option>
                                    <option value="America/Los_Angeles">Pacific Time (Los Angeles)</option>
                                    <option value="Europe/London">UK (London)</option>
                                    <option value="Europe/Paris">Central Europe (Paris)</option>
                                    <option value="Asia/Tokyo">Japan (Tokyo)</option>
                                    <option value="Australia/Sydney">Australia (Sydney)</option>
                                    <option value="UTC">UTC</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium mb-2">Notes (optional)</label>
                                <textarea 
                                    name="notes" 
                                    rows="3"
                                    placeholder="Any additional information..."
                                    class="w-full px-4 py-3 bg-black border border-gray-700 rounded-lg focus:outline-none focus:border-purple-500"
                                ></textarea>
                            </div>
                        </div>
                        
                        <div class="mt-6 flex justify-end space-x-3">
                            <button 
                                type="button"
                                onclick="hideAddClientForm()"
                                class="px-4 py-2 bg-gray-800 hover:bg-gray-700 rounded-lg transition-colors"
                            >
                                Cancel
                            </button>
                            <button 
                                type="submit"
                                class="px-4 py-2 bg-purple-600 hover:bg-purple-700 rounded-lg font-medium transition-colors"
                            >
                                Create Client
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Delete Confirmation Modal -->
            <div id="deleteModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
                <div class="bg-gray-900 rounded-lg max-w-md w-full p-6 border border-gray-800">
                    <h3 class="text-xl font-semibold mb-4">Delete Client?</h3>
                    
                    <p class="text-gray-400 mb-6">
                        Are you sure you want to delete <span id="deleteClientName" class="font-semibold text-white"></span>?
                    </p>
                    
                    <div id="deleteWarning" class="hidden bg-yellow-900/20 border border-yellow-500 text-yellow-400 px-4 py-3 rounded mb-6">
                        <p class="font-medium mb-1">⚠️ This client has data</p>
                        <p class="text-sm">The client will be archived instead of deleted to preserve associated posts and media.</p>
                    </div>
                    
                    <form method="POST" id="deleteForm">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="delete_client_id" id="deleteClientId">
                        
                        <div class="flex justify-end space-x-3">
                            <button 
                                type="button"
                                onclick="hideDeleteModal()"
                                class="px-4 py-2 bg-gray-800 hover:bg-gray-700 rounded-lg transition-colors"
                            >
                                Cancel
                            </button>
                            <button 
                                type="submit"
                                class="px-4 py-2 bg-red-600 hover:bg-red-700 rounded-lg font-medium transition-colors"
                            >
                                Delete Client
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <script>
                let deleteClientData = {};
                
                function showAddClientForm() {
                    document.getElementById('addClientModal').classList.remove('hidden');
                }
                
                function hideAddClientForm() {
                    document.getElementById('addClientModal').classList.add('hidden');
                }
                
                function confirmDelete(clientId, clientName) {
                    document.getElementById('deleteClientId').value = clientId;
                    document.getElementById('deleteClientName').textContent = clientName;
                    
                    // Check if client has data via AJAX (for now, we'll just show the modal)
                    // In a real implementation, you'd check if the client has posts/media
                    document.getElementById('deleteModal').classList.remove('hidden');
                }
                
                function hideDeleteModal() {
                    document.getElementById('deleteModal').classList.add('hidden');
                }
                
                // Close modals on escape key
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        hideAddClientForm();
                        hideDeleteModal();
                    }
                });
                
                // Close modal on backdrop click
                document.getElementById('addClientModal').addEventListener('click', function(e) {
                    if (e.target === this) {
                        hideAddClientForm();
                    }
                });
                
                document.getElementById('deleteModal').addEventListener('click', function(e) {
                    if (e.target === this) {
                        hideDeleteModal();
                    }
                });
            </script>
        </div>
    </div>
</body>
</html>