# File Structure

```
FIT_GYM_V2/
├── index.php                    ← Landing page
├── api/
│   ├── config.php               ← DB connection, sendResponse()
│   ├── session.php              ← Auth guards: requireAdmin(), requireTrainer(), etc.
│   ├── access-control.php       ← Role permissions constants
│   ├── email.php                ← All email sending functions
│   ├── auth/                    ← Login, signup, OTP, reset password
│   ├── bookings/                ← Create, verify, upgrade, get bookings
│   ├── equipment/               ← CRUD for gym equipment
│   ├── exercises/               ← CRUD for exercise library
│   ├── notifications/           ← Get all, mark as read
│   ├── packages/                ← CRUD for membership packages
│   ├── payments/                ← Get all payments
│   ├── reports/                 ← Sales reports
│   ├── settings/                ← Gym config, managers, DB export
│   ├── trainers/                ← Trainer clients, sessions, tips, food, progress
│   ├── upload/                  ← Receipt, student ID, progress photo, trainer photo
│   ├── users/                   ← Profile, questionnaire, password change
│   └── walkin/                  ← Walk-in booking creation
│
├── assets/
│   ├── css/
│   │   ├── dashboard.css        ← Shared styles for admin/trainer panels
│   │   ├── style.css            ← Landing page only
│   │   └── user-dashboard/      ← User member dashboard CSS (split into modules)
│   ├── js/
│   │   ├── dashboard.js         ← Admin dashboard logic + stats
│   │   ├── bookings.js          ← Admin bookings table + modal
│   │   ├── exercises.js         ← Exercise library CRUD
│   │   ├── user-dashboard.js    ← Member dashboard (all sections)
│   │   ├── reports.js           ← Sales reports + charts
│   │   ├── settings.js          ← Settings page logic
│   │   ├── trainers.js          ← Trainer management (admin side)
│   │   ├── theme.js             ← Dark/light mode toggle
│   │   └── mobile-menu.js       ← Sidebar hamburger menu
│   └── uploads/                 ← Uploaded files (receipts, photos, etc.)
│
└── views/
    ├── admin/                   ← Admin-only pages
    ├── trainer/                 ← Trainer pages
    └── user/                    ← Member page
```

---

## Important Conventions

- All API endpoints return JSON via `sendResponse(bool, string, data, httpCode)`
- Auth is checked at the top of every page/API via `requireAdmin()`, `requireTrainer()`, etc.
- `dashboard.css` is loaded by ALL non-landing pages — changes here affect everything
- `font-size: 110%` is set on `html` in `dashboard.css` and `user-dashboard/base.css` to scale fonts 10% larger
