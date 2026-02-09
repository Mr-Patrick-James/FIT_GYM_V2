# FitPay Dashboard - InfinityFree Deployment Guide

## Overview
FitPay Dashboard is a comprehensive gym membership management system with admin and user dashboards, booking management, payment tracking, and responsive design.

## Deployment to InfinityFree

### Prerequisites
- InfinityFree account (https://infinityfree.net/)
- FTP client or access to file manager
- phpMyAdmin access for database management

### Step-by-Step Deployment

#### 1. Prepare Your Local Database
1. Open phpMyAdmin on your local machine (WAMP/XAMPP)
2. Select the `fitpay_gym` database
3. Click on the "Export" tab
4. Choose "Quick" export method
5. Click "Go" to download the SQL file

#### 2. Set Up InfinityFree Database
1. Log into your InfinityFree account
2. Navigate to the "Databases" section
3. Create a new MySQL database
4. Note down the following details:
   - Hostname/Server
   - Username
   - Password
   - Database name

#### 3. Import Database
1. Access phpMyAdmin through your InfinityFree control panel
2. Select your newly created database
3. Click on the "Import" tab
4. Browse and select your exported SQL file
5. Click "Go" to import

#### 4. Update Configuration
1. Edit the `.env` file in your project root
2. Replace the database configuration with your InfinityFree credentials:
   ```
   DB_HOST=your_hostname
   DB_USER=your_username
   DB_PASS=your_password
   DB_NAME=your_database_name
   ```
3. Update your email configuration if needed

#### 5. Upload Files
1. Use an FTP client (like FileZilla) or InfinityFree's file manager
2. Upload all project files to the `public_html` directory
3. Make sure the directory structure remains intact

#### 6. Set File Permissions
1. Set permissions for the `uploads/` directory to 755 or 777
2. Ensure the directory is writable for file uploads

### Default Login Credentials
- **Admin**: admin@martinezfitness.com / admin123
- **User**: user@martinezfitness.com / user123

### Troubleshooting

#### Common Issues:
1. **Database Connection Error**: Verify your database credentials in `.env`
2. **Page Not Found**: Check that files are uploaded to `public_html`
3. **Email Not Working**: Update SMTP settings in the database or `.env` file
4. **File Upload Issues**: Verify `uploads/` directory permissions

#### Error Logs:
Check your InfinityFree control panel for error logs if issues persist.

### Post-Deployment Security
1. Change default passwords immediately
2. Update admin email address
3. Configure secure SMTP settings
4. Remove or rename installation files

### Limitations on Free Hosting
- Email sending may be restricted
- File upload sizes limited
- Processing power may be limited during peak times
- Database size limitations may apply

### Need Help?
If you encounter issues during deployment, check the following:
1. Verify all file uploads completed successfully
2. Confirm database credentials are correct
3. Test database connection independently
4. Check PHP version compatibility (requires PHP 7.4+)

---

**Note**: For production use, consider upgrading to a paid hosting plan for better performance, security, and support.