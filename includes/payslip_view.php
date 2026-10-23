<?php
/*
 * The payslip itself. Used by admin/payslip.php and employee/payslip.php.
 * Needs $slip (from getPayslip()). Prints nicely: the sidebar and buttons are hidden by the print CSS.
 */
$emp  = $slip['emp'];
$week = $slip['week'];
?>
<article class="payslip">
    <header class="ps-head">
        <div class="ps-brand">
            <span class="brand-mark">OS</span>
            <div>
                <strong>Oppa's Samgyeopsal</strong>
                <span>Payslip</span>
            </div>
        </div>
        <div class="ps-ref">
            <span>Reference</span>
            <strong><?= htmlspecialchars($slip['ref']) ?></strong>
        </div>
    </header>

    <div class="ps-meta">
        <div><span>Employee</span><strong><?= htmlspecialchars($emp['name']) ?></strong></div>
        <div><span>Employee ID</span><strong><?= empCode($emp['id']) ?></strong></div>
        <div><span>Pay period</span><strong><?= htmlspecialchars($week['label']) ?></strong></div>
        <div><span>Hourly rate</span><strong><?= peso($emp['hourly_rate']) ?></strong></div>
    </div>

    <h3 class="ps-title">Hours worked</h3>
    <?php if ($slip['shifts']): ?>
    <table class="ps-table">
        <tr><th>Date</th><th>Clock in</th><th>Clock out</th><th class="r">Hours</th></tr>
        <?php foreach ($slip['shifts'] as $s): ?>
        <tr>
            <td><?= date('D, M d', strtotime($s['clock_in'])) ?></td>
            <td><?= date('g:i A', strtotime($s['clock_in'])) ?></td>
            <td><?= date('g:i A', strtotime($s['clock_out'])) ?><?= date('Y-m-d', strtotime($s['clock_in'])) !== date('Y-m-d', strtotime($s['clock_out'])) ? ' <span class="small">(' . date('M d', strtotime($s['clock_out'])) . ')</span>' : '' ?></td>
            <td class="r"><?= number_format($s['secs'] / 3600, 2) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="sum"><td colspan="3">Total hours</td><td class="r"><?= number_format($slip['hours'], 2) ?></td></tr>
    </table>
    <?php else: ?>
        <p class="ps-empty">No completed shifts in this pay period.</p>
    <?php endif; ?>
    <?php if ($slip['openCount'] > 0): ?>
        <p class="note">A shift is still open (not clocked out). It will be counted after clock out.</p>
    <?php endif; ?>

    <h3 class="ps-title">Earnings</h3>
    <table class="ps-table">
        <tr>
            <td><?= number_format($slip['hours'], 2) ?> hours &times; <?= peso($emp['hourly_rate']) ?> / hr</td>
            <td class="r"><?= peso($slip['gross']) ?></td>
        </tr>
        <tr class="sum"><td>Gross pay</td><td class="r"><?= peso($slip['gross']) ?></td></tr>
    </table>

    <h3 class="ps-title">Deductions</h3>
    <?php if ($slip['deductions']): ?>
    <table class="ps-table">
        <tr><th>Date</th><th>Reason</th><th class="r">Amount</th></tr>
        <?php foreach ($slip['deductions'] as $d): ?>
        <tr>
            <td><?= date('M d, Y', strtotime($d['created_at'])) ?></td>
            <td><?= htmlspecialchars($d['reason']) ?></td>
            <td class="r ded">-<?= peso($d['amount']) ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="sum"><td colspan="2">Total deductions</td><td class="r ded">-<?= peso($slip['ded']) ?></td></tr>
    </table>
    <?php else: ?>
        <p class="ps-empty">No deductions in this pay period.</p>
    <?php endif; ?>

    <div class="ps-net">
        <span>Net pay</span>
        <strong class="<?= $slip['net'] < 0 ? 'negative' : '' ?>"><?= peso($slip['net']) ?></strong>
    </div>

    <footer class="ps-foot">
        Generated <?= date('M d, Y g:i A') ?> &middot; Net pay = gross pay &minus; deductions.
        Hours are counted by clock-in date; deductions by the date they were applied.
    </footer>
</article>
