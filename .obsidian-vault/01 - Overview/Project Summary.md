# Project Summary

## What is FitPay?

FitPay is a **gym management web app** built for Martinez Fitness Gym in Naujan, Oriental Mindoro. It handles the full membership lifecycle:

- Members book packages online, upload GCash receipts
- Admin/manager verifies payments
- Trainers manage their assigned clients
- Members track their progress, sessions, and tips from trainers

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8 (procedural, no framework) |
| Database | MySQL (via WAMP locally, Ubuntu/Apache live) |
| Frontend | Vanilla JS (no React/Vue), HTML, CSS |
| Charts | Chart.js |
| Calendar | FullCalendar v6 |
| Email | PHPMailer via SMTP |
| CSS Framework | Custom (`dashboard.css`, `style.css`) |
| Auth | PHP Sessions |
| File Uploads | PHP `move_uploaded_file()` |

---

## Entry Points

| URL | Description |
|---|---|
| `/` or `/index.php` | Landing page (packages, about, testimonials) |
| `/views/admin/dashboard.php` | Admin dashboard |
| `/views/admin/bookings.php` | Booking management |
| `/views/admin/manager-dashboard.php` | Manager landing page |
| `/views/trainer/dashboard.php` | Trainer dashboard |
| `/views/trainer/members.php` | Trainer client list |
| `/views/trainer/client-details.php` | Per-client management |
| `/views/user/dashboard.php` | Member dashboard |

---

## Team

- **Developer:** Pat (original), ongoing work with Kiro AI
- **Gym Owner:** Martinez Fitness Gym
