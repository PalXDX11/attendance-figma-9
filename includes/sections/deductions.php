<?php
/*
 * Section: Authorized deductions (apply form and history)
 * Included by admin/dashboard.php.
 */
$flash = getFlash('deductions');

$employees = $pdo->query("SELECT id, name FROM employees WHERE status = 'active' ORDER BY name")->fetchAll();

$history = $pdo->query("
    SELECT d.created_at, e.name, d.amount, d.reason, u.username
    FROM deductions d
    JOIN employees e ON e.id = d.employee_id
    JOIN users u ON u.id = d.applied_by
    ORDER BY d.created_at DESC, d.id DESC
    LIMIT 100
")->fetchAll();
?>

<!-- Authorized deductions Section -->
<section id="deductions" class="page-section">
    <div class="page-head">
        <span class="eyebrow">Payroll administration</span>
        <h1>Authorized deductions</h1>
        <p>Apply deductions to employees. Every deduction is logged.</p>
    </div>

<?php if ($flash): ?>
    <div class="msg <?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
<?php endif; ?>

<div class="split-form">
    <div class="panel">
        <div class="panel-head">
            <div>
                <h3>Apply a deduction</h3>
                <span class="small">Every deduction is recorded with a reason.</span>
            </div>
        </div>
        <form method="POST" class="panel-body form-stack" onsubmit="return confirm('Apply this deduction?');">
<?= csrfField() ?>
            <input type="hidden" name="action" value="add_deduction">
            <div class="field">
                <label for="employee_id">Employee</label>
                <div class="input-icon">
                <svg class="icon" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <select name="employee_id" id="employee_id" required>
                    <option value="">Select employee</option>
                    <?php foreach ($employees as $e): ?>
                        <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                </div>
            </div>
            <div class="field">
                <label for="amount">Amount (₱)</label>
                <div class="input-icon">
                <svg class="icon" viewBox="0 0 24 24"><rect width="16" height="20" x="4" y="2" rx="2"/><path d="M8 6h8"/><path d="M16 14v4"/><path d="M16 10h.01"/><path d="M12 10h.01"/><path d="M8 10h.01"/><path d="M12 14h.01"/><path d="M8 14h.01"/><path d="M12 18h.01"/><path d="M8 18h.01"/></svg>
                <input type="number" step="0.01" min="0.01" name="amount" id="amount" placeholder="0.00" required>
                </div>
            </div>
            <div class="field">
                <label for="reason">Reason</label>
                <textarea name="reason" id="reason" maxlength="255" placeholder="Reason" required></textarea>
            </div>
            <button type="submit" class="btn-block">Apply Deduction</button>
        </form>
    </div>

    <div class="panel">
        <div class="panel-head">
            <div>
                <h3>Deductions history</h3>
                <span class="small">Auditable record of every applied deduction.</span>
            </div>
        </div>
        <?php if ($history): ?>
        <div class="table-wrap">
            <table>
                <tr><th>Employee</th><th>Reason</th><th>Amount</th><th>Date</th><th>Authorized</th></tr>
                <?php foreach ($history as $h): ?>
                <tr>
                    <td><span class="emp-name"><?= htmlspecialchars($h['name']) ?></span></td>
                    <td class="muted-cell"><?= htmlspecialchars($h['reason']) ?></td>
                    <td class="amount-strong">₱<?= number_format($h['amount'], 2) ?></td>
                    <td class="muted-cell" title="<?= htmlspecialchars($h['created_at']) ?>"><?= date('M d, Y', strtotime($h['created_at'])) ?></td>
                    <td class="muted-cell"><?= htmlspecialchars($h['username']) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php else: ?>
            <div class="empty">No deductions yet.</div>
        <?php endif; ?>
    </div>
</div>
</section>
