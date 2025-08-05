<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Starting analytics debug...<br>";

// Test includes one by one
echo "1. Including config...<br>";
require_once '../config.php';
echo "✓ Config loaded<br>";

echo "2. Including Database...<br>";
require_once '../includes/Database.php';
echo "✓ Database loaded<br>";

echo "3. Including Auth...<br>";
require_once '../includes/Auth.php';
echo "✓ Auth loaded<br>";

echo "4. Including functions...<br>";
require_once '../includes/functions.php';
echo "✓ Functions loaded<br>";

echo "5. Including layout...<br>";
require_once '../includes/layout.php';
echo "✓ Layout loaded<br>";

echo "6. Including AnalyticsDashboard...<br>";
require_once '../includes/AnalyticsDashboard.php';
echo "✓ AnalyticsDashboard loaded<br>";

echo "7. Creating Auth instance...<br>";
$auth = new Auth();
echo "✓ Auth instance created<br>";

echo "8. Checking login...<br>";
if (!$auth->isLoggedIn()) {
    echo "✗ Not logged in - redirecting would happen here<br>";
    echo "Please login first at <a href='/login.php'>/login.php</a><br>";
    exit;
}
echo "✓ User is logged in<br>";

echo "9. Checking client...<br>";
$client = $auth->getCurrentClient();
if (!$client) {
    echo "✗ No client selected<br>";
    echo "Please select a client first<br>";
    exit;
}
echo "✓ Client selected: " . $client['name'] . " (ID: " . $client['id'] . ")<br>";

echo "10. Creating AnalyticsDashboard instance...<br>";
try {
    $analytics = new AnalyticsDashboard();
    echo "✓ AnalyticsDashboard instance created<br>";
} catch (Exception $e) {
    echo "✗ Error creating AnalyticsDashboard: " . $e->getMessage() . "<br>";
    exit;
}

echo "11. Getting dashboard data...<br>";
try {
    $period = $_GET['period'] ?? '30d';
    $platform = $_GET['platform'] ?? 'all';
    echo "Period: $period, Platform: $platform<br>";
    
    $dashboardData = $analytics->getDashboardData($client['id'], $period, $platform);
    echo "✓ Dashboard data retrieved<br>";
    
    echo "<pre>";
    echo "Dashboard data structure:\n";
    print_r(array_keys($dashboardData));
    echo "</pre>";
    
} catch (Exception $e) {
    echo "✗ Error getting dashboard data: " . $e->getMessage() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    exit;
}

echo "<br><strong>✓ All checks passed! The analytics system is working.</strong><br>";
echo "<br><a href='analytics.php'>Go to regular analytics page</a>";
?>