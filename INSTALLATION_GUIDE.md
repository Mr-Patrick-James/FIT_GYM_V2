# FitPay Gym Management System - Installation Guide

## System Requirements

- PHP 8.0 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Apache/Nginx web server
- Composer (for dependencies)
- 50MB+ disk space
- SSL certificate (recommended for production)

## Quick Start (Local Development)

### Step 1: Clone/Extract Files
```bash
# Extract the project to your web server directory
# For XAMPP: C:\xampp\htdocs\Fit
# For WAMP: C:\wamp64\www\Fit
```

### Step 2: Database Setup
```bash
# 1. Open phpMyAdmin (http://localhost/phpmyadmin)
# 2. Create a new database named 'fitpay_gym'
# 3. Import the SQL file: database/fitpay_gym.sql
```

Or via command line:
```bash
mysql -u root -p
CREATE DATABASE fitpay_gym;
USE fitpay_gym;
SOURCE database/fitpay_gym.sql;
EXIT;
```

### Step 3: Configure Environment
```bash
# 1. Copy the example environment file
cp env.example .env

# 2. Edit .env file with your settings
# Update database credentials if different from defaults
```

### Step 4: Install Dependencies
```bash
# Install Composer dependencies (PHPMailer)
php composer.phar install

# Or if composer is installed globally:
composer install
```

### Step 5: Configure Permissions
```bash
# On Linux/Mac, set proper permissions
chmod 755 api/
chmod 755 assets/uploads/
chmod 644 .env

# Make uploads directory writable
chmod 777 assets/uploads/exercises/
chmod 777 api/uploads/receipts/
```

### Step 6: Access the Application
```
http://localhost/Fit/
```

## Default Admin Account

After database import, you can create an admin account:

1. Go to signup page
2. Register with email: `admin@martinezfitness.com`
3. Verify OTP (check console/logs for OTP code)
4. Manually update user role in database:
```sql
UPDATE users SET role = 'admin', email_verified = 1 WHERE email = 'admin@martinezfitness.com';
```

## Email Configuration

### Option 1: Gmail SMTP (Recommended for Development)

1. Enable 2-Factor Authentication on your Gmail account
2. Generate an App Password:
   - Go to Google Account → Security → 2-Step Verification → App passwords
   - Select "Mail" and "Other (Custom name)"
   - Copy the 16-character password

3. Update `.env` file:
```env
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-16-char-app-password
SMTP_FROM_EMAIL=your-email@gmail.com
SMTP_FROM_NAME=Martinez Fitness
```

4. Or configure via Admin Dashboard:
   - Login as admin
   - Go to Settings → Email Configuration
   - Add SMTP credentials

### Option 2: Other SMTP Providers

**SendGrid:**
```env
SMTP_HOST=smtp.sendgrid.net
SMTP_PORT=587
SMTP_USERNAME=apikey
SMTP_PASSWORD=your-sendgrid-api-key
```

**Mailgun:**
```env
SMTP_HOST=smtp.mailgun.org
SMTP_PORT=587
SMTP_USERNAME=postmaster@your-domain.mailgun.org
SMTP_PASSWORD=your-mailgun-password
```

## Production Deployment

### InfinityFree Hosting

1. **Upload Files**
   - Use FileZilla or hosting file manager
   - Upload all files to `htdocs/` directory

2. **Database Setup**
   - Create MySQL database via control panel
   - Import `database/fitpay_gym.sql`
   - Note database credentials

3. **Configure Application**
   ```php
   // In api/config.php, set:
   $is_local = false;
   
   // Update .env with InfinityFree credentials
   DB_HOST=sql109.infinityfree.com
   DB_USER=if0_XXXXXXX
   DB_PASS=your-db-password
   DB_NAME=if0_XXXXXXX_fitpay_gym
   ```

4. **Set Permissions**
   - Ensure uploads directories are writable (755 or 777)
   - Protect .env file (644)

5. **Test Application**
   - Visit your domain
   - Test signup/login
   - Verify email delivery

### Shared Hosting (cPanel)

1. **Upload via FTP/File Manager**
2. **Create Database**
   - MySQL Databases → Create Database
   - Create user and assign to database
   - Import SQL file via phpMyAdmin

3. **Update Configuration**
   ```php
   // api/config.php
   $is_local = false;
   
   // .env file
   DB_HOST=localhost
   DB_USER=cpanel_username_dbuser
   DB_PASS=database-password
   DB_NAME=cpanel_username_fitpay_gym
   ```

4. **SSL Certificate**
   - Enable Let's Encrypt SSL via cPanel
   - Force HTTPS in .htaccess:
   ```apache
   RewriteEngine On
   RewriteCond %{HTTPS} off
   RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
   ```

## Troubleshooting

### Database Connection Failed
```
Error: Connection failed: Access denied for user 'root'@'localhost'
```
**Solution:** Check database credentials in `.env` or `api/config.php`

### Email Not Sending
```
Error: SMTP connect() failed
```
**Solutions:**
1. Verify SMTP credentials
2. Check if port 587 is open
3. Enable "Less secure app access" (Gmail)
4. Use App Password instead of regular password
5. Check error logs: `error_log` in PHP

### Session Not Persisting
```
User logged in but redirected to login page
```
**Solutions:**
1. Check `SESSION_COOKIE_PATH` in `api/session.php`
2. Verify session directory is writable
3. Check browser cookies are enabled
4. Review error logs for session warnings

### File Upload Errors
```
Error: Failed to move uploaded file
```
**Solutions:**
1. Check directory permissions (755 or 777)
2. Verify `upload_max_filesize` in php.ini
3. Check `post_max_size` in php.ini
4. Ensure directory exists

### 404 Errors on Pages
```
Error: Page not found
```
**Solutions:**
1. Check `.htaccess` file exists
2. Enable `mod_rewrite` in Apache
3. Verify `BASE_URL` in `api/config.php`
4. Check file paths are correct

## Configuration Files

### .htaccess (Root Directory)
```apache
# Enable rewrite engine
RewriteEngine On

# Force HTTPS (production only)
# RewriteCond %{HTTPS} off
# RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Protect sensitive files
<FilesMatch "\.(env|sql|md)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# PHP settings
php_value upload_max_filesize 10M
php_value post_max_size 10M
php_value max_execution_time 300
php_value max_input_time 300
```

### php.ini Recommendations
```ini
; Production settings
display_errors = Off
log_errors = On
error_log = /path/to/error.log

; Upload settings
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 300

; Session settings
session.cookie_httponly = 1
session.cookie_secure = 1
session.use_strict_mode = 1

; Security
expose_php = Off
```

## Post-Installation Tasks

### 1. Configure Gym Settings
- Login as admin
- Go to Settings
- Update:
  - Gym name and address
  - Contact information
  - Payment details (GCash)
  - Operating hours

### 2. Add Packages
- Navigate to Packages
- Create membership plans
- Set pricing and duration
- Add package descriptions

### 3. Add Exercises
- Go to Exercises section
- Upload exercise images
- Add descriptions and instructions
- Assign to equipment

### 4. Create Trainers
- Add trainer accounts
- Assign specializations
- Link to packages

### 5. Test Booking Flow
- Create test user account
- Make a booking
- Upload receipt
- Verify payment as admin
- Check email notifications

## Backup & Maintenance

### Database Backup
```bash
# Manual backup
mysqldump -u root -p fitpay_gym > backup_$(date +%Y%m%d).sql

# Automated backup (cron job)
0 2 * * * mysqldump -u root -p'password' fitpay_gym > /backups/fitpay_$(date +\%Y\%m\%d).sql
```

### File Backup
```bash
# Backup uploads directory
tar -czf uploads_backup_$(date +%Y%m%d).tar.gz assets/uploads/ api/uploads/
```

### Update Checklist
- [ ] Backup database
- [ ] Backup files
- [ ] Test on staging environment
- [ ] Update production
- [ ] Verify functionality
- [ ] Monitor error logs

## Security Hardening

1. **Remove sensitive files from web root**
   ```bash
   # Move .env outside public directory
   # Update paths in config.php
   ```

2. **Disable directory listing**
   ```apache
   Options -Indexes
   ```

3. **Set security headers**
   ```apache
   Header set X-Content-Type-Options "nosniff"
   Header set X-Frame-Options "SAMEORIGIN"
   Header set X-XSS-Protection "1; mode=block"
   ```

4. **Regular updates**
   - Update PHP version
   - Update Composer dependencies
   - Apply security patches

## Support & Resources

- **Documentation:** See `SECURITY_IMPROVEMENTS.md`
- **API Reference:** See `api/README.md`
- **Error Logs:** Check `debug/` folder and PHP error log
- **Database Schema:** See `database/fitpay_gym.sql`

## Common Issues & Solutions

| Issue | Solution |
|-------|----------|
| White screen | Check PHP error log, enable display_errors temporarily |
| 500 Internal Server Error | Check .htaccess syntax, verify PHP version |
| Database connection timeout | Check MySQL service is running |
| Email not received | Check spam folder, verify SMTP settings |
| Session expires quickly | Increase session.gc_maxlifetime in php.ini |
| Upload fails | Check directory permissions and PHP upload limits |

---

**Need Help?**
1. Check error logs first
2. Review this guide
3. Search for specific error messages
4. Check PHP and MySQL versions

**Last Updated:** March 20, 2026
