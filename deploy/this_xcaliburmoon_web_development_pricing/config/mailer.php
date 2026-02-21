<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

/**
 * sendQuoteEmail
 *
 * Sends the quote record to the configured MAIL_TO address using PHPMailer.
 * Returns true on success, false on failure.
 */
function sendQuoteEmail(array $record): bool
{
    // Autoload PHPMailer. Adjust path if Composer vendor is elsewhere.
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        error_log('XCM Mailer: Composer autoload not found. Run: bash setup.sh');
        return false;
    }
    require_once $autoload;

    $mail = new PHPMailer(true);

    try {
        $mode = defined('MAIL_MODE') ? MAIL_MODE : 'server';

        if ($mode === 'smtp' || $mode === 'tls') {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_PASSWORD;
            $mail->SMTPSecure = ($mode === 'tls') ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port       = SMTP_PORT;
        } else {
            $mail->isMail();
        }

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress(MAIL_TO);

        $contactName  = htmlspecialchars($record['quote_data']['contact_name']  ?? 'Unknown', ENT_QUOTES, 'UTF-8');
        $contactEmail = htmlspecialchars($record['quote_data']['contact_email'] ?? '', ENT_QUOTES, 'UTF-8');

        if (!empty($contactEmail)) {
            $mail->addReplyTo($contactEmail, $contactName);
        }

        $refID      = $record['ref_id']  ?? 'N/A';
        $rangeLow   = number_format($record['estimate']['range_low']  ?? 0, 0, '.', ',');
        $rangeHigh  = number_format($record['estimate']['range_high'] ?? 0, 0, '.', ',');
        $expiresDate = date('F j, Y', $record['expires_at'] ?? time());

        $mail->Subject = 'New Quote Request - ' . $refID;
        $mail->Body    = "New quote request received.\n\n"
            . "Reference ID : " . $refID . "\n"
            . "Name         : " . $contactName . "\n"
            . "Email        : " . $contactEmail . "\n"
            . "Estimate     : \$" . $rangeLow . " to \$" . $rangeHigh . "\n"
            . "Expires      : " . $expiresDate . "\n\n"
            . "Full quote data:\n"
            . json_encode($record['quote_data'], JSON_PRETTY_PRINT) . "\n";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log('XCM Mailer error: ' . $mail->ErrorInfo);
        return false;
    }
}
