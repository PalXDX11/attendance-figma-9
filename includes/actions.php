<?php
/*
 * Handles every form on the dashboard (attendance, deductions, employees and registrations).
 * After saving, it stores a message and goes back to the same section,
 * so refreshing the page does not send the form again.
 * This file runs BEFORE any HTML is printed.
 */
require_once __DIR__ . '/mailer.php';   // emails for approvals and rejections

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid    = $_SESSION['user_id'];
    $action = $_POST['action'] ?? '';

    switch ($action) {
        // ---------- Attendance management ----------
        case 'add_attendance':
        case 'edit_attendance':
            $isEdit = ($action === 'edit_attendance');
            $id     = (int)($_POST['id'] ?? 0);
            $reason = trim($_POST['reason'] ?? '');
            $in     = parseLocalDateTime($_POST['clock_in'] ?? '');
            $outRaw = trim($_POST['clock_out'] ?? '');
            $out    = $outRaw === '' ? null : parseLocalDateTime($outRaw);

            $old = null;
            if ($isEdit) {
                $stmt = $pdo->prepare("SELECT * FROM attendance_logs WHERE id = ?");
                $stmt->execute([$id]);
                $old = $stmt->fetch();
                $empId = $old ? (int)$old['employee_id'] : 0;
                // Keep the original seconds when the minute was not changed (the form only shows minutes)
                if ($old && $in  && substr($in, 0, 16)  === substr($old['clock_in'], 0, 16))  $in  = $old['clock_in'];
                if ($old && $out && $old['clock_out'] && substr($out, 0, 16) === substr($old['clock_out'], 0, 16)) $out = $old['clock_out'];
            } else {
                $empId = (int)($_POST['employee_id'] ?? 0);
            }

            $stmt = $pdo->prepare("SELECT name FROM employees WHERE id = ?");
            $stmt->execute([$empId]);
            $emp = $stmt->fetch();
            $limit = date('Y-m-d H:i:s', time() + 300);   // small allowance for clock differences

            if (!$emp || ($isEdit && !$old)) {
                setFlash('attendance', 'error', $isEdit ? 'Attendance record not found.' : 'Employee not found.');
            } elseif (!$in) {
                setFlash('attendance', 'error', 'Clock in date and time is required.');
            } elseif ($outRaw !== '' && !$out) {
                setFlash('attendance', 'error', 'Clock out date and time is not valid.');
            } elseif ($out && $out <= $in) {
                setFlash('attendance', 'error', 'Clock out must be after clock in.');
            } elseif ($in > $limit || ($out && $out > $limit)) {
                setFlash('attendance', 'error', 'Times cannot be in the future.');
            } elseif ($reason === '') {
                setFlash('attendance', 'error', 'Reason is required so the change can be audited.');
            } elseif (attendanceOverlaps($pdo, $empId, $in, $out, $id)) {
                setFlash('attendance', 'error', "That shift overlaps another shift of {$emp['name']}.");
            } else {
                $range = "$in -> " . ($out ?? 'open');
                if ($isEdit) {
                    $pdo->prepare("UPDATE attendance_logs SET clock_in = ?, clock_out = ? WHERE id = ?")->execute([$in, $out, $id]);
                    logAction($pdo, $uid, 'edit_attendance', "{$emp['name']}: shift #$id " . $old['clock_in'] . ' -> ' . ($old['clock_out'] ?? 'open') . " changed to $range. Reason: $reason");
                    setFlash('attendance', 'success', "Shift of {$emp['name']} was updated.");
                } else {
                    $pdo->prepare("INSERT INTO attendance_logs (employee_id, clock_in, clock_out) VALUES (?, ?, ?)")->execute([$empId, $in, $out]);
                    logAction($pdo, $uid, 'add_attendance', "{$emp['name']}: manual shift $range. Reason: $reason");
                    setFlash('attendance', 'success', "Manual shift added for {$emp['name']}.");
                }
            }
            redirectToSection('attendance');
            break;

        case 'end_shift':
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT a.id, a.clock_in, e.name FROM attendance_logs a
                JOIN employees e ON e.id = a.employee_id
                WHERE a.id = ? AND a.clock_out IS NULL
            ");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if ($row) {
                $pdo->prepare("UPDATE attendance_logs SET clock_out = NOW() WHERE id = ?")->execute([$id]);
                logAction($pdo, $uid, 'end_shift', "{$row['name']}: shift #$id (in {$row['clock_in']}) clocked out by admin");
                setFlash('attendance', 'success', "{$row['name']} was clocked out.");
            } else {
                setFlash('attendance', 'error', 'That shift is not open anymore.');
            }
            redirectToSection('attendance');
            break;

        case 'delete_attendance':
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT a.clock_in, a.clock_out, e.name FROM attendance_logs a
                JOIN employees e ON e.id = a.employee_id
                WHERE a.id = ?
            ");
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            if ($row) {
                $pdo->prepare("DELETE FROM attendance_logs WHERE id = ?")->execute([$id]);
                logAction($pdo, $uid, 'delete_attendance', "{$row['name']}: deleted shift #$id ({$row['clock_in']} -> " . ($row['clock_out'] ?? 'open') . ")");
                setFlash('attendance', 'success', "Shift of {$row['name']} was deleted.");
            } else {
                setFlash('attendance', 'error', 'Attendance record not found.');
            }
            redirectToSection('attendance');
            break;

        // ---------- Registrations (employee sign-ups) ----------
        case 'approve_registration':
            $id    = (int)($_POST['id'] ?? 0);
            $empId = (int)($_POST['employee_id'] ?? 0);

            $stmt = $pdo->prepare("SELECT * FROM registrations WHERE id = ? AND status = 'pending'");
            $stmt->execute([$id]);
            $reg = $stmt->fetch();
            $stmt = $pdo->prepare("SELECT name FROM employees WHERE id = ?");
            $stmt->execute([$empId]);
            $emp = $stmt->fetch();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE employee_id = ?");
            $stmt->execute([$empId]);
            $hasLogin = (int)$stmt->fetchColumn() > 0;

            if (!$reg) {
                setFlash('registrations', 'error', 'That registration is not waiting anymore.');
            } elseif (!$emp) {
                setFlash('registrations', 'error', 'Select the employee this account belongs to.');
            } elseif ($hasLogin) {
                setFlash('registrations', 'error', "{$emp['name']} already has a login.");
            } else {
                try {
                    $pdo->prepare("INSERT INTO users (username, email, password_hash, role, employee_id) VALUES (?, ?, ?, 'employee', ?)")
                        ->execute([$reg['username'], $reg['email'], $reg['password_hash'], $empId]);
                    $pdo->prepare("UPDATE registrations SET status = 'approved', employee_id = ?, reviewed_by = ?, reviewed_at = ? WHERE id = ?")
                        ->execute([$empId, $uid, date('Y-m-d H:i:s'), $id]);
                    logAction($pdo, $uid, 'approve_registration', "{$reg['full_name']} ({$reg['email']}) approved as {$emp['name']}, username {$reg['username']}");

                    [$html, $text] = mailTemplate('Your account is approved', [
                        "Hi {$reg['full_name']}, the admin approved your registration.",
                        'You can sign in now with your username or email and the password you chose.',
                    ], 'Sign in', appBaseUrl() . '/login.php');
                    $mailed = sendMail($reg['email'], 'Your account is approved', $html, $text);
                    setFlash('registrations', 'success', "{$reg['full_name']} was approved and linked to {$emp['name']}." . ($mailed ? '' : ' (The approval email could not be sent.)'));
                } catch (PDOException $e) {
                    setFlash('registrations', 'error', 'The username or email is already used by another account.');
                }
            }
            redirectToSection('registrations');
            break;

        case 'reject_registration':
            $id     = (int)($_POST['id'] ?? 0);
            $reason = mb_substr(trim($_POST['reason'] ?? ''), 0, 255);
            $stmt = $pdo->prepare("SELECT * FROM registrations WHERE id = ? AND status = 'pending'");
            $stmt->execute([$id]);
            $reg = $stmt->fetch();
            if ($reg) {
                $pdo->prepare("UPDATE registrations SET status = 'rejected', reject_reason = ?, reviewed_by = ?, reviewed_at = ? WHERE id = ?")
                    ->execute([$reason !== '' ? $reason : null, $uid, date('Y-m-d H:i:s'), $id]);
                logAction($pdo, $uid, 'reject_registration', "{$reg['full_name']} ({$reg['email']}) rejected" . ($reason !== '' ? ". Reason: $reason" : ''));

                [$html, $text] = mailTemplate('Your registration was not approved', array_filter([
                    "Hi {$reg['full_name']}, the admin did not approve your registration.",
                    $reason !== '' ? "Reason: $reason" : '',
                    'Please talk to your manager if you think this is a mistake.',
                ]));
                $mailed = sendMail($reg['email'], 'Your registration was not approved', $html, $text);
                setFlash('registrations', 'success', "Registration of {$reg['full_name']} was rejected." . ($mailed ? '' : ' (The email could not be sent.)'));
            } else {
                setFlash('registrations', 'error', 'That registration is not waiting anymore.');
            }
            redirectToSection('registrations');
            break;

        // ---------- Employees and deductions ----------
        case 'add_deduction':
            $empId  = (int)($_POST['employee_id'] ?? 0);
            $amount = (float)($_POST['amount'] ?? 0);
            $reason = trim($_POST['reason'] ?? '');

            $stmt = $pdo->prepare("SELECT name FROM employees WHERE id = ?");
            $stmt->execute([$empId]);
            $emp = $stmt->fetch();

            if (!$emp) {
                setFlash('deductions', 'error', 'Employee not found.');
            } elseif ($amount <= 0) {
                setFlash('deductions', 'error', 'Amount must be greater than 0.');
            } elseif ($reason === '') {
                setFlash('deductions', 'error', 'Reason is required.');
            } else {
                $pdo->prepare("INSERT INTO deductions (employee_id, amount, reason, applied_by) VALUES (?, ?, ?, ?)")
                    ->execute([$empId, $amount, $reason, $uid]);
                logAction($pdo, $uid, 'add_deduction', "{$emp['name']}: -" . number_format($amount, 2) . " ($reason)");
                setFlash('deductions', 'success', "Deduction of ₱" . number_format($amount, 2) . " applied to {$emp['name']}.");
            }
            redirectToSection('deductions');
            break;

        case 'add':
            $name = trim($_POST['name']);
            $fid  = (int)$_POST['fingerprint_id'];
            $rate = (float)$_POST['hourly_rate'];
            try {
                $pdo->prepare("INSERT INTO employees (name, fingerprint_id, hourly_rate) VALUES (?, ?, ?)")
                    ->execute([$name, $fid, $rate]);
                logAction($pdo, $uid, 'add_employee', "Added $name (FP $fid, rate $rate)");
                setFlash('employees', 'success', "$name was added.");
            } catch (PDOException $e) {
                setFlash('employees', 'error', 'Error: that fingerprint ID is already in use.');
            }
            redirectToSection('employees');
            break;

        case 'update_rate':
            $id   = (int)$_POST['id'];
            $rate = (float)$_POST['hourly_rate'];
            if ($rate < 0) {
                setFlash('employees', 'error', 'Rate cannot be negative.');
            } else {
                $stmt = $pdo->prepare("SELECT name, hourly_rate FROM employees WHERE id = ?");
                $stmt->execute([$id]);
                $emp = $stmt->fetch();
                if ($emp) {
                    $pdo->prepare("UPDATE employees SET hourly_rate = ? WHERE id = ?")->execute([$rate, $id]);
                    logAction($pdo, $uid, 'update_rate', "{$emp['name']}: {$emp['hourly_rate']} -> $rate");
                    setFlash('employees', 'success', "Updated rate for {$emp['name']}.");
                }
            }
            redirectToSection('employees');
            break;

        case 'toggle_status':
            $id = (int)$_POST['id'];
            $stmt = $pdo->prepare("SELECT name, status FROM employees WHERE id = ?");
            $stmt->execute([$id]);
            $emp = $stmt->fetch();
            if ($emp) {
                $new = $emp['status'] === 'active' ? 'inactive' : 'active';
                $pdo->prepare("UPDATE employees SET status = ? WHERE id = ?")->execute([$new, $id]);
                logAction($pdo, $uid, 'set_status', "{$emp['name']}: {$emp['status']} -> $new");
                setFlash('employees', 'success', "{$emp['name']} is now $new.");
            }
            redirectToSection('employees');
            break;

        case 'delete_employee':
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT name FROM employees WHERE id = ?");
            $stmt->execute([$id]);
            $emp = $stmt->fetch();

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance_logs WHERE employee_id = ?");
            $stmt->execute([$id]);
            $shifts = (int)$stmt->fetchColumn();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM deductions WHERE employee_id = ?");
            $stmt->execute([$id]);
            $deds = (int)$stmt->fetchColumn();

            if (!$emp) {
                setFlash('employees', 'error', 'Employee not found.');
            } elseif ($shifts > 0 || $deds > 0) {
                // Deleting would erase payroll history, so only deactivating is allowed
                setFlash('employees', 'error', "{$emp['name']} has $shifts shift(s) and $deds deduction(s) on record, so they cannot be deleted. Use Deactivate instead.");
            } else {
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE employee_id = ? AND role = 'employee'");
                    $stmt->execute([$id]);
                    foreach ($stmt->fetchAll() as $u) {
                        $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?")->execute([$u['id']]);
                    }
                    $pdo->prepare("DELETE FROM users WHERE employee_id = ? AND role = 'employee'")->execute([$id]);
                    $pdo->prepare("DELETE FROM registrations WHERE employee_id = ?")->execute([$id]);
                    $pdo->prepare("DELETE FROM employees WHERE id = ?")->execute([$id]);
                    $pdo->commit();
                    logAction($pdo, $uid, 'delete_employee', "Deleted employee {$emp['name']} (EMP-" . str_pad($id, 4, '0', STR_PAD_LEFT) . ")");
                    setFlash('employees', 'success', "{$emp['name']} was deleted.");
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    setFlash('employees', 'error', "{$emp['name']} could not be deleted.");
                }
            }
            redirectToSection('employees');
            break;

    }
}
