<?php
/*
 * Shared layout (top part) for the employee pages.
 * Before including this file, set:
 *   $pageTitle  - big title of the page
 *   $active     - 'home' or 'payslip' (highlighted sidebar item)
 *   $eyebrow / $subtitle - small texts around the title (optional)
 * Also needs $pdo and the logged-in employee row in $me.
 */
date_default_timezone_set('Asia/Manila');
require_once __DIR__ . '/functions.php';

$pageTitle = $pageTitle ?? 'Employee';
$active    = $active ?? '';
$eyebrow   = $eyebrow ?? '';
$subtitle  = $subtitle ?? '';

$nav = [
    'home'    => ['Home', 'home.php',
        '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>'],
    'payslip' => ['My payslip', 'payslip.php',
        '<path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1z"/><path d="M16 8h-6a2 2 0 1 0 0 4h4a2 2 0 1 1 0 4H8"/><path d="M12 17.5v-11"/>'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle) ?> | Oppa's Samgyeopsal</title>
    <link rel="stylesheet" href="../assets/style.css">
    <link rel="icon" href="../assets/images/logo.jpeg">
</head>
<body>
<div class="app">

    <aside class="sidebar">
        <div class="sb-brand">
            <span class="brand-mark">OS</span>
            <div>
                <strong>Oppa's Samgyeopsal</strong>
                <span>Employee portal</span>
            </div>
        </div>

        <div class="sb-label">My account</div>
        <nav class="sb-nav">
            <?php foreach ($nav as $key => $item): ?>
                <a href="<?= $item[1] ?>" class="<?= $key === $active ? 'active' : '' ?>">
                    <svg class="icon" viewBox="0 0 24 24"><?= $item[2] ?></svg>
                    <?= $item[0] ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="sb-user">
            <span class="avatar"><?= htmlspecialchars(initials($me['name'])) ?></span>
            <div>
                <strong><?= htmlspecialchars(explode(' ', trim($me['name']))[0]) ?></strong>
                <span><?= empCode($me['id']) ?></span>
            </div>
            <a href="../logout.php">Logout</a>
        </div>
    </aside>

    <div class="main">
        <div class="page-top">
            <span class="portal-label">
                <img src="../assets/images/icons/layout-dashboard.svg" alt="">
                Attendance &amp; Payroll Portal
            </span>
            <span class="date-pill">
                <svg class="icon" viewBox="0 0 24 24"><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>
                <?= date('M j, Y') ?>
            </span>
        </div>

        <div class="content">
            <div class="page-head">
                <?php if ($eyebrow): ?><span class="eyebrow"><?= htmlspecialchars($eyebrow) ?></span><?php endif; ?>
                <h1><?= htmlspecialchars($pageTitle) ?></h1>
                <?php if ($subtitle): ?><p><?= htmlspecialchars($subtitle) ?></p><?php endif; ?>
            </div>
