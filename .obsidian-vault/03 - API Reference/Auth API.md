# Auth API

Base path: `api/auth/`

---

## POST `login.php`
**Body:** `{ email, password }`  
**Returns:** `{ success, message, data: { redirect } }`  
**Redirects by role:**
- `admin` → `views/admin/dashboard.php`
- `manager` → `views/admin/manager-dashboard.php`
- `trainer` → `views/trainer/dashboard.php`
- `user` → `views/user/dashboard.php`

---

## POST `signup.php`
**Body:** `{ name, email, password, contact?, address? }`  
**Returns:** success + sends OTP email  
**Note:** Account is created with `email_verified = 0`. Must verify OTP before login.

---

## POST `verify-otp.php`
**Body:** `{ email, code }`  
**Returns:** success and marks `email_verified = 1` in `users` table  
**OTP expires:** 15 minutes (stored in `otps` table)

---

## POST `resend-otp.php`
**Body:** `{ email }`  
**Returns:** new OTP sent to email

---

## POST `forgot-password.php`
**Body:** `{ email }`  
**Behaviour:**
- Case-insensitive email lookup (`LOWER(email) = ?`)
- Creates OTP in `otps` table (15-minute expiry)
- Sends reset code via PHPMailer (falls back to `mail()`)
- Returns success even if email fails — OTP is logged server-side
- Does NOT disclose if account exists (privacy)

---

## POST `reset-password.php`
**Body:** `{ email, code, new_password }`  
**Returns:** success, marks OTP as used

---

## POST `logout.php`
Destroys session, returns success.

---

## ⚠️ Known Issues / Notes
- Email case sensitivity: all inputs are lowercased before DB lookup using `LOWER(email)`
- If SMTP is not configured in `email_configs` table or `.env`, emails silently fail but the OTP is still saved to DB and logged
- Check `api/email.php` → `getEmailConfig()` to see SMTP source priority
