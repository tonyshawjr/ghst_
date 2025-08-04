<?php
/**
 * Authentication Class
 */
class Auth {
    private $db;
    private $sessionTimeout = 3600; // 1 hour
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->startSession();
    }
    
    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_name(SESSION_NAME);
            session_start();
        }
    }
    
    public function login($email, $password) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            $this->setUserSession($user);
            $this->logAction('login', 'User logged in');
            return true;
        }
        
        return false;
    }
    
    public function loginWithGoogle($googleId, $email, $name) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE google_id = ? OR email = ?");
        $stmt->execute([$googleId, $email]);
        $user = $stmt->fetch();
        
        if ($user) {
            if (!$user['google_id']) {
                $updateStmt = $this->db->prepare("UPDATE users SET google_id = ? WHERE id = ?");
                $updateStmt->execute([$googleId, $user['id']]);
            }
            $this->setUserSession($user);
            $this->logAction('google_login', 'User logged in with Google');
            return true;
        }
        
        return false;
    }
    
    private function setUserSession($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        $this->updateSession();
    }
    
    private function updateSession() {
        $sessionId = session_id();
        $userId = $_SESSION['user_id'] ?? null;
        $clientId = $_SESSION['client_id'] ?? null;
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $payload = json_encode($_SESSION);
        $lastActivity = time();
        
        $stmt = $this->db->prepare("
            INSERT INTO sessions (id, user_id, client_id, ip_address, user_agent, payload, last_activity)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            user_id = VALUES(user_id),
            client_id = VALUES(client_id),
            ip_address = VALUES(ip_address),
            user_agent = VALUES(user_agent),
            payload = VALUES(payload),
            last_activity = VALUES(last_activity)
        ");
        
        $stmt->execute([$sessionId, $userId, $clientId, $ipAddress, $userAgent, $payload, $lastActivity]);
    }
    
    public function logout() {
        $this->logAction('logout', 'User logged out');
        
        session_unset();
        session_destroy();
        
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
    }
    
    public function isLoggedIn() {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        if (time() - $_SESSION['last_activity'] > $this->sessionTimeout) {
            $this->logout();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: /login.php');
            exit;
        }
    }
    
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'email' => $_SESSION['user_email'],
            'name' => $_SESSION['user_name']
        ];
    }
    
    public function getCurrentClient() {
        if (!isset($_SESSION['client_id'])) {
            return null;
        }
        
        $stmt = $this->db->prepare("SELECT * FROM clients WHERE id = ? AND is_active = 1");
        $stmt->execute([$_SESSION['client_id']]);
        return $stmt->fetch();
    }
    
    public function setCurrentClient($clientId) {
        $stmt = $this->db->prepare("SELECT * FROM clients WHERE id = ? AND is_active = 1");
        $stmt->execute([$clientId]);
        $client = $stmt->fetch();
        
        if ($client) {
            $_SESSION['client_id'] = $clientId;
            $_SESSION['client_name'] = $client['name'];
            $_SESSION['client_timezone'] = $client['timezone'];
            $this->updateSession();
            $this->logAction('switch_client', "Switched to client: {$client['name']}", $clientId);
            return true;
        }
        
        return false;
    }
    
    public function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    public function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    private function logAction($action, $description, $clientId = null) {
        $userId = $_SESSION['user_id'] ?? null;
        $clientId = $clientId ?? ($_SESSION['client_id'] ?? null);
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $stmt = $this->db->prepare("
            INSERT INTO user_actions (user_id, client_id, action_type, description, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([$userId, $clientId, $action, $description, $ipAddress, $userAgent]);
    }
    
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}