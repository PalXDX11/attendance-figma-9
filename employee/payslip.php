<?php
require '../auth.php';
require '../db.php';
require_once '../includes/functions.php';
requireEmployee();

// An employee can only ever see their own payslip (the id comes from the session, not the URL)
$slip = getPayslip($pdo, (int)$_SESSION['employee_id'], is_string($_GET['start'] ?? null) ? $_GET['start'] : null);
if (!$slip) {
    http_response_code(403);
    exit("Employee record not found.");
}
$me   = $slip['emp'];
$week = $slip['week'];

$pageTitle = 'My payslip';
$active    = 'payslip';
$eyebrow   = 'Payroll';
$subtitle  = 'Your weekly pay: hours worked, deductions and net pay.';
require '../includes/employee_header.php';
?>
<div class="toolbar">
    <a class="btn gray" href="?start=<?= $week['prev'] ?>">&larr; Previous week</a>
    <span class="range"><?= htmlspecialchars($week['label']) ?></span>
    <?php if (!$week['isCurrent'] && !$week['isFuture']): ?>
        <a class="btn gray" href="?start=<?= $week['next'] ?>">Next week &rarr;</a>
        <a class="btn" href="?start=<?= $week['thisWeek'] ?>">This week</a>
    <?php endif; ?>
    <button onclick="window.print()">Print</button>
</div>

<?php require '../includes/payslip_view.php'; ?>
<?php require '../includes/footer.php'; ?>
<?php require '../includes/scripts.php'; ?>
