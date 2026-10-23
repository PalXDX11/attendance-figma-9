<?php
/*
 * Shared layout of the pages you see before signing in (login, register, forgot, reset, verify).
 * Use authPageStart(...) before the content and authPageEnd() after it.
 * These pages live in the app root, so asset paths are relative to it.
 */

function authPageStart($title, $eyebrow, $heading, $sub = '') {
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="icon" href="assets/images/logo.jpeg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?> | Oppa's Samgyeopsal</title>
    <link rel="stylesheet" href="assets/style.css">
    <link rel="stylesheet" href="assets/auth.css">
</head>
<body class="login-split">

    <div class="ls-left">
        <div class="ls-hero">
            <span class="ls-status">Biometric attendance system</span>
            <h2>Every shift counts. Every minute stays transparent.</h2>
            <p>A secure biometric attendance workspace built for the people who keep Oppa&rsquo;s Samgyeopsal moving.</p>
            <ul>
                <li><img src="assets/images/icons/fingerprint.svg" alt="">Biometric verified</li>
                <li><img src="assets/images/icons/timer.svg" alt="">Real-time salary</li>
                <li><img src="assets/images/icons/shield-check.svg" alt="">Transparent deductions</li>
            </ul>
        </div>

        <div class="ls-brand">
            <span class="brand-mark">OS</span>
            <div>
                <strong>Oppa's Samgyeopsal</strong>
                <span>Attendance &amp; Payroll</span>
            </div>
        </div>
    </div>

    <div class="ls-right">
        <div class="ls-form">
            <div class="mobile-brand"><span class="brand-mark">OS</span><strong>Oppa's Samgyeopsal</strong></div>

            <div class="ls-heading">
                <span class="eyebrow"><?= htmlspecialchars($eyebrow) ?></span>
                <h1><?= htmlspecialchars($heading) ?></h1>
                <?php if ($sub !== ''): ?><p class="sub"><?= htmlspecialchars($sub) ?></p><?php endif; ?>
            </div>
<?php
}

function authPageEnd() {
    ?>
        </div>

        <div class="ls-foot">Biometric Attendance and Real-Time Salary Calculation System</div>
    </div>

</body>
</html>
<?php
}

// One labelled input with an icon, in the login style
function authField($id, $label, $type, $icon, $placeholder, $value = '', $extra = '') {
    ?>
                <div>
                    <label for="<?= $id ?>"><?= htmlspecialchars($label) ?></label>
                    <div class="ls-input">
                        <img src="assets/images/icons/<?= $icon ?>.svg" alt="">
                        <input type="<?= $type ?>" id="<?= $id ?>" name="<?= $id ?>" placeholder="<?= htmlspecialchars($placeholder) ?>" value="<?= htmlspecialchars($value) ?>" <?= $extra ?>>
                    </div>
                </div>
<?php
}
