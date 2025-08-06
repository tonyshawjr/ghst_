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
$action = $input['action'] ?? '';

if (!$campaignId || !$action) {
    jsonResponse(['error' => 'Missing required parameters'], 400);
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
    $strategyEngine = new CampaignStrategyEngine($client['id'], $campaignId);
    
    switch ($action) {
        case 'generate_full_strategy':
            // Get campaign wizard data to regenerate full strategy
            $wizardData = getCampaignWizardData($campaignId);
            
            if (empty($wizardData)) {
                jsonResponse(['error' => 'Campaign wizard data not found'], 400);
            }
            
            // Check if analytics data was provided in original campaign
            $analyticsData = null;
            if (!empty($input['analytics_data'])) {
                try {
                    $analyticsData = json_decode($input['analytics_data'], true);
                } catch (Exception $e) {
                    // Analytics parsing failed, continue without it
                    error_log("Analytics parsing failed: " . $e->getMessage());
                }
            }
            
            // Generate the full strategy
            $result = $strategyEngine->generateFullStrategy($wizardData, $analyticsData);
            
            if ($result['success']) {
                // Update campaign status
                $stmt = $db->prepare("UPDATE strategy_campaigns SET status = 'active', updated_at = NOW() WHERE id = ?");
                $stmt->execute([$campaignId]);
                
                jsonResponse([
                    'success' => true,
                    'message' => 'Strategy generated successfully',
                    'campaign_id' => $campaignId,
                    'weeks_generated' => $result['weeks_generated'],
                    'total_posts' => $result['total_posts']
                ]);
            } else {
                jsonResponse(['error' => 'Strategy generation failed'], 500);
            }
            break;
            
        case 'generate_wizard_strategy':
            // For new campaigns being created through the wizard
            $wizardParams = $input['wizard_data'] ?? [];
            
            if (empty($wizardParams)) {
                jsonResponse(['error' => 'Wizard data required'], 400);
            }
            
            // Add campaign ID to params
            $wizardParams['campaign_id'] = $campaignId;
            
            // Parse analytics if provided
            $analyticsData = null;
            if (!empty($wizardParams['analytics_data'])) {
                try {
                    if (is_string($wizardParams['analytics_data'])) {
                        $analyticsData = json_decode($wizardParams['analytics_data'], true);
                    } else {
                        $analyticsData = $wizardParams['analytics_data'];
                    }
                } catch (Exception $e) {
                    error_log("Analytics parsing failed: " . $e->getMessage());
                }
            }
            
            // Generate strategy
            $result = $strategyEngine->generateFullStrategy($wizardParams, $analyticsData);
            
            if ($result['success']) {
                jsonResponse([
                    'success' => true,
                    'message' => 'Campaign strategy generated successfully',
                    'campaign_id' => $result['campaign_id'],
                    'weeks_generated' => $result['weeks_generated'],
                    'total_posts' => $result['total_posts'],
                    'redirect_url' => "/dashboard/wrtr-campaign.php?id={$result['campaign_id']}"
                ]);
            } else {
                jsonResponse(['error' => 'Strategy generation failed'], 500);
            }
            break;
            
        default:
            jsonResponse(['error' => 'Invalid action'], 400);
    }
    
} catch (Exception $e) {
    error_log("Strategy generation error: " . $e->getMessage());
    jsonResponse([
        'error' => 'An error occurred while generating the strategy',
        'details' => $e->getMessage()
    ], 500);
}

/**
 * Get campaign wizard data for regeneration
 */
function getCampaignWizardData($campaignId) {
    $db = Database::getInstance();
    
    // Get basic campaign info
    $stmt = $db->prepare("SELECT * FROM strategy_campaigns WHERE id = ?");
    $stmt->execute([$campaignId]);
    $campaign = $stmt->fetch();
    
    if (!$campaign) {
        return null;
    }
    
    // Get wizard step data
    $stmt = $db->prepare("
        SELECT 
            cg.primary_goal, cg.secondary_goals, cg.target_audience, 
            cg.success_metrics, cg.business_objectives, cg.brand_positioning,
            co.primary_offer, co.offer_type, co.price_point, co.call_to_action,
            co.landing_page_url, co.offer_details,
            cvt.brand_voice, cvt.tone_attributes, cvt.writing_style,
            cvt.personality_traits, cvt.do_use, cvt.dont_use, cvt.hashtag_strategy,
            ct.campaign_type, ct.content_pillars, ct.posting_frequency,
            ct.platform_focus, ct.content_formats, ct.engagement_strategy
        FROM campaign_goals cg
        LEFT JOIN campaign_offers co ON cg.campaign_id = co.campaign_id
        LEFT JOIN campaign_voice_tone cvt ON cg.campaign_id = cvt.campaign_id
        LEFT JOIN campaign_types ct ON cg.campaign_id = ct.campaign_id
        WHERE cg.campaign_id = ?
    ");
    $stmt->execute([$campaignId]);
    $wizardData = $stmt->fetch();
    
    if (!$wizardData) {
        return null;
    }
    
    // Get key dates
    $stmt = $db->prepare("
        SELECT date_type, date_value, title, description, importance 
        FROM campaign_key_dates 
        WHERE campaign_id = ? 
        ORDER BY date_value ASC
    ");
    $stmt->execute([$campaignId]);
    $keyDates = $stmt->fetchAll();
    
    // Build structured wizard data
    $params = [
        // Basic campaign info
        'campaign_id' => $campaignId,
        'title' => $campaign['title'],
        'description' => $campaign['description'],
        'total_weeks' => $campaign['total_weeks'],
        'start_date' => $campaign['start_date'],
        'ai_model' => $campaign['ai_model'],
        
        // Step 1: Goals and basics
        'primary_goal' => $wizardData['primary_goal'],
        'secondary_goals' => json_decode($wizardData['secondary_goals'], true) ?? [],
        'target_audience' => $wizardData['target_audience'],
        'success_metrics' => json_decode($wizardData['success_metrics'], true) ?? [],
        'business_objectives' => $wizardData['business_objectives'],
        'brand_positioning' => $wizardData['brand_positioning'],
        
        // Step 2: Offers
        'primary_offer' => $wizardData['primary_offer'],
        'offer_type' => $wizardData['offer_type'],
        'price_point' => $wizardData['price_point'],
        'call_to_action' => $wizardData['call_to_action'],
        'landing_page_url' => $wizardData['landing_page_url'],
        'offer_details' => json_decode($wizardData['offer_details'], true) ?? [],
        
        // Step 3: Voice and tone
        'brand_voice' => $wizardData['brand_voice'],
        'tone_attributes' => json_decode($wizardData['tone_attributes'], true) ?? [],
        'writing_style' => $wizardData['writing_style'],
        'personality_traits' => json_decode($wizardData['personality_traits'], true) ?? [],
        'do_use' => $wizardData['do_use'],
        'dont_use' => $wizardData['dont_use'],
        'hashtag_strategy' => $wizardData['hashtag_strategy'],
        
        // Step 4: Campaign type and settings
        'campaign_type' => $wizardData['campaign_type'],
        'content_pillars' => json_decode($wizardData['content_pillars'], true) ?? [],
        'posting_frequency' => json_decode($wizardData['posting_frequency'], true) ?? [],
        'platforms' => json_decode($wizardData['platform_focus'], true) ?? ['instagram', 'facebook'],
        'content_formats' => json_decode($wizardData['content_formats'], true) ?? [],
        'engagement_strategy' => $wizardData['engagement_strategy'],
        
        // Step 5: Key dates
        'key_dates' => $keyDates,
        
        // Generation settings
        'generation_settings' => json_decode($campaign['generation_settings'], true) ?? []
    ];
    
    // Calculate posts per week based on posting frequency
    $totalPosts = 0;
    $platforms = $params['platforms'];
    $frequency = $params['posting_frequency'];
    
    if (is_array($frequency)) {
        foreach ($platforms as $platform) {
            $platformFreq = $frequency[$platform] ?? 3; // Default to 3 posts per week per platform
            $totalPosts += is_numeric($platformFreq) ? intval($platformFreq) : 3;
        }
    } else {
        $totalPosts = count($platforms) * 3; // Default fallback
    }
    
    $params['posts_per_week'] = min(max($totalPosts, 3), 7); // Between 3-7 posts per week
    
    return $params;
}