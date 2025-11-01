<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'giftify_db');

// Create Database Connection
function getDBConnection() {
    try {
        $conn = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS
        );
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return $conn;
    } catch(PDOException $e) {
        die(json_encode(['error' => 'Database connection failed']));
    }
}

// Start Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==================== ROLE-BASED ACCESS CONTROL ====================

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

function getUserRole() {
    return $_SESSION['role'] ?? 'customer';
}

function isAdmin() {
    return isLoggedIn() && getUserRole() === 'admin';
}

function isManager() {
    return isLoggedIn() && (getUserRole() === 'admin' || getUserRole() === 'manager');
}

function isCustomer() {
    return isLoggedIn() && getUserRole() === 'customer';
}

function requireAuth() {
    if (!isLoggedIn()) {
        jsonResponse(['error' => 'Authentication required'], 401);
    }
}

function requireAdmin() {
    requireAuth();
    if (!isAdmin()) {
        jsonResponse(['error' => 'Admin access required'], 403);
    }
}

function requireManager() {
    requireAuth();
    if (!isManager()) {
        jsonResponse(['error' => 'Manager or Admin access required'], 403);
    }
}

function hasPermission($permission) {
    $role = getUserRole();
    $permissions = [
        'admin' => ['all'],
        'manager' => ['view_orders', 'update_orders', 'view_products', 'update_products', 'view_users'],
        'customer' => ['view_own_orders', 'manage_own_cart']
    ];
    
    return isset($permissions[$role]) && 
           (in_array('all', $permissions[$role]) || in_array($permission, $permissions[$role]));
}

// ==================== ACTIVITY LOGGING ====================

function logActivity($action, $tableName = null, $recordId = null, $details = null) {
    if (!isLoggedIn()) return;
    
    try {
        $conn = getDBConnection();
        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, table_name, record_id, details, ip_address) 
                                VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_SESSION['user_id'],
            $action,
            $tableName,
            $recordId,
            $details ? json_encode($details) : null,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (Exception $e) {
        // Silently fail logging to not break main functionality
        error_log("Activity log error: " . $e->getMessage());
    }
}

// ==================== HELPER FUNCTIONS ====================

function redirect($url) {
    header("Location: $url");
    exit();
}

function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function formatCurrency($amount) {
    return '$' . number_format((float)$amount, 2, '.', ',');
}

function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

function formatDateTime($date) {
    return date('M d, Y h:i A', strtotime($date));
}
?>