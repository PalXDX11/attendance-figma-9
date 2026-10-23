<?php
/*
 * Section: Attendance management (filter, add manual entry, edit, clock out, delete)
 * Included by admin/dashboard.php.
 * Filters come from the URL: att_emp, att_from, att_to. Defaults to the current week.
 */
$flash = getFlash('attendance');

$allEmployees = $pdo->query("SELECT id, name, status FROM employees ORDER BY name")->fetchAll();

$thisMonday = (new DateTime('today'))->modify('monday this week');
$fEmp  = (int)($_GET['att_emp'] ?? 0);
$fFrom = validDate($_GET['att_from'] ?? '', $thisMonday->format('Y-m-d'));
$fTo   = validDate($_GET['att_to'] ?? '', (clone $thisMonday)->modify('+6 days')->format('Y-m-d'));
if ($fTo < $fFrom) { [$fFrom, $fTo] = [$fTo, $fFrom]; }

$from = $fFrom . ' 00:00:00';
$to   = (new DateTime($fTo))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';   // exclusive

$sql = "
    SELECT a.id, a.employee_id, a.clock_in, a.clock_out, e.name, e.hourly_rate,
           TIMESTAMPDIFF(SECOND, a.clock_in, COALESCE(a.clock_out, NOW())) AS secs
    FROM attendance_logs a
    JOIN employees e ON e.id = a.employee_id
    WHERE a.clock_in >= ? AND a.clock_in < ?";
$args = [$from, $to];
if ($fEmp > 0) {
    $sql .= " AND a.employee_id = ?";
    $args[] = $fEmp;
}
$sql .= " ORDER BY a.clock_in DESC, a.id DESC LIMIT 300";
$stmt = $pdo->prepare($sql);
$stmt->execute($args);
$records = $stmt->fetchAll();

$doneSecs = 0; $openNow = 0; $pay = 0;
foreach ($records as $r) {
    if ($r['clock_out'] === null) { $openNow++; continue; }
    $doneSecs += (int)$r['secs'];
    $pay      += computePay($r['secs'], $r['hourly_rate']);
}

$nowLocal = date('Y-m-d\TH:i');
?>

<!-- Attendance management Section -->
<section id="attendance" class="page-section">
    <div class="page-head">
        <span class="eyebrow">Timekeeping</span>
        <h1>Attendance management</h1>
        <p>Review every shift, fix missed punches, and keep the records accurate.</p>
    </div>

<?php if ($flash): ?>
    <div class="msg <?= $flash['type'] ?>"><?= htmlspecialchars($flash['msg']) ?></div>
<?php endif; ?>

<div class="stats">
    <div class="stat">
        <span class="label">Shifts shown</span>
        <span class="value"><?= count($records) ?></span>
    </div>
    <div class="stat">
        <span class="label">Completed hours</span>
        <span class="value green"><?= number_format($doneSecs / 3600, 2) ?></span>
    </div>
    <div class="stat">
        <span class="label">Gross pay (completed)</span>
        <span class="value"><?= peso($pay) ?></span>
    </div>
    <div class="stat">
        <span class="label">Still clocked in</span>
        <span class="value <?= $openNow ? 'red' : '' ?>"><?= $openNow ?></span>
    </div>
</div>

<div class="att-stack">
    <div class="panel">
        <div class="panel-head">
            <div>
                <h3>Add manual entry</h3>
                <span class="small">For a missed punch. Leave clock out empty for an open shift.</span>
            </div>
        </div>
        <form method="POST" class="panel-body edit-form add-form" onsubmit="return confirm('Add this shift?');">
<?= csrfField() ?>
            <input type="hidden" name="action" value="add_attendance">
            <div class="field">
                <label for="att_employee">Employee</label>
                <select name="employee_id" id="att_employee" required>
                    <option value="">Select employee</option>
                    <?php foreach ($allEmployees as $e): ?>
                        <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['name']) ?><?= $e['status'] === 'inactive' ? ' (inactive)' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="att_in">Clock in</label>
                <input type="datetime-local" id="att_in" name="clock_in" max="<?= $nowLocal ?>" required>
            </div>
            <div class="field">
                <label for="att_out">Clock out <span class="small">(optional)</span></label>
                <input type="datetime-local" id="att_out" name="clock_out" max="<?= $nowLocal ?>">
            </div>
            <div class="field grow">
                <label for="att_reason">Reason</label>
                <input type="text" name="reason" id="att_reason" maxlength="200" placeholder="e.g. Forgot to punch out" required>
            </div>
            <button type="submit">Add Shift</button>
        </form>
    </div>

    <div class="panel">
        <div class="panel-head">
            <div>
                <h3>Attendance records</h3>
                <span class="small"><?= date('M d, Y', strtotime($fFrom)) ?> - <?= date('M d, Y', strtotime($fTo)) ?>
                    <?= count($records) === 300 ? ' &middot; showing the latest 300' : '' ?></span>
            </div>
        </div>

        <form method="GET" class="filter-bar" action="#attendance">
            <?php if (isset($_GET['start']) && is_string($_GET['start'])): ?>
                <input type="hidden" name="start" value="<?= htmlspecialchars($_GET['start']) ?>">
            <?php endif; ?>
            <select name="att_emp" aria-label="Employee">
                <option value="0">All employees</option>
                <?php foreach ($allEmployees as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= $e['id'] == $fEmp ? 'selected' : '' ?>><?= htmlspecialchars($e['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="att_from" value="<?= $fFrom ?>" aria-label="From date">
            <span class="small">to</span>
            <input type="date" name="att_to" value="<?= $fTo ?>" aria-label="To date">
            <button type="submit" class="sm">Filter</button>
            <a class="btn sm gray" href="dashboard.php<?= isset($_GET['start']) && is_string($_GET['start']) ? '?start=' . urlencode($_GET['start']) : '' ?>#attendance">Reset</a>
        </form>

        <?php if ($records): ?>
        <div class="table-wrap">
            <table>
                <tr><th>Employee</th><th>Clock in</th><th>Clock out</th><th>Duration</th><th>Status</th><th>Actions</th></tr>
                <?php foreach ($records as $r):
                    $open = $r['clock_out'] === null;
                    $inVal  = date('Y-m-d\TH:i', strtotime($r['clock_in']));
                    $outVal = $open ? '' : date('Y-m-d\TH:i', strtotime($r['clock_out']));
                ?>
                <tr>
                    <td>
                        <div class="emp">
                            <span class="avatar"><?= htmlspecialchars(initials($r['name'])) ?></span>
                            <div>
                                <span class="emp-name"><?= htmlspecialchars($r['name']) ?></span>
                                <span class="emp-sub"><?= empCode($r['employee_id']) ?></span>
                            </div>
                        </div>
                    </td>
                    <td class="nowrap"><?= date('M d, g:i:s A', strtotime($r['clock_in'])) ?></td>
                    <td class="nowrap"><?= $open ? '<span class="muted-cell">&mdash;</span>' : date('M d, g:i:s A', strtotime($r['clock_out'])) ?></td>
                    <td class="mono"><?= formatDuration($r['secs']) ?></td>
                    <td><span class="badge <?= $open ? 'open' : 'done' ?>"><?= $open ? 'Clocked in' : 'Completed' ?></span></td>
                    <td>
                        <div class="row-actions">
                            <button type="button" class="sm gray" onclick="toggleAttEdit(<?= $r['id'] ?>)">Edit</button>
                            <?php if ($open): ?>
                            <form method="POST" class="inline" onsubmit="return confirm('Clock out <?= htmlspecialchars(addslashes($r['name']), ENT_QUOTES) ?> now?');">
<?= csrfField() ?>
                                <input type="hidden" name="action" value="end_shift">
                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <button type="submit" class="sm green">Clock out</button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" class="inline" onsubmit="return confirm('Delete this shift permanently?');">
<?= csrfField() ?>
                                <input type="hidden" name="action" value="delete_attendance">
                                <input type="hidden" name="id" value="<?= $r['id'] ?>">
                                <button type="submit" class="sm danger">Delete</button>
                            </form>
                        </div>
                    </td>
                </tr>
                <tr class="edit-row" id="att-edit-<?= $r['id'] ?>" style="display:none;">
                    <td colspan="6">
                        <form method="POST" class="edit-form" onsubmit="return confirm('Save changes to this shift?');">
<?= csrfField() ?>
                            <input type="hidden" name="action" value="edit_attendance">
                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <div class="field">
                                <label>Clock in</label>
                                <input type="datetime-local" name="clock_in" value="<?= $inVal ?>" max="<?= $nowLocal ?>" required>
                            </div>
                            <div class="field">
                                <label>Clock out</label>
                                <input type="datetime-local" name="clock_out" value="<?= $outVal ?>" max="<?= $nowLocal ?>">
                            </div>
                            <div class="field grow">
                                <label>Reason for change</label>
                                <input type="text" name="reason" maxlength="200" placeholder="Required" required>
                            </div>
                            <button type="submit" class="sm">Save</button>
                            <button type="button" class="sm gray" onclick="toggleAttEdit(<?= $r['id'] ?>)">Cancel</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php else: ?>
            <div class="empty">No attendance records for these filters.</div>
        <?php endif; ?>
    </div>
</div>

<p class="small">Every add, edit, clock out and delete is saved to the audit log. Clear a clock out time while editing to reopen a shift.</p>

<script>
function toggleAttEdit(id) {
    const row = document.getElementById('att-edit-' + id);
    row.style.display = row.style.display === 'none' ? '' : 'none';
}
</script>
</section>
