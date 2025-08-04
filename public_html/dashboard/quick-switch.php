<?php
require_once '../../config.php';
require_once '../../includes/Database.php';
require_once '../../includes/Auth.php';
require_once '../../includes/functions.php';

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->validateCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Invalid request');
    }
    
    $action = $_POST['action'] ?? 'switch';
    
    if ($action === 'switch') {
        // Quick switch client
        $clientId = $_POST['client_id'] ?? '';
        
        if (!empty($clientId) && $auth->setCurrentClient($clientId)) {
            // Get the referring page or default to dashboard
            $referrer = $_SERVER['HTTP_REFERER'] ?? '/dashboard/';
            redirect($referrer);
        } else {
            redirect('/dashboard/');
        }
    } elseif ($action === 'create') {
        // Create new client from modal
        $clientName = trim($_POST['client_name'] ?? '');
        $timezone = $_POST['timezone'] ?? 'UTC';
        $notes = trim($_POST['notes'] ?? '');
        
        if (!empty($clientName)) {
            $stmt = $db->prepare("INSERT INTO clients (name, timezone, notes) VALUES (?, ?, ?)");
            if ($stmt->execute([$clientName, $timezone, $notes])) {
                $newClientId = $db->lastInsertId();
                $auth->setCurrentClient($newClientId);
            }
        }
        
        // Redirect back to referring page or dashboard
        $referrer = $_SERVER['HTTP_REFERER'] ?? '/dashboard/';
        redirect($referrer);
    }
} else {
    // If not POST, redirect to dashboard
    redirect('/dashboard/');
}