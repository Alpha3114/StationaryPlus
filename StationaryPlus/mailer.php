<?php
// ============================================================
//  mailer.php — Shared email sender (Gmail SMTP via PHPMailer)
//  Replaces raw mail() calls, which Hostinger heavily restricts
//  and which frequently land in spam due to missing SPF/DKIM.
//
//  Usage:
//      require_once 'mailer.php';
//      $ok = sendAppEmail('customer@example.com', 'Subject here', "Plain text body");
//      // $ok is true on success, false on failure (check error_log for details)
// ============================================================

require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';
require_once __DIR__ . '/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/**
 * Send a plain-text email via Gmail SMTP.
 *
 * @param string $to      Recipient email address
 * @param string $subject Email subject line
 * @param string $body    Plain text email body
 * @return bool           true on success, false on failure
 */
function sendAppEmail(string $to, string $subject, string $body): bool
{
    if (!defined('GMAIL_ADDRESS') || !defined('GMAIL_APP_PASSWORD')) {
        error_log('sendAppEmail: GMAIL_ADDRESS or GMAIL_APP_PASSWORD not defined in config.php');
        return false;
    }

    $mail = new PHPMailer(true); // true = enable exceptions

    try {
        // ── SMTP setup (Gmail) ──────────────────────────────────
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = GMAIL_ADDRESS;
        $mail->Password   = GMAIL_APP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // ── Sender / recipient ───────────────────────────────────
        $mail->setFrom(GMAIL_ADDRESS, 'StationaryPlus');
        $mail->addAddress($to);
        $mail->addReplyTo(GMAIL_ADDRESS, 'StationaryPlus Support');

        // ── Content ───────────────────────────────────────────────
        $mail->isHTML(false); // plain text — matches existing email bodies
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->CharSet = 'UTF-8';

        $mail->send();
        return true;

    } catch (PHPMailerException $e) {
        // Log the real error server-side; never expose SMTP details to the user
        error_log('sendAppEmail failed: ' . $mail->ErrorInfo);
        return false;
    }
}