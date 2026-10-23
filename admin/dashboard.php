<?php
require '../auth.php';
require '../db.php';
require_once '../includes/functions.php';
requireAdmin();

// Handle any submitted form first (it redirects back here), before any HTML is printed
require '../includes/actions.php';

$pageTitle = 'Dashboard';
$active    = 'overview';
$showHead  = false;   // each section prints its own heading

include '../includes/head.php';
include '../includes/header.php';
?>

<main>
    <?php include '../includes/sections/overview.php'; ?>
    <?php include '../includes/sections/live.php'; ?>
    <?php include '../includes/sections/attendance.php'; ?>
    <?php include '../includes/sections/deductions.php'; ?>
    <?php include '../includes/sections/employees.php'; ?>
    <?php include '../includes/sections/registrations.php'; ?>
    <?php include '../includes/sections/payroll.php'; ?>
</main>

<?php
include '../includes/footer.php';
include '../includes/scripts.php';
