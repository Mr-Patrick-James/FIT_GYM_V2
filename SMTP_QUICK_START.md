# SMTP Quick Start - 3 Simple Steps

## What You Need to Understand:

**SMTP = How to SEND emails from your server**

When a user signs up:
1. System generates OTP code
2. System SENDS email TO user (using your email account)
3. User receives email with code
4. User enters code to verify

## Setup in 3 Steps:

### Step 1: Create Email Config Table

Run this SQL in phpMyAdmin:

```sql
USE fitpay_gym;

CREATE TABLE IF NOT EXISTS email_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    smtp_host VARCHAR(255) NOT NULL DEFAULT 'smtp.gmail.com',
    smtp_port INT NOT NULL DEFAULT 587,
    smtp_username VARCHAR(255) NOT NULL,
    smtp_password VARCHAR(255) NOT NULL,
    from_email VARCHAR(255) NOT NULL,
    from_name VARCHAR(255) NOT NULL DEFAULT 'Martinez Fitness',
    is_active BOOLEAN DEFAULT TRUE,
    is_default BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### Step 2: Add Your Email Credentials

#### For Gmail (Need App Password):
```sql
INSERT INTO email_configs (name, smtp_host, smtp_port, smtp_username, smtp_password, from_email, from_name, is_active, is_default) 
VALUES (
    'My Gmail',
    'smtp.gmail.com',
    587,
    'patrickmontero833@gmail.com',
    'your-app-password-here',  -- Get from: https://myaccount.google.com/apppasswords
    'patrickmontero833@gmail.com',
    'Martinez Fitness',
    TRUE,
    TRUE
);
```

#### For Outlook (Regular Password Works!):
```sql
INSERT INTO email_configs (name, smtp_host, smtp_port, smtp_username, smtp_password, from_email, from_name, is_active, is_default) 
VALUES (
    'My Outlook',
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

### Step 3: Test It!

1. Start web server (XAMPP/WAMP)
2. Go to your website
3. Sign up with your email
4. Check inbox for OTP code

## That's It!

The system will:
- ✅ Use PHPMailer if SMTP is configured
- ✅ Fall back to PHP mail() if not configured
- ✅ Save OTP in database (verification works either way)

## Troubleshooting:

**No email received?**
- Check spam folder
- Check PHP error logs
- Verify credentials in database

**Want to use different email?**
- Just update the `email_configs` table in database
- Set `is_default = TRUE` for the one you want to use
