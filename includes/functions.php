<?php
/*
 * Shared helper functions used across the system.
 * (User-defined functions with parameters, default argument values and return values.)
 */

// Convert seconds to hours, rounded to $decimals places (default 2)
function secondsToHours($secs, $decimals = 2) {
    return round($secs / 3600, $decimals);
}

// Salary earned for $secs seconds at $rate pesos per hour, rounded to centavos by default
function computePay($secs, $rate, $decimals = 2) {
    $hours = $secs / 3600;
    return round($hours * $rate, $decimals);
}

// Format a number as pesos, e.g. 1234.5 -> ₱1,234.50 and -20 -> -₱20.00
function peso($n) {
    return ($n < 0 ? '-₱' : '₱') . number_format(abs($n), 2);
}

// Initials from a name: first letter of the first and last word, e.g. "Paul Tenorio" -> "PT"
function initials($name) {
    $parts = preg_split('/\s+/', trim($name));
    $count = count($parts);
    $result = '';
    for ($i = 0; $i < $count; $i++) {
        if ($i == 0 || ($i == $count - 1 && $count > 1)) {
            $result .= strtoupper(substr($parts[$i], 0, 1));
        }
    }
    return $result !== '' ? $result : '?';
}

// Save an entry in the audit log
function logAction($pdo, $uid, $action, $details) {
    $pdo->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)")
        ->execute([$uid, $action, $details]);
}

// Remember a message for a section, to show it after the page reloads (see redirectToSection)
function setFlash($section, $type, $msg) {
    $_SESSION['flash'][$section] = ['type' => $type, 'msg' => $msg];
}

// Get (and remove) the saved message for a section, or null if there is none
function getFlash($section) {
    $flash = $_SESSION['flash'][$section] ?? null;
    unset($_SESSION['flash'][$section]);
    return $flash;
}

// Go back to the dashboard at the given section. Keeps the selected payroll week
// and the attendance filters (the query-string keys listed below).
function redirectToSection($section) {
    $keep = [];
    foreach (['start', 'att_emp', 'att_from', 'att_to'] as $key) {
        if (isset($_GET[$key]) && is_string($_GET[$key]) && $_GET[$key] !== '') {
            $keep[$key] = $_GET[$key];
        }
    }
    $url = 'dashboard.php' . ($keep ? '?' . http_build_query($keep) : '');
    header("Location: $url#$section");
    exit;
}


// ---------------------------------------------------------------------
// Dates, attendance and payslips
// ---------------------------------------------------------------------

// Employee code shown on screens and payslips, e.g. 3 -> EMP-0003
function empCode($id) {
    return 'EMP-' . str_pad($id, 4, '0', STR_PAD_LEFT);
}

// Return $s if it is a real date written as YYYY-MM-DD, otherwise $default
function validDate($s, $default) {
    if (is_string($s) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m) && checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
        return $s;
    }
    return $default;
}

// Turn the value of an <input type="datetime-local"> into "Y-m-d H:i:s" (or null if it is not valid)
function parseLocalDateTime($s) {
    if (!is_string($s) || $s === '') return null;
    foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s'] as $format) {
        $dt = DateTime::createFromFormat($format, $s);
        if ($dt && $dt->format($format) === $s) return $dt->format('Y-m-d H:i:s');
    }
    return null;
}

// Hours and minutes from seconds, e.g. 5400 -> 1h 30m
function formatDuration($secs) {
    $secs = max(0, (int)$secs);
    return floor($secs / 3600) . 'h ' . str_pad(floor(($secs % 3600) / 60), 2, '0', STR_PAD_LEFT) . 'm';
}

// The Monday-to-Sunday week that contains $date (a date string, default today).
// Returns the week start/end (end is exclusive), a label, and links to the neighbouring weeks.
function weekRange($date = null) {
    try {
        $base = new DateTime(is_string($date) && $date !== '' ? $date : 'today');
    } catch (Exception $e) {
        $base = new DateTime('today');
    }
    $base->modify('monday this week')->setTime(0, 0, 0);

    $end      = (clone $base)->modify('+7 days');
    $thisWeek = (new DateTime('today'))->modify('monday this week')->format('Y-m-d');

    return [
        'start'    => $base,
        'end'      => $end,
        'startStr' => $base->format('Y-m-d H:i:s'),
        'endStr'   => $end->format('Y-m-d H:i:s'),
        'label'    => $base->format('M d, Y') . ' - ' . (clone $end)->modify('-1 day')->format('M d, Y'),
        'key'      => $base->format('Y-m-d'),
        'prev'     => (clone $base)->modify('-7 days')->format('Y-m-d'),
        'next'     => (clone $base)->modify('+7 days')->format('Y-m-d'),
        'thisWeek' => $thisWeek,
        'isCurrent' => $base->format('Y-m-d') === $thisWeek,
        'isFuture'  => $base->format('Y-m-d') > $thisWeek,
    ];
}

// Does this employee already have a shift that overlaps $in .. $out?
// $out = null means the shift is still open. $ignoreId skips one record (used when editing).
function attendanceOverlaps($pdo, $employeeId, $in, $out, $ignoreId = 0) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM attendance_logs
        WHERE employee_id = ? AND id <> ?
          AND clock_in < ? AND COALESCE(clock_out, '9999-12-31 00:00:00') > ?
    ");
    $stmt->execute([$employeeId, $ignoreId, $out ?? '9999-12-31 00:00:00', $in]);
    return (int)$stmt->fetchColumn() > 0;
}

// Everything on one employee's payslip for one week (same rules as the payroll summary):
// completed shifts counted by clock-in date, deductions counted by the date they were applied.
// Returns null when the employee does not exist.
function getPayslip($pdo, $employeeId, $date = null) {
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
    $stmt->execute([$employeeId]);
    $emp = $stmt->fetch();
    if (!$emp) return null;

    $week = weekRange($date);

    $stmt = $pdo->prepare("
        SELECT clock_in, clock_out, TIMESTAMPDIFF(SECOND, clock_in, clock_out) AS secs
        FROM attendance_logs
        WHERE employee_id = ? AND clock_out IS NOT NULL AND clock_in >= ? AND clock_in < ?
        ORDER BY clock_in
    ");
    $stmt->execute([$employeeId, $week['startStr'], $week['endStr']]);
    $shifts = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM attendance_logs
        WHERE employee_id = ? AND clock_out IS NULL AND clock_in >= ? AND clock_in < ?
    ");
    $stmt->execute([$employeeId, $week['startStr'], $week['endStr']]);
    $openCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT created_at, amount, reason
        FROM deductions
        WHERE employee_id = ? AND created_at >= ? AND created_at < ?
        ORDER BY created_at, id
    ");
    $stmt->execute([$employeeId, $week['startStr'], $week['endStr']]);
    $deductions = $stmt->fetchAll();

    $secs  = 0;
    foreach ($shifts as $s) $secs += (int)$s['secs'];
    $gross = computePay($secs, $emp['hourly_rate']);
    $ded   = 0;
    foreach ($deductions as $d) $ded += (float)$d['amount'];

    return [
        'emp'        => $emp,
        'week'       => $week,
        'shifts'     => $shifts,
        'openCount'  => $openCount,
        'deductions' => $deductions,
        'secs'       => $secs,
        'hours'      => $secs / 3600,
        'gross'      => $gross,
        'ded'        => $ded,
        'net'        => $gross - $ded,
        'ref'        => 'PS-' . empCode($emp['id']) . '-' . $week['start']->format('Ymd'),
    ];
}
