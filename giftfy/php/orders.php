<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    jsonResponse(['error' => 'Please login first'], 401);
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch($action) {
    case 'create':
        createOrder();
        break;
    case 'list':
        listOrders();
        break;
    case 'get':
        getOrder();
        break;
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

function createOrder() {
    $conn = getDBConnection();
    $userId = $_SESSION['user_id'];
    
    // Get shipping details
    $shippingName = $_POST['shipping_name'] ?? '';
    $shippingEmail = $_POST['shipping_email'] ?? '';
    $shippingAddress = $_POST['shipping_address'] ?? '';
    $shippingCity = $_POST['shipping_city'] ?? '';
    $shippingZip = $_POST['shipping_zip'] ?? '';
    
    if (empty($shippingName) || empty($shippingEmail) || empty($shippingAddress)) {
        jsonResponse(['error' => 'All shipping fields are required'], 400);
    }
    
    // Get cart items
    $stmt = $conn->prepare("SELECT c.*, p.name, p.price 
                            FROM cart c 
                            JOIN products p ON c.product_id = p.id 
                            WHERE c.user_id = ?");
    $stmt->execute([$userId]);
    $cartItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($cartItems)) {
        jsonResponse(['error' => 'Cart is empty'], 400);
    }
    
    // Calculate total
    $total = 0;
    foreach ($cartItems as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    $total += 5.00; // Shipping cost
    
    try {
        $conn->beginTransaction();
        
        // Create order
        $stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, shipping_name, shipping_email, shipping_address, shipping_city, shipping_zip) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $total, $shippingName, $shippingEmail, $shippingAddress, $shippingCity, $shippingZip]);
        $orderId = $conn->lastInsertId();
        
        // Add order items
        $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, price) 
                                VALUES (?, ?, ?, ?, ?)");
        
        foreach ($cartItems as $item) {
            $stmt->execute([$orderId, $item['product_id'], $item['name'], $item['quantity'], $item['price']]);
            
            // Update product stock
            $updateStmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
            $updateStmt->execute([$item['quantity'], $item['product_id']]);
        }
        
        // Clear cart
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        $conn->commit();
        
        jsonResponse([
            'success' => true,
            'message' => 'Order placed successfully',
            'order_id' => $orderId
        ]);
        
    } catch (Exception $e) {
        $conn->rollBack();
        jsonResponse(['error' => 'Failed to create order: ' . $e->getMessage()], 500);
    }
}

function listOrders() {
    $conn = getDBConnection();
    $userId = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse(['success' => true, 'orders' => $orders]);
}

function getOrder() {
    $conn = getDBConnection();
    $userId = $_SESSION['user_id'];
    $orderId = $_GET['order_id'] ?? 0;
    
    $stmt = $conn->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$orderId, $userId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        jsonResponse(['error' => 'Order not found'], 404);
    }
    
    // Get order items
    $stmt = $conn->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse(['success' => true, 'order' => $order]);
}
?>