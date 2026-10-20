# 🏋️ FitPay Gym Management System

> Martinez Fitness Gym — Naujan, Oriental Mindoro  
> Live URL: https://fitpay.duckdns.org  
> Local URL: http://localhost/FIT_GYM_V2  
> Stack: PHP · MySQL · Vanilla JS · FullCalendar · Chart.js

---

## 🗂️ Vault Index

| Section | Description |
|---|---|
| [[01 - Overview/Project Summary]] | What the system does, tech stack, team |
| [[02 - Architecture/File Structure]] | Folder layout and how pages connect |
| [[02 - Architecture/Frontend Architecture]] | JS files, CSS files, how the UI is built |
| [[03 - API Reference/Auth API]] | Login, signup, OTP, forgot password |
| [[03 - API Reference/Bookings API]] | Create, verify, upgrade, expire |
| [[03 - API Reference/Trainer API]] | Clients, sessions, tips, food, progress |
| [[03 - API Reference/Settings API]] | Gym config, managers, backup |
| [[04 - Database/Schema Overview]] | All tables and relationships |
| [[04 - Database/Key Queries]] | Common queries used in the app |
| [[05 - Roles & Permissions/Role Matrix]] | What each role can access |
| [[06 - Features/Auto Workout Plan]] | AI-generated weekly workout plans |
| [[07 - Bug Log/Fixed Bugs]] | Bugs fixed during development |
| [[08 - Deployment/Live Server Setup]] | Ubuntu Apache deployment notes |

---

## 🚦 Quick Status

- **Live:** ✅ fitpay.duckdns.org
- **Email:** PHPMailer via SMTP (configured in `email_configs` table or `.env`)
- **Last major features:** Auto workout plan, manager role restrictions, trainer client visibility, notification bell
- **Last major fixes:** Forgot password, same-day expired bookings, revenue hiding for managers
