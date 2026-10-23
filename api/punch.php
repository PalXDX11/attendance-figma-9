<?php
require '../db.php';
require_once '../includes/functions.php';
header('Content-Type: application/json');

// An employee must wait this many seconds between two punches (clock in or clock out)
const PUNCH_COOLDOWN_SECONDS = 60;

$fingerprintId = $_POST['fingerprint_id'] ?? null;
if (!$fingerprintId) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'No fingerprint_id provided.']);
    exit;
}

// Hanapin ang employee
$stmt = $pdo->prepare("SELECT * FROM employees WHERE fingerprint_id = ? AND status = 'active'");
$stmt->execute([$fingerprintId]);
$emp = $stmt->fetch();

if (!$emp) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Employee not found.']);
    exit;
}

// Lock this employee while we work, so two scans at the same moment cannot create two shifts
$pdo->beginTransaction();
$pdo->prepare("SELECT id FROM employees WHERE id = ? FOR UPDATE")->execute([$emp['id']]);

// Cooldown: block another punch too soon after the last clock in or clock out
$stmt = $pdo->prepare("
    SELECT TIMESTAMPDIFF(SECOND, MAX(GREATEST(clock_in, COALESCE(clock_out, clock_in))), NOW()) AS since
    FROM attendance_logs
    WHERE employee_id = ?
");
$stmt->execute([$emp['id']]);
$since = $stmt->fetch()['since'];   // null when the employee has no records yet

if ($since !== null && (int)$since < PUNCH_COOLDOWN_SECONDS) {
    $pdo->rollBack();
    $wait = PUNCH_COOLDOWN_SECONDS - (int)$since;
    http_response_code(429);
    echo json_encode([
        'status'  => 'error',
        'message' => "Please wait $wait second" . ($wait == 1 ? '' : 's') . " before punching again.",
        'wait'    => $wait
    ]);
    exit;
}

// May open na attendance ba?
$stmt = $pdo->prepare("SELECT * FROM attendance_logs WHERE employee_id = ? AND clock_out IS NULL ORDER BY clock_in DESC LIMIT 1");
$stmt->execute([$emp['id']]);
$open = $stmt->fetch();

if ($open) {
    // CLOCK OUT
    $pdo->prepare("UPDATE attendance_logs SET clock_out = NOW() WHERE id = ?")->execute([$open['id']]);

    $stmt = $pdo->prepare("SELECT TIMESTAMPDIFF(SECOND, clock_in, clock_out) AS secs FROM attendance_logs WHERE id = ?");
    $stmt->execute([$open['id']]);
    $secs = $stmt->fetch()['secs'];
    $pay = computePay($secs, $emp['hourly_rate']);
    $pdo->commit();

    echo json_encode([
        'status' => 'ok',
        'action' => 'clock_out',
        'name' => $emp['name'],
        'hours' => secondsToHours($secs),
        'pay' => $pay
    ]);
} else {
    // CLOCK IN
    $pdo->prepare("INSERT INTO attendance_logs (employee_id, clock_in) VALUES (?, NOW())")->execute([$emp['id']]);
    $pdo->commit();

    echo json_encode([
        'status' => 'ok',
        'action' => 'clock_in',
        'name' => $emp['name']
    ]);
}
