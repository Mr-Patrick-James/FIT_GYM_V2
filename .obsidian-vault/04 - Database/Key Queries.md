# Key Queries

## Get trainer's active clients (including same-day expired)
```sql
SELECT b.*, u.name, p.name as package_name
FROM bookings b
LEFT JOIN users u ON b.user_id = u.id
JOIN packages p ON b.package_id = p.id
WHERE (
    b.status = 'verified'
    OR (b.status = 'expired' AND DATE(b.booking_date) = CURDATE())
)
AND (
    b.trainer_id = :trainer_id
    OR (b.trainer_id IS NULL AND EXISTS (
        SELECT 1 FROM package_trainers pt
        WHERE pt.package_id = b.package_id AND pt.trainer_id = :trainer_id
    ))
);
```

---

## Auto-expire verified bookings
```sql
UPDATE bookings 
SET status = 'expired' 
WHERE status = 'verified' 
  AND expires_at IS NOT NULL 
  AND expires_at < NOW();
```

---

## Get total revenue (all paid bookings)
```sql
SELECT SUM(b.amount) as total
FROM bookings b
WHERE b.status IN ('verified', 'expired');
```

---

## Get monthly revenue
```sql
SELECT SUM(b.amount) as monthly
FROM bookings b
WHERE b.status IN ('verified', 'expired')
  AND MONTH(COALESCE(b.verified_at, b.booking_date)) = MONTH(NOW())
  AND YEAR(COALESCE(b.verified_at, b.booking_date)) = YEAR(NOW());
```

---

## Backfill missing trainer_id from package_trainers
```sql
UPDATE bookings b
JOIN package_trainers pt ON pt.package_id = b.package_id
JOIN trainers t ON t.id = pt.trainer_id AND t.is_active = 1
SET b.trainer_id = pt.trainer_id
WHERE b.trainer_id IS NULL
  AND b.status IN ('verified', 'expired');
```
*(Run `api/bookings/fix-missing-trainer.php` to do this safely)*

---

## Check active members count
```sql
SELECT COUNT(DISTINCT user_id) as active
FROM bookings
WHERE status = 'verified'
  AND (expires_at IS NULL OR expires_at >= NOW());
```
