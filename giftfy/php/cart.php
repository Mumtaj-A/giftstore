<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['error' => 'Please login first'], 401);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch($action) {
    case 'list':
        listCart();
        break;
    case 'add':
        addToCart();
        break;
    case 'update':
        updateCart();
        break;
    case 'remove':
        removeFromCart();
        break;
    case 'clear':
        clearCart();
        break;
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

function listCart() {
    $conn = getDBConnection();
    $userId = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("SELECT c.*, p.name, p.price, p.image, p.stock 
                            FROM cart c 
                            JOIN products p ON c.product_id = p.id 
                            WHERE c.user_id = ?");
    $stmt->execute([$userId]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse(['success' => true, 'cart' => $items]);
}

function addToCart() {
    $conn = getDBConnection();
    $userId = $_SESSION['user_id'];
    $productId = $_POST['product_id'] ?? 0;
    $quantity = $_POST['quantity'] ?? 1;
    
    // Check if product exists and has stock
    $stmt = $conn->prepare("SELECT stock FROM products WHERE id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product || $product['stock'] < $quantity) {
        jsonResponse(['error' => 'Product not available'], 400);
    }
    
    // Check if already in cart
    $stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$userId, $productId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Update quantity
        $newQuantity = $existing['quantity'] + $quantity;
        $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
        $stmt->execute([$newQuantity, $existing['id']]);
    } else {
        // Insert new
        $stmt = $conn->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $productId, $quantity]);
    }
    
    jsonResponse(['success' => true, 'message' => 'Added to cart']);
}

function updateCart() {
    $conn = getDBConnection();
    $userId = $_SESSION['user_id'];
    $productId = $_POST['product_id'] ?? 0;
    $quantity = $_POST['quantity'] ?? 1;
    
    if ($quantity <= 0) {
        removeFromCart();
        return;
    }
    
    $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
    if ($stmt->execute([$quantity, $userId, $productId])) {
        jsonResponse(['success' => true, 'message' => 'Cart updated']);
    } else {
        jsonResponse(['error' => 'Failed to update cart'], 500);
    }
}

function removeFromCart() {
    $conn = getDBConnection();
    $userId = $_SESSION['user_id'];
    $productId = $_POST['product_id'] ?? 0;
    
    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ? AND product_id = ?");
    if ($stmt->execute([$userId, $productId])) {
        jsonResponse(['success' => true, 'message' => 'Item removed']);
    } else {
        jsonResponse(['error' => 'Failed to remove item'], 500);
    }
}

function clearCart() {
    $conn = getDBConnection();
    $userId = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    if ($stmt->execute([$userId])) {
        jsonResponse(['success' => true, 'message' => 'Cart cleared']);
    } else {
        jsonResponse(['error' => 'Failed to clear cart'], 500);
    }
}
?>