<?php
/**
 * LinkedIn Analytics Webhook Handler
 * 
 * Dedicated webhook for LinkedIn analytics data collection
 * Processes real-time engagement and performance metrics
 */

require_once '../../../config.php';
require_once '../../../includes/Database.php';
require_once '../../../includes/AnalyticsCollector.php';

// LinkedIn webhook verification
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $challenge = $_GET['challenge'] ?? '';
    
    if ($challenge) {
        http_response_code(200);
        echo $challenge;
        exit;
    }
    
    http_response_code(400);
    exit;
}

// Handle webhook POST data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = file_get_contents('php://input');
    $signature = $_SERVER['HTTP_X_LINKEDIN_SIGNATURE'] ?? '';
    
    // Verify webhook signature (if LinkedIn provides one)
    if (defined('LINKEDIN_WEBHOOK_SECRET') && LINKEDIN_WEBHOOK_SECRET) {
        $expectedSignature = hash_hmac('sha256', $body, LINKEDIN_WEBHOOK_SECRET);
        if (!hash_equals($expectedSignature, $signature)) {
            http_response_code(403);
            exit;
        }
    }
    
    // Parse webhook data
    $data = json_decode($body, true);
    
    // Process webhook
    try {
        $db = Database::getInstance();
        
        // Log webhook for debugging
        $stmt = $db->prepare(
            "INSERT INTO webhook_logs (platform, event_type, payload, created_at) 
             VALUES ('linkedin_analytics', ?, ?, NOW())"
        );
        $stmt->execute([getLinkedInEventType($data), $body]);
        
        // Process different webhook types
        processLinkedInAnalyticsWebhook($data);
        
        // Always respond 200 OK
        http_response_code(200);
        echo 'OK';
        
    } catch (Exception $e) {
        // Log error but still respond 200 to prevent retries
        error_log('LinkedIn analytics webhook error: ' . $e->getMessage());
        http_response_code(200);
        echo 'OK';
    }
    exit;
}

// Invalid request method
http_response_code(405);
exit;

/**
 * Get LinkedIn event type from webhook data
 */
function getLinkedInEventType($data) {
    if (isset($data['shareStatistics'])) return 'share_statistics';
    if (isset($data['socialActions'])) return 'social_actions';
    if (isset($data['followerStatistics'])) return 'follower_statistics';
    if (isset($data['pageStatistics'])) return 'page_statistics';
    
    return 'unknown';
}

/**
 * Process LinkedIn analytics webhooks
 */
function processLinkedInAnalyticsWebhook($data) {
    // Handle share statistics (post analytics)
    if (isset($data['shareStatistics'])) {
        foreach ($data['shareStatistics'] as $stat) {
            handleLinkedInShareStatistics($stat);
        }
    }
    
    // Handle social actions (engagement events)
    if (isset($data['socialActions'])) {
        foreach ($data['socialActions'] as $action) {
            handleLinkedInSocialAction($action);
        }
    }
    
    // Handle follower statistics
    if (isset($data['followerStatistics'])) {
        foreach ($data['followerStatistics'] as $stat) {
            handleLinkedInFollowerStatistics($stat);
        }
    }
    
    // Handle page statistics
    if (isset($data['pageStatistics'])) {
        foreach ($data['pageStatistics'] as $stat) {
            handleLinkedInPageStatistics($stat);
        }
    }
}

/**
 * Handle LinkedIn share statistics
 */
function handleLinkedInShareStatistics($stat) {
    global $db;
    
    try {
        $shareId = $stat['share'] ?? null;
        if (!$shareId) return;
        
        // Extract URN ID from LinkedIn URN format
        $shareUrn = $shareId;
        if (strpos($shareId, 'urn:li:share:') === 0) {
            $shareId = str_replace('urn:li:share:', '', $shareId);
        }
        
        // Find corresponding post
        $stmt = $db->prepare(
            "SELECT p.id, a.id as account_id, a.client_id FROM posts p
             JOIN accounts a ON p.client_id = a.client_id
             WHERE JSON_EXTRACT(p.platform_posts_json, '$.linkedin') = ?
             AND a.platform = 'linkedin' AND a.is_active = 1"
        );
        $stmt->execute([$shareId]);
        $result = $stmt->fetch();
        
        if (!$result) {
            // Try with full URN
            $stmt->execute([$shareUrn]);
            $result = $stmt->fetch();
        }
        
        if (!$result) return;
        
        // Process share metrics
        $analyticsData = [
            'impressions' => $stat['impressionCount'] ?? 0,
            'reach' => $stat['uniqueImpressionsCount'] ?? 0,
            'engagement_rate' => 0,
            'clicks' => $stat['clickCount'] ?? 0,
            'shares' => $stat['shareCount'] ?? 0,
            'comments' => $stat['commentCount'] ?? 0,
            'likes' => $stat['likeCount'] ?? 0,
            'video_views' => $stat['videoViews'] ?? 0
        ];
        
        // Calculate engagement rate
        if ($analyticsData['impressions'] > 0) {
            $totalEngagement = $analyticsData['likes'] + $analyticsData['comments'] + 
                             $analyticsData['shares'] + $analyticsData['clicks'];
            $analyticsData['engagement_rate'] = round(($totalEngagement / $analyticsData['impressions']) * 100, 2);
        }
        
        // Store analytics
        storePostAnalytics($result['id'], $result['account_id'], $shareId, $analyticsData);
        
    } catch (Exception $e) {
        error_log('LinkedIn share statistics error: ' . $e->getMessage());
    }
}

/**
 * Handle LinkedIn social actions
 */
function handleLinkedInSocialAction($action) {
    global $db;
    
    try {
        $shareId = $action['object'] ?? null;
        $actionType = $action['verb'] ?? null;
        
        if (!$shareId || !$actionType) return;
        
        // Extract share ID from URN
        if (strpos($shareId, 'urn:li:share:') === 0) {
            $shareId = str_replace('urn:li:share:', '', $shareId);
        }
        
        // Find the post and update real-time metrics
        $stmt = $db->prepare(
            "SELECT p.id, a.id as account_id FROM posts p
             JOIN accounts a ON p.client_id = a.client_id
             WHERE JSON_EXTRACT(p.platform_posts_json, '$.linkedin') LIKE ?
             AND a.platform = 'linkedin' AND a.is_active = 1"
        );
        $stmt->execute(['%' . $shareId . '%']);
        $result = $stmt->fetch();
        
        if (!$result) return;
        
        // Update the specific metric based on action type
        switch ($actionType) {
            case 'like':
                $column = 'likes';
                break;
            case 'comment':
                $column = 'comments';
                break;
            case 'share':
                $column = 'shares';
                break;
            case 'click':
                $column = 'clicks';
                break;
            default:
                return;
        }
        
        $stmt = $db->prepare("
            INSERT INTO post_analytics (post_id, account_id, platform_post_id, {$column}, last_updated)
            VALUES (?, ?, ?, 1, NOW())
            ON DUPLICATE KEY UPDATE
                {$column} = {$column} + 1,
                last_updated = NOW()
        ");
        
        $stmt->execute([$result['id'], $result['account_id'], $shareId]);
        
        // Recalculate engagement rate
        updateEngagementRate($result['id'], $result['account_id']);
        
    } catch (Exception $e) {
        error_log('LinkedIn social action error: ' . $e->getMessage());
    }
}

/**
 * Handle LinkedIn follower statistics
 */
function handleLinkedInFollowerStatistics($stat) {
    global $db;
    
    try {
        $organizationId = $stat['organizationalEntity'] ?? null;
        if (!$organizationId) return;
        
        // Extract organization ID from URN
        if (strpos($organizationId, 'urn:li:organization:') === 0) {
            $organizationId = str_replace('urn:li:organization:', '', $organizationId);
        }
        
        // Find account
        $stmt = $db->prepare(
            "SELECT * FROM accounts 
             WHERE platform = 'linkedin' 
             AND JSON_EXTRACT(account_data, '$.organization_id') = ?
             AND is_active = 1"
        );
        $stmt->execute([$organizationId]);
        $account = $stmt->fetch();
        
        if (!$account) return;
        
        // Process follower metrics
        $followerData = [
            'follower_count' => $stat['followerCount'] ?? 0,
            'following_count' => 0 // LinkedIn doesn't have following for company pages
        ];
        
        // Store follower analytics
        storeFollowerAnalytics($account['id'], $followerData);
        
    } catch (Exception $e) {
        error_log('LinkedIn follower statistics error: ' . $e->getMessage());
    }
}

/**
 * Handle LinkedIn page statistics
 */
function handleLinkedInPageStatistics($stat) {
    global $db;
    
    try {
        $organizationId = $stat['organizationalEntity'] ?? null;
        if (!$organizationId) return;
        
        // Extract organization ID from URN
        if (strpos($organizationId, 'urn:li:organization:') === 0) {
            $organizationId = str_replace('urn:li:organization:', '', $organizationId);
        }
        
        // Find account
        $stmt = $db->prepare(
            "SELECT * FROM accounts 
             WHERE platform = 'linkedin' 
             AND JSON_EXTRACT(account_data, '$.organization_id') = ?
             AND is_active = 1"
        );
        $stmt->execute([$organizationId]);
        $account = $stmt->fetch();
        
        if (!$account) return;
        
        // Store page-level analytics if needed
        // This could include page views, visitor demographics, etc.
        
    } catch (Exception $e) {
        error_log('LinkedIn page statistics error: ' . $e->getMessage());
    }
}

/**
 * Store post analytics in database
 */
function storePostAnalytics($postId, $accountId, $platformPostId, $analytics) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            INSERT INTO post_analytics (
                post_id, account_id, platform_post_id, impressions, reach, 
                engagement_rate, clicks, shares, saves, comments, likes, 
                video_views, last_updated
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                impressions = GREATEST(impressions, VALUES(impressions)),
                reach = GREATEST(reach, VALUES(reach)),
                engagement_rate = VALUES(engagement_rate),
                clicks = GREATEST(clicks, VALUES(clicks)),
                shares = GREATEST(shares, VALUES(shares)),
                saves = GREATEST(saves, VALUES(saves)),
                comments = GREATEST(comments, VALUES(comments)),
                likes = GREATEST(likes, VALUES(likes)),
                video_views = GREATEST(video_views, VALUES(video_views)),
                last_updated = NOW()
        ");
        
        $stmt->execute([
            $postId,
            $accountId,
            $platformPostId,
            $analytics['impressions'] ?? 0,
            $analytics['reach'] ?? 0,
            $analytics['engagement_rate'] ?? 0,
            $analytics['clicks'] ?? 0,
            $analytics['shares'] ?? 0,
            $analytics['saves'] ?? 0,
            $analytics['comments'] ?? 0,
            $analytics['likes'] ?? 0,
            $analytics['video_views'] ?? 0
        ]);
        
    } catch (Exception $e) {
        error_log('Failed to store LinkedIn post analytics: ' . $e->getMessage());
    }
}

/**
 * Store follower analytics in database
 */
function storeFollowerAnalytics($accountId, $data) {
    global $db;
    
    try {
        // Get previous day's data to calculate growth
        $stmt = $db->prepare(
            "SELECT follower_count FROM follower_analytics 
             WHERE account_id = ? 
             ORDER BY date DESC 
             LIMIT 1"
        );
        $stmt->execute([$accountId]);
        $previous = $stmt->fetch();
        
        $previousCount = $previous['follower_count'] ?? 0;
        $currentCount = $data['follower_count'] ?? 0;
        $dailyGrowth = $currentCount - $previousCount;
        
        $stmt = $db->prepare("
            INSERT INTO follower_analytics (
                account_id, date, follower_count, following_count, daily_growth
            ) VALUES (?, CURDATE(), ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                follower_count = VALUES(follower_count),
                following_count = VALUES(following_count),
                daily_growth = VALUES(daily_growth)
        ");
        
        $stmt->execute([
            $accountId,
            $data['follower_count'] ?? 0,
            $data['following_count'] ?? 0,
            $dailyGrowth
        ]);
        
    } catch (Exception $e) {
        error_log('Failed to store LinkedIn follower analytics: ' . $e->getMessage());
    }
}

/**
 * Update engagement rate for a post
 */
function updateEngagementRate($postId, $accountId) {
    global $db;
    
    try {
        $stmt = $db->prepare("
            UPDATE post_analytics 
            SET engagement_rate = CASE 
                WHEN impressions > 0 THEN ROUND(((likes + comments + shares + clicks) / impressions) * 100, 2)
                WHEN reach > 0 THEN ROUND(((likes + comments + shares + clicks) / reach) * 100, 2)
                ELSE 0 
            END
            WHERE post_id = ? AND account_id = ?
        ");
        
        $stmt->execute([$postId, $accountId]);
        
    } catch (Exception $e) {
        error_log('Failed to update engagement rate: ' . $e->getMessage());
    }
}