# Bug Fixes

## Bug: Response body stream already read in `finishSurvey`

**File:** `assets/js/user-dashboard.js`
**Function:** `finishSurvey()`
**Date Fixed:** 2026-09-28

### Problem
`TypeError: Failed to execute 'text' on 'Response': body stream already read`

The `finishSurvey` function was calling `response.json()` and then, inside the catch block, attempting a second `response.text()` call on the same `Response` object. A fetch `Response` body can only be consumed once — the second read throws the stream error.

### Root Cause
```js
// BROKEN — reads body twice
try {
    result = await response.json();        // first read
} catch (parseError) {
    const text = await response.text();    // second read — throws!
    ...
}
```

### Fix
Read the body once as raw text, then parse it manually with `JSON.parse()`:
```js
// FIXED — single read
const rawText = await response.text();
console.log('save-questionnaire raw response:', rawText);
result = JSON.parse(rawText);
```

---

## Bug: PHP warnings corrupting JSON response from `save-questionnaire.php`

**File:** `api/users/save-questionnaire.php`
**Date Fixed:** 2026-09-28

### Problem
`SyntaxError: Unexpected token '<', "<br />"... is not valid JSON`

The PHP file had `display_errors = 1`, causing PHP warnings/notices from included files (`config.php`, `session.php`) to be printed as HTML before the JSON output. This broke `JSON.parse()` on the frontend.

### Root Cause
```php
// BROKEN — HTML errors bleed into the response body
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
```

### Fix
Disable display errors so warnings go to the PHP error log instead of the response:
```php
// FIXED
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
```
