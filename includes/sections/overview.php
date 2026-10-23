<?php
/*
 * Section: Overview (stats, who is clocked in, quick actions)
 * Included by admin/dashboard.php.
 */
$clockedIn = (int)$pdo->query("SELECT COUNT(*) FROM attendance_logs WHERE clock_out IS NULL")->fetchColumn();
$activeEmp = (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn();
$dedToday  = (float)$pdo->query("SELECT COALESCE(SUM(amount), 0) FROM deductions WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$secsWeek  = (int)$pdo->query("
    SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND, clock_in, clock_out)), 0)
    FROM attendance_logs
    WHERE clock_out IS NOT NULL AND YEARWEEK(clock_in, 1) = YEARWEEK(CURDATE(), 1)
")->fetchColumn();

$stmt = $pdo->query("
    SELECT e.name, a.clock_in
    FROM attendance_logs a
    JOIN employees e ON e.id = a.employee_id
    WHERE a.clock_out IS NULL
    ORDER BY a.clock_in ASC
    LIMIT 8
");
$nowIn = [];
while ($row = $stmt->fetch()) {
    $nowIn[] = $row;
}
?>

<!-- Overview Section -->
<section id="overview" class="page-section">
    <div class="page-head">
        <span class="eyebrow">Dashboard</span>
        <h1>Overview</h1>
        <p>Welcome, admin! Here is what is happening today.</p>
    </div>

<div class="stats">
    <div class="stat">
        <span class="label">Clocked in now</span>
        <span class="value"><?= $clockedIn ?> <small>/ <?= $activeEmp ?> active</small></span>
    </div>
    <div class="stat">
        <span class="label">Active employees</span>
        <span class="value"><?= $activeEmp ?></span>
    </div>
    <div class="stat">
        <span class="label">Deductions today</span>
        <span class="value red">₱<?= number_format($dedToday, 2) ?></span>
    </div>
    <div class="stat">
        <span class="label">Hours this week</span>
        <span class="value green"><?= number_format($secsWeek / 3600, 2) ?></span>
    </div>
</div>

<div class="split-2">
    <div class="panel">
        <div class="panel-head">
            <h3>Clocked in right now</h3>
            <a class="btn sm gray" href="#live">Open live attendance</a>
        </div>
        <?php if ($nowIn): ?>
        <div class="table-wrap">
            <table>
                <tr><th>Employee</th><th>Clock in</th></tr>
                <?php foreach ($nowIn as $r): ?>
                <tr>
                    <td>
                        <div class="emp">
                            <span class="avatar"><?= htmlspecialchars(initials($r['name'])) ?></span>
                            <?= htmlspecialchars($r['name']) ?>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($r['clock_in']) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php else: ?>
            <div class="empty">No one is clocked in right now.</div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <div class="panel-head"><h3>Quick actions</h3></div>
        <div class="panel-body quick-links">
            <a class="btn" href="#attendance">Manage Attendance</a>
            <a class="btn" href="#employees">Manage Employees</a>
            <a class="btn" href="#registrations">Review Registrations</a>
            <a class="btn" href="#deductions">Apply a Deduction</a>
            <a class="btn" href="#payroll">View Payroll Summary</a>
        </div>
    </div>
</div>
</section>
