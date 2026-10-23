<?php
/*
 * Section: Employees (list, rates, add, activate/deactivate, delete)
 * Included by admin/dashboard.php.
 */
$flash = getFlash('employees');

$employees = $pdo->query("
    SELECT * FROM employees ORDER BY id
")->fetchAll();
?>

<!-- Employees Section -->
<section id="employees" class="page-section">
    <div class="page-head">
        <span class="eyebrow">Payroll administration</span>
        <h1>Employees</h1>
        <p>Add employees, edit hourly rates, and activate or deactivate accounts.</p>
    </div>

<?php if ($flash): ?>
    <div class="msg <?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
<?php endif; ?>

<div class="split-rates">
    <div class="panel">
        <div class="panel-head">
            <div>
                <h3>Employees</h3>
                <span class="small">Showing <?= count($employees) ?> employees</span>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <tr><th>Employee / ID</th><th>Fingerprint ID</th><th>Base rate</th><th>Status</th><th>Actions</th></tr>
                <?php foreach ($employees as $e): ?>
                <tr class="<?= $e['status'] === 'inactive' ? 'inactive' : '' ?>">
                    <td>
                        <div class="emp">
                            <span class="avatar"><?= htmlspecialchars(initials($e['name'])) ?></span>
                            <div>
                                <span class="emp-name"><?= htmlspecialchars($e['name']) ?></span>
                                <span class="emp-sub">EMP-<?= str_pad($e['id'], 4, '0', STR_PAD_LEFT) ?></span>
                            </div>
                        </div>
                    </td>
                    <td><?= $e['fingerprint_id'] ?></td>
                    <td>
                        <form method="POST" class="inline rate-form">
<?= csrfField() ?>
                            <input type="hidden" name="action" value="update_rate">
                            <input type="hidden" name="id" value="<?= $e['id'] ?>">
                            ₱<input type="number" step="0.01" min="0" name="hourly_rate" value="<?= $e['hourly_rate'] ?>" required>
                            <span class="per">/ hr</span>
                            <button type="submit" class="sm soft">Save</button>
                        </form>
                    </td>
                    <td><span class="badge <?= $e['status'] ?>"><?= ucfirst($e['status']) ?></span></td>
                    <td>
                        <div class="row-actions">
                            <form method="POST" class="inline" onsubmit="return confirm('Are you sure?');">
<?= csrfField() ?>
                                <input type="hidden" name="action" value="toggle_status">
                                <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                <?php if ($e['status'] === 'active'): ?>
                                    <button type="submit" class="sm gray">Deactivate</button>
                                <?php else: ?>
                                    <button type="submit" class="sm green">Activate</button>
                                <?php endif; ?>
                            </form>
                            <form method="POST" class="inline" onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($e['name']), ENT_QUOTES) ?> permanently? This cannot be undone.');">
<?= csrfField() ?>
                                <input type="hidden" name="action" value="delete_employee">
                                <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                <button type="submit" class="sm danger">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <div>
                <h3>Add employee</h3>
                <span class="small">Register a new employee and base rate.</span>
            </div>
        </div>
        <form method="POST" class="panel-body form-stack">
<?= csrfField() ?>
            <input type="hidden" name="action" value="add">
            <div class="field">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" placeholder="Name" required>
            </div>
            <div class="field-row">
                <div class="field">
                    <label for="fingerprint_id">Fingerprint ID</label>
                    <input type="number" id="fingerprint_id" name="fingerprint_id" placeholder="Fingerprint ID" required>
                </div>
                <div class="field">
                    <label for="hourly_rate">Base rate (₱/hr)</label>
                    <input type="number" step="0.01" id="hourly_rate" name="hourly_rate" placeholder="Rate/hr" value="50" required>
                </div>
            </div>
            <button type="submit" class="btn-block">Add Employee</button>
        </form>
    </div>
</div>
</section>
