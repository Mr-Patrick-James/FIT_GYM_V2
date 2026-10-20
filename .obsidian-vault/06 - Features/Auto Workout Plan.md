# Auto-Generated Workout Plan

## Overview

After completing the fitness questionnaire, members get an **AI-generated weekly workout calendar** based on their goals, experience level, and workout frequency. No trainer needed — the system builds a complete 7-day plan automatically.

---

## How It Works

### 1. Member completes questionnaire
- Questions: primary goal, exercise history, workout days/week, focus areas, workout type, etc.
- Saved to `user_questionnaire` table

### 2. System generates plan
- **Trigger:** After questionnaire save (auto) OR click "Generate Plan" button
- **Endpoint:** `POST api/users/generate-plan.php`
- **Logic:** Pure PHP rule-based generator (no ML/AI API)
- **Output:** 7-day JSON plan saved to `generated_workout_plans` table

### 3. Plan displayed as weekly calendar
- **Location:** Member dashboard → "My Plan" section
- **View:** 7 color-coded day cards (Mon–Sun)
- **Each day shows:** Focus (e.g. "Leg Day"), exercises with sets/reps, rest days, motivational notes

---

## Plan Generation Rules

### Workout Days Per Week
- **"1-2 days"** → 2 workout days, 5 rest
- **"3-4 days"** → 4 workout days (Mon/Tue/Thu/Fri pattern)
- **"5+ days"** → 5 workout days (Mon–Fri), Sat/Sun rest

### Muscle Group Rotation
- **Leg Day** (green) — Squats, Lunges, Leg Press, Curls, Calf Raises
- **Push Day** (blue) — Bench, Shoulder Press, Incline Press, Lateral Raises, Tricep Dips
- **Pull Day** (orange) — Pull-ups, Rows, Face Pulls, Bicep Curls
- **Core Day** (yellow) — Planks, Russian Twists, Leg Raises, Mountain Climbers
- **Cardio Day** (red) — Treadmill, Rowing, Battle Ropes, Jump Rope, Burpees
- **Full Body** (purple) — Deadlifts, Bench, Squats, Pull-ups, Planks
- **Rest Day** (grey) — Active recovery, stretching, hydration

### Intensity by Experience
| Level | Sets | Reps |
|---|---|---|
| Beginner | 3 | 10-12 |
| Intermediate | 3 | 8-12 |
| Advanced | 4 | 6-10 |

---

## Database

### Table: `generated_workout_plans`
```sql
CREATE TABLE `generated_workout_plans` (
    `id`         INT NOT NULL AUTO_INCREMENT,
    `user_id`    INT NOT NULL,
    `plan_json`  LONGTEXT NOT NULL COMMENT 'JSON array of 7 day objects',
    `generated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_user` (`user_id`)
);
```

### Plan JSON Structure
```json
[
  {
    "day": "Monday",
    "focus": "Leg Day",
    "type": "workout",
    "color": "#10b981",
    "exercises": [
      "Squats 3×10-12",
      "Lunges 3×10-12 each leg",
      "Leg Press 3×10-12"
    ],
    "notes": "Stay consistent!"
  },
  {
    "day": "Tuesday",
    "focus": "Rest Day",
    "type": "rest",
    "color": "#6b7280",
    "exercises": [
      "Active recovery",
      "Light stretching or yoga"
    ],
    "notes": "Recovery is when muscles grow!"
  }
]
```

---

## API Endpoints

### `POST api/users/generate-plan.php`
Generates + saves a new plan for the logged-in user.  
**Returns:** `{ success, data: { plan: [...], generated_at: "..." } }`

### `GET api/users/get-plan.php`
Returns the user's saved plan (if exists).  
**Returns:** `{ success, data: { plan: [...], generated_at: "..." } }` or 404

---

## UI Components

### Member Dashboard
- **Nav link:** "My Plan" (calendar-week icon)
- **Section:** `#planSection`
- **Grid:** `#weeklyPlanGrid` — 7 day cards in CSS grid
- **Button:** "Generate Plan" — calls `generateMyPlan()`
- **Status:** Shows "Generated: [date] at [time]"

### Styling
- Cards: dark background, color-coded left border
- Hover: lift + shadow effect
- Mobile: single column stack or horizontal scroll
- CSS: `assets/css/user-dashboard/dashboard.css`

---

## JS Functions

### `loadMyPlan()`
Fetches existing plan from `get-plan.php`, renders it or shows empty state.

### `generateMyPlan()`
POSTs to `generate-plan.php`, shows loading spinner, renders result.

### `renderWeeklyPlan(plan, generatedAt)`
Builds 7 day cards with exercises, color borders, type badges.

---

## Auto-Generation Flow

1. User fills out questionnaire
2. Click "Submit" → saves to DB
3. `finishSurvey()` → auto-calls `generateMyPlan()`
4. Plan generated in background, saved to DB
5. Toast: "Workout plan generated successfully!"
6. User can view it anytime via "My Plan" nav

---

## Future Enhancements

- [ ] Add "Export to PDF" button
- [ ] Add "Share Plan" feature
- [ ] Integrate with trainer-assigned plans (show both side-by-side)
- [ ] Weekly progress tracking per plan
- [ ] Allow manual exercise swaps
