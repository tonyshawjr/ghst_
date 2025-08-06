<?php
require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Auth.php';
require_once '../../includes/functions.php';
require_once '../../includes/CampaignStrategyEngine.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    jsonResponse(['error' => 'Method not allowed'], 405);
}

$auth = new Auth();
$auth->requireLogin();
requireClient();

$client = $auth->getCurrentClient();

$campaignId = intval($_GET['campaign_id'] ?? 0);
$weekNumber = intval($_GET['week_number'] ?? 0);

if (!$campaignId || !$weekNumber || $weekNumber < 1 || $weekNumber > 24) {
    jsonResponse(['error' => 'Invalid campaign ID or week number'], 400);
}

$db = Database::getInstance();

// Verify campaign belongs to client
$stmt = $db->prepare("SELECT id FROM strategy_campaigns WHERE id = ? AND client_id = ?");
$stmt->execute([$campaignId, $client['id']]);
if (!$stmt->fetch()) {
    jsonResponse(['error' => 'Campaign not found'], 404);
}

try {
    $strategyEngine = new CampaignStrategyEngine($client['id'], $campaignId);
    $weekPlan = $strategyEngine->getWeeklyPlan($weekNumber);
    
    jsonResponse([
        'success' => true,
        'week' => $weekPlan
    ]);
    
} catch (Exception $e) {
    error_log("Get week error: " . $e->getMessage());
    jsonResponse([
        'error' => 'Failed to load week data',
        'details' => $e->getMessage()
    ], 500);
}