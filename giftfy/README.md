# Giftify - Professional Gift Store with Admin Panel

A complete, professional gift store system with role-based access control, comprehensive admin panel, and modern UI.

## Features

### 🎁 Customer Features
- Browse products by category
- Search and filter products
- Shopping cart functionality
- Secure checkout process
- Order tracking
- User registration and authentication

### 👨‍💼 Admin & Manager Features
- **Role-Based Access Control**: Three roles (Admin, Manager, Customer)
  - **Admin**: Full system access including user management, reports, and activity logs
  - **Manager**: Product/order management, user viewing (customers only)
  - **Customer**: Standard shopping experience

- **Comprehensive Dashboard**: 
  - Total orders, users, products, revenue
  - Recent orders (7 days)
  - Pending orders
  - Low stock alerts
  - Today's revenue

- **Product Management**:
  - Add, edit, delete products
  - Manage inventory (stock levels)
  - Category assignment
  - Image/icon support

- **Category Management** (Admin only):
  - Create, edit, delete categories
  - Icon customization
  - Product count per category

- **Order Management**:
  - View all orders
  - Update order status (Pending, Shipped, Delivered, Cancelled)
  - Customer information
  - Order history

- **User Management** (Admin only):
  - View all users
  - Change user roles
  - Delete users
  - View user statistics (orders, total spent)

- **Analytics & Reports**:
  - Monthly sales trends
  - Order status distribution
  - Top customers analysis
  - Sales reports by date range
  - Product performance reports

- **Activity Logs** (Admin only):
  - Track all system actions
  - User activity monitoring
  - IP address logging

## Installation

### Prerequisites
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- Modern web browser

### Setup Instructions

1. **Clone/Download the project**
   ```bash
   cd giftfy
   ```

2. **Configure Database**
   - Create a MySQL database named `giftify_db`
   - Update database credentials in `php/config.php`:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USER', 'your_username');
     define('DB_PASS', 'your_password');
     define('DB_NAME', 'giftify_db');
     ```

3. **Import Database Schema**
   - Execute the SQL file in your MySQL database:
     ```bash
     mysql -u your_username -p giftify_db < Database/schema.sql
     ```
   - Or import `Database/schema.sql` using phpMyAdmin

4. **Set Up Admin Account**
   - The default admin account is created automatically:
     - Email: `admin@giftify.com`
     - Password: `admin123` (default hash included)
   - **Important**: Change the default password hash in `Database/schema.sql` before importing:
     ```sql
     -- Generate a proper hash using PHP: password_hash('your_password', PASSWORD_DEFAULT)
     ```

5. **Web Server Configuration**
   - Point your web server document root to the project folder
   - Ensure PHP sessions are enabled
   - Make sure mod_rewrite is enabled (if using Apache)

6. **Access the Application**
   - Open your browser and navigate to your server URL
   - Default admin login:
     - Email: `admin@giftify.com`
     - Password: `admin123`

## Default Accounts

After database import:
- **Admin**: admin@giftify.com / admin123
- **Manager**: manager@giftify.com / admin123

⚠️ **Security Note**: Change default passwords immediately!

## Project Structure

```
giftfy/
├── Database/
│   └── schema.sql          # Database schema and seed data
├── php/
│   ├── config.php          # Configuration and helper functions
│   ├── auth.php            # Authentication (login/register/logout)
│   ├── products.php        # Product CRUD operations
│   ├── cart.php            # Shopping cart functionality
│   ├── orders.php          # Order management
│   └── admin.php           # Admin panel API endpoints
├── css/
│   └── style.css           # All styles
├── js/
│   └── script.js           # Frontend JavaScript
└── index.html              # Main application file
```

## Security Features

- ✅ Password hashing (bcrypt)
- ✅ SQL injection prevention (prepared statements)
- ✅ XSS protection (input sanitization)
- ✅ Role-based access control
- ✅ Session management
- ✅ Activity logging
- ✅ CSRF protection ready (can be enhanced)
- ✅ Account deactivation support

## Role Permissions

### Admin
- Full system access
- User role management
- Category management
- Reports and analytics
- Activity logs
- Product deletion
- User deletion

### Manager
- Product management (add/edit)
- Order management
- View customers (read-only)
- Analytics dashboard
- Cannot delete products/users
- Cannot manage categories
- Cannot view reports/logs

### Customer
- Browse and purchase products
- Manage own cart
- View own orders
- Profile management

## Technologies Used

- **Frontend**: HTML5, CSS3, JavaScript (Vanilla)
- **Backend**: PHP 7.4+
- **Database**: MySQL
- **Architecture**: RESTful API pattern

## Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)

## Future Enhancements

Potential features to add:
- Image upload for products
- Email notifications
- Payment gateway integration
- Advanced search filters
- Wishlist functionality
- Product reviews and ratings
- Discount codes and coupons
- Inventory alerts
- Export reports to CSV/PDF
- Multi-language support

## Troubleshooting

### Database Connection Error
- Verify database credentials in `php/config.php`
- Ensure MySQL service is running
- Check database exists and is accessible

### Session Issues
- Ensure PHP sessions are enabled
- Check session save path permissions
- Clear browser cookies

### Permission Errors
- Verify file/folder permissions
- Ensure web server can read project files

## License

This project is open source and available for educational purposes.

## Support

For issues or questions, please check:
1. Database connection settings
2. PHP error logs
3. Browser console for JavaScript errors
4. Network tab for API call errors

---

**Note**: This is a professional-grade system suitable for learning and can be adapted for production use with additional security enhancements.

