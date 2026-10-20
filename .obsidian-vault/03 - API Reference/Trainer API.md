# Trainer API

Base path: `api/trainers/`

---

## GET `get-clients.php`
Returns all clients assigned to the logged-in trainer.  
**Includes:**
- Verified bookings with `trainer_id` matching this trainer
- Verified bookings with `trainer_id = NULL` but `package_trainers` links the package to this trainer (fallback)
- Same-day expired bookings (daily pass clients)

**`is_expired` flag:** `false` for same-day expired (still workable today)

---

## GET `get-sessions.php?booking_id={id}&upcoming={0|1}`
Returns trainer sessions for a booking. `upcoming=1` filters future sessions only.

---

## POST `save-session.php`
**Body:** `{ booking_id, member_id, session_date, session_time, duration, type, title, notes, exercises }`  
No status check — trainer can always add sessions regardless of booking status.

---

## GET `get-tips.php?member_id={id}`
Returns all tips for a member.

---

## POST `save-tip.php`
**Body:** `{ member_id, tip_text }` or `{ tip_id, tip_text, member_id }` (edit mode)  
**Access rule:** Trainer must have a verified OR same-day-expired booking with this member.  
Supports **create** (no `tip_id`) and **update** (with `tip_id`).

---

## POST `delete-tip.php`
**Body:** `{ tip_id }`  
Only deletes tips belonging to the calling trainer.

---

## GET `get-food.php?member_id={id}`
Returns food recommendations for a member.

---

## POST `save-food.php`
**Body:** `{ member_id, meal_type, food_items, calories? }`  
**Access rule:** Same-day-expired bookings allowed.

---

## POST `log-progress.php`
Trainer logs progress for a client.  
**Body:** `{ booking_id, weight?, height?, remarks, logged_at }`

---

## POST `log-progress-user.php`
Member logs their own progress.  
**Body:** `{ booking_id, weight?, height?, remarks, logged_at }`  
**Access rule:** Allows same-day-expired bookings.

---

## GET `get-progress-history.php?booking_id={id}`
Returns progress logs for a booking.

---

## POST `save-member-plan.php`
Saves a customised exercise plan for a booking.  
**Body:** `{ booking_id, exercises: [{ id, sets, reps, notes }] }`

---

## GET `get-member-plan.php?booking_id={id}`
Returns the exercise plan for a booking.

---

## GET `get-my-trainer.php`
Called from member side — returns the trainer assigned to the member's active booking.

---

## ⚠️ Trainer Assignment Flow
1. Admin assigns a trainer when verifying a booking (via `modalTrainerSelect`)
2. If no trainer is manually assigned, `update.php` auto-assigns from `package_trainers`
3. `fix-missing-trainer.php` is a one-time repair script to backfill NULL trainer_ids
