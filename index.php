<?php
// Check if config.php exists, if not redirect to installer
if (!file_exists('config.php')) {
    header('Location: installer.php');
    exit;
}

// Redirect to login or dashboard
header('Location: login.php');
exit;