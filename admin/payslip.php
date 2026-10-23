<?php
require '../auth.php';
require '../db.php';
require_once '../includes/functions.php';
requireAdmin();

$empId = (int)($_GET['employee_id'] ?? 0);
$slip  = getPayslip($pdo, $empId, is_string($_GET['start'] ?? null) ? $_GET['start'] : null);
if (!$slip) {
    http_response_code(404);
    exit("Employee not found.");
}
$week = $slip['week'];

$pageTitle = 'Payslip';
$active    = 'payroll';
$navBase   = 'dashboard.php';
$eyebrow   = 'Payroll';
$subtitle  = 'Weekly payslip for ' . $slip['emp']['name'] . '.';
require '../includes/head.php';
require '../includes/header.php';
$q = 'employee_id=' . $empId . '&amp;start=';
?>
<div class="toolbar">
    <a class="btn gray" href="dashboard.php?start=<?= $week['key'] ?>#payroll">&larr; Back to payroll</a>
    <a class="btn gray" href="?<?= $q . $week['prev'] ?>">&larr; Previous week</a>
    <span class="range"><?= htmlspecialchars($week['label']) ?></span>
    <?php if (!$week['isCurrent'] && !$week['isFuture']): ?>
        <a class="btn gray" href="?<?= $q . $week['next'] ?>">Next week &rarr;</a>
        <a class="btn" href="?<?= $q . $week['thisWeek'] ?>">This week</a>
    <?php endif; ?>
    <button onclick="window.print()">Print</button>
</div>

<?php require '../includes/payslip_view.php'; ?>
<?php require '../includes/footer.php'; ?>
<?php require '../includes/scripts.php'; ?>
