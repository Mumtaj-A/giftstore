// API Base URL - Update this to match your server
const API_BASE = 'php/';

// State Management
let state = {
    currentUser: null,
    isAdmin: false,
    cart: [],
    products: [],
    categories: [],
    orders: []
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    checkAuth();
    loadCategories();
    loadProducts();
    showPage('home');
});

// ==================== API CALLS ====================

// Authentication
async function checkAuth() {
    try {
        const response = await fetch(`${API_BASE}auth.php?action=check`);
        const data = await response.json();
        
        if (data.logged_in) {
            state.currentUser = data.user;
            state.isAdmin = data.user.is_admin;
            updateAuthUI();
            loadCart();
        }
    } catch (error) {
        console.error('Auth check failed:', error);
    }
}

async function handleAuthSubmit(e) {
    e.preventDefault();
    
    const email = document.getElementById('authEmail').value.trim();
    const password = document.getElementById('authPassword').value.trim();
    const name = document.getElementById('authName').value;
    
    const action = isRegisterMode ? 'register' : 'login';
    const formData = new FormData();
    formData.append('action', action);
    formData.append('email', email);
    formData.append('password', password);
    
    if (isRegisterMode) {
        formData.append('name', name);
    }
    
    try {
        const response = await fetch(`${API_BASE}auth.php`, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            state.currentUser = data.user;
            state.isAdmin = data.user.is_admin;
            updateAuthUI();
            
            if (state.isAdmin) {
                showPage('admin');
                loadAdminData();
            } else {
                showPage('home');
                loadCart();
            }
            
            alert(data.message);
        } else {
            alert(data.error || 'Authentication failed');
        }
    } catch (error) {
        console.error('Auth error:', error);
        alert('An error occurred. Please try again.');
    }
}

async function logout() {
    try {
        await fetch(`${API_BASE}auth.php?action=logout`);
        state.currentUser = null;
        state.isAdmin = false;
        state.cart = [];
        updateAuthUI();
        updateCartCount();
        showPage('home');
        alert('Logged out successfully');
    } catch (error) {
        console.error('Logout error:', error);
    }
}

// Products
async function loadProducts() {
    try {
        const search = document.getElementById('searchBar')?.value || '';
        const category = document.getElementById('categoryFilter')?.value || '';
        const priceRange = document.getElementById('priceFilter')?.value || '';
        
        let url = `${API_BASE}products.php?action=list`;
        if (search) url += `&search=${encodeURIComponent(search)}`;
        if (category) url += `&category=${encodeURIComponent(category)}`;
        
        if (priceRange) {
            const [min, max] = priceRange === '100+' ? [100, 999999] : priceRange.split('-').map(Number);
            url += `&price_min=${min}&price_max=${max || 999999}`;
        }
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success) {
            state.products = data.products;
            renderProducts();
            renderFeaturedProducts();
        }
    } catch (error) {
        console.error('Error loading products:', error);
    }
}

async function loadCategories() {
    try {
        const response = await fetch(`${API_BASE}products.php?action=categories`);
        const data = await response.json();
        
        if (data.success) {
            state.categories = data.categories;
            renderCategories();
            populateCategoryFilters();
        }
    } catch (error) {
        console.error('Error loading categories:', error);
    }
}

// Cart
async function loadCart() {
    if (!state.currentUser) return;
    
    try {
        const response = await fetch(`${API_BASE}cart.php?action=list`);
        const data = await response.json();
        
        if (data.success) {
            state.cart = data.cart;
            updateCartCount();
        }
    } catch (error) {
        console.error('Error loading cart:', error);
    }
}

async function addToCart(productId) {
    if (!state.currentUser) {
        alert('Please login to add items to cart');
        showPage('auth');
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('action', 'add');
        formData.append('product_id', productId);
        formData.append('quantity', 1);
        
        const response = await fetch(`${API_BASE}cart.php`, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            await loadCart();
            alert('Added to cart!');
        } else {
            alert(data.error || 'Failed to add to cart');
        }
    } catch (error) {
        console.error('Error adding to cart:', error);
        alert('An error occurred. Please try again.');
    }
}

async function updateQuantity(productId, change) {
    const item = state.cart.find(i => i.product_id === productId);
    if (!item) return;
    
    const newQuantity = item.quantity + change;
    
    if (newQuantity <= 0) {
        await removeFromCart(productId);
        return;
    }
    
    try {
        const formData = new FormData();
        formData.append('action', 'update');
        formData.append('product_id', productId);
        formData.append('quantity', newQuantity);
        
        const response = await fetch(`${API_BASE}cart.php`, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            await loadCart();
            renderCart();
        }
    } catch (error) {
        console.error('Error updating cart:', error);
    }
}

async function removeFromCart(productId) {
    if (!confirm('Remove this item from cart?')) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'remove');
        formData.append('product_id', productId);
        
        const response = await fetch(`${API_BASE}cart.php`, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            await loadCart();
            renderCart();
        }
    } catch (error) {
        console.error('Error removing from cart:', error);
    }
}

// Orders
async function placeOrder(e) {
    e.preventDefault();
    
    if (!state.currentUser) {
        alert('Please login to place an order');
        showPage('auth');
        return;
    }
    
    if (state.cart.length === 0) {
        alert('Your cart is empty');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'create');
    formData.append('shipping_name', document.getElementById('shippingName').value);
    formData.append('shipping_email', document.getElementById('shippingEmail').value);
    formData.append('shipping_address', document.getElementById('shippingAddress').value);
    formData.append('shipping_city', document.getElementById('shippingCity').value);
    formData.append('shipping_zip', document.getElementById('shippingZip').value);
    
    try {
        const response = await fetch(`${API_BASE}orders.php`, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(`Order placed successfully! Order ID: ${data.order_id}\n\nA confirmation email will be sent to you shortly.`);
            await loadCart();
            document.getElementById('checkoutForm').reset();
            showPage('home');
        } else {
            alert(data.error || 'Failed to place order');
        }
    } catch (error) {
        console.error('Error placing order:', error);
        alert('An error occurred. Please try again.');
    }
}

// Admin Functions
async function loadAdminData() {
    updateAdminUserInfo();
    await loadAdminDashboard();
    await loadAdminProducts();
    await loadAdminOrders();
    await loadAdminUsers();
}

function updateAdminUserInfo() {
    if (state.currentUser) {
        document.getElementById('adminUserName').textContent = state.currentUser.name;
        const roleText = state.currentUser.role === 'admin' ? 'Administrator' : 
                        state.currentUser.role === 'manager' ? 'Manager' : 'Customer';
        document.getElementById('adminUserRole').textContent = roleText;
        
        // Show/hide admin-only features
        if (state.currentUser.role === 'admin') {
            document.getElementById('adminReportsLink').style.display = 'block';
            document.getElementById('adminLogsLink').style.display = 'block';
            const addCategoryBtn = document.getElementById('adminAddCategoryBtn');
            if (addCategoryBtn) addCategoryBtn.style.display = 'block';
        }
    }
}

async function loadAdminDashboard() {
    try {
        const response = await fetch(`${API_BASE}admin.php?action=dashboard`);
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('totalOrders').textContent = data.stats.total_orders;
            document.getElementById('totalUsers').textContent = data.stats.total_users;
            document.getElementById('totalProducts').textContent = data.stats.total_products;
            document.getElementById('totalRevenue').textContent = `$${data.stats.total_revenue}`;
            
            // Update new dashboard stats
            if (data.stats.recent_orders !== undefined) {
                document.getElementById('recentOrders').textContent = data.stats.recent_orders;
            }
            if (data.stats.pending_orders !== undefined) {
                document.getElementById('pendingOrders').textContent = data.stats.pending_orders;
            }
            if (data.stats.low_stock !== undefined) {
                document.getElementById('lowStock').textContent = data.stats.low_stock;
            }
            if (data.stats.today_revenue !== undefined) {
                document.getElementById('todayRevenue').textContent = `$${data.stats.today_revenue}`;
            }
        }
    } catch (error) {
        console.error('Error loading dashboard:', error);
    }
}

async function loadAdminProducts() {
    try {
        const response = await fetch(`${API_BASE}products.php?action=list`);
        const data = await response.json();
        
        if (data.success) {
            renderAdminProducts(data.products);
        }
    } catch (error) {
        console.error('Error loading admin products:', error);
    }
}

async function loadAdminOrders() {
    try {
        const response = await fetch(`${API_BASE}admin.php?action=orders`);
        const data = await response.json();
        
        if (data.success) {
            renderAdminOrders(data.orders);
        }
    } catch (error) {
        console.error('Error loading admin orders:', error);
    }
}

async function loadAdminUsers() {
    try {
        const response = await fetch(`${API_BASE}admin.php?action=users`);
        const data = await response.json();
        
        if (data.success) {
            renderAdminUsers(data.users);
        }
    } catch (error) {
        console.error('Error loading admin users:', error);
    }
}

async function saveProduct(e) {
    e.preventDefault();
    
    const productId = document.getElementById('productId').value;
    const action = productId ? 'update' : 'add';
    
    const formData = new FormData();
    formData.append('action', action);
    if (productId) formData.append('id', productId);
    formData.append('name', document.getElementById('productName').value);
    formData.append('category_id', document.getElementById('productCategoryId').value);
    formData.append('price', document.getElementById('productPrice').value);
    formData.append('stock', document.getElementById('productStock').value);
    formData.append('description', document.getElementById('productDescription').value);
    formData.append('image', document.getElementById('productImage').value);
    
    try {
        const response = await fetch(`${API_BASE}products.php`, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(data.message);
            closeModal('adminProductModal');
            await loadAdminProducts();
            await loadAdminDashboard();
        } else {
            alert(data.error || 'Failed to save product');
        }
    } catch (error) {
        console.error('Error saving product:', error);
        alert('An error occurred. Please try again.');
    }
}

async function deleteProduct(id) {
    if (!confirm('Are you sure you want to delete this product?')) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);
        
        const response = await fetch(`${API_BASE}products.php`, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Product deleted successfully');
            await loadAdminProducts();
            await loadAdminDashboard();
        }
    } catch (error) {
        console.error('Error deleting product:', error);
    }
}

async function updateOrderStatus(orderId, status) {
    try {
        const formData = new FormData();
        formData.append('action', 'update_order');
        formData.append('order_id', orderId);
        formData.append('status', status);
        
        const response = await fetch(`${API_BASE}admin.php`, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(`Order #${orderId} status updated to ${status}`);
            await loadAdminOrders();
        }
    } catch (error) {
        console.error('Error updating order:', error);
    }
}

async function updateUserRole(userId, role) {
    try {
        const formData = new FormData();
        formData.append('action', 'update_user_role');
        formData.append('user_id', userId);
        formData.append('role', role);
        
        const response = await fetch(`${API_BASE}admin.php`, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('User role updated successfully');
            await loadAdminUsers();
        } else {
            alert(data.error || 'Failed to update user role');
        }
    } catch (error) {
        console.error('Error updating user role:', error);
        alert('An error occurred. Please try again.');
    }
}

async function deleteUser(userId) {
    if (!confirm('Are you sure you want to delete this user?')) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'delete_user');
        formData.append('user_id', userId);
        
        const response = await fetch(`${API_BASE}admin.php`, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('User deleted successfully');
            await loadAdminUsers();
            await loadAdminDashboard();
        } else {
            alert(data.error || 'Failed to delete user');
        }
    } catch (error) {
        console.error('Error deleting user:', error);
    }
}

// Categories Management
async function loadAdminCategories() {
    try {
        const response = await fetch(`${API_BASE}admin.php?action=categories&sub_action=list`);
        const data = await response.json();
        
        if (data.success) {
            renderAdminCategories(data.categories);
        }
    } catch (error) {
        console.error('Error loading categories:', error);
    }
}

function renderAdminCategories(categories) {
    const tbody = document.getElementById('adminCategoryList');
    if (!tbody) return;
    
    tbody.innerHTML = categories.map(cat => {
        const actions = state.currentUser && state.currentUser.role === 'admin' ? `
            <button class="btn btn-secondary" onclick="editCategory(${cat.id}, ${JSON.stringify(cat.name)}, ${JSON.stringify(cat.icon || '🎁')})" style="margin-right: 0.5rem;">Edit</button>
            <button class="btn btn-danger" onclick="deleteCategory(${cat.id})">Delete</button>
        ` : '<span>No actions available</span>';
        
        return `
        <tr>
            <td><div style="font-size: 2rem;">${cat.icon || '🎁'}</div></td>
            <td>${cat.name}</td>
            <td>${cat.product_count || 0}</td>
            <td>${actions}</td>
        </tr>
    `;
    }).join('');
}

function openCategoryModal(id = null, name = '', icon = '🎁') {
    if (id) {
        document.getElementById('categoryModalTitle').textContent = 'Edit Category';
        document.getElementById('categoryId').value = id;
        document.getElementById('categoryName').value = name;
        document.getElementById('categoryIcon').value = icon;
    } else {
        document.getElementById('categoryModalTitle').textContent = 'Add Category';
        document.getElementById('adminCategoryForm').reset();
        document.getElementById('categoryId').value = '';
    }
    
    document.getElementById('adminCategoryModal').classList.add('active');
}

function editCategory(id, name, icon) {
    openCategoryModal(id, name, icon);
}

async function saveCategory(e) {
    e.preventDefault();
    
    const categoryId = document.getElementById('categoryId').value;
    const subAction = categoryId ? 'update' : 'add';
    
    const formData = new FormData();
    formData.append('action', 'categories');
    formData.append('sub_action', subAction);
    if (categoryId) formData.append('id', categoryId);
    formData.append('name', document.getElementById('categoryName').value);
    formData.append('icon', document.getElementById('categoryIcon').value);
    
    try {
        const response = await fetch(`${API_BASE}admin.php`, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(data.message);
            closeModal('adminCategoryModal');
            await loadAdminCategories();
            await loadCategories(); // Reload for frontend
        } else {
            alert(data.error || 'Failed to save category');
        }
    } catch (error) {
        console.error('Error saving category:', error);
        alert('An error occurred. Please try again.');
    }
}

async function deleteCategory(id) {
    if (!confirm('Are you sure you want to delete this category?')) return;
    
    try {
        const formData = new FormData();
        formData.append('action', 'categories');
        formData.append('sub_action', 'delete');
        formData.append('id', id);
        
        const response = await fetch(`${API_BASE}admin.php`, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert('Category deleted successfully');
            await loadAdminCategories();
            await loadCategories(); // Reload for frontend
        } else {
            alert(data.error || 'Failed to delete category');
        }
    } catch (error) {
        console.error('Error deleting category:', error);
    }
}

// Analytics
async function loadAdminAnalytics() {
    try {
        const response = await fetch(`${API_BASE}admin.php?action=analytics`);
        const data = await response.json();
        
        if (data.success) {
            renderAdminAnalytics(data.analytics);
        }
    } catch (error) {
        console.error('Error loading analytics:', error);
    }
}

function renderAdminAnalytics(analytics) {
    const container = document.getElementById('analyticsContent');
    if (!container) return;
    
    let html = '<div class="stats-grid" style="margin-bottom: 2rem;">';
    
    // Monthly Sales
    if (analytics.monthly_sales && analytics.monthly_sales.length > 0) {
        html += '<div style="background: white; padding: 2rem; border-radius: 10px; grid-column: 1/-1;">';
        html += '<h3>Monthly Sales (Last 6 Months)</h3>';
        html += '<table class="data-table">';
        html += '<thead><tr><th>Month</th><th>Orders</th><th>Revenue</th></tr></thead><tbody>';
        analytics.monthly_sales.forEach(item => {
            html += `<tr><td>${item.month}</td><td>${item.orders}</td><td>$${parseFloat(item.revenue || 0).toFixed(2)}</td></tr>`;
        });
        html += '</tbody></table></div>';
    }
    
    // Status Distribution
    if (analytics.status_distribution) {
        html += '<div style="background: white; padding: 2rem; border-radius: 10px;">';
        html += '<h3>Order Status Distribution</h3>';
        html += '<table class="data-table"><thead><tr><th>Status</th><th>Count</th></tr></thead><tbody>';
        analytics.status_distribution.forEach(item => {
            html += `<tr><td>${item.status}</td><td>${item.count}</td></tr>`;
        });
        html += '</tbody></table></div>';
    }
    
    // Top Customers
    if (analytics.top_customers && analytics.top_customers.length > 0) {
        html += '<div style="background: white; padding: 2rem; border-radius: 10px;">';
        html += '<h3>Top Customers</h3>';
        html += '<table class="data-table"><thead><tr><th>Name</th><th>Email</th><th>Orders</th><th>Total Spent</th></tr></thead><tbody>';
        analytics.top_customers.forEach(item => {
            html += `<tr><td>${item.name}</td><td>${item.email}</td><td>${item.order_count}</td><td>$${parseFloat(item.total_spent || 0).toFixed(2)}</td></tr>`;
        });
        html += '</tbody></table></div>';
    }
    
    html += '</div>';
    container.innerHTML = html;
}

// Reports
async function loadReports() {
    const type = document.getElementById('reportType').value;
    const startDate = document.getElementById('reportStartDate').value;
    const endDate = document.getElementById('reportEndDate').value;
    
    try {
        let url = `${API_BASE}admin.php?action=reports&type=${type}`;
        if (startDate) url += `&start_date=${startDate}`;
        if (endDate) url += `&end_date=${endDate}`;
        
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success) {
            renderReports(data.report, data.type);
        }
    } catch (error) {
        console.error('Error loading reports:', error);
    }
}

function renderReports(report, type) {
    const container = document.getElementById('reportsContent');
    if (!container) return;
    
    if (type === 'sales') {
        let html = '<table class="data-table"><thead><tr><th>Date</th><th>Orders</th><th>Revenue</th></tr></thead><tbody>';
        report.forEach(item => {
            html += `<tr><td>${item.date}</td><td>${item.order_count}</td><td>$${parseFloat(item.revenue || 0).toFixed(2)}</td></tr>`;
        });
        html += '</tbody></table>';
        container.innerHTML = html;
    } else if (type === 'products') {
        let html = '<table class="data-table"><thead><tr><th>Product</th><th>Total Sold</th><th>Revenue</th></tr></thead><tbody>';
        report.forEach(item => {
            html += `<tr><td>${item.name}</td><td>${item.total_sold}</td><td>$${parseFloat(item.revenue || 0).toFixed(2)}</td></tr>`;
        });
        html += '</tbody></table>';
        container.innerHTML = html;
    }
}

// Activity Logs
async function loadAdminLogs() {
    try {
        const response = await fetch(`${API_BASE}admin.php?action=activity_logs&limit=100`);
        const data = await response.json();
        
        if (data.success) {
            renderAdminLogs(data.logs);
        }
    } catch (error) {
        console.error('Error loading logs:', error);
    }
}

function renderAdminLogs(logs) {
    const tbody = document.getElementById('adminLogList');
    if (!tbody) return;
    
    tbody.innerHTML = logs.map(log => `
        <tr>
            <td>${new Date(log.created_at).toLocaleString()}</td>
            <td>${log.user_name || 'System'}</td>
            <td>${log.action}</td>
            <td>${log.table_name || '-'}</td>
            <td>${log.record_id || '-'}</td>
        </tr>
    `).join('');
}

// ==================== UI RENDERING ====================

function renderCategories() {
    const grid = document.getElementById('categoryGrid');
    if (!grid) return;
    
    grid.innerHTML = state.categories.map(cat => `
        <div class="category-card" onclick="filterByCategory('${cat.name}')">
            <div class="category-icon">${cat.icon}</div>
            <h3>${cat.name}</h3>
        </div>
    `).join('');
}

function populateCategoryFilters() {
    const filter = document.getElementById('categoryFilter');
    const adminFilter = document.getElementById('productCategoryId');
    
    if (filter) {
        filter.innerHTML = '<option value="">All Categories</option>' +
            state.categories.map(cat => `<option value="${cat.name}">${cat.name}</option>`).join('');
    }
    
    if (adminFilter) {
        adminFilter.innerHTML = state.categories.map(cat => 
            `<option value="${cat.id}">${cat.name}</option>`
        ).join('');
    }
}

function renderProducts() {
    const grid = document.getElementById('productGrid');
    if (!grid) return;
    
    if (state.products.length === 0) {
        grid.innerHTML = '<p style="text-align: center; padding: 2rem; grid-column: 1/-1;">No products found</p>';
        return;
    }
    
    grid.innerHTML = state.products.map(product => `
        <div class="product-card" onclick="viewProduct(${product.id})">
            <div class="product-image">${product.image}</div>
            <div class="product-info">
                <div class="product-name">${product.name}</div>
                <div class="rating">${'⭐'.repeat(product.rating || 5)}</div>
                <div class="product-price">$${parseFloat(product.price).toFixed(2)}</div>
                <button class="btn btn-secondary" style="width: 100%;" onclick="event.stopPropagation(); addToCart(${product.id})">
                    Add to Cart
                </button>
            </div>
        </div>
    `).join('');
}

function renderFeaturedProducts() {
    const grid = document.getElementById('featuredProducts');
    if (!grid) return;
    
    const featured = state.products.slice(0, 4);
    
    grid.innerHTML = featured.map(product => `
        <div class="product-card" onclick="viewProduct(${product.id})">
            <div class="product-image">${product.image}</div>
            <div class="product-info">
                <div class="product-name">${product.name}</div>
                <div class="rating">${'⭐'.repeat(product.rating || 5)}</div>
                <div class="product-price">$${parseFloat(product.price).toFixed(2)}</div>
            </div>
        </div>
    `).join('');
}

function viewProduct(id) {
    const product = state.products.find(p => p.id === id);
    if (!product) return;
    
    document.getElementById('modalProductName').textContent = product.name;
    document.getElementById('modalProductImage').innerHTML = product.image;
    document.getElementById('modalProductPrice').textContent = `$${parseFloat(product.price).toFixed(2)}`;
    document.getElementById('modalProductRating').innerHTML = '⭐'.repeat(product.rating || 5);
    document.getElementById('modalProductDescription').textContent = product.description;
    document.getElementById('modalProductCategory').textContent = product.category_name || '';
    document.getElementById('modalProductStock').textContent = `${product.stock} available`;
    document.getElementById('productModal').dataset.productId = id;
    document.getElementById('productModal').classList.add('active');
}

function addToCartFromModal() {
    const productId = parseInt(document.getElementById('productModal').dataset.productId);
    addToCart(productId);
    closeModal('productModal');
}

function renderCart() {
    const container = document.getElementById('cartItems');
    if (!container) return;
    
    if (state.cart.length === 0) {
        container.innerHTML = '<p style="text-align: center; padding: 2rem;">Your cart is empty</p>';
        document.getElementById('subtotal').textContent = '$0.00';
        document.getElementById('total').textContent = '$5.00';
        return;
    }
    
    container.innerHTML = state.cart.map(item => `
        <div class="cart-item">
            <div class="cart-item-image">${item.image}</div>
            <div class="cart-item-details">
                <h3>${item.name}</h3>
                <p>$${parseFloat(item.price).toFixed(2)}</p>
            </div>
            <div class="quantity-control">
                <button onclick="updateQuantity(${item.product_id}, -1)">-</button>
                <span style="padding: 0 1rem;">${item.quantity}</span>
                <button onclick="updateQuantity(${item.product_id}, 1)">+</button>
            </div>
            <div>
                <strong>$${(parseFloat(item.price) * item.quantity).toFixed(2)}</strong>
            </div>
            <button class="btn btn-danger" onclick="removeFromCart(${item.product_id})">Remove</button>
        </div>
    `).join('');
    
    updateCartTotals();
}

function updateCartTotals() {
    const subtotal = state.cart.reduce((sum, item) => sum + (parseFloat(item.price) * item.quantity), 0);
    const shipping = 5.00;
    const total = subtotal + shipping;
    
    document.getElementById('subtotal').textContent = `$${subtotal.toFixed(2)}`;
    document.getElementById('total').textContent = `$${total.toFixed(2)}`;
}

function updateCartCount() {
    const count = state.cart.reduce((sum, item) => sum + item.quantity, 0);
    document.getElementById('cartCount').textContent = count;
}

function renderCheckoutSummary() {
    const container = document.getElementById('checkoutSummary');
    if (!container) return;
    
    const subtotal = state.cart.reduce((sum, item) => sum + (parseFloat(item.price) * item.quantity), 0);
    const shipping = 5.00;
    const total = subtotal + shipping;
    
    container.innerHTML = state.cart.map(item => `
        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
            <span>${item.name} x${item.quantity}</span>
            <span>$${(parseFloat(item.price) * item.quantity).toFixed(2)}</span>
        </div>
    `).join('') + `
        <div style="border-top: 1px solid var(--light); margin: 1rem 0; padding-top: 1rem;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                <span>Subtotal:</span>
                <span>$${subtotal.toFixed(2)}</span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                <span>Shipping:</span>
                <span>$${shipping.toFixed(2)}</span>
            </div>
            <div style="display: flex; justify-content: space-between; font-weight: bold; font-size: 1.2rem; margin-top: 1rem; padding-top: 1rem; border-top: 2px solid var(--light);">
                <span>Total:</span>
                <span>$${total.toFixed(2)}</span>
            </div>
        </div>
    `;
}

function renderAdminProducts(products) {
    const tbody = document.getElementById('adminProductList');
    if (!tbody) return;
    
    tbody.innerHTML = products.map(product => `
        <tr>
            <td><div style="font-size: 2rem;">${product.image}</div></td>
            <td>${product.name}</td>
            <td>${product.category_name || 'N/A'}</td>
            <td>$${parseFloat(product.price).toFixed(2)}</td>
            <td>${product.stock}</td>
            <td>
                <button class="btn btn-secondary" onclick="editProduct(${product.id})" style="margin-right: 0.5rem;">Edit</button>
                <button class="btn btn-danger" onclick="deleteProduct(${product.id})">Delete</button>
            </td>
        </tr>
    `).join('');
}

function renderAdminOrders(orders) {
    const tbody = document.getElementById('adminOrderList');
    if (!tbody) return;
    
    tbody.innerHTML = orders.map(order => `
        <tr>
            <td>#${order.id}</td>
            <td>${order.customer_name || order.shipping_name}</td>
            <td>${new Date(order.created_at).toLocaleDateString()}</td>
            <td>$${parseFloat(order.total_amount).toFixed(2)}</td>
            <td><span class="badge badge-${order.status.toLowerCase()}">${order.status}</span></td>
            <td>
                <select onchange="updateOrderStatus(${order.id}, this.value)" class="filter-select" style="padding: 0.5rem;">
                    <option value="Pending" ${order.status === 'Pending' ? 'selected' : ''}>Pending</option>
                    <option value="Shipped" ${order.status === 'Shipped' ? 'selected' : ''}>Shipped</option>
                    <option value="Delivered" ${order.status === 'Delivered' ? 'selected' : ''}>Delivered</option>
                    <option value="Cancelled" ${order.status === 'Cancelled' ? 'selected' : ''}>Cancelled</option>
                </select>
            </td>
        </tr>
    `).join('');
}

function renderAdminUsers(users) {
    const tbody = document.getElementById('adminUserList');
    if (!tbody) return;
    
    tbody.innerHTML = users.map(user => {
        const roleBadge = user.role === 'admin' ? '<span class="badge badge-admin">Admin</span>' :
                         user.role === 'manager' ? '<span class="badge badge-manager">Manager</span>' :
                         '<span class="badge badge-customer">Customer</span>';
        
        const actions = state.currentUser && state.currentUser.role === 'admin' ? `
            <select onchange="updateUserRole(${user.id}, this.value)" class="filter-select" style="padding: 0.5rem; margin-right: 0.5rem;">
                <option value="customer" ${user.role === 'customer' ? 'selected' : ''}>Customer</option>
                <option value="manager" ${user.role === 'manager' ? 'selected' : ''}>Manager</option>
                <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Admin</option>
            </select>
            <button class="btn btn-danger" onclick="deleteUser(${user.id})">Delete</button>
        ` : '<span>No actions available</span>';
        
        return `
        <tr>
            <td>${user.name}</td>
            <td>${user.email}</td>
            <td>${roleBadge}</td>
            <td>${new Date(user.created_at).toLocaleDateString()}</td>
            <td>${user.order_count || 0}</td>
            <td>$${parseFloat(user.total_spent || 0).toFixed(2)}</td>
            <td>${actions}</td>
        </tr>
    `;
    }).join('');
}

// ==================== UI INTERACTIONS ====================

function showPage(page) {
    document.querySelectorAll('.page').forEach(p => p.classList.add('hidden'));
    
    if (page === 'admin') {
        if (!state.currentUser || (state.currentUser.role !== 'admin' && state.currentUser.role !== 'manager')) {
            alert('Access denied. Admin or Manager privileges required.');
            return;
        }
    }
    
    const pageElement = document.getElementById(page + 'Page');
    if (pageElement) {
        pageElement.classList.remove('hidden');
    }
    
    if (page === 'cart') renderCart();
    if (page === 'checkout') {
        if (state.cart.length === 0) {
            alert('Your cart is empty');
            showPage('cart');
            return;
        }
        renderCheckoutSummary();
    }
    if (page === 'shop') loadProducts();
    if (page === 'admin') loadAdminData();
    
    window.scrollTo(0, 0);
}

function showAdminSection(section) {
    document.querySelectorAll('.admin-section').forEach(s => s.classList.add('hidden'));
    const sectionElement = document.getElementById('admin' + section.charAt(0).toUpperCase() + section.slice(1));
    if (sectionElement) sectionElement.classList.remove('hidden');
    
    document.querySelectorAll('.admin-nav li').forEach(li => li.classList.remove('active'));
    if (event && event.target) event.target.classList.add('active');
    
    if (section === 'dashboard') loadAdminDashboard();
    if (section === 'products') loadAdminProducts();
    if (section === 'orders') loadAdminOrders();
    if (section === 'users') loadAdminUsers();
    if (section === 'categories') loadAdminCategories();
    if (section === 'analytics') loadAdminAnalytics();
    if (section === 'reports') {
        // Set default dates
        const endDate = new Date();
        const startDate = new Date();
        startDate.setDate(startDate.getDate() - 30);
        document.getElementById('reportStartDate').value = startDate.toISOString().split('T')[0];
        document.getElementById('reportEndDate').value = endDate.toISOString().split('T')[0];
    }
    if (section === 'logs') loadAdminLogs();
}

function handleAuth() {
    if (state.currentUser) {
        const action = confirm(`Logged in as ${state.currentUser.name}\n\nClick OK to logout, Cancel to stay`);
        if (action) logout();
    } else {
        showPage('auth');
    }
}

let isRegisterMode = false;

function toggleAuthMode() {
    isRegisterMode = !isRegisterMode;
    document.getElementById('authTitle').textContent = isRegisterMode ? 'Register' : 'Login';
    document.getElementById('authSubmitBtn').textContent = isRegisterMode ? 'Register' : 'Login';
    document.getElementById('nameField').style.display = isRegisterMode ? 'block' : 'none';
    document.getElementById('authToggle').textContent = isRegisterMode ? 
        'Already have an account? Login' : "Don't have an account? Register";
    
    document.getElementById('authName').required = isRegisterMode;
}

function updateAuthUI() {
    if (state.currentUser) {
        document.getElementById('userGreeting').textContent = `Hello, ${state.currentUser.name}`;
        document.getElementById('authBtn').textContent = 'Account';
        
        // Show cart icon for customers
        const cartIcon = document.getElementById('cartIcon');
        if (cartIcon) cartIcon.style.display = 'block';
        
        // Show admin panel button for managers and admins
        const adminBtn = document.getElementById('adminPanelBtn');
        if (adminBtn && (state.currentUser.role === 'admin' || state.currentUser.role === 'manager')) {
            adminBtn.style.display = 'block';
        }
    } else {
        document.getElementById('userGreeting').textContent = '';
        document.getElementById('authBtn').textContent = 'Login';
        const cartIcon = document.getElementById('cartIcon');
        if (cartIcon) cartIcon.style.display = 'none';
        const adminBtn = document.getElementById('adminPanelBtn');
        if (adminBtn) adminBtn.style.display = 'none';
    }
}

function proceedToCheckout() {
    if (!state.currentUser) {
        alert('Please login to proceed');
        showPage('auth');
        return;
    }
    
    if (state.cart.length === 0) {
        alert('Your cart is empty');
        return;
    }
    
    showPage('checkout');
}

function searchProducts() {
    loadProducts();
}

function applyFilters() {
    loadProducts();
}

function filterByCategory(category) {
    showPage('shop');
    setTimeout(() => {
        document.getElementById('categoryFilter').value = category;
        loadProducts();
    }, 100);
}

function openProductModal(id = null) {
    populateCategoryFilters();
    
    if (id) {
        const product = state.products.find(p => p.id === id);
        if (product) {
            document.getElementById('productModalTitle').textContent = 'Edit Product';
            document.getElementById('productId').value = product.id;
            document.getElementById('productName').value = product.name;
            document.getElementById('productCategoryId').value = product.category_id;
            document.getElementById('productPrice').value = product.price;
            document.getElementById('productStock').value = product.stock;
            document.getElementById('productDescription').value = product.description;
            document.getElementById('productImage').value = product.image;
        }
    } else {
        document.getElementById('productModalTitle').textContent = 'Add Product';
        document.getElementById('adminProductForm').reset();
        document.getElementById('productId').value = '';
    }
    
    document.getElementById('adminProductModal').classList.add('active');
}

function editProduct(id) {
    openProductModal(id);
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

function handleContactForm(e) {
    e.preventDefault();
    alert('Thank you for contacting us! We will get back to you soon.');
    e.target.reset();
}

// Close modal when clicking outside
window.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('active');
    }
});