# Database Schema Overview

---

## Core Tables

### `users`
| Column | Type | Notes |
|---|---|---|
| id | INT PK | |
| name | VARCHAR | |
| email | VARCHAR | Stored mixed-case; always query with `LOWER()` |
| password | VARCHAR | bcrypt hashed |
| role | ENUM | `admin`, `manager`, `trainer`, `user` |
| contact | VARCHAR | |
| address | TEXT | |
| email_verified | TINYINT | 0 = unverified, 1 = verified |
| created_at | TIMESTAMP | |

---

### `bookings`
| Column | Type | Notes |
|---|---|---|
| id | INT PK | |
| user_id | INT FK→users | NULL for walk-ins |
| trainer_id | INT FK→trainers | Set on verification |
| package_id | INT FK→packages | |
| package_name | VARCHAR | Snapshot at booking time |
| amount | DECIMAL | |
| status | ENUM | `pending`, `verified`, `expired`, `rejected` |
| booking_date | DATE | Member's chosen start date |
| expires_at | DATETIME | Calculated from booking_date + duration |
| verified_at | DATETIME | When admin verified payment |
| receipt_url | VARCHAR | Path to uploaded receipt |
| is_student | TINYINT | 1 = student discount applied |
| student_id_url | VARCHAR | Path to student ID photo |
| notes | TEXT | Admin/trainer notes |
| is_walkin | computed | `user_id IS NULL` |

---

### `packages`
| Column | Type | Notes |
|---|---|---|
| id | INT PK | |
| name | VARCHAR | e.g. "Daily Pass", "Weekly Pass" |
| duration | VARCHAR | e.g. "1 Day", "7 Days", "1 Month" |
| price | DECIMAL | |
| tag | VARCHAR | "Popular", "Best Value", etc. |
| description | TEXT | Feature list (newline-separated) |
| is_trainer_assisted | TINYINT | Enables trainer assignment |
| is_active | TINYINT | |

---

### `package_trainers`
Junction table linking packages to trainers.
| Column | Type |
|---|---|
| id | INT PK |
| package_id | INT FK→packages |
| trainer_id | INT FK→trainers |

---

### `trainers`
| Column | Type | Notes |
|---|---|---|
| id | INT PK | |
| user_id | INT FK→users | Links trainer profile to user account |
| name | VARCHAR | |
| specialization | VARCHAR | |
| bio | TEXT | |
| email | VARCHAR | |
| contact | VARCHAR | |
| is_active | TINYINT | |
| photo_url | VARCHAR | |

---

### `payments`
Auto-created when a booking is verified.
| Column | Type |
|---|---|
| id | INT PK |
| user_id | INT FK→users |
| booking_id | INT FK→bookings |
| amount | DECIMAL |
| status | VARCHAR |
| payment_method | VARCHAR |
| transaction_id | VARCHAR |
| receipt_url | VARCHAR |

---

### `otps`
| Column | Type | Notes |
|---|---|---|
| id | INT PK | |
| email | VARCHAR | |
| code | VARCHAR(6) | 6-digit OTP |
| used | TINYINT | |
| expires_at | DATETIME | 15 minutes from creation |
| created_at | TIMESTAMP | |

---

### `notifications`
| Column | Type |
|---|---|
| id | INT PK |
| user_id | INT FK→users |
| title | VARCHAR |
| message | TEXT |
| type | VARCHAR |
| is_read | TINYINT |
| created_at | TIMESTAMP |

---

### `trainer_tips`
| Column | Type |
|---|---|
| id | INT PK |
| trainer_id | INT FK→trainers |
| member_id | INT FK→users |
| tip_text | TEXT |
| created_at | TIMESTAMP |

---

### `food_recommendations`
| Column | Type |
|---|---|
| id | INT PK |
| trainer_id | INT FK→trainers |
| member_id | INT FK→users |
| meal_type | VARCHAR |
| food_items | TEXT |
| calories | INT |
| created_at | TIMESTAMP |

---

### `trainer_sessions`
| Column | Type |
|---|---|
| id | INT PK |
| trainer_id | INT FK→trainers |
| member_id | INT FK→users |
| booking_id | INT FK→bookings |
| session_date | DATE |
| session_time | TIME |
| duration | INT (minutes) |
| type | VARCHAR |
| title | VARCHAR |
| notes | TEXT |
| exercises | TEXT |

---

### `member_progress`
| Column | Type |
|---|---|
| id | INT PK |
| booking_id | INT FK→bookings |
| trainer_id | INT FK→trainers |
| weight | DECIMAL |
| height | DECIMAL |
| remarks | TEXT |
| logged_by | ENUM `trainer`, `user` |
| logged_at | DATETIME |

---

### `member_exercise_plans`
| Column | Type |
|---|---|
| id | INT PK |
| booking_id | INT FK→bookings |
| exercise_id | INT FK→exercises |
| sets | INT |
| reps | VARCHAR |
| notes | TEXT |

---

### `exercises`
| Column | Type |
|---|---|
| id | INT PK |
| name | VARCHAR |
| category | VARCHAR |
| equipment_id | INT FK→equipment |
| description | TEXT |
| instructions | TEXT |
| image_url | VARCHAR |

---

### `gym_settings`
Simple key-value store.
| Column | Type |
|---|---|
| id | INT PK |
| setting_key | VARCHAR UNIQUE |
| setting_value | TEXT |

---

### Other Tables
- `equipment` — gym equipment list
- `email_configs` — SMTP configuration
- `user_questionnaire` — fitness assessment data per user
