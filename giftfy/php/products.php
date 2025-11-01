<?php
require_once 'config.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch($action) {
    case 'list':
        listProducts();
        break;
    case 'get':
        getProduct();
        break;
    case 'add':
        addProduct();
        break;
    case 'update':
        updateProduct();
        break;
    case 'delete':
        deleteProduct();
        break;
    case 'categories':
        getCategories();
        break;
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

function listProducts() {
    $conn = getDBConnection();
    
    $search = $_GET['search'] ?? '';
    $category = $_GET['category'] ?? '';
    $priceMin = $_GET['price_min'] ?? 0;
    $priceMax = $_GET['price_max'] ?? 999999;

    $sql = "SELECT p.*, c.name as category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.stock > 0";
    
    $params = [];
    
    if (!empty($search)) {
        $sql .= " AND (p.name LIKE ? OR p.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($category)) {
        $sql .= " AND c.name = ?";
        $params[] = $category;
    }
    
    $sql .= " AND p.price BETWEEN ? AND ?";
    $params[] = $priceMin;
    $params[] = $priceMax;
    
    $sql .= " ORDER BY p.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse(['success' => true, 'products' => $products]);
}

function getProduct() {
    $id = $_GET['id'] ?? 0;
    
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT p.*, c.name as category_name 
                            FROM products p 
                            LEFT JOIN categories c ON p.category_id = c.id 
                            WHERE p.id = ?");
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product) {
        jsonResponse(['success' => true, 'product' => $product]);
    } else {
        jsonResponse(['error' => 'Product not found'], 404);
    }
}

function addProduct() {
    requireManager(); // Managers and Admins can add products

    $name = sanitizeInput($_POST['name'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);
    $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $image = $_POST['image'] ?? '🎁';

    if (empty($name) || $price <= 0) {
        jsonResponse(['error' => 'Product name and valid price are required'], 400);
    }

    $conn = getDBConnection();
    $stmt = $conn->prepare("INSERT INTO products (name, description, price, stock, category_id, image) 
                            VALUES (?, ?, ?, ?, ?, ?)");
    
    if ($stmt->execute([$name, $description, $price, $stock, $categoryId, $image])) {
        $id = $conn->lastInsertId();
        logActivity('product_created', 'products', $id);
        jsonResponse(['success' => true, 'message' => 'Product added successfully', 'id' => $id]);
    } else {
        jsonResponse(['error' => 'Failed to add product'], 500);
    }
}

function updateProduct() {
    requireManager(); // Managers and Admins can update products

    $id = (int)($_POST['id'] ?? 0);
    $name = sanitizeInput($_POST['name'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);
    $categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $image = $_POST['image'] ?? '🎁';

    if (empty($name) || $price <= 0 || $id <= 0) {
        jsonResponse(['error' => 'Invalid product data'], 400);
    }

    $conn = getDBConnection();
    $stmt = $conn->prepare("UPDATE products 
                            SET name = ?, description = ?, price = ?, stock = ?, category_id = ?, image = ?, updated_at = NOW()
                            WHERE id = ?");
    
    if ($stmt->execute([$name, $description, $price, $stock, $categoryId, $image, $id])) {
        logActivity('product_updated', 'products', $id);
        jsonResponse(['success' => true, 'message' => 'Product updated successfully']);
    } else {
        jsonResponse(['error' => 'Failed to update product'], 500);
    }
}

function deleteProduct() {
    requireAdmin(); // Only admins can delete products

    $id = (int)($_POST['id'] ?? 0);

    if ($id <= 0) {
        jsonResponse(['error' => 'Invalid product ID'], 400);
    }

    $conn = getDBConnection();
    
    // Check if product has orders
    $stmt = $conn->prepare("SELECT COUNT(*) FROM order_items WHERE product_id = ?");
    $stmt->execute([$id]);
    $orderCount = $stmt->fetchColumn();
    
    if ($orderCount > 0) {
        jsonResponse(['error' => 'Cannot delete product with existing orders'], 400);
    }
    
    $stmt = $conn->prepare("DELETE FROM products WHERE id = ?");
    
    if ($stmt->execute([$id])) {
        logActivity('product_deleted', 'products', $id);
        jsonResponse(['success' => true, 'message' => 'Product deleted successfully']);
    } else {
        jsonResponse(['error' => 'Failed to delete product'], 500);
    }
}

function getCategories() {
    $conn = getDBConnection();
    $stmt = $conn->query("SELECT * FROM categories ORDER BY name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse(['success' => true, 'categories' => $categories]);
}
?>