<?php
// This page is now a section of the dashboard. Send the user there (keeps the selected week).
$url = 'dashboard.php';
if (isset($_GET['start']) && is_string($_GET['start'])) {
    $url .= '?start=' . urlencode($_GET['start']);
}
header("Location: $url#payroll");
exit;
