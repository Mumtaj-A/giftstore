<?php
require_once 'config.php';

header('Content-Type: application/json');

// Role-based access: Admin and Manager can access admin panel
requireManager();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch($action) {
    case 'dashboard':
        getDashboard();
        break;
    case 'orders':
        getAllOrders();
        break;
    case 'update_order':
        updateOrderStatus();
        break;
    case 'users':
        getAllUsers();
        break;
    case 'delete_user':
        deleteUser();
        break;
    case 'update_user_role':
        updateUserRole();
        break;
    case 'categories':
        manageCategories();
        break;
    case 'reports':
        getReports();
        break;
    case 'activity_logs':
        getActivityLogs();
        break;
    case 'analytics':
        getAnalytics();
        break;
    default:
        jsonResponse(['error' => 'Invalid action'], 400);
}

function getDashboard() {
    $conn = getDBConnection();
    
    // Get stats
    $totalOrders = $conn->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    $totalUsers = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
    $totalProducts = $conn->query("SELECT COUNT(*) FROM products")->fetchColumn();
    $totalRevenue = $conn->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status != 'Cancelled'")->fetchColumn();
    
    // Recent orders (last 7 days)
    $recentOrders = $conn->query("SELECT COUNT(*) FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
    
    // Pending orders
    $pendingOrders = $conn->query("SELECT COUNT(*) FROM orders WHERE status = 'Pending'")->fetchColumn();
    
    // Low stock products (stock < 10)
    $lowStock = $conn->query("SELECT COUNT(*) FROM products WHERE stock < 10")->fetchColumn();
    
    // Today's revenue
    $todayRevenue = $conn->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE DATE(created_at) = CURDATE() AND status != 'Cancelled'")->fetchColumn();
    
    jsonResponse([
        'success' => true,
        'stats' => [
            'total_orders' => (int)$totalOrders,
            'total_users' => (int)$totalUsers,
            'total_products' => (int)$totalProducts,
            'total_revenue' => number_format((float)$totalRevenue, 2),
            'recent_orders' => (int)$recentOrders,
            'pending_orders' => (int)$pendingOrders,
            'low_stock' => (int)$lowStock,
            'today_revenue' => number_format((float)$todayRevenue, 2)
        ]
    ]);
}

function getAllOrders() {
    $conn = getDBConnection();
    
    $stmt = $conn->query("SELECT o.*, u.name as customer_name, u.email as customer_email 
                         FROM orders o 
                         LEFT JOIN users u ON o.user_id = u.id 
                         ORDER BY o.created_at DESC");
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse(['success' => true, 'orders' => $orders]);
}

function updateOrderStatus() {
    $orderId = $_POST['order_id'] ?? 0;
    $status = $_POST['status'] ?? '';
    
    $validStatuses = ['Pending', 'Shipped', 'Delivered', 'Cancelled'];
    if (!in_array($status, $validStatuses)) {
        jsonResponse(['error' => 'Invalid status'], 400);
    }
    
    $conn = getDBConnection();
    
    // Get old status for logging
    $stmt = $conn->prepare("SELECT status FROM orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $oldStatus = $stmt->fetchColumn();
    
    $stmt = $conn->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
    
    if ($stmt->execute([$status, $orderId])) {
        logActivity('order_status_updated', 'orders', $orderId, [
            'old_status' => $oldStatus,
            'new_status' => $status
        ]);
        jsonResponse(['success' => true, 'message' => 'Order status updated']);
    } else {
        jsonResponse(['error' => 'Failed to update order'], 500);
    }
}

function getAllUsers() {
    $conn = getDBConnection();
    
    // Only admins can see all users (including other admins)
    if (isAdmin()) {
        $stmt = $conn->query("SELECT u.id, u.name, u.email, u.role, u.is_active, u.created_at, 
                             COUNT(o.id) as order_count,
                             COALESCE(SUM(o.total_amount), 0) as total_spent
                             FROM users u 
                             LEFT JOIN orders o ON u.id = o.user_id 
                             GROUP BY u.id 
                             ORDER BY u.created_at DESC");
    } else {
        // Managers can only see customers
        $stmt = $conn->query("SELECT u.id, u.name, u.email, u.role, u.is_active, u.created_at, 
                             COUNT(o.id) as order_count,
                             COALESCE(SUM(o.total_amount), 0) as total_spent
                             FROM users u 
                             LEFT JOIN orders o ON u.id = o.user_id 
                             WHERE u.role = 'customer'
                             GROUP BY u.id 
                             ORDER BY u.created_at DESC");
    }
    
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse(['success' => true, 'users' => $users]);
}

function updateUserRole() {
    requireAdmin(); // Only admins can change roles
    
    $userId = $_POST['user_id'] ?? 0;
    $role = $_POST['role'] ?? '';
    
    $validRoles = ['admin', 'manager', 'customer'];
    if (!in_array($role, $validRoles)) {
        jsonResponse(['error' => 'Invalid role'], 400);
    }
    
    // Prevent self-demotion
    if ($userId == $_SESSION['user_id'] && $role != 'admin') {
        jsonResponse(['error' => 'Cannot change your own admin role'], 400);
    }
    
    $conn = getDBConnection();
    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
    
    if ($stmt->execute([$role, $userId])) {
        logActivity('user_role_updated', 'users', $userId, ['new_role' => $role]);
        jsonResponse(['success' => true, 'message' => 'User role updated successfully']);
    } else {
        jsonResponse(['error' => 'Failed to update user role'], 500);
    }
}

function deleteUser() {
    requireAdmin(); // Only admins can delete users
    
    $userId = $_POST['user_id'] ?? 0;
    
    // Prevent self-deletion
    if ($userId == $_SESSION['user_id']) {
        jsonResponse(['error' => 'Cannot delete your own account'], 400);
    }
    
    $conn = getDBConnection();
    
    // Check if user is admin
    $stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if ($user && $user['role'] === 'admin' && !isAdmin()) {
        jsonResponse(['error' => 'Only admins can delete admin users'], 403);
    }
    
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'admin' OR ? = 1");
    
    if ($stmt->execute([$userId, isAdmin() ? 1 : 0])) {
        logActivity('user_deleted', 'users', $userId);
        jsonResponse(['success' => true, 'message' => 'User deleted successfully']);
    } else {
        jsonResponse(['error' => 'Failed to delete user'], 500);
    }
}

function manageCategories() {
    $conn = getDBConnection();
    $subAction = $_POST['sub_action'] ?? $_GET['sub_action'] ?? 'list';
    
    switch($subAction) {
        case 'list':
            $stmt = $conn->query("SELECT c.*, COUNT(p.id) as product_count 
                                 FROM categories c 
                                 LEFT JOIN products p ON c.id = p.category_id 
                                 GROUP BY c.id 
                                 ORDER BY c.name");
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            jsonResponse(['success' => true, 'categories' => $categories]);
            break;
            
        case 'add':
            requireAdmin(); // Only admins can add categories
            $name = sanitizeInput($_POST['name'] ?? '');
            $icon = $_POST['icon'] ?? '🎁';
            
            if (empty($name)) {
                jsonResponse(['error' => 'Category name is required'], 400);
            }
            
            $stmt = $conn->prepare("INSERT INTO categories (name, icon) VALUES (?, ?)");
            if ($stmt->execute([$name, $icon])) {
                $id = $conn->lastInsertId();
                logActivity('category_created', 'categories', $id);
                jsonResponse(['success' => true, 'message' => 'Category added successfully', 'id' => $id]);
            } else {
                jsonResponse(['error' => 'Failed to add category'], 500);
            }
            break;
            
        case 'update':
            requireAdmin();
            $id = $_POST['id'] ?? 0;
            $name = sanitizeInput($_POST['name'] ?? '');
            $icon = $_POST['icon'] ?? '🎁';
            
            $stmt = $conn->prepare("UPDATE categories SET name = ?, icon = ? WHERE id = ?");
            if ($stmt->execute([$name, $icon, $id])) {
                logActivity('category_updated', 'categories', $id);
                jsonResponse(['success' => true, 'message' => 'Category updated successfully']);
            } else {
                jsonResponse(['error' => 'Failed to update category'], 500);
            }
            break;
            
        case 'delete':
            requireAdmin();
            $id = $_POST['id'] ?? 0;
            
            // Check if category has products
            $stmt = $conn->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
            $stmt->execute([$id]);
            $productCount = $stmt->fetchColumn();
            
            if ($productCount > 0) {
                jsonResponse(['error' => 'Cannot delete category with products. Remove products first.'], 400);
            }
            
            $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
            if ($stmt->execute([$id])) {
                logActivity('category_deleted', 'categories', $id);
                jsonResponse(['success' => true, 'message' => 'Category deleted successfully']);
            } else {
                jsonResponse(['error' => 'Failed to delete category'], 500);
            }
            break;
    }
}

function getReports() {
    requireAdmin(); // Only admins can view reports
    
    $conn = getDBConnection();
    $type = $_GET['type'] ?? 'sales';
    $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    
    switch($type) {
        case 'sales':
            $stmt = $conn->prepare("SELECT DATE(created_at) as date, 
                                   COUNT(*) as order_count,
                                   SUM(total_amount) as revenue
                                   FROM orders 
                                   WHERE created_at BETWEEN ? AND ? 
                                   AND status != 'Cancelled'
                                   GROUP BY DATE(created_at)
                                   ORDER BY date");
            $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
            $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            jsonResponse(['success' => true, 'report' => $sales, 'type' => 'sales']);
            break;
            
        case 'products':
            $stmt = $conn->query("SELECT p.name, SUM(oi.quantity) as total_sold, 
                                 SUM(oi.quantity * oi.price) as revenue
                                 FROM order_items oi
                                 JOIN products p ON oi.product_id = p.id
                                 JOIN orders o ON oi.order_id = o.id
                                 WHERE o.status != 'Cancelled'
                                 GROUP BY p.id
                                 ORDER BY total_sold DESC
                                 LIMIT 20");
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            jsonResponse(['success' => true, 'report' => $products, 'type' => 'products']);
            break;
            
        default:
            jsonResponse(['error' => 'Invalid report type'], 400);
    }
}

function getActivityLogs() {
    requireAdmin(); // Only admins can view activity logs
    
    $limit = (int)($_GET['limit'] ?? 50);
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("SELECT al.*, u.name as user_name, u.email as user_email
                           FROM activity_logs al
                           LEFT JOIN users u ON al.user_id = u.id
                           ORDER BY al.created_at DESC
                           LIMIT ?");
    $stmt->execute([$limit]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse(['success' => true, 'logs' => $logs]);
}

function getAnalytics() {
    $conn = getDBConnection();
    
    // Sales by month (last 6 months)
    $stmt = $conn->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as month,
                         COUNT(*) as orders,
                         SUM(total_amount) as revenue
                         FROM orders
                         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                         AND status != 'Cancelled'
                         GROUP BY month
                         ORDER BY month");
    $monthlySales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Orders by status
    $stmt = $conn->query("SELECT status, COUNT(*) as count
                         FROM orders
                         GROUP BY status");
    $statusDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Top customers
    $stmt = $conn->query("SELECT u.name, u.email, COUNT(o.id) as order_count,
                         SUM(o.total_amount) as total_spent
                         FROM users u
                         JOIN orders o ON u.id = o.user_id
                         WHERE o.status != 'Cancelled'
                         GROUP BY u.id
                         ORDER BY total_spent DESC
                         LIMIT 10");
    $topCustomers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    jsonResponse([
        'success' => true,
        'analytics' => [
            'monthly_sales' => $monthlySales,
            'status_distribution' => $statusDistribution,
            'top_customers' => $topCustomers
        ]
    ]);
}
?>