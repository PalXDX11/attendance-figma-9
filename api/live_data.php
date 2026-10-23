<?php
require '../auth.php';
require '../db.php';
requireAdmin();
header('Content-Type: application/json');

$stmt = $pdo->query("
    SELECT e.name, e.hourly_rate, a.clock_in,
           TIMESTAMPDIFF(SECOND, a.clock_in, NOW()) AS elapsed_secs
    FROM attendance_logs a
    JOIN employees e ON e.id = a.employee_id
    WHERE a.clock_out IS NULL
    ORDER BY a.clock_in ASC
");

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));