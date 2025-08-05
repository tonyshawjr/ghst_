<?php
/**
 * Email Statistics API Endpoint
 * Returns email delivery statistics for the dashboard
 */

require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Auth.php';
require_once '../../includes/EmailService.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    // Authentication
    $auth = new Auth();
    if (!$auth->isLoggedIn()) {
        http_response_code(401);
        echo json_encode(['error' => 'Authentication required']);
        exit();
    }
    
    $userId = $auth->getCurrentUserId();
    
    // Get date range from query parameters
    $dateFrom = $_GET['from'] ?? null;
    $dateTo = $_GET['to'] ?? null;
    $period = $_GET['period'] ?? '30'; // Default to last 30 days
    
    // Set default date range if not provided
    if (!$dateFrom && !$dateTo) {
        $dateTo = date('Y-m-d 23:59:59');
        $dateFrom = date('Y-m-d 00:00:00', strtotime("-{$period} days"));
    }
    
    // Validate date format
    if ($dateFrom && !DateTime::createFromFormat('Y-m-d H:i:s', $dateFrom)) {
        $dateFrom = date('Y-m-d 00:00:00', strtotime($dateFrom));
    }
    if ($dateTo && !DateTime::createFromFormat('Y-m-d H:i:s', $dateTo)) {
        $dateTo = date('Y-m-d 23:59:59', strtotime($dateTo));
    }
    
    $db = Database::getInstance();
    
    // Get overall email statistics
    $stmt = $db->prepare("
        SELECT 
            COUNT(DISTINCT et.id) as total_sent,
            COUNT(DISTINCT CASE WHEN et.status IN ('delivered', 'opened', 'clicked') THEN et.id END) as delivered,
            COUNT(DISTINCT CASE WHEN et.opened_at IS NOT NULL THEN et.id END) as opened,
            COUNT(DISTINCT CASE WHEN et.clicked_at IS NOT NULL THEN et.id END) as clicked,
            COALESCE(SUM(et.open_count), 0) as total_opens,
            COALESCE(SUM(et.click_count), 0) as total_clicks,
            COUNT(DISTINCT CASE WHEN et.status = 'bounced' THEN et.id END) as bounced,
            COUNT(DISTINCT CASE WHEN et.status = 'failed' THEN et.id END) as failed
        FROM email_tracking et
        LEFT JOIN email_activity_log eal ON eal.id = (
            SELECT eal2.id FROM email_activity_log eal2 
            WHERE JSON_EXTRACT(eal2.details, '$.results[*].tracking_id') LIKE CONCAT('%', et.tracking_id, '%')
            AND eal2.user_id = ?
            LIMIT 1
        )
        WHERE et.created_at BETWEEN ? AND ?
        AND (eal.user_id = ? OR et.tracking_id IN (
            SELECT eq.tracking_id FROM email_queue eq 
            LEFT JOIN email_activity_log eal3 ON eal3.id = (
                SELECT eal4.id FROM email_activity_log eal4 
                WHERE JSON_EXTRACT(eal4.details, '$.results[*].tracking_id') LIKE CONCAT('%', eq.tracking_id, '%')
                AND eal4.user_id = ?
                LIMIT 1
            )
            WHERE eal3.user_id = ?
        ))
    ");
    
    $stmt->execute([$userId, $dateFrom, $dateTo, $userId, $userId, $userId]);
    $stats = $stmt->fetch();
    
    // Calculate rates
    $totalSent = (int)$stats['total_sent'];
    $delivered = (int)$stats['delivered'];
    $opened = (int)$stats['opened'];
    $clicked = (int)$stats['clicked'];
    
    $deliveryRate = $totalSent > 0 ? ($delivered / $totalSent) * 100 : 0;
    $openRate = $delivered > 0 ? ($opened / $delivered) * 100 : 0;
    $clickRate = $opened > 0 ? ($clicked / $opened) * 100 : 0;
    $bounceRate = $totalSent > 0 ? ((int)$stats['bounced'] / $totalSent) * 100 : 0;
    
    // Get recent email activity
    $stmt = $db->prepare("
        SELECT 
            eal.action,
            eal.recipients_count,
            eal.success_count,
            eal.fail_count,
            eal.created_at,
            JSON_EXTRACT(eal.details, '$.message') as message
        FROM email_activity_log eal
        WHERE eal.user_id = ?
        AND eal.created_at BETWEEN ? AND ?
        ORDER BY eal.created_at DESC
        LIMIT 10
    ");
    
    $stmt->execute([$userId, $dateFrom, $dateTo]);
    $recentActivity = $stmt->fetchAll();
    
    // Get email statistics by day (for chart data)
    $stmt = $db->prepare("
        SELECT 
            DATE(et.created_at) as date,
            COUNT(DISTINCT et.id) as sent,
            COUNT(DISTINCT CASE WHEN et.opened_at IS NOT NULL THEN et.id END) as opened,
            COUNT(DISTINCT CASE WHEN et.clicked_at IS NOT NULL THEN et.id END) as clicked
        FROM email_tracking et
        LEFT JOIN email_activity_log eal ON eal.id = (
            SELECT eal2.id FROM email_activity_log eal2 
            WHERE JSON_EXTRACT(eal2.details, '$.results[*].tracking_id') LIKE CONCAT('%', et.tracking_id, '%')
            AND eal2.user_id = ?
            LIMIT 1
        )
        WHERE et.created_at BETWEEN ? AND ?
        AND (eal.user_id = ? OR et.tracking_id IN (
            SELECT eq.tracking_id FROM email_queue eq 
            LEFT JOIN email_activity_log eal3 ON eal3.id = (
                SELECT eal4.id FROM email_activity_log eal4 
                WHERE JSON_EXTRACT(eal4.details, '$.results[*].tracking_id') LIKE CONCAT('%', eq.tracking_id, '%')
                AND eal4.user_id = ?
                LIMIT 1
            )
            WHERE eal3.user_id = ?
        ))
        GROUP BY DATE(et.created_at)
        ORDER BY date DESC
        LIMIT 30
    ");
    
    $stmt->execute([$userId, $dateFrom, $dateTo, $userId, $userId, $userId]);
    $dailyStats = $stmt->fetchAll();
    
    // Get queue statistics
    $stmt = $db->prepare("
        SELECT 
            status,
            COUNT(*) as count
        FROM email_queue eq
        WHERE EXISTS (
            SELECT 1 FROM email_activity_log eal 
            WHERE JSON_EXTRACT(eal.details, '$.results[*].queue_id') LIKE CONCAT('%', eq.id, '%')
            AND eal.user_id = ?
        )
        GROUP BY status
    ");
    
    $stmt->execute([$userId]);
    $queueStats = [];
    while ($row = $stmt->fetch()) {
        $queueStats[$row['status']] = (int)$row['count'];
    }
    
    // Get top performing email subjects (most opened)
    $stmt = $db->prepare("
        SELECT 
            et.subject,
            COUNT(DISTINCT et.id) as sent,
            COUNT(DISTINCT CASE WHEN et.opened_at IS NOT NULL THEN et.id END) as opened,
            COUNT(DISTINCT CASE WHEN et.clicked_at IS NOT NULL THEN et.id END) as clicked,
            CASE 
                WHEN COUNT(DISTINCT et.id) > 0 THEN 
                    (COUNT(DISTINCT CASE WHEN et.opened_at IS NOT NULL THEN et.id END) / COUNT(DISTINCT et.id)) * 100
                ELSE 0 
            END as open_rate
        FROM email_tracking et
        LEFT JOIN email_activity_log eal ON eal.id = (
            SELECT eal2.id FROM email_activity_log eal2 
            WHERE JSON_EXTRACT(eal2.details, '$.results[*].tracking_id') LIKE CONCAT('%', et.tracking_id, '%')
            AND eal2.user_id = ?
            LIMIT 1
        )
        WHERE et.created_at BETWEEN ? AND ?
        AND (eal.user_id = ? OR et.tracking_id IN (
            SELECT eq.tracking_id FROM email_queue eq 
            LEFT JOIN email_activity_log eal3 ON eal3.id = (
                SELECT eal4.id FROM email_activity_log eal4 
                WHERE JSON_EXTRACT(eal4.details, '$.results[*].tracking_id') LIKE CONCAT('%', eq.tracking_id, '%')
                AND eal4.user_id = ?
                LIMIT 1
            )
            WHERE eal3.user_id = ?
        ))
        AND et.subject IS NOT NULL
        GROUP BY et.subject
        HAVING sent >= 3
        ORDER BY open_rate DESC, sent DESC
        LIMIT 5
    ");
    
    $stmt->execute([$userId, $dateFrom, $dateTo, $userId, $userId, $userId]);
    $topSubjects = $stmt->fetchAll();
    
    // Prepare response
    $response = [
        'success' => true,
        'period' => [
            'from' => $dateFrom,
            'to' => $dateTo,
            'days' => ceil((strtotime($dateTo) - strtotime($dateFrom)) / 86400)
        ],
        'stats' => [
            'total_sent' => $totalSent,
            'delivered' => $delivered,
            'opened' => $opened,
            'clicked' => $clicked,
            'bounced' => (int)$stats['bounced'],
            'failed' => (int)$stats['failed'],
            'total_opens' => (int)$stats['total_opens'],
            'total_clicks' => (int)$stats['total_clicks'],
            'delivery_rate' => round($deliveryRate, 2),
            'open_rate' => round($openRate, 2),
            'click_rate' => round($clickRate, 2),
            'bounce_rate' => round($bounceRate, 2)
        ],
        'queue_stats' => $queueStats,
        'daily_stats' => $dailyStats,
        'recent_activity' => $recentActivity,
        'top_subjects' => $topSubjects
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Email stats API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to retrieve email statistics',
        'message' => ENVIRONMENT === 'development' ? $e->getMessage() : 'Internal server error'
    ]);
}
?>