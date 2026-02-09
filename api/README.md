# FitPay API Documentation

## Setup Instructions

1. **Configure Database Connection**
   - Edit `api/config.php`
   - Update database credentials:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     define('DB_NAME', 'fitpay_gym');
     ```

2. **Import Database**
   - Open phpMyAdmin
   - Import `database/create_database.sql`
   - This creates the database and tables

3. **Test API**
   - Make sure your web server (XAMPP/WAMP) is running
   - Access: `http://localhost/Fit/api/auth/login.php`

## API Endpoints

### Authentication

#### POST `/api/auth/signup.php`
Register a new user and send OTP.

**Request:**
```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123"
}
```

**Response:**
```json
{
    "success": true,
    "message": "OTP sent to your email",
    "data": {
        "email": "john@example.com",
        "otp": "123456"  // Remove in production
    }
}
```

#### POST `/api/auth/verify-otp.php`
Verify OTP and create user account.

**Request:**
```json
{
    "email": "john@example.com",
    "otp": "123456",
    "name": "John Doe",
    "password": "password123",
    "contact": "0917-123-4567",
    "address": "Manila, Philippines"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Email verified and account created successfully",
    "data": {
        "user_id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "role": "user"
    }
}
```

#### POST `/api/auth/resend-otp.php`
Resend OTP code.

**Request:**
```json
{
    "email": "john@example.com"
}
```

**Response:**
```json
{
    "success": true,
    "message": "New OTP sent to your email",
    "data": {
        "email": "john@example.com",
        "otp": "123456"  // Remove in production
    }
}
```

#### POST `/api/auth/login.php`
Login user.

**Request:**
```json
{
    "email": "admin@martinezfitness.com",
    "password": "admin123"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Login successful",
    "data": {
        "user": {
            "id": 1,
            "name": "Admin Martinez",
            "email": "admin@martinezfitness.com",
            "role": "admin",
            "contact": null,
            "address": null,
            "email_verified": true
        },
        "redirect": "/views/admin/dashboard.html"
    }
}
```

## Frontend Integration

The JavaScript in `assets/js/main.js` is now connected to these PHP APIs. It will:
1. Try to use the PHP API first
2. Fall back to localStorage/demo accounts if API is not available

## Testing

1. Start your web server (XAMPP/WAMP)
2. Open the application in browser
3. Try signing up a new user
4. Check the OTP in the notification (for development)
5. Verify the OTP
6. Login with the new account

## Production Notes

- Remove OTP from API responses in production
- Implement actual email sending in PHP
- Use proper password hashing (already implemented with bcrypt)
- Add rate limiting for OTP requests
- Use HTTPS in production
