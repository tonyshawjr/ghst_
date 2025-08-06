<?php
require_once '../config.php';
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/functions.php';

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
    $db->beginTransaction();
    
    switch ($action) {
        case 'schedule_post':
            $result = scheduleIndividualPost($campaignId, $input, $client['id']);
            break;
            
        case 'schedule_week':
            $result = scheduleWeekPosts($campaignId, $input, $client['id']);
            break;
            
        default:
            jsonResponse(['error' => 'Invalid action'], 400);
    }
    
    $db->commit();
    jsonResponse($result);
    
} catch (Exception $e) {
    $db->rollBack();
    error_log("Scheduler error: " . $e->getMessage());
    jsonResponse([
        'error' => 'Failed to schedule posts',
        'details' => $e->getMessage()
    ], 500);
}

/**
 * Schedule an individual post from campaign strategy
 */
function scheduleIndividualPost($campaignId, $input, $clientId) {
    global $db;
    
    $postId = intval($input['post_id'] ?? 0);
    if (!$postId) {
        throw new Exception('Post ID required');
    }
    
    // Get campaign post details
    $stmt = $db->prepare("
        SELECT cwp.*, cw.week_start_date, cw.week_end_date, cw.week_number
        FROM campaign_week_posts cwp
        JOIN campaign_weeks cw ON cwp.campaign_week_id = cw.id
        WHERE cwp.id = ? AND cwp.campaign_id = ?
    ");
    $stmt->execute([$postId, $campaignId]);
    $campaignPost = $stmt->fetch();
    
    if (!$campaignPost) {
        throw new Exception('Campaign post not found');
    }
    
    // Check if already scheduled
    if ($campaignPost['actual_post_id']) {
        throw new Exception('Post is already scheduled');
    }
    
    // Calculate optimal scheduling time
    $scheduleTime = calculateOptimalPostTime($campaignPost);
    
    // Create post in main posts table
    $stmt = $db->prepare("
        INSERT INTO posts (
            client_id, content, platform, post_type, scheduled_datetime,
            hashtags, status, meta_data, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, 'scheduled', ?, NOW())
    ");
    
    $metaData = json_encode([
        'source' => 'campaign_strategy',
        'campaign_id' => $campaignId,
        'week_number' => $campaignPost['week_number'],
        'content_pillar' => $campaignPost['content_pillar'],
        'call_to_action' => $campaignPost['call_to_action'],
        'generated_by_ai' => true
    ]);
    
    $stmt->execute([
        $clientId,
        $campaignPost['content'],
        $campaignPost['platform'],
        $campaignPost['post_type'],
        $scheduleTime,
        $campaignPost['hashtags'],
        $metaData
    ]);
    
    $scheduledPostId = $db->lastInsertId();
    
    // Update campaign post with reference to scheduled post
    $stmt = $db->prepare("
        UPDATE campaign_week_posts 
        SET actual_post_id = ?, status = 'scheduled', scheduled_datetime = ?
        WHERE id = ?
    ");
    $stmt->execute([$scheduledPostId, $scheduleTime, $postId]);
    
    // Create mapping record
    $stmt = $db->prepare("
        INSERT INTO campaign_post_mappings (
            campaign_id, campaign_week_id, campaign_post_id, 
            scheduled_post_id, mapping_type, content_match_score, created_at
        ) VALUES (?, ?, ?, ?, 'direct', 1.00, NOW())
    ");
    $stmt->execute([
        $campaignId,
        $campaignPost['campaign_week_id'],
        $postId,
        $scheduledPostId
    ]);
    
    return [
        'success' => true,
        'message' => 'Post scheduled successfully',
        'scheduled_post_id' => $scheduledPostId,
        'scheduled_time' => $scheduleTime,
        'platform' => $campaignPost['platform']
    ];
}

/**
 * Schedule all posts for a specific week
 */
function scheduleWeekPosts($campaignId, $input, $clientId) {
    global $db;
    
    $weekNumber = intval($input['week_number'] ?? 0);
    if (!$weekNumber || $weekNumber < 1 || $weekNumber > 24) {
        throw new Exception('Invalid week number');
    }
    
    // Get all unscheduled posts for the week
    $stmt = $db->prepare("
        SELECT cwp.*, cw.week_start_date, cw.week_end_date
        FROM campaign_week_posts cwp
        JOIN campaign_weeks cw ON cwp.campaign_week_id = cw.id
        WHERE cw.campaign_id = ? AND cw.week_number = ? 
        AND cwp.actual_post_id IS NULL
        ORDER BY cwp.post_order ASC
    ");
    $stmt->execute([$campaignId, $weekNumber]);
    $posts = $stmt->fetchAll();
    
    if (empty($posts)) {
        throw new Exception('No unscheduled posts found for this week');
    }
    
    $scheduledCount = 0;
    $scheduledPosts = [];
    
    foreach ($posts as $post) {
        try {
            // Calculate optimal scheduling time for each post
            $scheduleTime = calculateOptimalPostTime($post, $scheduledCount);
            
            // Create post in main posts table
            $stmt = $db->prepare("
                INSERT INTO posts (
                    client_id, content, platform, post_type, scheduled_datetime,
                    hashtags, status, meta_data, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'scheduled', ?, NOW())
            ");
            
            $metaData = json_encode([
                'source' => 'campaign_strategy',
                'campaign_id' => $campaignId,
                'week_number' => $weekNumber,
                'content_pillar' => $post['content_pillar'],
                'call_to_action' => $post['call_to_action'],
                'generated_by_ai' => true
            ]);
            
            $stmt->execute([
                $clientId,
                $post['content'],
                $post['platform'],
                $post['post_type'],
                $scheduleTime,
                $post['hashtags'],
                $metaData
            ]);
            
            $scheduledPostId = $db->lastInsertId();
            
            // Update campaign post
            $stmt = $db->prepare("
                UPDATE campaign_week_posts 
                SET actual_post_id = ?, status = 'scheduled', scheduled_datetime = ?
                WHERE id = ?
            ");
            $stmt->execute([$scheduledPostId, $scheduleTime, $post['id']]);
            
            // Create mapping record
            $stmt = $db->prepare("
                INSERT INTO campaign_post_mappings (
                    campaign_id, campaign_week_id, campaign_post_id, 
                    scheduled_post_id, mapping_type, content_match_score, created_at
                ) VALUES (?, ?, ?, ?, 'direct', 1.00, NOW())
            ");
            $stmt->execute([
                $campaignId,
                $post['campaign_week_id'],
                $post['id'],
                $scheduledPostId
            ]);
            
            $scheduledPosts[] = [
                'post_id' => $post['id'],
                'scheduled_post_id' => $scheduledPostId,
                'platform' => $post['platform'],
                'scheduled_time' => $scheduleTime
            ];
            
            $scheduledCount++;
            
        } catch (Exception $e) {
            error_log("Failed to schedule post {$post['id']}: " . $e->getMessage());
            // Continue with other posts
        }
    }
    
    // Update week status if all posts scheduled
    if ($scheduledCount === count($posts)) {
        $stmt = $db->prepare("
            UPDATE campaign_weeks 
            SET status = 'in_progress', completion_percentage = 50
            WHERE campaign_id = ? AND week_number = ?
        ");
        $stmt->execute([$campaignId, $weekNumber]);
    }
    
    return [
        'success' => true,
        'message' => "Scheduled {$scheduledCount} posts successfully",
        'scheduled_count' => $scheduledCount,
        'total_posts' => count($posts),
        'week_number' => $weekNumber,
        'scheduled_posts' => $scheduledPosts
    ];
}

/**
 * Calculate optimal posting time based on platform and timing preferences
 */
function calculateOptimalPostTime($post, $postIndex = 0) {
    $weekStart = new DateTime($post['week_start_date']);
    $weekEnd = new DateTime($post['week_end_date']);
    
    // Platform-specific optimal times (in 24-hour format)
    $optimalTimes = [
        'instagram' => ['09:00', '11:00', '13:00', '15:00', '17:00', '19:00', '20:00'],
        'facebook' => ['09:00', '13:00', '15:00', '18:00', '20:00'],
        'linkedin' => ['09:00', '12:00', '17:00'],
        'twitter' => ['09:00', '12:00', '15:00', '18:00', '21:00'],
        'threads' => ['10:00', '14:00', '16:00', '19:00'],
        'tiktok' => ['12:00', '15:00', '18:00', '20:00', '21:00'],
        'youtube' => ['14:00', '16:00', '18:00', '20:00']
    ];
    
    $platform = $post['platform'] ?? 'instagram';
    $platformTimes = $optimalTimes[$platform] ?? $optimalTimes['instagram'];
    
    // Distribute posts throughout the week
    $totalDays = 7;
    $dayOffset = $postIndex % $totalDays;
    
    // Calculate target date
    $targetDate = clone $weekStart;
    $targetDate->modify("+{$dayOffset} days");
    
    // Skip weekends for B2B platforms
    if (in_array($platform, ['linkedin']) && in_array($targetDate->format('N'), [6, 7])) {
        // Move to next weekday
        while (in_array($targetDate->format('N'), [6, 7])) {
            $targetDate->modify('+1 day');
        }
        
        // If we've gone past the week, move back to Friday
        if ($targetDate > $weekEnd) {
            $targetDate = clone $weekEnd;
            while ($targetDate->format('N') != 5) { // Friday
                $targetDate->modify('-1 day');
            }
        }
    }
    
    // Select optimal time for the platform
    $timeIndex = $postIndex % count($platformTimes);
    $selectedTime = $platformTimes[$timeIndex];
    
    // Set the time
    $targetDate->setTime(
        intval(explode(':', $selectedTime)[0]),
        intval(explode(':', $selectedTime)[1])
    );
    
    // Ensure we don't schedule in the past
    $now = new DateTime();
    if ($targetDate <= $now) {
        $targetDate = clone $now;
        $targetDate->modify('+1 hour');
        
        // Round to next optimal time
        $currentHour = $targetDate->format('H');
        $nextOptimalTime = null;
        
        foreach ($platformTimes as $time) {
            $timeHour = intval(explode(':', $time)[0]);
            if ($timeHour > $currentHour) {
                $nextOptimalTime = $time;
                break;
            }
        }
        
        if ($nextOptimalTime) {
            $targetDate->setTime(
                intval(explode(':', $nextOptimalTime)[0]),
                intval(explode(':', $nextOptimalTime)[1])
            );
        } else {
            // Use first optimal time of next day
            $targetDate->modify('+1 day');
            $targetDate->setTime(
                intval(explode(':', $platformTimes[0])[0]),
                intval(explode(':', $platformTimes[0])[1])
            );
        }
    }
    
    return $targetDate->format('Y-m-d H:i:s');
}

/**
 * Get posting preferences for client (future enhancement)
 */
function getClientPostingPreferences($clientId) {
    global $db;
    
    // This could be expanded to read client-specific preferences
    $stmt = $db->prepare("
        SELECT posting_preferences 
        FROM clients 
        WHERE id = ?
    ");
    $stmt->execute([$clientId]);
    $result = $stmt->fetch();
    
    if ($result && $result['posting_preferences']) {
        return json_decode($result['posting_preferences'], true);
    }
    
    return [
        'timezone' => 'America/New_York',
        'avoid_weekends' => false,
        'preferred_times' => null
    ];
}

/**
 * Log scheduling activity for analytics
 */
function logSchedulingActivity($campaignId, $postsScheduled, $weekNumber = null) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            INSERT INTO activity_logs (
                client_id, activity_type, description, metadata, created_at
            ) SELECT 
                client_id, 'campaign_scheduling',
                CONCAT('Scheduled ', ?, ' posts from campaign strategy'),
                JSON_OBJECT('campaign_id', ?, 'week_number', ?, 'posts_count', ?),
                NOW()
            FROM strategy_campaigns WHERE id = ?
        ");
        
        $stmt->execute([
            $postsScheduled,
            $campaignId,
            $weekNumber,
            $postsScheduled,
            $campaignId
        ]);
    } catch (Exception $e) {
        error_log("Failed to log scheduling activity: " . $e->getMessage());
    }
}