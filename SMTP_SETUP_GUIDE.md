# SMTP Setup Guide - Step by Step

## What is SMTP?

**SMTP = Simple Mail Transfer Protocol**

It's how your server **SENDS** emails to users. Think of it like a post office - you need credentials to send mail.

## How It Works:

```
Your Website → SMTP Server → User's Email Inbox
     ↓              ↓                ↓
  Generates    Sends email      User receives
  OTP code     via your Gmail   the code
```

## Step-by-Step Setup:

### Option 1: Gmail (Most Common)

#### Step 1: Enable 2-Factor Authentication
1. Go to: https://myaccount.google.com/security
2. Find "2-Step Verification"
3. Click "Get Started"
4. Follow the setup (usually via phone)

#### Step 2: Generate App Password
1. Go to: https://myaccount.google.com/apppasswords
2. Select:
   - **App**: Mail
   - **Device**: Other (Custom name)
   - **Name**: FitPay Gym
3. Click "Generate"
4. Copy the 16-character password (like: `abcd efgh ijkl mnop`)

#### Step 3: Create Email Config Table in Database
Run this SQL in phpMyAdmin:

```sql
-- Copy and paste from: database/add_email_config_table.sql
```

#### Step 4: Update Database with App Password
```sql
UPDATE email_configs 
SET smtp_password = 'abcdefghijklmnop'  -- Your 16-char App Password (no spaces)
WHERE smtp_username = 'patrickmontero833@gmail.com';
```

### Option 2: Outlook/Hotmail (Easier - No App Password Needed!)

#### Step 1: Use Your Regular Password
Outlook allows regular passwords for SMTP!

#### Step 2: Update Database
```sql
INSERT INTO email_configs (name, smtp_host, smtp_port, smtp_username, smtp_password, from_email, from_name, is_active, is_default) 
VALUES (
    'Outlook Email',
    'smtp-mail.outlook.com',
    587,
    'your-email@outlook.com',
    'your-regular-password',  -- Regular password works!
    'your-email@outlook.com',
    'Martinez Fitness',
    TRUE,
    TRUE
);
```

### Option 3: Use PHP mail() Function (Simplest - No Config!)

The code already falls back to PHP's `mail()` function if SMTP isn't configured. This works if your server/hosting supports it.

**No setup needed** - just test it!

## Testing SMTP:

### 1. Check if PHPMailer is Installed
```bash
# Should see vendor folder
dir vendor
```

### 2. Check Database Table
```sql
SELECT * FROM email_configs WHERE is_default = TRUE;
```

### 3. Test Signup
1. Go to your website
2. Sign up with your email
3. Check inbox for OTP

### 4. Check PHP Error Logs
If email doesn't send, check:
- XAMPP: `C:\xampp\php\logs\php_error_log`
- WAMP: `C:\wamp64\logs\php_error.log`

Look for errors like:
- "SMTP connect() failed"
- "Authentication failed"
- "PHPMailer not installed"

## Quick Setup Checklist:

- [ ] PHPMailer installed (`vendor` folder exists)
- [ ] Database table `email_configs` created
- [ ] Email credentials added to database
- [ ] App Password generated (for Gmail)
- [ ] Test signup and check email

## Common Issues:

**"Authentication failed"**
→ Wrong password or need App Password (Gmail)

**"SMTP connect() failed"**
→ Wrong SMTP host or port

**"PHPMailer not installed"**
→ Run `composer install` or `php composer.phar install`

**Email not received**
→ Check spam folder, check PHP error logs

## Which Email Provider to Use?

1. **Gmail** - Most common, but needs App Password
2. **Outlook** - Easier, regular password works
3. **PHP mail()** - Simplest, but less reliable
4. **Email API** - Most reliable (SendGrid, Mailgun)

Choose what works best for you!
