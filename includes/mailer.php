<?php
/*
 * Sending emails. Settings are in config.php.
 *   mode 'log'  -> the email is written to storage/mail.log (nothing is sent)
 *   mode 'smtp' -> sent through PHPMailer (Gmail or any SMTP server)
 */

// Send one email. Returns true when it was sent (or logged), false when it failed.
function sendMail($to, $subject, $html, $text = '') {
    $config = require __DIR__ . '/../config.php';
    $mail   = $config['mail'];
    $text   = $text !== '' ? $text : trim(html_entity_decode(strip_tags(preg_replace('#<br\s*/?>#i', "\n", $html))));

    if (($mail['mode'] ?? 'log') !== 'smtp') {
        $line = str_repeat('-', 60) . "\n" . date('Y-m-d H:i:s') . "\nTo: $to\nSubject: $subject\n\n$text\n\n";
        return file_put_contents(__DIR__ . '/../storage/mail.log', $line, FILE_APPEND | LOCK_EX) !== false;
    }

    require_once __DIR__ . '/../lib/PHPMailer/Exception.php';
    require_once __DIR__ . '/../lib/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/../lib/PHPMailer/SMTP.php';

        if (!file_exists(__DIR__ . '/../lib/PHPMailer/PHPMailer.php')) {
        error_log('PHPMailer is not installed, so the email was not sent.');
        return false;
    }

    try {
        $m = new PHPMailer\PHPMailer\PHPMailer(true);
        $m->isSMTP();
        $m->Host       = $mail['host'];
        $m->Port       = (int)$mail['port'];
        $m->SMTPAuth   = true;
        $m->Username   = $mail['username'];
        $m->Password   = $mail['password'];
        $m->SMTPSecure = ($mail['encryption'] ?? 'tls') === 'ssl'
            ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $m->Timeout    = 10;
        $m->CharSet    = 'UTF-8';
        $m->setFrom($mail['from_email'] ?: $mail['username'], $mail['from_name'] ?? '');
        $m->addAddress($to);
        $m->isHTML(true);
        $m->Subject = $subject;
        $m->Body    = $html;
        $m->AltBody = $text;
        $m->send();
        return true;
    } catch (Throwable $e) {
        error_log('Mail failed: ' . $e->getMessage());
        return false;
    }
}

// Simple email layout: a heading, some paragraphs and one button. Returns [html, text].
function mailTemplate($heading, array $paragraphs, $buttonText = '', $url = '') {
    $h = htmlspecialchars($heading);
    $body = '';
    $plain = $heading . "\n\n";
    foreach ($paragraphs as $p) {
        $body  .= '<p style="margin:0 0 14px;font-size:14px;line-height:1.55;color:#221F1C;">' . htmlspecialchars($p) . '</p>';
        $plain .= $p . "\n\n";
    }
    $button = '';
    if ($url !== '') {
        $u = htmlspecialchars($url);
        $button = '<p style="margin:22px 0;"><a href="' . $u . '" style="background:#E75A3C;color:#fff;text-decoration:none;font-weight:600;font-size:14px;padding:12px 22px;border-radius:8px;display:inline-block;">' . htmlspecialchars($buttonText) . '</a></p>'
                . '<p style="margin:0 0 14px;font-size:12px;color:#615B55;">Or copy this link into your browser:<br>' . $u . '</p>';
        $plain .= "$buttonText: $url\n\n";
    }
    $html = '<div style="background:#F6F1E8;padding:28px 12px;font-family:Arial,sans-serif;"><div style="max-width:520px;margin:0 auto;background:#FFFDFC;border:1px solid #DED7CF;border-radius:14px;padding:28px;">'
          . '<div style="font-size:12px;font-weight:700;color:#E75A3C;letter-spacing:.04em;text-transform:uppercase;margin-bottom:10px;">Oppa\'s Samgyeopsal</div>'
          . '<h1 style="margin:0 0 16px;font-size:22px;font-weight:500;color:#221F1C;">' . $h . '</h1>'
          . $body . $button . '</div></div>';
    return [$html, $plain];
}
