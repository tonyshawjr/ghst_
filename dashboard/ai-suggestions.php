<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/AIContentSuggestions.php';
require_once '../includes/functions.php';
require_once '../includes/layout.php';

$auth = new Auth();
$auth->requireLogin();
requireClient();

$db = Database::getInstance();
$client = $auth->getCurrentClient();
$clientId = $client['id'];
$ai = new AIContentSuggestions($clientId);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'generate':
                if (!$ai->isConfigured()) {
                    throw new Exception("Please configure your AI API key in settings first.");
                }
                
                $suggestions = $ai->generateSuggestions([
                    'provider' => $_POST['provider'] ?? null,
                    'platform' => $_POST['platform'] ?? 'general',
                    'topic' => $_POST['topic'] ?? '',
                    'tone' => $_POST['tone'] ?? 'professional',
                    'length' => $_POST['length'] ?? 'medium',
                    'include_hashtags' => $_POST['include_hashtags'] ?? true,
                    'include_emojis' => $_POST['include_emojis'] ?? false,
                    'model' => $_POST['model'] ?? null
                ]);
                
                echo json_encode([
                    'success' => true,
                    'suggestions' => $suggestions
                ]);
                break;
                
            case 'save_settings':
                $ai->saveApiSettings(
                    $_POST['provider'],
                    $_POST['api_key'],
                    $_POST['model'] ?? null
                );
                
                echo json_encode([
                    'success' => true,
                    'message' => 'AI settings saved successfully'
                ]);
                break;
                
            default:
                throw new Exception("Invalid action");
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

// Load current AI settings
$stmt = $db->prepare("SELECT claude_api_key, claude_model, openai_api_key, openai_model FROM clients WHERE id = ?");
$stmt->execute([$clientId]);
$aiSettings = $stmt->fetch();

// Get configured providers
$configuredProviders = $ai->getConfiguredProviders();

renderHeader('AI Content Suggestions');
?>

<div class="max-w-6xl mx-auto">
    <!-- Header -->
    <div class="bg-gray-900 rounded-lg p-6 mb-6 border border-gray-800">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-purple-400 mb-2">AI Content Suggestions</h1>
                <p class="text-gray-400">Generate engaging content ideas using AI</p>
            </div>
            <button onclick="openSettingsModal()" class="bg-gray-800 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg transition-colors flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                </svg>
                AI Settings
            </button>
        </div>
    </div>

    <?php if (!$ai->isConfigured()): ?>
    <!-- Setup Required -->
    <div class="bg-yellow-900/20 border border-yellow-600 rounded-lg p-6 mb-6">
        <div class="flex items-start">
            <svg class="w-6 h-6 text-yellow-400 mr-3 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            <div>
                <h3 class="text-yellow-400 font-semibold mb-1">AI Configuration Required</h3>
                <p class="text-gray-300 mb-3">To use AI content suggestions, you need to configure your API key.</p>
                <button onclick="openSettingsModal()" class="bg-yellow-600 hover:bg-yellow-700 text-white font-bold py-2 px-4 rounded transition-colors">
                    Configure AI Settings
                </button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Content Generation Form -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Input Section -->
        <div class="bg-gray-900 rounded-lg p-6 border border-gray-800">
            <h2 class="text-xl font-semibold text-purple-400 mb-4">Generate Content</h2>
            
            <form id="generateForm" class="space-y-4">
                <?php if (count($configuredProviders) > 1): ?>
                <!-- Provider Selection -->
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-2">AI Provider</label>
                    <select id="provider" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-purple-500">
                        <?php if (in_array('claude', $configuredProviders)): ?>
                        <option value="claude">Claude (Anthropic)</option>
                        <?php endif; ?>
                        <?php if (in_array('openai', $configuredProviders)): ?>
                        <option value="openai">ChatGPT (OpenAI)</option>
                        <?php endif; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <!-- Platform -->
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-2">Platform</label>
                    <select id="platform" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-purple-500">
                        <option value="general">General</option>
                        <option value="instagram">Instagram</option>
                        <option value="facebook">Facebook</option>
                        <option value="twitter">Twitter/X</option>
                        <option value="linkedin">LinkedIn</option>
                    </select>
                </div>

                <!-- Topic -->
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-2">Topic or Keywords</label>
                    <textarea id="topic" rows="3" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-purple-500" placeholder="What should the post be about?"></textarea>
                </div>

                <!-- Tone -->
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-2">Tone</label>
                    <select id="tone" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-purple-500">
                        <option value="professional">Professional</option>
                        <option value="casual">Casual</option>
                        <option value="friendly">Friendly</option>
                        <option value="humorous">Humorous</option>
                        <option value="inspirational">Inspirational</option>
                        <option value="educational">Educational</option>
                    </select>
                </div>

                <!-- Length -->
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-2">Length</label>
                    <select id="length" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-purple-500">
                        <option value="short">Short</option>
                        <option value="medium">Medium</option>
                        <option value="long">Long</option>
                    </select>
                </div>

                <!-- Options -->
                <div class="space-y-2">
                    <label class="flex items-center">
                        <input type="checkbox" id="include_hashtags" checked class="rounded border-gray-700 text-purple-600 focus:ring-purple-500 mr-2">
                        <span class="text-gray-400">Include hashtags</span>
                    </label>
                    <label class="flex items-center">
                        <input type="checkbox" id="include_emojis" class="rounded border-gray-700 text-purple-600 focus:ring-purple-500 mr-2">
                        <span class="text-gray-400">Include emojis</span>
                    </label>
                </div>

                <!-- Generate Button -->
                <button type="submit" class="w-full bg-purple-600 hover:bg-purple-700 text-white font-bold py-3 px-4 rounded-lg transition-colors flex items-center justify-center" <?php echo !$ai->isConfigured() ? 'disabled' : ''; ?>>
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                    </svg>
                    Generate Suggestions
                </button>
            </form>
        </div>

        <!-- Suggestions Section -->
        <div class="bg-gray-900 rounded-lg p-6 border border-gray-800">
            <h2 class="text-xl font-semibold text-purple-400 mb-4">AI Suggestions</h2>
            
            <div id="suggestionsContainer" class="space-y-4">
                <!-- Placeholder -->
                <div class="text-center py-12 text-gray-500">
                    <svg class="w-16 h-16 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                    </svg>
                    <p>Generate content to see AI suggestions</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- AI Settings Modal -->
<div id="settingsModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden">
    <div class="flex items-center justify-center min-h-screen px-4">
        <div class="bg-gray-900 rounded-lg max-w-2xl w-full p-6 border border-gray-800 max-h-[90vh] overflow-y-auto">
            <h3 class="text-xl font-semibold text-purple-400 mb-4">AI Settings</h3>
            <p class="text-sm text-gray-400 mb-6">Configure API keys for both providers. You can use either or both.</p>
            
            <div class="space-y-6">
                <!-- Claude Settings -->
                <div class="border border-gray-700 rounded-lg p-4">
                    <h4 class="font-medium text-purple-400 mb-4">Claude (Anthropic)</h4>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-2">API Key</label>
                            <input 
                                type="password" 
                                id="claudeApiKey" 
                                value="<?= !empty($aiSettings['claude_api_key']) ? str_repeat('*', 20) : '' ?>"
                                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-purple-500" 
                                placeholder="Your Claude API key"
                            >
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-2">Model</label>
                            <select id="claudeModel" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-purple-500">
                                <option value="claude-3-5-sonnet-20241022" <?= ($aiSettings['claude_model'] ?? '') === 'claude-3-5-sonnet-20241022' ? 'selected' : '' ?>>Claude 3.5 Sonnet</option>
                                <option value="claude-3-5-haiku-20241022" <?= ($aiSettings['claude_model'] ?? '') === 'claude-3-5-haiku-20241022' ? 'selected' : '' ?>>Claude 3.5 Haiku</option>
                                <option value="claude-3-opus-20240229" <?= ($aiSettings['claude_model'] ?? '') === 'claude-3-opus-20240229' ? 'selected' : '' ?>>Claude 3 Opus</option>
                            </select>
                        </div>
                        
                        <p class="text-xs text-gray-500">
                            Get your API key: <a href="https://console.anthropic.com/api-keys" target="_blank" class="text-purple-400 hover:text-purple-300">console.anthropic.com</a>
                        </p>
                    </div>
                </div>
                
                <!-- OpenAI Settings -->
                <div class="border border-gray-700 rounded-lg p-4">
                    <h4 class="font-medium text-green-400 mb-4">ChatGPT (OpenAI)</h4>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-2">API Key</label>
                            <input 
                                type="password" 
                                id="openaiApiKey" 
                                value="<?= !empty($aiSettings['openai_api_key']) ? str_repeat('*', 20) : '' ?>"
                                class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-purple-500" 
                                placeholder="Your OpenAI API key"
                            >
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-400 mb-2">Model</label>
                            <select id="openaiModel" class="w-full bg-gray-800 border border-gray-700 rounded-lg px-4 py-2 text-white focus:outline-none focus:border-purple-500">
                                <option value="gpt-4o" <?= ($aiSettings['openai_model'] ?? '') === 'gpt-4o' ? 'selected' : '' ?>>GPT-4o</option>
                                <option value="gpt-4-turbo" <?= ($aiSettings['openai_model'] ?? '') === 'gpt-4-turbo' ? 'selected' : '' ?>>GPT-4 Turbo</option>
                                <option value="gpt-3.5-turbo" <?= ($aiSettings['openai_model'] ?? '') === 'gpt-3.5-turbo' ? 'selected' : '' ?>>GPT-3.5 Turbo</option>
                            </select>
                        </div>
                        
                        <p class="text-xs text-gray-500">
                            Get your API key: <a href="https://platform.openai.com/api-keys" target="_blank" class="text-green-400 hover:text-green-300">platform.openai.com</a>
                        </p>
                    </div>
                </div>

                <div class="text-xs text-gray-500">
                    <p>Your API keys are encrypted and stored securely. Leave blank to keep existing keys.</p>
                </div>

                <!-- Buttons -->
                <div class="flex space-x-3">
                    <button type="button" onclick="closeSettingsModal()" class="flex-1 bg-gray-800 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded-lg transition-colors">
                        Cancel
                    </button>
                    <button onclick="saveAllSettings()" class="flex-1 bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded-lg transition-colors">
                        Save Settings
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const providers = <?php echo json_encode(AIContentSuggestions::PROVIDERS); ?>;
const configuredProviders = <?php echo json_encode($configuredProviders); ?>;

// Settings Modal
function openSettingsModal() {
    document.getElementById('settingsModal').classList.remove('hidden');
}

function closeSettingsModal() {
    document.getElementById('settingsModal').classList.add('hidden');
}

// Save all settings
async function saveAllSettings() {
    const claudeApiKey = document.getElementById('claudeApiKey').value;
    const claudeModel = document.getElementById('claudeModel').value;
    const openaiApiKey = document.getElementById('openaiApiKey').value;
    const openaiModel = document.getElementById('openaiModel').value;
    
    let hasError = false;
    let messages = [];
    
    // Save Claude settings if provided
    if (claudeApiKey && !claudeApiKey.includes('*')) {
        try {
            const response = await fetch('ai-suggestions.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'save_settings',
                    provider: 'claude',
                    api_key: claudeApiKey,
                    model: claudeModel
                })
            });
            
            const result = await response.json();
            if (result.success) {
                messages.push('Claude settings saved');
            } else {
                hasError = true;
                messages.push('Claude error: ' + result.error);
            }
        } catch (error) {
            hasError = true;
            messages.push('Claude error: ' + error.message);
        }
    }
    
    // Save OpenAI settings if provided
    if (openaiApiKey && !openaiApiKey.includes('*')) {
        try {
            const response = await fetch('ai-suggestions.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    action: 'save_settings',
                    provider: 'openai',
                    api_key: openaiApiKey,
                    model: openaiModel
                })
            });
            
            const result = await response.json();
            if (result.success) {
                messages.push('OpenAI settings saved');
            } else {
                hasError = true;
                messages.push('OpenAI error: ' + result.error);
            }
        } catch (error) {
            hasError = true;
            messages.push('OpenAI error: ' + error.message);
        }
    }
    
    if (messages.length > 0) {
        alert(messages.join('\n'));
        if (!hasError) {
            closeSettingsModal();
            location.reload();
        }
    } else {
        alert('No changes to save');
    }
}

// Generate suggestions
document.getElementById('generateForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const container = document.getElementById('suggestionsContainer');
    container.innerHTML = '<div class="text-center py-12"><div class="animate-spin rounded-full h-12 w-12 border-b-2 border-purple-400 mx-auto"></div><p class="text-gray-400 mt-4">Generating suggestions...</p></div>';
    
    try {
        const response = await fetch('ai-suggestions.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
                action: 'generate',
                provider: document.getElementById('provider')?.value || '<?php echo $configuredProviders[0] ?? ''; ?>',
                platform: document.getElementById('platform').value,
                topic: document.getElementById('topic').value,
                tone: document.getElementById('tone').value,
                length: document.getElementById('length').value,
                include_hashtags: document.getElementById('include_hashtags').checked,
                include_emojis: document.getElementById('include_emojis').checked
            })
        });
        
        const result = await response.json();
        if (result.success) {
            displaySuggestions(result.suggestions);
        } else {
            container.innerHTML = `<div class="bg-red-900/20 border border-red-600 rounded-lg p-4"><p class="text-red-400">${result.error}</p></div>`;
        }
    } catch (error) {
        container.innerHTML = `<div class="bg-red-900/20 border border-red-600 rounded-lg p-4"><p class="text-red-400">Error: ${error.message}</p></div>`;
    }
});

function displaySuggestions(suggestions) {
    const container = document.getElementById('suggestionsContainer');
    container.innerHTML = '';
    
    suggestions.forEach((suggestion, index) => {
        const div = document.createElement('div');
        div.className = 'bg-gray-800 rounded-lg p-4 border border-gray-700';
        div.innerHTML = `
            <div class="flex items-start justify-between mb-2">
                <span class="text-sm font-medium text-purple-400">Suggestion ${index + 1}</span>
                <button onclick="copyToClipboard(${index})" class="text-gray-400 hover:text-white transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                    </svg>
                </button>
            </div>
            <p class="text-gray-300 whitespace-pre-wrap" id="suggestion-${index}">${suggestion}</p>
            <div class="mt-3 flex space-x-2">
                <button onclick="useInScheduler(${index})" class="text-sm bg-purple-600 hover:bg-purple-700 text-white px-3 py-1 rounded transition-colors">
                    Use in Scheduler
                </button>
            </div>
        `;
        container.appendChild(div);
    });
}

function copyToClipboard(index) {
    const text = document.getElementById(`suggestion-${index}`).textContent;
    navigator.clipboard.writeText(text).then(() => {
        alert('Copied to clipboard!');
    });
}

function useInScheduler(index) {
    const text = document.getElementById(`suggestion-${index}`).textContent;
    // Send message to parent window or store in session storage
    if (window.opener) {
        window.opener.postMessage({
            type: 'ai_suggestion',
            content: text
        }, '*');
        window.close();
    } else {
        // Store in session storage and redirect to posts page
        sessionStorage.setItem('ai_suggestion', text);
        window.location.href = 'posts.php';
    }
}

// Show provider selection info
if (configuredProviders.length === 0) {
    document.getElementById('suggestionsContainer').innerHTML = `
        <div class="text-center py-12 text-gray-500">
            <svg class="w-16 h-16 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            <p>No AI providers configured</p>
            <button onclick="openSettingsModal()" class="mt-4 bg-purple-600 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded-lg transition-colors">
                Configure AI Settings
            </button>
        </div>
    `;
}
</script>

<?php renderFooter(); ?>