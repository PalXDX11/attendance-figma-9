<?php
/*
 * Security helpers: CSRF protection, attempt limits, one-time tokens and the app address.
 * Loaded by auth.php, so every page that uses auth.php is protected.
 */

// ---------- CSRF: every POST form must carry the secret token of the current session ----------
function csrfToken() {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

// Hidden input to put inside every <form method="POST">
function csrfField() {
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrfToken()) . '">';
}

function requireCsrf() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $sent    = $_POST['csrf'] ?? '';
    $expects = $_SESSION['csrf'] ?? '';
    if (!is_string($sent) || $expects === '' || !hash_equals($expects, $sent)) {
        http_response_code(419);
        exit("This page expired or the request was not valid. Go back, refresh the page and try again.");
    }
}

// ---------- Attempt limits (failed logins, sign-ups, reset requests) ----------
function clientIp() {
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function tooManyAttempts($pdo, $kind, $subject, $max, $minutes) {
    $since = date('Y-m-d H:i:s', time() - $minutes * 60);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM auth_attempts WHERE kind = ? AND subject = ? AND attempted_at > ?");
    $stmt->execute([$kind, $subject, $since]);
    return (int)$stmt->fetchColumn() >= $max;
}

function recordAttempt($pdo, $kind, $subject) {
    $pdo->prepare("INSERT INTO auth_attempts (kind, subject, attempted_at) VALUES (?, ?, ?)")
        ->execute([$kind, substr($subject, 0, 190), date('Y-m-d H:i:s')]);
    // keep the table small
    $pdo->prepare("DELETE FROM auth_attempts WHERE attempted_at < ?")
        ->execute([date('Y-m-d H:i:s', time() - 86400)]);
}

function clearAttempts($pdo, $kind, $subject) {
    $pdo->prepare("DELETE FROM auth_attempts WHERE kind = ? AND subject = ?")->execute([$kind, $subject]);
}

// ---------- One-time tokens: the link carries $raw, the database only keeps $hash ----------
function newToken() {
    $raw = bin2hex(random_bytes(32));
    return ['raw' => $raw, 'hash' => hash('sha256', $raw)];
}

function tokenHash($raw) {
    return hash('sha256', (string)$raw);
}

// ---------- Address of the system, used in the links inside emails ----------
function appBaseUrl() {
    $config = require __DIR__ . '/../config.php';
    $url = rtrim($config['app_url'] ?? '', '/');
    if ($url !== '') return $url;

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $dir    = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir    = preg_replace('#/(admin|employee|api)$#', '', $dir);
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim($dir, '/');
}
