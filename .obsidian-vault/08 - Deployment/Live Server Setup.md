# Live Server Setup

## Server Details

- **Host:** Ubuntu 22.04 LTS (DigitalOcean / VPS)
- **Web Server:** Apache 2.4
- **PHP:** 8.1+
- **Database:** MySQL 8.0
- **Domain:** fitpay.duckdns.org (DuckDNS dynamic DNS)
- **SSL:** Let's Encrypt (via Certbot)

---

## Directory Structure

```
/var/www/html/FIT_GYM_V2/
├── index.php
├── api/
├── assets/
├── views/
├── vendor/           ← Composer autoload (PHPMailer)
└── .env              ← Not in git — server-only
```

---

## Environment Variables (.env)

Create `/var/www/html/FIT_GYM_V2/.env`:

```ini
DB_HOST=localhost
DB_USER=fitpay_user
DB_PASS=strong_password_here
DB_NAME=fitpay_db

SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=app-password-here
SMTP_FROM_EMAIL=noreply@martinezfitness.com
SMTP_FROM_NAME=Martinez Fitness

BASE_URL=https://fitpay.duckdns.org
```

---

## Apache Configuration

### Virtual Host (`/etc/apache2/sites-available/fitpay.conf`)

```apache
<VirtualHost *:80>
    ServerName fitpay.duckdns.org
    DocumentRoot /var/www/html/FIT_GYM_V2
    
    <Directory /var/www/html/FIT_GYM_V2>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/fitpay_error.log
    CustomLog ${APACHE_LOG_DIR}/fitpay_access.log combined
</VirtualHost>
```

Enable site and rewrite module:
```bash
sudo a2ensite fitpay
sudo a2enmod rewrite
sudo systemctl restart apache2
```

---

## SSL Setup (Let's Encrypt)

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d fitpay.duckdns.org
```

Certbot auto-creates the HTTPS VirtualHost and redirects HTTP → HTTPS.

---

## MySQL Setup

```bash
mysql -u root -p

CREATE DATABASE fitpay_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'fitpay_user'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT ALL PRIVILEGES ON fitpay_db.* TO 'fitpay_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Import schema (from local export or fresh setup):
```bash
mysql -u fitpay_user -p fitpay_db < fitpay_schema.sql
```

---

## PHP Configuration

Edit `/etc/php/8.1/apache2/php.ini`:

```ini
upload_max_filesize = 10M
post_max_size = 12M
session.save_path = "/var/www/html/FIT_GYM_V2/api/sessions"
```

Create sessions directory:
```bash
sudo mkdir -p /var/www/html/FIT_GYM_V2/api/sessions
sudo chown www-data:www-data /var/www/html/FIT_GYM_V2/api/sessions
sudo chmod 700 /var/www/html/FIT_GYM_V2/api/sessions
```

---

## File Permissions

```bash
sudo chown -R www-data:www-data /var/www/html/FIT_GYM_V2
sudo chmod -R 755 /var/www/html/FIT_GYM_V2
sudo chmod 700 /var/www/html/FIT_GYM_V2/.env
sudo chmod -R 775 /var/www/html/FIT_GYM_V2/assets/uploads
```

---

## Composer Dependencies (PHPMailer)

```bash
cd /var/www/html/FIT_GYM_V2
composer install --no-dev
```

---

## Deployment Workflow

### Initial Deploy
1. SSH into server
2. Clone repo or upload files via SFTP
3. Copy `.env.example` → `.env`, fill in credentials
4. Run `composer install`
5. Import DB schema
6. Set permissions
7. Test in browser

### Updates
1. Local: commit + push to GitHub
2. Server: `git pull origin main`
3. Clear browser cache (if CSS/JS changed)
4. Test live site

---

## Monitoring & Logs

### Apache Logs
```bash
sudo tail -f /var/log/apache2/fitpay_error.log
sudo tail -f /var/log/apache2/fitpay_access.log
```

### PHP Error Log
Check `api/error_log` or syslog depending on config.

### MySQL Slow Query Log
```bash
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf
# Add:
slow_query_log = 1
slow_query_log_file = /var/log/mysql/slow.log
long_query_time = 2
```

---

## Backup Strategy

### Daily Database Backup (Cron)
```bash
crontab -e
# Add:
0 2 * * * /usr/bin/mysqldump -u fitpay_user -pPASSWORD fitpay_db | gzip > /backups/fitpay_$(date +\%Y\%m\%d).sql.gz
```

### Weekly Full Backup
```bash
0 3 * * 0 tar -czf /backups/fitpay_full_$(date +\%Y\%m\%d).tar.gz /var/www/html/FIT_GYM_V2
```

---

## Security Checklist

- [x] `.env` not in git, chmod 700
- [x] `display_errors = 0` in production
- [x] MySQL root password changed
- [x] Firewall enabled (ufw allow 80,443,22)
- [x] SSH key-only auth (no password login)
- [x] Regular security updates (`apt update && apt upgrade`)
- [x] Certbot auto-renewal enabled
- [x] Session files outside webroot
- [x] File upload directory ACL restricted
