-- ============================================
-- FITPAY GYM - COMPLETE DATABASE SETUP
-- ============================================
-- Run this entire file in phpMyAdmin to set up everything
-- This includes: Database, Tables, Default Users, and Email Config
-- ============================================

-- Step 1: Create Database
CREATE DATABASE IF NOT EXISTS fitpay_gym;
USE fitpay_gym;

-- ============================================
-- Step 2: Create Tables
-- ============================================

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(100) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    contact VARCHAR(50),
    address TEXT,
    email_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- OTP table for email verification
CREATE TABLE IF NOT EXISTS otps (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    code VARCHAR(6) NOT NULL,
    expires_at DATETIME NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_code (code),
    INDEX idx_expires (expires_at)
);

-- Email configuration table for SMTP settings
CREATE TABLE IF NOT EXISTS email_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    smtp_host VARCHAR(100) NOT NULL DEFAULT 'smtp.gmail.com',
    smtp_port INT NOT NULL DEFAULT 587,
    smtp_username VARCHAR(100) NOT NULL,
    smtp_password VARCHAR(100) NOT NULL,
    from_email VARCHAR(100) NOT NULL,
    from_name VARCHAR(100) NOT NULL DEFAULT 'Martinez Fitness',
    is_active BOOLEAN DEFAULT TRUE,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_default (is_default),
    INDEX idx_active (is_active)
);

-- Packages table for gym membership packages
CREATE TABLE IF NOT EXISTS packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    duration VARCHAR(100) NOT NULL,
    price DECIMAL(10, 2) NOT NULL,
    tag VARCHAR(50),
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_tag (tag)
);

-- Bookings table for gym membership bookings
CREATE TABLE IF NOT EXISTS bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    contact VARCHAR(50),
    package_id INT,
    package_name VARCHAR(100),
    amount DECIMAL(10, 2) NOT NULL,
    booking_date DATE,
    expires_at DATETIME,
    status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    receipt_url VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (package_id) REFERENCES packages(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_user_id (user_id),
    INDEX idx_package_id (package_id)
);

-- Payments table for gym membership payments
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    booking_id INT,
    amount DECIMAL(10, 2) NOT NULL,
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    payment_method VARCHAR(50),
    transaction_id VARCHAR(100),
    receipt_url VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE SET NULL,
    UNIQUE KEY unique_booking (booking_id),
    UNIQUE KEY unique_transaction (transaction_id),
    INDEX idx_status (status),
    INDEX idx_transaction_id (transaction_id),
    INDEX idx_user_id (user_id),
    INDEX idx_booking_id (booking_id)
);

-- Gym settings table
CREATE TABLE IF NOT EXISTS gym_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key)
);

-- ============================================
-- Step 3: Insert Default Data
-- ============================================

-- Insert default admin user
-- Email: admin@martinezfitness.com
-- Password: admin123
-- Bcrypt hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
INSERT INTO users (name, email, password, role, email_verified) 
VALUES (
    'Admin Martinez',
    'admin@martinezfitness.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin',
    TRUE
)
ON DUPLICATE KEY UPDATE
    name = 'Admin Martinez',
    role = 'admin';

-- Insert default demo user
-- Email: user@martinezfitness.com
-- Password: user123
-- Bcrypt hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
INSERT INTO users (name, email, password, role, contact, address, email_verified) 
VALUES (
    'Juan Dela Cruz',
    'user@martinezfitness.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'user',
    '0917-123-4567',
    'Manila, Philippines',
    TRUE
)
ON DUPLICATE KEY UPDATE
    name = 'Juan Dela Cruz',
    role = 'user';

-- ============================================
-- Step 4: Email Configuration
-- ============================================

-- Delete any existing default configs first
DELETE FROM email_configs WHERE is_default = TRUE;

-- Insert email configuration with Gmail App Password
-- Email: belugaw6@gmail.com
-- App Password: qjinidnxxfcdyvqo (spaces removed from: qjin idnx xfcd yvqo)
INSERT INTO email_configs (
    name, 
    smtp_host, 
    smtp_port, 
    smtp_username, 
    smtp_password, 
    from_email, 
    from_name, 
    is_active, 
    is_default
) 
VALUES (
    'Primary Gmail',
    'smtp.gmail.com',
    587,
    'belugaw6@gmail.com',
    'qjinidnxxfcdyvqo',  -- Gmail App Password (spaces removed)
    'belugaw6@gmail.com',
    'Martinez Fitness',
    TRUE,
    TRUE
);

-- ============================================
-- Step 5: Insert Default Packages
-- ============================================

-- Insert default gym packages
INSERT IGNORE INTO packages (name, duration, price, tag, description) VALUES
    ('Walk-in Pass', '1 Day', 200.00, 'Basic', 'Perfect for trying out our facilities'),
    ('Weekly Pass', '7 Days', 500.00, 'Popular', 'Great for short-term fitness goals'),
    ('Monthly Membership', '30 Days', 1500.00, 'Best Value', 'Most popular choice for regular gym-goers'),
    ('3-Month Package', '90 Days', 4000.00, 'Premium', 'Save more with our 3-month package'),
    ('Annual Membership', '1 Year', 15000.00, 'VIP', 'Best value for long-term commitment');

-- ============================================
-- Step 6: Insert Default Gym Settings
-- ============================================

INSERT IGNORE INTO gym_settings (setting_key, setting_value) VALUES
    ('gym_name', 'Martinez Fitness Gym'),
    ('gym_address', ''),
    ('gym_contact', '0917-123-4567'),
    ('gym_email', 'info@martinezfitness.com'),
    ('gcash_number', '0917-123-4567'),
    ('gcash_name', 'Martinez Fitness'),
    ('gcash_qr_path', ''),
    ('payment_instructions', 'Please send payment via GCash to the number above. Include your name and booking reference in the payment notes.');

-- ============================================
-- Step 7: Verify Setup
-- ============================================

-- Check if everything was created successfully
SELECT 'Database Setup Complete!' AS Status;

SELECT 
    'Users Table' AS TableName,
    COUNT(*) AS RecordCount 
FROM users
UNION ALL
SELECT 
    'OTPs Table' AS TableName,
    COUNT(*) AS RecordCount 
FROM otps
UNION ALL
SELECT 
    'Email Configs Table' AS TableName,
    COUNT(*) AS RecordCount 
FROM email_configs
UNION ALL
SELECT 
    'Packages Table' AS TableName,
    COUNT(*) AS RecordCount 
FROM packages
UNION ALL
SELECT 
    'Bookings Table' AS TableName,
    COUNT(*) AS RecordCount 
FROM bookings
UNION ALL
SELECT 
    'Gym Settings Table' AS TableName,
    COUNT(*) AS RecordCount 
FROM gym_settings;

-- Show email configuration
SELECT 
    name,
    smtp_host,
    smtp_port,
    smtp_username,
    CASE 
        WHEN smtp_password IS NOT NULL AND smtp_password != '' THEN '***SET***'
        ELSE 'NOT SET'
    END AS password_status,
    from_email,
    from_name,
    is_active,
    is_default
FROM email_configs
WHERE is_default = TRUE;

-- ============================================
-- Setup Complete!
-- ============================================
-- Next Steps:
-- 1. Test email: Visit http://localhost/Fit/test-smtp.php
-- 2. Test signup: Go to your website and sign up
-- 3. Check inbox at belugaw6@gmail.com for OTP
-- 4. Access packages admin: http://localhost/Fit/views/admin/packages.php
-- 5. Access bookings admin: http://localhost/Fit/views/admin/bookings.php
-- ============================================
