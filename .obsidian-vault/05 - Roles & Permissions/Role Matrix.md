# Role Matrix

## Overview

FitPay has 4 user roles: **Admin**, **Manager**, **Trainer**, **User** (member).

---

## Access Control

| Feature | Admin | Manager | Trainer | User |
|---|---|---|---|---|
| **Dashboard Stats** | ✅ All | ✅ Bookings only | ✅ My clients | ✅ Own stats |
| **Revenue Data** | ✅ | ❌ | ❌ | ❌ |
| **View All Bookings** | ✅ | ✅ | ❌ Only assigned | ❌ Own only |
| **Verify Payments** | ✅ | ✅ | ❌ | ❌ |
| **Manage Packages** | ✅ | ❌ | ❌ | ❌ |
| **Manage Trainers** | ✅ | ❌ | ❌ | ❌ |
| **Manage Equipment** | ✅ | ❌ | ❌ | ❌ |
| **View Members** | ✅ | ✅ | ✅ Assigned only | ❌ |
| **Gym Settings** | ✅ | ❌ | ❌ | ❌ |
| **Export Database** | ✅ | ❌ | ❌ | ❌ |
| **Sales Reports** | ✅ | ❌ | ❌ | ❌ |
| **Manage Admins/Managers** | ✅ | ❌ | ❌ | ❌ |
| **Book Packages** | ❌ | ❌ | ❌ | ✅ |
| **View Own Bookings** | ✅ | ✅ | ✅ | ✅ |
| **Trainer Features** | ❌ | ❌ | ✅ | ❌ |
| **Auto Workout Plan** | ❌ | ❌ | ❌ | ✅ |

---

## Role Definitions

### Admin
- **Full system access**
- Can see all revenue, bookings, members, trainers
- Manages gym settings, packages, equipment
- Can create/remove managers and other admins
- Dashboard: `views/admin/dashboard.php`

### Manager
- **Operational oversight** (no financial data)
- Can verify bookings (no revenue visible)
- Can view members and trainers
- Cannot see Package Performance chart (revenue-based)
- Cannot see Amount column in bookings table
- Dashboard: `views/admin/manager-dashboard.php` (loads `dashboard.php` with restrictions)

### Trainer
- **Client management only**
- Sees only assigned clients (via `trainer_id` in bookings or `package_trainers` fallback)
- Can add sessions, tips, food recommendations, progress logs
- Can view client exercise plans
- Same-day expired bookings still workable (daily pass clients)
- Dashboard: `views/trainer/dashboard.php`

### User (Member)
- **Self-service membership**
- Books packages, uploads receipts
- Views own bookings, payments, assigned trainer
- Completes fitness questionnaire
- Gets auto-generated weekly workout plan
- Dashboard: `views/user/dashboard.php`

---

## How Managers Are Restricted

Managers use the same UI as admins but with hidden revenue sections:

1. **PHP-side:** `isAdmin()` checks wrap revenue-sensitive HTML blocks
2. **JS-side:** `document.body.classList.contains('role-manager')` hides revenue stat values with `—`
3. **Package Performance chart** — hidden entirely for managers
4. **Amount column** in bookings table — hidden in both table header and row cells

Check `api/access-control.php` and `api/session.php` for role enforcement.
