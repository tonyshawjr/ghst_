<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/functions.php';
require_once '../includes/CampaignStrategyEngine.php';

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$auth = new Auth();
$auth->requireLogin();
requireClient();

$client = $auth->getCurrentClient();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !$auth->validateCSRFToken($input['csrf_token'] ?? '')) {
    jsonResponse(['error' => 'Invalid request'], 400);
}

$campaignId = intval($input['campaign_id'] ?? 0);
$weekNumber = intval($input['week_number'] ?? 0);
$regenerationReason = $input['regeneration_reason'] ?? 'user_request';
$userFeedback = trim($input['user_feedback'] ?? '');

if (!$campaignId || !$weekNumber || $weekNumber < 1 || $weekNumber > 24) {
    jsonResponse(['error' => 'Invalid campaign ID or week number'], 400);
}

$db = Database::getInstance();

// Verify campaign belongs to client
$stmt = $db->prepare("SELECT * FROM strategy_campaigns WHERE id = ? AND client_id = ?");
$stmt->execute([$campaignId, $client['id']]);
$campaign = $stmt->fetch();

if (!$campaign) {
    jsonResponse(['error' => 'Campaign not found'], 404);
}

// Verify week exists
$stmt = $db->prepare("SELECT id FROM campaign_weeks WHERE campaign_id = ? AND week_number = ?");
$stmt->execute([$campaignId, $weekNumber]);
$week = $stmt->fetch();

if (!$week) {
    jsonResponse(['error' => 'Week not found'], 404);
}

try {
    $strategyEngine = new CampaignStrategyEngine($client['id'], $campaignId);
    
    // Prepare regeneration options
    $options = [
        'regeneration_reason' => $regenerationReason,
        'user_feedback' => $userFeedback,
        'ai_model' => $input['ai_model'] ?? $campaign['ai_model'] ?? 'claude'
    ];
    
    // Add specific instructions based on reason
    switch ($regenerationReason) {
        case 'performance':
            $options['focus'] = 'Improve engagement and performance metrics. Focus on more compelling hooks and stronger calls-to-action.';
            break;
        case 'feedback':
            $options['focus'] = 'Address user feedback: ' . $userFeedback;
            break;
        case 'date_change':
            $options['focus'] = 'Adjust content for date changes and timing updates.';
            break;
        case 'strategy_pivot':
            $options['focus'] = 'Align with new strategy direction while maintaining campaign coherence.';
            break;
        case 'user_request':
        default:
            $options['focus'] = !empty($userFeedback) ? $userFeedback : 'General improvement and refinement.';
            break;
    }
    
    // Log regeneration attempt
    error_log("Week regeneration initiated: Campaign {$campaignId}, Week {$weekNumber}, Reason: {$regenerationReason}");
    
    // Regenerate the week
    $result = $strategyEngine->regenerateWeek($weekNumber, $options);
    
    if ($result['success']) {
        // Update campaign's updated_at timestamp
        $stmt = $db->prepare("UPDATE strategy_campaigns SET updated_at = NOW() WHERE id = ?");
        $stmt->execute([$campaignId]);
        
        // Log successful regeneration
        error_log("Week regenerated successfully: Campaign {$campaignId}, Week {$weekNumber}, Posts: {$result['posts_count']}");
        
        jsonResponse([
            'success' => true,
            'message' => 'Week regenerated successfully',
            'week_number' => $result['week_number'],
            'posts_count' => $result['posts_count'],
            'week_data' => $result['week_data']
        ]);
    } else {
        jsonResponse(['error' => 'Week regeneration failed'], 500);
    }
    
} catch (Exception $e) {
    error_log("Week regeneration error: " . $e->getMessage());
    
    // Provide more specific error messages
    $errorMessage = 'An error occurred while regenerating the week';
    
    if (strpos($e->getMessage(), 'AI') !== false || strpos($e->getMessage(), 'API') !== false) {
        $errorMessage = 'AI service temporarily unavailable. Please try again in a few moments.';
    } elseif (strpos($e->getMessage(), 'timeout') !== false) {
        $errorMessage = 'Request timed out. The week content may be too complex to regenerate quickly.';
    } elseif (strpos($e->getMessage(), 'database') !== false || strpos($e->getMessage(), 'SQL') !== false) {
        $errorMessage = 'Database error occurred. Please try again.';
    }
    
    jsonResponse([
        'error' => $errorMessage,
        'details' => DEBUG_MODE ? $e->getMessage() : null
    ], 500);
}

/**
 * Validate regeneration reason
 */
function isValidRegenerationReason($reason) {
    $validReasons = ['performance', 'feedback', 'date_change', 'strategy_pivot', 'user_request'];
    return in_array($reason, $validReasons);
}

/**
 * Get regeneration history for a week (for future analytics)
 */
function getRegenerationHistory($campaignId, $weekNumber) {
    $db = Database::getInstance();
    
    $stmt = $db->prepare("
        SELECT wrh.*, u.name as user_name
        FROM week_regeneration_history wrh
        LEFT JOIN users u ON wrh.created_by = u.id
        WHERE wrh.campaign_week_id IN (
            SELECT id FROM campaign_weeks 
            WHERE campaign_id = ? AND week_number = ?
        )
        ORDER BY wrh.created_at DESC
    ");
    
    $stmt->execute([$campaignId, $weekNumber]);
    return $stmt->fetchAll();
}

/**
 * Log regeneration metrics for optimization
 */
function logRegenerationMetrics($campaignId, $weekNumber, $reason, $success, $processingTime = null) {
    $db = Database::getInstance();
    
    try {
        $stmt = $db->prepare("
            INSERT INTO ai_generation_analytics (
                campaign_id, generation_type, ai_model, generation_time_seconds,
                user_satisfaction_rating, created_at
            ) VALUES (?, 'post_regeneration', 'claude', ?, NULL, NOW())
        ");
        
        $stmt->execute([$campaignId, $processingTime]);
    } catch (Exception $e) {
        // Don't fail the request if metrics logging fails
        error_log("Failed to log regeneration metrics: " . $e->getMessage());
    }
}