<?php
require_once 'config.php';

echo "DEMO_MODE: " . (defined('DEMO_MODE') ? (DEMO_MODE ? 'true' : 'false') : 'not defined') . "<br>";
echo "APP_URL: " . APP_URL . "<br>";

session_start();
echo "Session ID: " . session_id() . "<br>";
echo "Session data: " . print_r($_SESSION, true) . "<br>";

if (isset($_SESSION['user_id'])) {
    echo "User logged in: " . $_SESSION['user_id'] . "<br>";
} else {
    echo "User NOT logged in<br>";
}

if (isset($_SESSION['client_id'])) {
    echo "Client selected: " . $_SESSION['client_id'] . "<br>";
} else {
    echo "Client NOT selected<br>";
}