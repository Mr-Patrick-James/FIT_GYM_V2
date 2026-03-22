<?php
require_once '../config.php';
require_once '../session.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendResponse(false, 'Method not allowed', null, 405);
}

$conn = getDBConnection();

$query = "SELECT t.name, t.specialization, t.email, t.contact, t.bio,
          t.certifications, t.availability, t.max_clients, t.is_active, t.created_at,
          (SELECT COUNT(*) FROM package_trainers pt WHERE pt.trainer_id = t.id) as package_count,
          (SELECT COUNT(*) FROM bookings b WHERE b.trainer_id = t.id AND b.status = 'verified' AND (b.expires_at IS NULL OR b.expires_at >= CURDATE())) as active_clients,
          (SELECT COUNT(*) FROM bookings b WHERE b.trainer_id = t.id AND b.status = 'verified') as total_clients
          FROM trainers t ORDER BY t.name ASC";

$result = $conn->query($query);

if (!headers_sent()) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="trainers_' . date('Y-m-d') . '.csv"');
}

$out = fopen('php://output', 'w');
fputcsv($out, ['Name', 'Specialization', 'Email', 'Contact', 'Bio', 'Certifications', 'Availability', 'Max Clients', 'Active', 'Active Clients', 'Total Clients Handled', 'Packages Assigned', 'Joined']);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        fputcsv($out, [
            $row['name'],
            $row['specialization'],
            $row['email'],
            $row['contact'],
            $row['bio'],
            $row['certifications'],
            $row['availability'],
            $row['max_clients'],
            $row['is_active'] ? 'Yes' : 'No',
            $row['active_clients'],
            $row['total_clients'],
            $row['package_count'],
            $row['created_at'],
        ]);
    }
}

fclose($out);
$conn->close();
exit();
?>
