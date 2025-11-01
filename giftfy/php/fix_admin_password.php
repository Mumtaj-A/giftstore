<?php
/**
 * Fix Admin Password Script
 * 
 * This script will update the admin and manager passwords to 'admin123'
 * Run this once after setting up the database
 * 
 * Usage: Access via browser: http://your-domain/giftfy/php/fix_admin_password.php
 * Or via command line: php fix_admin_password.php
 */

require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');

try {
    $conn = getDBConnection();

    // Generate password hash for 'admin123'
    $password = 'admin123';
    $hash = password_hash($password, PASSWORD_DEFAULT);

    // Check if users exist
    $stmt = $conn->prepare("SELECT email FROM users WHERE email IN ('admin@giftify.com', 'manager@giftify.com')");
    $stmt->execute();
    $existingUsers = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $results = [];

    // Update or create admin
    if (in_array('admin@giftify.com', $existingUsers)) {
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = 'admin@giftify.com'");
        $stmt->execute([$hash]);
        $results[] = "✅ Admin password updated";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['Admin User', 'admin@giftify.com', $hash, 'admin']);
        $results[] = "✅ Admin user created";
    }

    // Update or create manager
    if (in_array('manager@giftify.com', $existingUsers)) {
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = 'manager@giftify.com'");
        $stmt->execute([$hash]);
        $results[] = "✅ Manager password updated";
    } else {
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['Manager User', 'manager@giftify.com', $hash, 'manager']);
        $results[] = "✅ Manager user created";
    }

    echo "<!DOCTYPE html><html><head><title>Password Fix</title>";
    echo "<style>body{font-family:Arial;padding:40px;max-width:600px;margin:0 auto;background:#f5f5f5;}";
    echo ".success{background:white;padding:20px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1);}";
    echo "h1{color:#e91e63;} .btn{display:inline-block;padding:10px 20px;background:#e91e63;color:white;text-decoration:none;border-radius:5px;margin-top:20px;}</style></head><body>";
    echo "<div class='success'>";
    echo "<h1>✅ Password Fix Complete!</h1>";
    foreach ($results as $result) {
        echo "<p>$result</p>";
    }
    echo "<hr style='margin:20px 0;'>";
    echo "<h3>Login Credentials:</h3>";
    echo "<p><strong>Admin:</strong><br>Email: admin@giftify.com<br>Password: admin123</p>";
    echo "<p><strong>Manager:</strong><br>Email: manager@giftify.com<br>Password: admin123</p>";
    echo "<a href='../index.html' class='btn'>Go to Homepage</a>";
    echo "</div></body></html>";

} catch (Exception $e) {
    echo "<!DOCTYPE html><html><head><title>Error</title>";
    echo "<style>body{font-family:Arial;padding:40px;max-width:600px;margin:0 auto;}";
    echo ".error{background:#fee;padding:20px;border-radius:10px;border:2px solid #f00;}</style></head><body>";
    echo "<div class='error'>";
    echo "<h2>❌ Error occurred:</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p>Please check your database connection in php/config.php</p>";
    echo "</div></body></html>";
}

