<?php
// Shared trainer booking rules. Do not change PHP/MySQL's global timezone.
function trainerGymNow(?DateTimeImmutable $instant = null): DateTimeImmutable
{
    return ($instant ?? new DateTimeImmutable('now'))->setTimezone(new DateTimeZone('Asia/Manila'));
}

function trainerGymToday(?DateTimeImmutable $instant = null): string
{
    return trainerGymNow($instant)->format('Y-m-d');
}

// Placeholder order: explicit trainer, package trainer. An explicit assignment wins.
function trainerBookingAssignmentSql(): string
{
    return "(b.trainer_id = ? OR (b.trainer_id IS NULL AND EXISTS (
        SELECT 1 FROM package_trainers pt
        WHERE pt.package_id = b.package_id AND pt.trainer_id = ?
    )))";
}

// Paid assigned history stays visible independently of current write eligibility.
function trainerBookingHistorySql(): string
{
    return "b.status IN ('verified', 'expired', 'upgraded')";
}

// Placeholder order: gym-local timestamp, gym-local calendar date.
function trainerBookingWritableSql(): string
{
    return "((b.status = 'verified' AND (b.expires_at IS NULL OR b.expires_at >= ?))
        OR (b.status IN ('verified', 'expired') AND DATE(b.booking_date) = ?))";
}

function trainerBookingIsWritable(array $booking, ?DateTimeImmutable $instant = null): bool
{
    $now = trainerGymNow($instant);
    $status = $booking['status'] ?? '';
    if (!in_array($status, ['verified', 'expired'], true)) {
        return false;
    }
    // booking_date is the recorded visit/start day; scheduled sessions are not attendance.
    if (substr((string)($booking['booking_date'] ?? ''), 0, 10) === $now->format('Y-m-d')) {
        return true;
    }
    $expiry = $booking['expires_at'] ?? null;
    return $status === 'verified' && ($expiry === null || $expiry >= $now->format('Y-m-d H:i:s'));
}

function trainerValidCalendarDate($value): bool
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
        return false;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Manila'));
    return $date !== false && $date->format('Y-m-d') === $value;
}

function findTrainerProfileId($conn, int $userId): ?int
{
    $stmt = $conn->prepare('SELECT id FROM trainers WHERE user_id = ?');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $trainer = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $trainer ? (int)$trainer['id'] : null;
}

function requireTrainerProfileId($conn, int $userId): int
{
    $trainerId = findTrainerProfileId($conn, $userId);
    if (!$trainerId) {
        sendResponse(false, 'Trainer profile not found.', null, 403);
    }
    return $trainerId;
}

// Historical reads may use this without requiring current write eligibility.
function findTrainerBooking($conn, int $bookingId, int $trainerId): ?array
{
    $assignment = trainerBookingAssignmentSql();
    $stmt = $conn->prepare("SELECT b.*, p.name AS package_name, p.is_trainer_assisted
        FROM bookings b JOIN packages p ON b.package_id = p.id
        WHERE b.id = ? AND $assignment");
    $stmt->bind_param('iii', $bookingId, $trainerId, $trainerId);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $booking ?: null;
}

function requireWritableTrainerBooking($conn, int $bookingId, int $trainerId, ?int $memberId = null): array
{
    if ($bookingId <= 0) {
        sendResponse(false, 'Booking ID is required. Reopen the selected client booking.', null, 400);
    }
    $booking = findTrainerBooking($conn, $bookingId, $trainerId);
    if (!$booking || ($memberId !== null && ($memberId <= 0 || (int)$booking['user_id'] !== $memberId))) {
        sendResponse(false, 'Unauthorized or selected client booking not found.', null, 403);
    }
    if (!trainerBookingIsWritable($booking)) {
        sendResponse(false, 'This booking is no longer active. Expired bookings can only be managed on their recorded gym date (Philippine time).', null, 403);
    }
    return $booking;
}

// A notification failure must not make an already-saved recommendation look unsaved.
function notifyTrainerMember(int $memberId, string $title, string $message, string $type): void
{
    try {
        createNotification($memberId, $title, $message, $type);
    } catch (Throwable $error) {
        error_log('Trainer notification failed: ' . $error->getMessage());
    }
}
