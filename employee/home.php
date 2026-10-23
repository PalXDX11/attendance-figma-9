<?php
require '../auth.php';
require '../db.php';
require_once '../includes/functions.php';
requireEmployee();

$empId = (int)$_SESSION['employee_id'];
$slip  = getPayslip($pdo, $empId);          // current week
if (!$slip) {
    http_response_code(403);
    exit("Employee record not found.");
}
$me   = $slip['emp'];
$week = $slip['week'];

// Open shift (if clocked in right now)
$stmt = $pdo->prepare("
    SELECT clock_in, TIMESTAMPDIFF(SECOND, clock_in, NOW()) AS elapsed_secs
    FROM attendance_logs WHERE employee_id = ? AND clock_out IS NULL
    ORDER BY clock_in DESC LIMIT 1
");
$stmt->execute([$empId]);
$open = $stmt->fetch();

// Last 8 shifts
$stmt = $pdo->prepare("
    SELECT clock_in, clock_out, TIMESTAMPDIFF(SECOND, clock_in, COALESCE(clock_out, NOW())) AS secs
    FROM attendance_logs WHERE employee_id = ?
    ORDER BY clock_in DESC LIMIT 8
");
$stmt->execute([$empId]);
$recent = $stmt->fetchAll();

// Last 5 deductions
$stmt = $pdo->prepare("SELECT created_at, amount, reason FROM deductions WHERE employee_id = ? ORDER BY created_at DESC, id DESC LIMIT 5");
$stmt->execute([$empId]);
$deds = $stmt->fetchAll();

$lastWeek = $week['prev'];
$firstName = explode(' ', trim($me['name']))[0];

$pageTitle = "Hi, " . $firstName;
$active    = 'home';
$eyebrow   = 'Employee home';
$subtitle  = $me['status'] === 'active' ? 'Here is your attendance and pay for this week.' : 'Your account is inactive. Please contact your manager.';
require '../includes/employee_header.php';
?>

<div class="split">
    <div>
        <div class="card clock-card" id="statusCard">
            <?php if ($open): ?>
                <div class="clock-label"><span class="pulse"></span> You are clocked in</div>
                <div class="clock-time" id="elapsed">00:00:00</div>
                <div class="clock-date">Since <?= date('g:i A, M j', strtotime($open['clock_in'])) ?></div>
            <?php else: ?>
                <div class="clock-label">Not clocked in</div>
                <div class="clock-time" id="clock">--:--:--</div>
                <div class="clock-date" id="cdate"></div>
            <?php endif; ?>
        </div>

        <div class="stat">
            <span class="label"><?= $open ? 'Earning this shift' : 'Hourly rate' ?></span>
            <span class="value green" id="earning"><?= $open ? '₱0.00' : peso($me['hourly_rate']) ?></span>
        </div>
        <?php if ($open): ?>
        <div class="stat">
            <span class="label">Hourly rate</span>
            <span class="value"><?= peso($me['hourly_rate']) ?></span>
        </div>
        <?php endif; ?>
    </div>

    <div>
        <div class="stats stats-3">
            <div class="stat">
                <span class="label">Hours this week</span>
                <span class="value"><?= number_format($slip['hours'], 2) ?></span>
            </div>
            <div class="stat">
                <span class="label">Gross pay</span>
                <span class="value"><?= peso($slip['gross']) ?></span>
            </div>
            <div class="stat">
                <span class="label">Deductions</span>
                <span class="value red"><?= peso($slip['ded']) ?></span>
            </div>
        </div>

        <div class="panel net-panel">
            <div>
                <span class="small">Net pay so far &middot; <?= htmlspecialchars($week['label']) ?></span>
                <div class="net-big <?= $slip['net'] < 0 ? 'negative' : '' ?>"><?= peso($slip['net']) ?></div>
                <?php if ($open): ?><span class="note">Your current shift is added after you clock out.</span><?php endif; ?>
            </div>
            <div class="row-actions">
                <a class="btn" href="payslip.php">View payslip</a>
                <a class="btn gray" href="payslip.php?start=<?= $lastWeek ?>">Last week</a>
            </div>
        </div>
    </div>
</div>

<div class="split-2">
    <div class="panel">
        <div class="panel-head"><h3>Recent attendance</h3></div>
        <?php if ($recent): ?>
        <div class="table-wrap">
            <table>
                <tr><th>Date</th><th>Clock in</th><th>Clock out</th><th>Duration</th></tr>
                <?php foreach ($recent as $r): ?>
                <tr>
                    <td><?= date('D, M d', strtotime($r['clock_in'])) ?></td>
                    <td><?= date('g:i A', strtotime($r['clock_in'])) ?></td>
                    <td><?= $r['clock_out'] ? date('g:i A', strtotime($r['clock_out'])) : '<span class="badge open">Clocked in</span>' ?></td>
                    <td class="mono"><?= formatDuration($r['secs']) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php else: ?>
            <div class="empty">No attendance yet. Use the biometric scanner to clock in.</div>
        <?php endif; ?>
    </div>

    <div class="panel">
        <div class="panel-head"><h3>Recent deductions</h3></div>
        <?php if ($deds): ?>
        <div class="table-wrap">
            <table>
                <tr><th>Date</th><th>Reason</th><th>Amount</th></tr>
                <?php foreach ($deds as $d): ?>
                <tr>
                    <td class="muted-cell"><?= date('M d', strtotime($d['created_at'])) ?></td>
                    <td><?= htmlspecialchars($d['reason']) ?></td>
                    <td class="ded">-<?= peso($d['amount']) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php else: ?>
            <div class="empty">No deductions. Nice!</div>
        <?php endif; ?>
    </div>
</div>

<script>
const rate = <?= json_encode((float)$me['hourly_rate']) ?>;
const base = <?= $open ? (int)$open['elapsed_secs'] : 'null' ?>;   // seconds worked when the page loaded
const loadedAt = Date.now();

function pad(n) { return String(n).padStart(2, '0'); }

function tick() {
    if (base !== null) {
        const secs = base + Math.floor((Date.now() - loadedAt) / 1000);
        document.getElementById('elapsed').textContent =
            pad(Math.floor(secs / 3600)) + ':' + pad(Math.floor((secs % 3600) / 60)) + ':' + pad(secs % 60);
        document.getElementById('earning').textContent = '₱' + ((secs / 3600) * rate).toFixed(2);
    } else {
        const now = new Date();
        document.getElementById('clock').textContent = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true });
        document.getElementById('cdate').textContent = now.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    }
}
tick();
setInterval(tick, 1000);
</script>
<?php require '../includes/footer.php'; ?>
<?php require '../includes/scripts.php'; ?>
