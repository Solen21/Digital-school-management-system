<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Include the configuration file for SMTP settings
require_once __DIR__ . '/config.php';

/**
 * Sends an email using PHPMailer with SMTP.
 *
 * @param string $to The recipient's email address.
 * @param string $subject The subject of the email.
 * @param string $body The HTML body of the email.
 * @param string $altBody The plain text alternative body.
 * @return bool True on success, false on failure.
 */
function sendEmail(string $to, string $subject, string $body, string $altBody = ''): bool {
    $mail = new PHPMailer(true);

    try {
        // Server settings from config.php
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;

        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to); // Add a recipient

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = $altBody ?: strip_tags($body);

        $mail->send();
        return true;
    } catch (Exception $e) {
        // In a real application, you would log this error.
        // error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

?>