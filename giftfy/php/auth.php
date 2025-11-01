<?php
require_once 'config.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch($action) {
    case 'register':
        register();
        break;
    case 'login':
        login();
        break;
    case 'logout':
        logout();
        break;
    case 'check':
        checkAuth();
        break;
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

function register() {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($name) || empty($email) || empty($password)) {
        jsonResponse(['error' => 'All fields are required'], 400);
    }

    $conn = getDBConnection();
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        jsonResponse(['error' => 'Email already exists'], 400);
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert user
    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'customer')");
    if ($stmt->execute([$name, $email, $hashedPassword])) {
        $userId = $conn->lastInsertId();
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_name'] = $name;
        $_SESSION['user_email'] = $email;
        $_SESSION['role'] = 'customer';
        $_SESSION['is_admin'] = false;

        logActivity('user_registered', 'users', $userId);

        jsonResponse([
            'success' => true,
            'message' => 'Registration successful',
            'user' => [
                'id' => $userId,
                'name' => $name,
                'email' => $email,
                'role' => 'customer',
                'is_admin' => false
            ]
        ]);
    } else {
        jsonResponse(['error' => 'Registration failed'], 500);
    }
}

function login() {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        jsonResponse(['error' => 'Email and password are required'], 400);
    }

    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id, name, email, password, role, is_active FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        if (!$user['is_active']) {
            jsonResponse(['error' => 'Account is deactivated. Please contact support.'], 403);
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['is_admin'] = ($user['role'] === 'admin');

        logActivity('user_login', 'users', $user['id']);

        jsonResponse([
            'success' => true,
            'message' => 'Login successful',
            'user' => [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
                'is_admin' => ($user['role'] === 'admin')
            ]
        ]);
    } else {
        jsonResponse(['error' => 'Invalid email or password'], 401);
    }
}

function logout() {
    if (isLoggedIn()) {
        logActivity('user_logout', 'users', $_SESSION['user_id']);
    }
    session_destroy();
    jsonResponse(['success' => true, 'message' => 'Logged out successfully']);
}

function checkAuth() {
    if (isLoggedIn()) {
        jsonResponse([
            'logged_in' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'name' => $_SESSION['user_name'],
                'email' => $_SESSION['user_email'],
                'role' => $_SESSION['role'],
                'is_admin' => $_SESSION['is_admin']
            ]
        ]);
    } else {
        jsonResponse(['logged_in' => false]);
    }
}
?>