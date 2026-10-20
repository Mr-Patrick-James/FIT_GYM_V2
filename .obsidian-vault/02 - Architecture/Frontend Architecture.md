# Frontend Architecture

## How Pages Work

Each PHP view file is a **complete HTML page** — it includes its own `<head>`, loads CSS, and at the bottom loads JS files via `<script src>`. There is no build system (no webpack, no npm).

## API Calls

All fetch calls use a `getApiUrl(path)` helper that builds an absolute URL:

```js
function getApiUrl(path) {
    const parts = window.location.pathname.split('/views/');
    if (parts.length >= 2) {
        const rootPath = parts[0] || '';
        return window.location.origin + rootPath + '/api/' + path;
    }
    return '../../api/' + path;
}
```

> ⚠️ **Important:** Always use `getApiUrl()` — never hardcode `../../api/` paths. These break on the live Ubuntu server because the path depth differs.

## Key JS Files

| File | Purpose |
|---|---|
| `dashboard.js` | Admin dashboard stats, bookings table, package chart |
| `bookings.js` | Admin bookings full CRUD, verify/reject modal |
| `user-dashboard.js` | Everything on the member page (bookings, payments, trainer, profile, notifications) |
| `exercises.js` | Exercise library (used by admin AND trainer) |
| `reports.js` | Sales report charts |
| `settings.js` | Admin settings page (gym config, managers, admins) |
| `theme.js` | Dark/light mode — saves to `localStorage` |
| `mobile-menu.js` | Hamburger sidebar for mobile |

## CSS Strategy

- `dashboard.css` — single shared file for all admin/trainer pages
- `user-dashboard/base.css` + module files — member dashboard
- `style.css` — landing page only (never loads dashboard.css)
- `html { font-size: 110% }` in both dashboard CSS roots scales fonts 10%

## Responsive Strategy

- Sidebar goes off-canvas at ≤992px (hamburger menu)
- Tab bars scroll horizontally on mobile (`flex-wrap: nowrap; overflow-x: auto`)
- Tables reflow to card layout on mobile via `display: block` on `tr/td`
- Inline `style=` grid layouts are overridden by CSS classes in media queries
