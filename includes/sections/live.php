<?php
/*
 * Section: Live attendance (clock, totals, live list)
 * Included by admin/dashboard.php.
 */
$totalActive = (int)$pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'active'")->fetchColumn();
?>

<!-- Live attendance Section -->
<section id="live" class="page-section">
    <div class="page-head">
        <span class="eyebrow">Real time monitoring</span>
        <h1>Live attendance</h1>
        <p>See who is clocked in and how their salary grows in real time.</p>
    </div>

<div class="split">
    <div>
        <div class="card clock-card">
            <div class="clock-time" id="clock">--:--:--</div>
            <div class="clock-date" id="cdate"></div>
        </div>

        <div class="stat">
            <span class="label">Clocked in now</span>
            <span class="value"><span id="mIn">0</span> <small>/ <?= $totalActive ?> active</small></span>
        </div>

        <div class="stat">
            <span class="label">Total salary so far</span>
            <span class="value green" id="mSal">₱0.00</span>
        </div>
    </div>

    <div class="panel">
        <div class="panel-head">
            <div>
                <h3>Live attendance list</h3>
                <span class="small" id="showing"></span>
            </div>
            <input type="text" class="search" id="search" placeholder="Search employees...">
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Clock In</th>
                        <th>Time elapsed</th>
                        <th>Rate/hr</th>
                        <th>Salary so far</th>
                    </tr>
                </thead>
                <tbody id="rows"></tbody>
            </table>
        </div>
        <div id="empty" class="empty" style="display:none;">No one is clocked in right now.</div>
    </div>
</div>

<script>
let sessions = [];
let loadedAt = Date.now();

function initials(name) {
    const p = name.trim().split(/\s+/);
    let i = (p[0] || '').charAt(0);
    if (p.length > 1) i += p[p.length - 1].charAt(0);
    return (i || '?').toUpperCase();
}

async function load() {
    try {
        const res = await fetch('../api/live_data.php');
        sessions = await res.json();
    } catch (e) {
        return; // keep the old data if the request fails
    }
    sessions.forEach(s => s.base = Number(s.elapsed_secs));
    loadedAt = Date.now();
    build();
}

function build() {
    const tbody = document.getElementById('rows');
    tbody.innerHTML = '';
    document.getElementById('empty').style.display = sessions.length ? 'none' : 'block';
    document.getElementById('mIn').textContent = sessions.length;

    sessions.forEach((s, i) => {
        const tr = document.createElement('tr');
        tr.dataset.name = s.name.toLowerCase();
        tr.innerHTML = `<td><div class="emp"><span class="avatar"></span><span class="ename"></span></div></td>
                        <td></td>
                        <td class="mono" id="t${i}"></td>
                        <td></td>
                        <td class="salary" id="p${i}"></td>`;
        tr.querySelector('.avatar').textContent = initials(s.name);
        tr.querySelector('.ename').textContent = s.name;
        tr.children[1].textContent = s.clock_in;
        tr.children[3].textContent = '₱' + Number(s.hourly_rate).toFixed(2);
        tbody.appendChild(tr);
    });
    applyFilter();
    tick();
}

function applyFilter() {
    const q = document.getElementById('search').value.trim().toLowerCase();
    let shown = 0;
    document.querySelectorAll('#rows tr').forEach(tr => {
        const match = !q || tr.dataset.name.includes(q);
        tr.style.display = match ? '' : 'none';
        if (match) shown++;
    });
    document.getElementById('showing').textContent = 'Showing ' + shown + ' employee' + (shown === 1 ? '' : 's');
}

function tick() {
    const now = new Date();
    document.getElementById('clock').textContent = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true });
    document.getElementById('cdate').textContent = now.toLocaleDateString('en-US', {
        weekday: 'long', year: 'numeric', month: 'long', day: 'numeric'
    });

    const extra = Math.floor((Date.now() - loadedAt) / 1000);
    let total = 0;
    sessions.forEach((s, i) => {
        const secs = s.base + extra;
        const h = String(Math.floor(secs / 3600)).padStart(2, '0');
        const m = String(Math.floor((secs % 3600) / 60)).padStart(2, '0');
        const sec = String(secs % 60).padStart(2, '0');
        const pay = (secs / 3600) * Number(s.hourly_rate);
        total += pay;
        document.getElementById('t' + i).textContent = `${h}:${m}:${sec}`;
        document.getElementById('p' + i).textContent = '₱' + pay.toFixed(2);
    });
    document.getElementById('mSal').textContent = '₱' + total.toFixed(2);
}

document.getElementById('search').addEventListener('input', applyFilter);

load();
setInterval(tick, 1000);   // update every second
setInterval(load, 10000);  // refresh the list every 10 seconds
</script>
</section>
