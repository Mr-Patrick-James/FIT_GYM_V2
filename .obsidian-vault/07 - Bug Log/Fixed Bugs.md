# Fixed Bugs

---

## 2026-09-28 — Response body stream already read in `finishSurvey`

**File:** `assets/js/user-dashboard.js`  
**Function:** `finishSurvey()`

### Problem
`TypeError: Failed to execute 'text' on 'Response': body stream already read`

The function called `response.json()` first, then inside the catch block tried `response.text()` on the same Response object. A fetch Response body can only be consumed once.

### Fix
Read the body once as raw text, then parse manually with `JSON.parse()`:
```js
const rawText = await response.text();
result = JSON.parse(rawText);
```

---

## 2026-09-28 — PHP warnings corrupting JSON in `save-questionnaire.php`

**File:** `api/users/save-questionnaire.php`

### Problem
`SyntaxError: Unexpected token '<', "<br />"... is not valid JSON`

PHP had `display_errors = 1`, causing warnings/notices from included files to print as HTML before the JSON output, breaking `JSON.parse()` on the frontend.

### Fix
Disabled display errors so warnings go to PHP error log instead of response:
```php
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
```

---

## 2026-10-21 — Trainer client visibility (missing fallback to `package_trainers`)

**File:** `api/trainers/get-clients.php`

### Problem
Trainers couldn't see clients whose bookings had `trainer_id = NULL` even though the package was linked to them via `package_trainers` table.

### Fix
Added fallback query:
```sql
OR (b.trainer_id IS NULL AND EXISTS (
    SELECT 1 FROM package_trainers pt
    WHERE pt.package_id = b.package_id AND pt.trainer_id = ?
))
```

---

## 2026-10-21 — Same-day expired bookings not workable for trainers

**Files:** Multiple trainer save endpoints (tips, food, sessions, progress)

### Problem
Daily pass clients expire at midnight the same day they book. Trainers couldn't save tips/food for them because the status was `expired`.

### Fix
Changed validation from:
```sql
WHERE b.status = 'verified'
```
To:
```sql
WHERE (b.status = 'verified' OR (b.status = 'expired' AND DATE(b.booking_date) = CURDATE()))
```

Now trainers can work with same-day expired bookings.

---

## 2026-10-21 — Manager role seeing revenue data

**Files:** `views/admin/dashboard.php`, `views/admin/bookings.php`, `assets/js/dashboard.js`

### Problem
Managers (operational role) could see all revenue stats, Package Performance chart, and payment amounts — which should be admin-only.

### Fix
- PHP: Wrapped revenue sections in `<?php if (isAdmin()): ?>`
- JS: Added `isManagerRole` check, replaced revenue values with `—` for managers
- Hidden: Package Performance chart, Amount column in bookings table, Payment Amount in modal

---

## 2026-10-21 — Forgot password not working

**File:** `api/auth/forgot-password.php`

### Problems
1. Email delivery failed silently → returned 503 error → frontend showed nothing
2. Case-sensitive email lookup failed for mixed-case stored emails
3. 5-minute OTP window too short

### Fixes
1. Always save OTP regardless of email success, log it server-side, return success message
2. Changed query to `WHERE LOWER(email) = ?` (case-insensitive)
3. Extended OTP expiry from 5 → 15 minutes
4. Applied same case-insensitive fix to `reset-password.php` and `verify-otp.php`

---

## 2026-10-21 — Notification bell not showing count

**File:** `assets/js/user-dashboard.js`

### Problem
Notification count badge always showed 0 even when unread notifications existed.

### Fix
The `loadNotifications()` function was correctly fetching data but the badge update logic was missing the unread count calculation. Added:
```js
const unreadCount = notifications.filter(n => !n.is_read).length;
const badge = document.getElementById('notificationBadge');
if (badge) badge.textContent = unreadCount || '';
```

---

## 2026-10-21 — "Active Bookings" stat showing expired bookings

**Files:** `assets/js/user-dashboard.js`

### Problem
The dashboard stat box labeled "Booking Logs" showed count of all `verified` bookings, including expired ones. Misleading for members.

### Fixes
1. Renamed label from "Booking Logs" → "Active Bookings" with check-circle icon
2. Changed count from `filter(b => b.status === 'verified')` → `filter(b => isBookingActive(b))`
3. `isBookingActive()` checks both `status === 'verified'` AND `expires_at > now`

Now accurately shows only currently active memberships.


---

## 2026-10-21 — Package recommendation not pre-selecting in booking modal

**File:** `assets/js/user-dashboard.js`  
**Function:** `openBookingModal()`

### Problem
When the system recommended a package after the fitness survey, clicking "Book Now" opened the booking modal but the dropdown was blank — user had to manually find and select the recommended package. The end date field was also empty.

### Fix
Modified `openBookingModal()` to accept optional parameters:
```js
function openBookingModal(preselectedPackage = null, preselectedPrice = null) {
    // ... set today's date ...
    
    // Auto-select recommended package
    if (preselectedPackage) {
        const packageSelect = document.getElementById('bookingPackage');
        const options = Array.from(packageSelect.options);
        const matchingOption = options.find(opt => opt.value === preselectedPackage);
        if (matchingOption) {
            packageSelect.value = preselectedPackage;
        }
    }
    
    handlePackageSelectChange(); // This triggers updateBookingDatePreview()
    // ...
}
```

Now when the recommendation modal calls `openBookingModal(pkg.name, pkg.price)`:
1. The recommended package is auto-selected in the dropdown
2. `handlePackageSelectChange()` → `updateBookingDatePreview()` auto-fills the end date based on package duration
3. User just needs to upload receipt and submit
