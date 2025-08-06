<?php
require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/CampaignStrategyEngine.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$auth = new Auth();
$auth->requireLogin();
requireClient();

$client = $auth->getCurrentClient();

// Validate CSRF token
if (!$auth->validateCSRFToken($_POST['csrf_token'] ?? '')) {
    jsonResponse(['error' => 'Invalid request'], 400);
}

$campaignId = intval($_POST['campaign_id'] ?? 0);
if (!$campaignId) {
    jsonResponse(['error' => 'Campaign ID required'], 400);
}

$db = Database::getInstance();

// Verify campaign belongs to client
$stmt = $db->prepare("SELECT * FROM strategy_campaigns WHERE id = ? AND client_id = ?");
$stmt->execute([$campaignId, $client['id']]);
$campaign = $stmt->fetch();

if (!$campaign) {
    jsonResponse(['error' => 'Campaign not found'], 404);
}

try {
    $analyticsData = null;
    
    // Handle file upload
    if (isset($_FILES['analytics_file']) && $_FILES['analytics_file']['error'] === UPLOAD_ERR_OK) {
        $analyticsData = processAnalyticsFile($_FILES['analytics_file']);
    } 
    // Handle text data
    elseif (!empty($_POST['analytics_data'])) {
        $analyticsData = processAnalyticsText($_POST['analytics_data']);
    } 
    else {
        jsonResponse(['error' => 'No analytics data provided'], 400);
    }
    
    if (!$analyticsData) {
        jsonResponse(['error' => 'Failed to parse analytics data'], 400);
    }
    
    // Initialize strategy engine and evolve strategy
    $strategyEngine = new CampaignStrategyEngine($client['id'], $campaignId);
    
    // Evolution options
    $evolutionOptions = [
        'auto_apply' => $_POST['auto_apply'] ?? false,
        'focus_areas' => $_POST['focus_areas'] ?? [],
        'preserve_scheduled' => $_POST['preserve_scheduled'] ?? true
    ];
    
    // Evolve the strategy based on analytics
    $result = $strategyEngine->evolveStrategy($analyticsData, $evolutionOptions);
    
    if ($result['success']) {
        // Log the analytics upload
        error_log("Analytics uploaded for campaign {$campaignId}: " . json_encode([
            'insights_count' => count($result['insights']),
            'recommendations' => count($result['recommendations']),
            'changes_applied' => count($result['applied_changes'])
        ]));
        
        jsonResponse([
            'success' => true,
            'message' => 'Analytics processed and strategy evolved successfully',
            'insights' => $result['insights'],
            'recommendations' => $result['recommendations'],
            'applied_changes' => $result['applied_changes'],
            'performance_comparison' => $result['performance_comparison']
        ]);
    } else {
        jsonResponse(['error' => 'Failed to evolve strategy based on analytics'], 500);
    }
    
} catch (Exception $e) {
    error_log("Analytics upload error: " . $e->getMessage());
    jsonResponse([
        'error' => 'Failed to process analytics data',
        'details' => $e->getMessage()
    ], 500);
}

/**
 * Process uploaded analytics file
 */
function processAnalyticsFile($file): ?array {
    $maxFileSize = 10 * 1024 * 1024; // 10MB
    
    if ($file['size'] > $maxFileSize) {
        throw new Exception('File too large. Maximum size is 10MB.');
    }
    
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedTypes = ['json', 'csv', 'xlsx', 'xls', 'txt'];
    
    if (!in_array($fileExt, $allowedTypes)) {
        throw new Exception('Invalid file type. Allowed types: ' . implode(', ', $allowedTypes));
    }
    
    $tempFile = $file['tmp_name'];
    
    switch ($fileExt) {
        case 'json':
            return parseJsonAnalytics($tempFile);
        case 'csv':
            return parseCsvAnalytics($tempFile);
        case 'xlsx':
        case 'xls':
            return parseExcelAnalytics($tempFile);
        case 'txt':
            return parseTextAnalytics($tempFile);
        default:
            throw new Exception('Unsupported file type');
    }
}

/**
 * Process analytics text data
 */
function processAnalyticsText($textData): ?array {
    // Try to parse as JSON first
    $decoded = json_decode($textData, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return normalizeAnalyticsData($decoded);
    }
    
    // Try to parse as CSV
    $lines = explode("\n", $textData);
    if (count($lines) > 1) {
        return parseCsvLines($lines);
    }
    
    // Try to parse as key-value pairs
    return parseKeyValueAnalytics($textData);
}

/**
 * Parse JSON analytics file
 */
function parseJsonAnalytics($filePath): ?array {
    $content = file_get_contents($filePath);
    $data = json_decode($content, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON format: ' . json_last_error_msg());
    }
    
    return normalizeAnalyticsData($data);
}

/**
 * Parse CSV analytics file
 */
function parseCsvAnalytics($filePath): ?array {
    $file = fopen($filePath, 'r');
    if (!$file) {
        throw new Exception('Could not open CSV file');
    }
    
    $headers = fgetcsv($file);
    if (!$headers) {
        fclose($file);
        throw new Exception('CSV file has no headers');
    }
    
    $data = [];
    while (($row = fgetcsv($file)) !== false) {
        if (count($row) === count($headers)) {
            $data[] = array_combine($headers, $row);
        }
    }
    
    fclose($file);
    return normalizeAnalyticsData(['posts' => $data]);
}

/**
 * Parse CSV lines from text
 */
function parseCsvLines($lines): ?array {
    $headers = str_getcsv(trim($lines[0]));
    $data = [];
    
    for ($i = 1; $i < count($lines); $i++) {
        $line = trim($lines[$i]);
        if (empty($line)) continue;
        
        $row = str_getcsv($line);
        if (count($row) === count($headers)) {
            $data[] = array_combine($headers, $row);
        }
    }
    
    return normalizeAnalyticsData(['posts' => $data]);
}

/**
 * Parse Excel analytics file (requires PhpSpreadsheet)
 */
function parseExcelAnalytics($filePath): ?array {
    // This would require PhpSpreadsheet library
    // For now, return an error or implement basic parsing
    throw new Exception('Excel file parsing not yet implemented. Please use JSON or CSV format.');
}

/**
 * Parse text analytics as key-value pairs
 */
function parseTextAnalytics($filePath): ?array {
    $content = file_get_contents($filePath);
    return parseKeyValueAnalytics($content);
}

/**
 * Parse key-value analytics format
 */
function parseKeyValueAnalytics($text): ?array {
    $data = [
        'summary' => [],
        'posts' => []
    ];
    
    $lines = explode("\n", $text);
    $currentSection = 'summary';
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        // Check for section headers
        if (preg_match('/^(posts|summary|metrics|performance):\s*$/i', $line, $matches)) {
            $currentSection = strtolower($matches[1]);
            continue;
        }
        
        // Parse key-value pairs
        if (preg_match('/^([^:]+):\s*(.+)$/', $line, $matches)) {
            $key = trim($matches[1]);
            $value = trim($matches[2]);
            
            // Convert numeric values
            if (is_numeric($value)) {
                $value = strpos($value, '.') !== false ? floatval($value) : intval($value);
            }
            
            $data[$currentSection][$key] = $value;
        }
    }
    
    return normalizeAnalyticsData($data);
}

/**
 * Normalize analytics data to standard format
 */
function normalizeAnalyticsData($data): array {
    $normalized = [
        'posts' => [],
        'summary' => [],
        'platforms' => [],
        'time_periods' => []
    ];
    
    // Handle different data structures
    if (isset($data['posts']) && is_array($data['posts'])) {
        $normalized['posts'] = normalizePostsData($data['posts']);
    }
    
    if (isset($data['summary'])) {
        $normalized['summary'] = $data['summary'];
    }
    
    // Handle Instagram Insights format
    if (isset($data['media_insights'])) {
        $normalized['posts'] = normalizeInstagramInsights($data['media_insights']);
    }
    
    // Handle Facebook Insights format
    if (isset($data['data']) && is_array($data['data'])) {
        $normalized['posts'] = normalizeFacebookInsights($data['data']);
    }
    
    // Handle Twitter Analytics format
    if (isset($data['tweets'])) {
        $normalized['posts'] = normalizeTwitterAnalytics($data['tweets']);
    }
    
    // Handle LinkedIn Analytics format
    if (isset($data['elements'])) {
        $normalized['posts'] = normalizeLinkedInAnalytics($data['elements']);
    }
    
    // Handle Google Analytics format
    if (isset($data['reports'])) {
        $normalized = array_merge($normalized, normalizeGoogleAnalytics($data['reports']));
    }
    
    // Extract platform performance
    $normalized['platforms'] = extractPlatformPerformance($normalized['posts']);
    
    return $normalized;
}

/**
 * Normalize posts data to standard format
 */
function normalizePostsData($posts): array {
    $normalized = [];
    
    foreach ($posts as $post) {
        $normalizedPost = [
            'id' => $post['id'] ?? $post['post_id'] ?? uniqid(),
            'platform' => detectPlatform($post),
            'content' => $post['content'] ?? $post['message'] ?? $post['text'] ?? '',
            'post_type' => $post['post_type'] ?? $post['type'] ?? 'feed',
            'posted_at' => normalizeDate($post['posted_at'] ?? $post['created_time'] ?? $post['date'] ?? null),
            'impressions' => intval($post['impressions'] ?? $post['reach'] ?? $post['views'] ?? 0),
            'reach' => intval($post['reach'] ?? $post['impressions'] ?? 0),
            'likes' => intval($post['likes'] ?? $post['reactions'] ?? $post['favorites'] ?? 0),
            'comments' => intval($post['comments'] ?? $post['replies'] ?? 0),
            'shares' => intval($post['shares'] ?? $post['retweets'] ?? $post['reposts'] ?? 0),
            'saves' => intval($post['saves'] ?? $post['bookmarks'] ?? 0),
            'clicks' => intval($post['clicks'] ?? $post['link_clicks'] ?? $post['url_clicks'] ?? 0),
            'hashtags' => extractHashtags($post),
            'engagement_rate' => calculateEngagementRate($post),
            'total_engagement' => calculateTotalEngagement($post)
        ];
        
        $normalized[] = $normalizedPost;
    }
    
    return $normalized;
}

/**
 * Detect platform from post data
 */
function detectPlatform($post): string {
    if (isset($post['platform'])) {
        return strtolower($post['platform']);
    }
    
    // Try to detect from field names
    if (isset($post['tweet_id']) || isset($post['retweets'])) return 'twitter';
    if (isset($post['ig_id']) || isset($post['instagram_id'])) return 'instagram';
    if (isset($post['fb_id']) || isset($post['facebook_id'])) return 'facebook';
    if (isset($post['linkedin_id']) || isset($post['li_id'])) return 'linkedin';
    if (isset($post['tiktok_id']) || isset($post['tt_id'])) return 'tiktok';
    
    return 'unknown';
}

/**
 * Normalize date formats
 */
function normalizeDate($date): ?string {
    if (empty($date)) return null;
    
    try {
        $dateTime = new DateTime($date);
        return $dateTime->format('Y-m-d H:i:s');
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Extract hashtags from post content or dedicated field
 */
function extractHashtags($post): string {
    if (isset($post['hashtags'])) {
        return is_array($post['hashtags']) ? implode(' ', $post['hashtags']) : $post['hashtags'];
    }
    
    $content = $post['content'] ?? $post['message'] ?? $post['text'] ?? '';
    preg_match_all('/#[\w]+/', $content, $matches);
    
    return implode(' ', $matches[0]);
}

/**
 * Calculate engagement rate
 */
function calculateEngagementRate($post): float {
    $impressions = intval($post['impressions'] ?? $post['reach'] ?? 1);
    $engagement = calculateTotalEngagement($post);
    
    return $impressions > 0 ? round(($engagement / $impressions) * 100, 2) : 0;
}

/**
 * Calculate total engagement
 */
function calculateTotalEngagement($post): int {
    return intval($post['likes'] ?? 0) + 
           intval($post['comments'] ?? 0) + 
           intval($post['shares'] ?? 0) + 
           intval($post['saves'] ?? 0) + 
           intval($post['clicks'] ?? 0);
}

/**
 * Extract platform performance summaries
 */
function extractPlatformPerformance($posts): array {
    $platforms = [];
    
    foreach ($posts as $post) {
        $platform = $post['platform'];
        
        if (!isset($platforms[$platform])) {
            $platforms[$platform] = [
                'post_count' => 0,
                'total_impressions' => 0,
                'total_engagement' => 0,
                'total_reach' => 0,
                'avg_engagement_rate' => 0
            ];
        }
        
        $platforms[$platform]['post_count']++;
        $platforms[$platform]['total_impressions'] += $post['impressions'];
        $platforms[$platform]['total_engagement'] += $post['total_engagement'];
        $platforms[$platform]['total_reach'] += $post['reach'];
    }
    
    // Calculate averages
    foreach ($platforms as $platform => $data) {
        if ($data['post_count'] > 0) {
            $platforms[$platform]['avg_engagement_rate'] = 
                $data['total_impressions'] > 0 ? 
                round(($data['total_engagement'] / $data['total_impressions']) * 100, 2) : 0;
        }
    }
    
    return $platforms;
}

/**
 * Platform-specific normalization functions
 */
function normalizeInstagramInsights($insights): array {
    // Implement Instagram-specific parsing
    return [];
}

function normalizeFacebookInsights($insights): array {
    // Implement Facebook-specific parsing
    return [];
}

function normalizeTwitterAnalytics($tweets): array {
    // Implement Twitter-specific parsing
    return [];
}

function normalizeLinkedInAnalytics($elements): array {
    // Implement LinkedIn-specific parsing
    return [];
}

function normalizeGoogleAnalytics($reports): array {
    // Implement Google Analytics parsing for social media data
    return ['summary' => [], 'time_periods' => []];
}