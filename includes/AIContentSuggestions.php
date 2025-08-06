<?php
/**
 * AI Content Suggestions
 * 
 * Provides AI-powered content recommendations using user's own API keys
 * Supports Claude (Anthropic) and ChatGPT (OpenAI)
 */

require_once __DIR__ . '/Database.php';

class AIContentSuggestions {
    private $db;
    private $clientId;
    private $userId;
    private $provider;
    private $apiKey;
    private $claudeApiKey;
    private $claudeModel;
    private $openaiApiKey;
    private $openaiModel;
    
    const PROVIDERS = [
        'claude' => [
            'name' => 'Claude (Anthropic)',
            'api_url' => 'https://api.anthropic.com/v1/messages',
            'models' => ['claude-3-5-sonnet-20241022', 'claude-3-5-haiku-20241022', 'claude-3-opus-20240229']
        ],
        'openai' => [
            'name' => 'ChatGPT (OpenAI)',
            'api_url' => 'https://api.openai.com/v1/chat/completions',
            'models' => ['gpt-4o', 'gpt-4-turbo', 'gpt-3.5-turbo']
        ]
    ];
    
    public function __construct($clientId, $userId = null) {
        $this->db = Database::getInstance();
        $this->clientId = $clientId;
        $this->userId = $userId;
        $this->loadApiSettings();
    }
    
    /**
     * Load API settings for the user
     */
    private function loadApiSettings() {
        if ($this->userId) {
            // Load from user settings table (new method)
            $stmt = $this->db->prepare(
                "SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'ai_%' AND user_id = ?"
            );
            $stmt->execute([$this->userId]);
            $settings = $stmt->fetchAll();
            
            foreach ($settings as $setting) {
                switch ($setting['setting_key']) {
                    case 'ai_claude_api_key':
                        $this->claudeApiKey = $setting['setting_value'];
                        break;
                    case 'ai_claude_model':
                        $this->claudeModel = $setting['setting_value'];
                        break;
                    case 'ai_openai_api_key':
                        $this->openaiApiKey = $setting['setting_value'];
                        break;
                    case 'ai_openai_model':
                        $this->openaiModel = $setting['setting_value'];
                        break;
                }
            }
        } else {
            // Fallback to client settings for backward compatibility
            $stmt = $this->db->prepare(
                "SELECT claude_api_key, claude_model, openai_api_key, openai_model FROM clients WHERE id = ?"
            );
            $stmt->execute([$this->clientId]);
            $settings = $stmt->fetch();
            
            if ($settings) {
                $this->claudeApiKey = $settings['claude_api_key'];
                $this->claudeModel = $settings['claude_model'];
                $this->openaiApiKey = $settings['openai_api_key'];
                $this->openaiModel = $settings['openai_model'];
            }
        }
    }
    
    /**
     * Check if AI is configured
     */
    public function isConfigured($provider = null) {
        if ($provider) {
            if ($provider === 'claude') {
                return !empty($this->claudeApiKey);
            } elseif ($provider === 'openai') {
                return !empty($this->openaiApiKey);
            }
            return false;
        }
        // Check if any provider is configured
        return !empty($this->claudeApiKey) || !empty($this->openaiApiKey);
    }
    
    /**
     * Get configured providers
     */
    public function getConfiguredProviders() {
        $providers = [];
        if (!empty($this->claudeApiKey)) {
            $providers[] = 'claude';
        }
        if (!empty($this->openaiApiKey)) {
            $providers[] = 'openai';
        }
        return $providers;
    }
    
    /**
     * Generate content suggestions
     */
    public function generateSuggestions($params = []) {
        // Get provider from params or use first configured
        $provider = $params['provider'] ?? null;
        
        if (!$provider) {
            // Use first configured provider
            $configured = $this->getConfiguredProviders();
            if (empty($configured)) {
                throw new Exception("No AI provider configured. Please add your API key in settings.");
            }
            $provider = $configured[0];
        }
        
        if (!$this->isConfigured($provider)) {
            throw new Exception("AI provider '{$provider}' not configured. Please add your API key in settings.");
        }
        
        // Set current provider and API key
        $this->provider = $provider;
        if ($provider === 'claude') {
            $this->apiKey = $this->claudeApiKey;
        } else {
            $this->apiKey = $this->openaiApiKey;
        }
        
        $prompt = $this->buildPrompt($params);
        
        switch ($provider) {
            case 'claude':
                return $this->callClaude($prompt, $params);
            case 'openai':
                return $this->callOpenAI($prompt, $params);
            default:
                throw new Exception("Unsupported AI provider: {$provider}");
        }
    }
    
    /**
     * Build prompt based on parameters
     */
    private function buildPrompt($params) {
        $platform = $params['platform'] ?? 'general';
        $topic = $params['topic'] ?? '';
        $tone = $params['tone'] ?? 'professional';
        $length = $params['length'] ?? 'medium';
        $includeHashtags = $params['include_hashtags'] ?? true;
        $includeEmojis = $params['include_emojis'] ?? false;
        
        $platformLimits = [
            'twitter' => '280 characters',
            'instagram' => '2200 characters with relevant hashtags',
            'facebook' => 'up to 500 words',
            'linkedin' => 'professional tone, up to 3000 characters',
            'general' => 'appropriate length for social media'
        ];
        
        $limit = $platformLimits[$platform] ?? $platformLimits['general'];
        
        $prompt = "Generate a social media post for {$platform} with the following requirements:\n\n";
        
        if ($topic) {
            $prompt .= "Topic: {$topic}\n";
        }
        
        $prompt .= "Tone: {$tone}\n";
        $prompt .= "Length: {$length} ({$limit})\n";
        
        if ($includeHashtags && in_array($platform, ['instagram', 'twitter', 'linkedin'])) {
            $prompt .= "Include relevant hashtags\n";
        }
        
        if ($includeEmojis) {
            $prompt .= "Include appropriate emojis\n";
        }
        
        $prompt .= "\nGenerate 3 different variations of the post.";
        
        return $prompt;
    }
    
    /**
     * Call Claude API
     */
    private function callClaude($prompt, $params) {
        $model = $params['model'] ?? $this->claudeModel ?? 'claude-3-5-sonnet-20241022';
        
        $data = [
            'model' => $model,
            'max_tokens' => 1024,
            'messages' => [
                ['role' => 'user', 'content' => $prompt]
            ]
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => self::PROVIDERS['claude']['api_url'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01'
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            $error = json_decode($response, true);
            $errorMsg = $error['error']['message'] ?? $error['message'] ?? 'Unknown error';
            $errorType = $error['error']['type'] ?? $error['type'] ?? '';
            if ($errorType) {
                $errorMsg = "[$errorType] $errorMsg";
            }
            throw new Exception("Claude API error: $errorMsg (HTTP $httpCode)");
        }
        
        $result = json_decode($response, true);
        return $this->parseClaudeResponse($result);
    }
    
    /**
     * Call OpenAI API
     */
    private function callOpenAI($prompt, $params) {
        $model = $params['model'] ?? $this->openaiModel ?? 'gpt-3.5-turbo';
        
        $data = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a social media content expert who creates engaging posts.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.7,
            'max_tokens' => 1024
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => self::PROVIDERS['openai']['api_url'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            $error = json_decode($response, true);
            $errorMsg = $error['error']['message'] ?? $error['message'] ?? 'Unknown error';
            $errorType = $error['error']['type'] ?? $error['type'] ?? '';
            if ($errorType) {
                $errorMsg = "[$errorType] $errorMsg";
            }
            throw new Exception("OpenAI API error: $errorMsg (HTTP $httpCode)");
        }
        
        $result = json_decode($response, true);
        return $this->parseOpenAIResponse($result);
    }
    
    /**
     * Parse Claude response
     */
    private function parseClaudeResponse($response) {
        $content = $response['content'][0]['text'] ?? '';
        return $this->extractSuggestions($content);
    }
    
    /**
     * Parse OpenAI response
     */
    private function parseOpenAIResponse($response) {
        $content = $response['choices'][0]['message']['content'] ?? '';
        return $this->extractSuggestions($content);
    }
    
    /**
     * Extract suggestions from AI response
     */
    private function extractSuggestions($content) {
        // Split content into individual suggestions
        $suggestions = [];
        
        // Try to split by numbers (1., 2., 3.) or variations
        $patterns = [
            '/(?:^|\n)(?:\d+\.|Variation \d+:|Option \d+:)\s*(.+?)(?=\n(?:\d+\.|Variation \d+:|Option \d+:)|$)/s',
            '/(?:^|\n)[-•]\s*(.+?)(?=\n[-•]|$)/s'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $match) {
                    $suggestion = trim($match);
                    if (strlen($suggestion) > 10) {
                        $suggestions[] = $suggestion;
                    }
                }
                break;
            }
        }
        
        // If no pattern matches, treat the whole content as one suggestion
        if (empty($suggestions)) {
            $suggestions[] = trim($content);
        }
        
        // Limit to 3 suggestions
        return array_slice($suggestions, 0, 3);
    }
    
    /**
     * Save API settings
     */
    public function saveApiSettings($provider, $apiKey, $model = null) {
        if (!isset(self::PROVIDERS[$provider])) {
            throw new Exception("Invalid AI provider");
        }
        
        // Don't test if clearing the key
        if (!empty($apiKey) && !str_contains($apiKey, '*')) {
            // Test the API key
            $this->testApiKey($provider, $apiKey);
        }
        
        // Update the specific provider settings
        if ($this->userId) {
            // Save to user settings table (new method)
            if ($provider === 'claude') {
                $this->saveSettingToDatabase('ai_claude_api_key', $apiKey);
                $this->saveSettingToDatabase('ai_claude_model', $model ?: 'claude-3-5-sonnet-20241022');
                $this->claudeApiKey = $apiKey;
                $this->claudeModel = $model;
            } else {
                $this->saveSettingToDatabase('ai_openai_api_key', $apiKey);
                $this->saveSettingToDatabase('ai_openai_model', $model ?: 'gpt-4o');
                $this->openaiApiKey = $apiKey;
                $this->openaiModel = $model;
            }
        } else {
            // Fallback to client settings for backward compatibility
            if ($provider === 'claude') {
                $stmt = $this->db->prepare(
                    "UPDATE clients SET claude_api_key = ?, claude_model = ? WHERE id = ?"
                );
                $stmt->execute([$apiKey, $model, $this->clientId]);
                $this->claudeApiKey = $apiKey;
                $this->claudeModel = $model;
            } else {
                $stmt = $this->db->prepare(
                    "UPDATE clients SET openai_api_key = ?, openai_model = ? WHERE id = ?"
                );
                $stmt->execute([$apiKey, $model, $this->clientId]);
                $this->openaiApiKey = $apiKey;
                $this->openaiModel = $model;
            }
        }
        
        return true;
    }
    
    /**
     * Save a setting to the database
     */
    private function saveSettingToDatabase($key, $value) {
        if (!$this->userId) return;
        
        $stmt = $this->db->prepare("
            INSERT INTO settings (setting_key, setting_value, user_id) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()
        ");
        $stmt->execute([$key, $value, $this->userId, $value]);
    }
    
    /**
     * Test API key validity
     */
    private function testApiKey($provider, $apiKey) {
        $testPrompt = "Say 'Hello' in one word.";
        
        $oldProvider = $this->provider;
        $oldApiKey = $this->apiKey;
        
        $this->provider = $provider;
        $this->apiKey = $apiKey;
        
        try {
            $this->generateSuggestions([
                'topic' => 'test',
                'platform' => 'general',
                'length' => 'short'
            ]);
        } catch (Exception $e) {
            $this->provider = $oldProvider;
            $this->apiKey = $oldApiKey;
            throw new Exception("API key validation failed: " . $e->getMessage());
        }
        
        $this->provider = $oldProvider;
        $this->apiKey = $oldApiKey;
    }
    
    /**
     * Get usage statistics
     */
    public function getUsageStats() {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as total_requests, 
                    SUM(tokens_used) as total_tokens,
                    DATE(created_at) as date
             FROM ai_usage_logs 
             WHERE client_id = ? 
             AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(created_at)
             ORDER BY date DESC"
        );
        $stmt->execute([$this->clientId]);
        
        return $stmt->fetchAll();
    }
    
    /**
     * Log AI usage
     */
    private function logUsage($provider, $model, $tokensUsed, $cost = 0) {
        $stmt = $this->db->prepare(
            "INSERT INTO ai_usage_logs (client_id, provider, model, tokens_used, cost, created_at)
             VALUES (?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([$this->clientId, $provider, $model, $tokensUsed, $cost]);
    }
}