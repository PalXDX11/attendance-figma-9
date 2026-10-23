<?php
/*
 * Section: Registrations (employees who signed up themselves)
 * Admin approves (and links the account to an employee record) or rejects.
 * Included by admin/dashboard.php.
 */
$flash = getFlash('registrations');

$pending = $pdo->query("SELECT * FROM registrations WHERE status = 'pending' ORDER BY verified_at, id")->fetchAll();
$waitingEmail = (int)$pdo->query("SELECT COUNT(*) FROM registrations WHERE status = 'unverified'")->fetchColumn();
$approvedCount = (int)$pdo->query("SELECT COUNT(*) FROM registrations WHERE status = 'approved'")->fetchColumn();
$rejectedCount = (int)$pdo->query("SELECT COUNT(*) FROM registrations WHERE status = 'rejected'")->fetchColumn();

// Employee records that do not have a login yet (the account can be linked to one of these)
$free = $pdo->query("
    SELECT e.id, e.name, e.status
    FROM employees e
    LEFT JOIN users u ON u.employee_id = e.id
    WHERE u.id IS NULL
    ORDER BY e.name
")->fetchAll();

$history = $pdo->query("
    SELECT r.*, e.name AS employee_name, u.username AS reviewer
    FROM registrations r
    LEFT JOIN employees e ON e.id = r.employee_id
    LEFT JOIN users u ON u.id = r.reviewed_by
    WHERE r.status IN ('approved','rejected')
    ORDER BY r.reviewed_at DESC, r.id DESC
    LIMIT 15
")->fetchAll();
?>

<!-- Registrations Section -->
<section id="registrations" class="page-section">
    <div class="page-head">
        <span class="eyebrow">Access control</span>
        <h1>Registrations</h1>
        <p>Approve employees who registered and link each account to their employee record.</p>
    </div>

<?php if ($flash): ?>
    <div class="msg <?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
<?php endif; ?>

<div class="stats">
    <div class="stat">
        <span class="label">Waiting for approval</span>
        <span class="value <?= $pending ? 'red' : '' ?>"><?= count($pending) ?></span>
    </div>
    <div class="stat">
        <span class="label">Email not confirmed yet</span>
        <span class="value"><?= $waitingEmail ?></span>
    </div>
    <div class="stat">
        <span class="label">Approved</span>
        <span class="value green"><?= $approvedCount ?></span>
    </div>
    <div class="stat">
        <span class="label">Rejected</span>
        <span class="value"><?= $rejectedCount ?></span>
    </div>
</div>

<div class="panel">
    <div class="panel-head">
        <div>
            <h3>Waiting for approval</h3>
            <span class="small">These employees confirmed their email. Link each one to their employee record, then approve.</span>
        </div>
    </div>
    <?php if ($pending): ?>
    <div class="table-wrap">
        <table>
            <tr><th>Registered as</th><th>Email / username</th><th>Confirmed</th><th>Link to employee</th><th>Decision</th></tr>
            <?php foreach ($pending as $r):
                // Pre-select the employee whose name matches what the person typed
                $guess = 0;
                foreach ($free as $f) {
                    if (mb_strtolower(trim($f['name'])) === mb_strtolower(trim($r['full_name']))) { $guess = $f['id']; break; }
                }
            ?>
            <tr>
                <td>
                    <div class="emp">
                        <span class="avatar"><?= htmlspecialchars(initials($r['full_name'])) ?></span>
                        <span class="emp-name"><?= htmlspecialchars($r['full_name']) ?></span>
                    </div>
                </td>
                <td>
                    <span class="emp-name"><?= htmlspecialchars($r['email']) ?></span>
                    <span class="emp-sub"><?= htmlspecialchars($r['username']) ?></span>
                </td>
                <td class="muted-cell nowrap"><?= date('M d, g:i A', strtotime($r['verified_at'])) ?></td>
                <td>
                    <form method="POST" class="inline reg-approve" id="approve-<?= $r['id'] ?>" onsubmit="return confirm('Approve this account and link it to the selected employee?');">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="approve_registration">
                        <input type="hidden" name="id" value="<?= $r['id'] ?>">
                        <select name="employee_id" required aria-label="Employee record">
                            <option value="">Select employee…</option>
                            <?php foreach ($free as $f): ?>
                                <option value="<?= $f['id'] ?>" <?= $f['id'] == $guess ? 'selected' : '' ?>><?= htmlspecialchars($f['name']) ?><?= $f['status'] === 'inactive' ? ' (inactive)' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </td>
                <td>
                    <div class="row-actions">
                        <button type="submit" form="approve-<?= $r['id'] ?>" class="sm green">Approve</button>
                        <form method="POST" class="inline" onsubmit="var r = prompt('Reason for rejecting (optional, the employee will see it):'); if (r === null) return false; this.reason.value = r; return true;">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="reject_registration">
                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <input type="hidden" name="reason" value="">
                            <button type="submit" class="sm danger">Reject</button>
                        </form>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php if (!$free): ?>
        <p class="small" style="padding:14px 18px;">Every employee record already has a login. Add the employee in the Employees section first, then approve.</p>
    <?php endif; ?>
    <?php else: ?>
        <div class="empty">No registrations are waiting. New sign-ups appear here after the employee confirms their email.</div>
    <?php endif; ?>
</div>

<div class="panel">
    <div class="panel-head">
        <div>
            <h3>Recent decisions</h3>
            <span class="small">The last 15 approved or rejected registrations.</span>
        </div>
    </div>
    <?php if ($history): ?>
    <div class="table-wrap">
        <table>
            <tr><th>Name / email</th><th>Result</th><th>Linked employee / reason</th><th>Decided</th><th>By</th></tr>
            <?php foreach ($history as $h): ?>
            <tr>
                <td>
                    <span class="emp-name"><?= htmlspecialchars($h['full_name']) ?></span>
                    <span class="emp-sub"><?= htmlspecialchars($h['email']) ?></span>
                </td>
                <td><span class="badge <?= $h['status'] === 'approved' ? 'done' : 'inactive' ?>"><?= ucfirst($h['status']) ?></span></td>
                <td class="muted-cell"><?= $h['status'] === 'approved' ? htmlspecialchars($h['employee_name'] ?? '') : htmlspecialchars($h['reject_reason'] ?: 'No reason given') ?></td>
                <td class="muted-cell nowrap"><?= date('M d, Y', strtotime($h['reviewed_at'])) ?></td>
                <td class="muted-cell"><?= htmlspecialchars($h['reviewer'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
    <?php else: ?>
        <div class="empty">No decisions yet.</div>
    <?php endif; ?>
</div>
</section>
