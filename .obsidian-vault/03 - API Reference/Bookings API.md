# Bookings API

Base path: `api/bookings/`

---

## GET `get-all.php`
**Params:** `status=all|verified|pending|expired`, `search`, `sort`  
**Access:** Admin/Manager see all; regular users see only their own  
**Note:** Auto-expires verified bookings past `expires_at` before returning  
**Returns:** Array of booking objects with `amount`, `package_name`, `trainer_name`, `expires_at`, `is_walkin`, `is_student`

---

## POST `create.php`
**Body:** FormData with `package_id`, `contact`, `booking_date`, `notes`, `receipt` (file), `is_student`, `student_id` (file)  
**Behaviour:**
- Inserts booking with `status = 'pending'`
- Sends email notification to gym admin
- Does NOT set `trainer_id` (set during verification)

---

## PUT `update.php?id={bookingId}`
**Body:** `{ status, notes, trainer_id? }`  
**Statuses:** `verified`, `rejected`, `pending`  
**On verify:**
- Calculates `expires_at` from `booking_date` + package `duration`
- Creates payment record
- Auto-assigns trainer from `package_trainers` if none manually set
- Sends verification email to member
- Notifies assigned trainer

---

## POST `upgrade.php`
**Body:** `{ package_id, contact, notes, receipt }`  
Upgrades member to a higher-tier package.

---

## POST `change-plan.php`
Edit an existing pending booking.

---

## GET `check-expiry.php`
Checks and updates expired bookings in DB.

---

## GET `check-status.php?booking_id={id}`
Returns current status of a single booking.

---

## Booking Statuses

| Status | Meaning |
|---|---|
| `pending` | Submitted, awaiting admin verification |
| `verified` | Payment verified, membership active |
| `expired` | Past `expires_at` — auto-set by DB query |
| `rejected` | Admin rejected the payment |

---

## ⚠️ Same-Day Rule
Expired bookings where `DATE(booking_date) = CURDATE()` are treated as **active** for trainer save operations (tips, food, sessions, progress). This handles daily pass clients who visit and expire the same day.
