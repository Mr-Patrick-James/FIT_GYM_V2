# Settings API

Base path: `api/settings/`

---

## GET `get.php`
Returns all key-value pairs from `gym_settings` table.  
Used by admin settings page and landing page (`index.php`).

---

## POST `update.php`
**Access:** Admin only  
Accepts any `key=value` POST field and upserts into `gym_settings`.  
Also handles:
- Admin profile update (`admin_name`, `admin_email`)
- Password change (`current_password`, `new_password`)
- Gallery/hero image uploads
- Why-Join feature items (`why_title_1..6`, `why_desc_1..6`)

---

## POST/DELETE `manage-managers.php`
- `POST` → creates a new gym manager account
- `DELETE` → removes manager access  
**Access:** Admin only

---

## POST `manage-admins.php`
Manages admin accounts.  
**Access:** Admin only

---

## GET `export-db.php`
Exports the database as a SQL dump.  
**Access:** Admin only

---

## Settings Keys Reference

| Key | Used In | Description |
|---|---|---|
| `gym_name` | Landing page, sidebar | Gym display name |
| `gym_address` | Landing page, footer | Physical address |
| `gym_contact` | Landing page, footer | Phone number |
| `gym_email` | Landing page, emails | Contact email |
| `opening_time` | Settings display | Opening hour |
| `closing_time` | Settings display | Closing hour |
| `about_text` | Landing page About section | Main description |
| `mission_text` | Landing page About section | Mission statement |
| `footer_tagline` | Landing page footer | Short tagline |
| `about_images` | Landing page slider | JSON array of image paths |
| `hero_images` | Landing page hero slider | JSON array of image paths |
| `gcash_number` | Booking modal | GCash payment number |
| `gcash_name` | Booking modal | GCash account name |
| `gcash_qr_path` | Booking modal | Path to QR image |
| `why_title_1..6` | Landing page Why section | Feature headings |
| `why_desc_1..6` | Landing page Why section | Feature descriptions |
