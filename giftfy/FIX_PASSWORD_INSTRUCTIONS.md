# Fix Admin Password - Instructions

The admin password wasn't working because the hash in the database was incorrect. Here are 3 ways to fix it:

## ✅ Option 1: Run PHP Fix Script (EASIEST)

1. Open your browser and navigate to:
   ```
   http://localhost/giftfy/php/fix_admin_password.php
   ```
   (Replace `localhost` with your server URL)

2. The script will automatically update both admin and manager passwords.

3. Login with:
   - **Email**: `admin@giftify.com`
   - **Password**: `admin123`

## ✅ Option 2: Run SQL Script

1. Open phpMyAdmin or your MySQL client
2. Select the `giftify_db` database
3. Run the SQL script: `Database/fix_passwords.sql`
4. Or copy/paste this SQL:

```sql
UPDATE users 
SET password = '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy' 
WHERE email IN ('admin@giftify.com', 'manager@giftify.com');
```

## ✅ Option 3: Manual PHP Generation

1. Create a temporary PHP file or run via command line:

```php
<?php
require_once 'php/config.php';
$conn = getDBConnection();
$hash = password_hash('admin123', PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
$stmt->execute([$hash, 'admin@giftify.com']);
$stmt->execute([$hash, 'manager@giftify.com']);

echo "Password updated successfully!";
?>
```

## 🔍 Troubleshooting

### Still not working?

1. **Check if user exists:**
   ```sql
   SELECT email, role FROM users WHERE email = 'admin@giftify.com';
   ```

2. **Check password hash:**
   ```sql
   SELECT email, LEFT(password, 20) as hash_preview FROM users WHERE email = 'admin@giftify.com';
   ```

3. **Try registering a new admin:**
   - Register normally, then manually update the role:
   ```sql
   UPDATE users SET role = 'admin' WHERE email = 'your-email@example.com';
   ```

4. **Verify PHP password functions work:**
   - Check if `password_hash` and `password_verify` are available
   - These require PHP 5.5+ (should be fine on PHP 7.4+)

### Login Credentials

After fixing, use these credentials:
- **Admin**: `admin@giftify.com` / `admin123`
- **Manager**: `manager@giftify.com` / `admin123`

## 📝 Note

The hash in the original schema.sql was just a placeholder. The updated schema.sql now has the correct hash, so if you re-import the database, it should work correctly.

