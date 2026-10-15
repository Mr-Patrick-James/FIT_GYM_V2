<?php
// Memberships use Philippine calendar days; never change the global timezone.
function packageGymNow(?DateTimeImmutable $instant = null): DateTimeImmutable
{
    return ($instant ?? new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('Asia/Manila'));
}

function packageDurationDays($duration): ?int
{
    if (!is_string($duration)) return null;
    $duration = strtolower(trim($duration));
    $units = ['day' => 1, 'week' => 7, 'month' => 30, 'year' => 365];
    $days = null;
    if (preg_match('/^([1-9]\d*)\s*(day|week|month|year)s?(?:\s*\([^()]*\))?$/D', $duration, $match)) {
        $days = (float)$match[1] * $units[$match[2]];
    } elseif (preg_match('/^[1-9]\d*$/D', $duration)) {
        $days = (float)$duration;
    } elseif (preg_match('/^(daily|day|weekly|week|monthly|month|yearly|year|annual|annually)(?:\s+(?:pass|membership))?(?:\s*\([^()]*\))?$/D', $duration, $match)) {
        $days = ['daily' => 1, 'day' => 1, 'weekly' => 7, 'week' => 7,
            'monthly' => 30, 'month' => 30, 'yearly' => 365, 'year' => 365,
            'annual' => 365, 'annually' => 365][$match[1]];
    }
    // Reject malformed/zero durations rather than silently issuing a never-expiring pass.
    return $days !== null && $days >= 1 && $days <= 36500 ? (int)$days : null;
}

function packageCalendarDate($value): ?DateTimeImmutable
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) return null;
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Manila'));
    return $date !== false && $date->format('Y-m-d') === $value && (int)$date->format('Y') >= 1000 ? $date : null;
}

function packageBookingDates($bookingDate, $duration): ?array
{
    $start = packageCalendarDate($bookingDate);
    $days = packageDurationDays($duration);
    if (!$start || $days === null) return null;
    $end = $start->modify('+' . ($days - 1) . ' days');
    $exclusiveEnd = $start->modify('+' . $days . ' days');
    if ((int)$exclusiveEnd->format('Y') > 9999) return null;
    return [
        'booking_date' => $start->format('Y-m-d'),
        'duration_days' => $days,
        'end_date' => $end->format('Y-m-d'),
        'expires_at' => $end->format('Y-m-d') . ' 23:59:59',
        'calendar_end' => $exclusiveEnd->format('Y-m-d'),
    ];
}

function requirePackageBookingDates($bookingDate, $duration): array
{
    if (!packageCalendarDate($bookingDate)) {
        sendResponse(false, 'A valid booking start date (YYYY-MM-DD) is required.', null, 400);
    }
    if (packageDurationDays($duration) === null) {
        sendResponse(false, 'This package has an invalid duration. Please ask staff to set Daily, Weekly, or a positive number of Days, Weeks, Months, or Years.', null, 422);
    }
    $dates = packageBookingDates($bookingDate, $duration);
    if (!$dates) sendResponse(false, 'The package date range is not supported.', null, 400);
    return $dates;
}
