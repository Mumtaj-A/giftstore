-- Quick Fix: Update Admin Passwords
-- Run this SQL script if you can't access the PHP script
-- This uses a pre-generated hash for password: admin123

-- Option 1: Direct UPDATE (if you already have the hash)
UPDATE users 
SET password = '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy' 
WHERE email IN ('admin@giftify.com', 'manager@giftify.com');

-- Option 2: Delete and re-insert (if above doesn't work)
DELETE FROM users WHERE email IN ('admin@giftify.com', 'manager@giftify.com');

INSERT INTO users (name, email, password, role) VALUES 
('Admin User', 'admin@giftify.com', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 'admin'),
('Manager User', 'manager@giftify.com', '$2y$10$N9qo8uLOickgx2ZMRZoMyeIjZAgcfl7p92ldGxad68LJZdL17lhWy', 'manager');

