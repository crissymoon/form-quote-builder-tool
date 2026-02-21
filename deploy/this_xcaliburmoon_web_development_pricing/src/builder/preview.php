<?php
declare(strict_types=1);
/**
 * Quote Form Builder -- Live Preview Renderer
 * Reads configured services, complexity, add-ons, contact fields
 * and calculates an accurate quote with budget tier options.
 * $form is already loaded by index.php or src/index.php (live mode).
 *
 * $isBuilderPreview: true when opened from the builder, false when
 * rendered as the live public-facing form.
 */
$isBuilderPreview = $isBuilderPreview ?? false;

// ── Handle form submission (AJAX POST) ────────────────────────────────────────
if (!$isBuilderPreview && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (is_array($input) && ($input['action'] ?? '') === 'submit') {
        header('Content-Type: application/json; charset=utf-8');
        $result = pvHandleSubmission($form, $input);
        echo json_encode($result);
        exit;
    }
}

/**
 * Handle a form submission: save to JSON + send emails.
 * Returns an associative array with 'ok', 'message', 'saved', 'emailed'.
 */
function pvHandleSubmission(array $form, array $input): array
{
    $base = dirname(__DIR__, 2);
    $settingsFile = $base . '/config/settings.php';
    $autoload     = $base . '/vendor/autoload.php';

    if (!file_exists($settingsFile)) {
        error_log('XCM Quote: settings not found at ' . $settingsFile);
        return ['ok' => false, 'message' => 'Server configuration missing.', 'saved' => false, 'emailed' => false];
    }
    if (!defined('APP_NAME')) {
        require_once $settingsFile;
    }

    $contact    = $input['contact']    ?? [];
    $service    = $input['service']    ?? '';
    $complexity = $input['complexity'] ?? '';
    $addons     = $input['addons']     ?? [];
    $tierData   = $input['tiers']      ?? [];
    $subtotal   = (float) ($input['subtotal'] ?? 0);
    $details    = trim($input['details'] ?? '');
    $userName   = $contact['name'] ?? $contact['full_name'] ?? 'Visitor';
    $userEmail  = filter_var($contact['email'] ?? $contact['email_address'] ?? '', FILTER_VALIDATE_EMAIL);

    // ── 1. Save submission to JSON ────────────────────────────────────────
    $saved = false;
    $saveEnabled = defined('SAVE_SUBMISSIONS') ? SAVE_SUBMISSIONS : true;
    if ($saveEnabled) {
        $saved = pvSaveSubmission($base, [
            'submitted_at' => date('c'),
            'form_name'    => $form['name'] ?? 'Quote Form',
            'contact'      => $contact,
            'service'      => $service,
            'complexity'   => $complexity,
            'addons'       => $addons,
            'tiers'        => $tierData,
            'subtotal'     => $subtotal,
            'details'      => $details,
        ]);
    }

    // ── 2. Send emails ────────────────────────────────────────────────────
    $emailed = false;
    $sendEnabled = defined('SEND_EMAILS') ? SEND_EMAILS : true;
    if ($sendEnabled && file_exists($autoload)) {
        require_once $autoload;
        $emailed = pvSendEmails($form, $input);
    } elseif ($sendEnabled && !file_exists($autoload)) {
        error_log('XCM Quote: vendor/autoload.php not found -- skipping email. Run composer install.');
    }

    // Build user-facing message
    $parts = [];
    if ($saved)   { $parts[] = 'Your submission has been recorded.'; }
    if ($emailed && $userEmail) { $parts[] = 'A confirmation has been sent to your email.'; }
    if ($sendEnabled && !$emailed) {
        $parts[] = 'Email delivery is not available at this time.';
    }
    if (!$saved && !$emailed) { $parts[] = 'Please take note of your estimate.'; }

    return [
        'ok'      => $saved || $emailed,
        'message' => implode(' ', $parts),
        'saved'   => $saved,
        'emailed' => $emailed,
    ];
}

/**
 * Append a submission record to the submissions JSON file.
 */
function pvSaveSubmission(string $base, array $record): bool
{
    $file = defined('SUBMISSIONS_FILE')
        ? SUBMISSIONS_FILE
        : $base . '/data/submissions.json';

    $dir = dirname($file);
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true)) {
            error_log('XCM Quote: cannot create data dir ' . $dir);
            return false;
        }
    }

    // Protect directory with .htaccess if not already present
    $htaccess = $dir . '/.htaccess';
    if (!file_exists($htaccess)) {
        @file_put_contents($htaccess, "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order deny,allow\n    Deny from all\n</IfModule>\n");
    }

    // Read existing entries
    $entries = [];
    if (file_exists($file)) {
        $raw = (string) file_get_contents($file);
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $entries = $decoded;
        }
    }

    $entries[] = $record;

    $written = file_put_contents($file, json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    if ($written === false) {
        error_log('XCM Quote: failed to write ' . $file);
        return false;
    }
    return true;
}

/**
 * Send themed HTML emails to admin and/or user via PHPMailer.
 */
function pvSendEmails(array $form, array $input): bool
{
    $sty  = $form['style']    ?? [];
    $lang = $form['language']  ?? [];
    $primaryColor = $sty['primaryColor'] ?? '#244c47';
    $accentColor  = $sty['accentColor']  ?? '#459289';
    $bgColor      = $sty['bgColor']      ?? '#fcfdfd';
    $textColor    = $sty['textColor']     ?? '#182523';
    $headerBg     = $sty['headerBg']      ?? '#244c47';
    $headerText   = $sty['headerText']    ?? '#eaf5f4';
    $currency     = $lang['currency']     ?? '$';
    $formName     = $form['name']         ?? 'Quote Form';

    $contact   = $input['contact']   ?? [];
    $service   = $input['service']   ?? '';
    $complexity = $input['complexity'] ?? '';
    $addons    = $input['addons']    ?? [];
    $tiers     = $input['tiers']     ?? [];
    $subtotal  = (float) ($input['subtotal'] ?? 0);
    $details   = trim($input['details'] ?? '');

    $userName  = htmlspecialchars($contact['name']  ?? $contact['full_name'] ?? 'Visitor');
    $userEmail = filter_var($contact['email'] ?? $contact['email_address'] ?? '', FILTER_VALIDATE_EMAIL);

    // Build tier rows
    $tierRows = '';
    foreach ($tiers as $t) {
        $tierRows .= '<td style="padding:12px 10px;text-align:center;border:1px solid ' . $accentColor . '40;">';
        $tierRows .= '<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:' . $accentColor . ';">' . htmlspecialchars($t['name'] ?? 'Tier') . '</div>';
        $tierRows .= '<div style="font-size:22px;font-weight:700;color:' . $primaryColor . ';margin:6px 0;">' . $currency . number_format((float)($t['price'] ?? 0)) . '</div>';
        $tierRows .= '<div style="font-size:11px;color:' . $textColor . ';opacity:0.7;">' . htmlspecialchars($t['description'] ?? '') . '</div>';
        $tierRows .= '</td>';
    }

    // Build addon rows
    $addonRows = '';
    foreach ($addons as $a) {
        $addonRows .= '<tr><td style="padding:6px 0;font-size:13px;color:' . $textColor . ';">' . htmlspecialchars($a['label'] ?? '') . '</td>';
        $addonRows .= '<td style="padding:6px 0;font-size:13px;font-weight:700;color:' . $primaryColor . ';text-align:right;">+' . $currency . number_format((float)($a['price'] ?? 0)) . '</td></tr>';
    }

    // Contact summary rows
    $contactRows = '';
    foreach ($contact as $key => $val) {
        if ($val === '' || $val === null) continue;
        $label = ucwords(str_replace('_', ' ', $key));
        $contactRows .= '<tr><td style="padding:4px 0;font-size:13px;color:' . $accentColor . ';font-weight:600;width:140px;vertical-align:top;">' . htmlspecialchars($label) . '</td>';
        $contactRows .= '<td style="padding:4px 0;font-size:13px;color:' . $textColor . ';">' . htmlspecialchars((string)$val) . '</td></tr>';
    }

    // Common email body
    $emailBody = function(string $greeting, bool $isClient) use ($formName, $primaryColor, $accentColor, $bgColor, $textColor, $headerBg, $headerText, $currency, $service, $complexity, $subtotal, $tierRows, $addonRows, $contactRows, $details, $userName) {
        $html  = '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"></head>';
        $html .= '<body style="margin:0;padding:0;background:' . $bgColor . ';font-family:-apple-system,BlinkMacSystemFont,Segoe UI,sans-serif;">';
        $html .= '<table width="100%" cellpadding="0" cellspacing="0" style="background:' . $bgColor . ';">';
        $html .= '<tr><td align="center" style="padding:20px 15px;">';
        $html .= '<table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">';

        // Header
        $html .= '<tr><td style="background:' . $headerBg . ';padding:20px 24px;border-bottom:3px solid ' . $primaryColor . ';">';
        $html .= '<div style="font-size:18px;font-weight:700;color:' . $headerText . ';">' . htmlspecialchars($formName) . '</div>';
        $html .= '</td></tr>';

        // Body
        $html .= '<tr><td style="background:#ffffff;padding:28px 24px;">';
        $html .= '<p style="font-size:15px;color:' . $textColor . ';margin:0 0 18px 0;line-height:1.5;">' . $greeting . '</p>';

        // Selections summary
        $html .= '<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">';
        $html .= '<tr><td style="padding:6px 0;font-size:13px;color:' . $accentColor . ';font-weight:600;width:140px;">Service</td>';
        $html .= '<td style="padding:6px 0;font-size:13px;color:' . $textColor . ';">' . htmlspecialchars($service) . '</td></tr>';
        $html .= '<tr><td style="padding:6px 0;font-size:13px;color:' . $accentColor . ';font-weight:600;">Complexity</td>';
        $html .= '<td style="padding:6px 0;font-size:13px;color:' . $textColor . ';">' . htmlspecialchars($complexity) . '</td></tr>';
        if ($addonRows) {
            $html .= '<tr><td colspan="2" style="padding:10px 0 4px 0;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:' . $accentColor . ';">Add-Ons</td></tr>';
            $html .= $addonRows;
        }
        $html .= '</table>';

        // Tiers
        if ($tierRows) {
            $html .= '<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:' . $accentColor . ';margin-bottom:8px;">Estimate Tiers</div>';
            $html .= '<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;"><tr>' . $tierRows . '</tr></table>';
        }

        // Subtotal
        $html .= '<div style="border-top:2px solid ' . $primaryColor . ';padding-top:12px;margin-bottom:20px;display:flex;justify-content:space-between;">';
        $html .= '<table width="100%"><tr><td style="font-size:14px;font-weight:700;color:' . $primaryColor . ';">Base Subtotal</td>';
        $html .= '<td style="text-align:right;font-size:14px;font-weight:700;color:' . $primaryColor . ';">' . $currency . number_format($subtotal) . '</td></tr></table>';
        $html .= '</div>';

        // Details
        if ($details !== '') {
            $html .= '<div style="margin-bottom:20px;padding:12px 14px;border-left:4px solid ' . $accentColor . ';background:' . $primaryColor . '08;">';
            $html .= '<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.06em;color:' . $accentColor . ';margin-bottom:4px;">Project Details</div>';
            $html .= '<div style="font-size:13px;line-height:1.5;color:' . $textColor . ';">' . nl2br(htmlspecialchars($details)) . '</div>';
            $html .= '</div>';
        }

        // Contact info (shown in both emails)
        if ($contactRows) {
            $html .= '<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;color:' . $accentColor . ';margin-bottom:6px;">Contact Information</div>';
            $html .= '<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:20px;">' . $contactRows . '</table>';
        }

        if ($isClient) {
            $html .= '<p style="font-size:12px;color:' . $textColor . ';opacity:0.7;line-height:1.5;margin:0;">This is an automated estimate. A team member will review your submission and follow up with a formal quote via email.</p>';
        }

        $html .= '</td></tr>';

        // Footer
        $html .= '<tr><td style="background:' . $headerBg . ';padding:14px 24px;text-align:center;border-top:2px solid ' . $primaryColor . ';">';
        $html .= '<div style="font-size:11px;color:' . $headerText . ';opacity:0.6;">&copy; ' . date('Y') . ' ' . htmlspecialchars($formName) . '</div>';
        $html .= '</td></tr>';

        $html .= '</table></td></tr></table></body></html>';
        return $html;
    };

    $clientBody = $emailBody('Hello ' . $userName . ', thank you for your interest. Here is a summary of the estimate you requested:', true);
    $adminBody  = $emailBody('A new quote request has been submitted by ' . $userName . '. Details below:', false);

    $fromAddr = defined('MAIL_FROM') ? MAIL_FROM : 'noreply@example.com';
    $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : $formName;
    $adminTo  = defined('MAIL_TO') ? MAIL_TO : '';
    $sendToAdmin = defined('SEND_EMAIL_TO_ADMIN') ? SEND_EMAIL_TO_ADMIN : true;
    $sendToUser  = defined('SEND_EMAIL_TO_USER') ? SEND_EMAIL_TO_USER : true;

    $anySent = false;
    $errors  = [];

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->CharSet  = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->XMailer  = ' '; // suppress X-Mailer header

        $mode = strtolower(defined('MAIL_MODE') ? MAIL_MODE : 'server');

        if ($mode === 'ssl' || $mode === 'smtp' || $mode === 'tls') {
            $mail->isSMTP();
            $mail->Host       = defined('SMTP_HOST')     ? SMTP_HOST     : '';
            $mail->Port       = defined('SMTP_PORT')     ? (int) SMTP_PORT : 465;
            $mail->SMTPAuth   = true;
            $mail->Username   = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
            $mail->Password   = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
            $mail->Timeout    = 15;

            // ssl / smtp  = implicit SSL on port 465
            // tls         = STARTTLS on port 587
            if ($mode === 'tls') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                if ($mail->Port === 465) { $mail->Port = 587; } // auto-fix common misconfiguration
            } else {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                if ($mail->Port === 587) { $mail->Port = 465; } // auto-fix common misconfiguration
            }

            // Log SMTP conversation to error_log when in development
            if (defined('APP_ENV') && APP_ENV === 'development') {
                $mail->SMTPDebug  = 2;
                $mail->Debugoutput = function ($str, $level) {
                    error_log('XCM SMTP [' . $level . ']: ' . trim($str));
                };
            }

            // Validate SMTP credentials are actually set
            if (empty($mail->Host) || empty($mail->Username) || empty($mail->Password)) {
                $msg = 'SMTP credentials incomplete (host/username/password). Check config/settings.php.';
                error_log('XCM Quote: ' . $msg);
                $errors[] = $msg;
                return false;
            }
        } else {
            // 'server' mode -- try sendmail first, fall back to mail()
            $sendmail = ini_get('sendmail_path');
            if (!empty($sendmail) && $sendmail !== '/dev/null') {
                $mail->isSendmail();
            } else {
                $mail->isMail();
            }
        }

        // 1. Send to business/developer
        if ($sendToAdmin && $adminTo !== '') {
            try {
                $mail->setFrom($fromAddr, $fromName);
                $mail->addAddress($adminTo);
                if ($userEmail) {
                    $mail->addReplyTo($userEmail, $userName);
                }
                $mail->isHTML(true);
                $mail->Subject = 'New Quote Request - ' . $userName;
                $mail->Body    = $adminBody;
                $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $adminBody));
                $mail->send();
                $anySent = true;
            } catch (\Exception $e) {
                $msg = 'Admin email failed: ' . $e->getMessage();
                error_log('XCM Quote: ' . $msg);
                $errors[] = $msg;
            }
            $mail->clearAddresses();
            $mail->clearReplyTos();
        }

        // 2. Send to user (confirmation)
        if ($sendToUser && $userEmail) {
            try {
                $mail->setFrom($fromAddr, $fromName);
                $mail->addAddress($userEmail, $userName);
                $mail->isHTML(true);
                $mail->Subject = 'Your Estimate - ' . htmlspecialchars($formName);
                $mail->Body    = $clientBody;
                $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $clientBody));
                $mail->send();
                $anySent = true;
            } catch (\Exception $e) {
                $msg = 'User email failed: ' . $e->getMessage();
                error_log('XCM Quote: ' . $msg);
                $errors[] = $msg;
            }
        }

        if (!$anySent && !empty($errors)) {
            error_log('XCM Quote: all emails failed. Errors: ' . implode(' | ', $errors));
        }

        return $anySent;
    } catch (\Exception $e) {
        error_log('XCM Quote Email Error: ' . $e->getMessage());
        return false;
    }
}
// ── End form submission handler ───────────────────────────────────────────────

$sty   = $form['style']    ?? [];
$lang  = $form['language']  ?? [];
$tiers = $form['tiers']     ?? [
    ['name' => 'Basic',    'multiplier' => 0.9, 'description' => 'Essential features only'],
    ['name' => 'Standard', 'multiplier' => 1.0, 'description' => 'Recommended for most projects'],
    ['name' => 'Premium',  'multiplier' => 1.3, 'description' => 'Full-service with priority support'],
];

$fonts = [
    'system'    => "-apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
    'mono'      => "'SFMono-Regular', 'Consolas', 'Liberation Mono', monospace",
    'serif'     => "Georgia, 'Times New Roman', serif",
    'inter'     => "'Inter', 'Helvetica Neue', Arial, sans-serif",
    'trebuchet' => "'Trebuchet MS', 'Lucida Grande', sans-serif",
];

$fontFamily   = $fonts[$sty['font'] ?? 'system'] ?? $fonts['system'];
$primaryColor = htmlspecialchars($sty['primaryColor'] ?? '#244c47');
$accentColor  = htmlspecialchars($sty['accentColor']  ?? '#459289');
$bgColor      = htmlspecialchars($sty['bgColor']      ?? '#fcfdfd');
$textColor    = htmlspecialchars($sty['textColor']     ?? '#182523');
$headerBg     = htmlspecialchars($sty['headerBg']      ?? '#244c47');
$headerText   = htmlspecialchars($sty['headerText']    ?? '#eaf5f4');
$fontSize     = (int) ($sty['fontSize'] ?? 16);

$headerTitle  = htmlspecialchars($lang['headerTitle']    ?? $form['name'] ?? 'Request a Quote');
$headerSub    = htmlspecialchars($lang['headerSubtitle'] ?? '');
$svcTitle     = htmlspecialchars($lang['serviceStepTitle']    ?? 'Tell us about your project');
$svcDesc      = htmlspecialchars($lang['serviceStepDesc']     ?? 'Select the primary service type.');
$cplxTitle    = htmlspecialchars($lang['complexityStepTitle'] ?? 'Project Complexity');
$cplxDesc     = htmlspecialchars($lang['complexityStepDesc']  ?? 'How complex is your project?');
$addonTitle   = htmlspecialchars($lang['addonStepTitle']      ?? 'Add-On Services');
$addonDesc    = htmlspecialchars($lang['addonStepDesc']       ?? 'Select any additional services.');
$contactTitle = htmlspecialchars($lang['contactStepTitle']    ?? 'Contact Information');
$contactDesc  = htmlspecialchars($lang['contactStepDesc']     ?? 'How can we reach you?');
$nextLabel    = htmlspecialchars($lang['nextLabel']    ?? 'Next');
$backLabel    = htmlspecialchars($lang['backLabel']    ?? 'Back');
$submitLabel  = htmlspecialchars($lang['submitLabel']  ?? 'Get Estimate');
$resultHead   = htmlspecialchars($lang['resultHeading'] ?? 'Your Estimate');
$resultDescT  = htmlspecialchars($lang['resultDesc']    ?? 'Based on your selections, here are your pricing options.');
$resultDisclaimer = htmlspecialchars($lang['resultDisclaimer'] ?? 'Please note: these figures are ballpark estimates generated by our pricing tool. Once a developer reviews your submission, you will receive a detailed quote via email within 1 to 7 business days depending on project scope.');
$detailsLabel       = htmlspecialchars(($lang['detailsLabel'] ?? '') ?: 'Describe Your Project');
$detailsPlaceholder = htmlspecialchars(($lang['detailsPlaceholder'] ?? '') ?: 'Tell us about your project goals, features you need, deadlines, or other requirements.');
$currency     = htmlspecialchars($lang['currency'] ?? '$');
$showBreak    = ($form['showBreakdown'] ?? true) ? 'true' : 'false';

$backLinkUrl    = htmlspecialchars(trim($lang['backLinkUrl'] ?? ''));
$backLinkLabel  = htmlspecialchars(trim($lang['backLinkLabel'] ?? ''));

$videoUrl       = trim($form['videoUrl'] ?? '');
$introHeading   = htmlspecialchars($lang['introHeading']    ?? 'Welcome');
$introText      = htmlspecialchars($lang['introText']       ?? '');
$introButton    = htmlspecialchars($lang['introButtonLabel'] ?? 'Get Started');
$hasIntro       = ($videoUrl !== '' || ($lang['introHeading'] ?? '') !== '' || ($lang['introText'] ?? '') !== '');

// Convert YouTube / Vimeo URLs to embeddable form
$embedUrl = '';
if ($videoUrl !== '') {
    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w\-]+)/', $videoUrl, $m)) {
        $embedUrl = 'https://www.youtube.com/embed/' . $m[1];
    } elseif (preg_match('/vimeo\.com\/(\d+)/', $videoUrl, $m)) {
        $embedUrl = 'https://player.vimeo.com/video/' . $m[1];
    } else {
        $embedUrl = $videoUrl; // assume already an embed URL
    }
}

$services   = $form['services']   ?? [];
$complexity = $form['complexity']  ?? [];
$addons     = $form['addons']      ?? [];
$contact    = $form['contact']     ?? [];
$totalSteps = 4;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $isBuilderPreview ? 'Preview: ' : '' ?><?= htmlspecialchars($form['name'] ?? 'Quote Form') ?></title>
<link rel="icon" type="image/png" href="assets/favicon.png">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: <?= $fontSize ?>px; font-family: <?= $fontFamily ?>; color: <?= $textColor ?>; background: <?= $bgColor ?>; }
body { min-height: 100vh; display: flex; flex-direction: column; }

.preview-bar { background: #111; color: #ccc; font-family: monospace; font-size: 12px; padding: 0.4rem 1rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.preview-bar a { color: #90c8c5; text-decoration: none; font-weight: 600; }
.preview-bar a:hover { color: #fff; }

.pv-header { background: <?= $headerBg ?>; color: <?= $headerText ?>; padding: 1rem 1.5rem; border-bottom: 3px solid <?= $primaryColor ?>; }
.pv-header-title { font-size: 1.3rem; font-weight: 700; }
.pv-header-sub { font-size: 0.85rem; opacity: 0.8; margin-top: 0.2rem; }

.pv-main { flex: 1; padding: 2.5rem 1.5rem; }
.pv-container { max-width: 640px; margin: 0 auto; }

.pv-progress-wrap { height: 6px; background: <?= $accentColor ?>30; border: 1px solid <?= $accentColor ?>50; margin-bottom: 0.5rem; overflow: hidden; }
.pv-progress { height: 100%; background: <?= $primaryColor ?>; transition: width 0.4s; }
.pv-step-ind { font-size: 0.75rem; color: <?= $accentColor ?>; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 600; margin-bottom: 1.2rem; }

.pv-step-block { background: #fff; border: 1px solid <?= $accentColor ?>60; padding: 2rem; }
.pv-step-title { font-size: 1.35rem; font-weight: 700; color: <?= $textColor ?>; margin-bottom: 0.35rem; }
.pv-step-desc { font-size: 0.9rem; color: <?= $accentColor ?>; margin-bottom: 1.5rem; }
.pv-options { display: flex; flex-direction: column; gap: 0.5rem; }

.pv-radio-opt, .pv-check-opt { display: flex; align-items: flex-start; gap: 0.55rem; cursor: pointer; padding: 0.6rem 0.8rem; border: 1px solid <?= $accentColor ?>50; transition: background 0.15s; }
.pv-radio-opt:hover, .pv-check-opt:hover { background: <?= $primaryColor ?>10; }
.pv-radio-opt input, .pv-check-opt input { accent-color: <?= $primaryColor ?>; margin-top: 3px; flex-shrink: 0; }
.pv-opt-info { flex: 1; }
.pv-opt-label { font-size: 0.9rem; color: <?= $textColor ?>; }
.pv-opt-sub { font-size: 0.75rem; color: <?= $accentColor ?>; margin-top: 0.1rem; }
.pv-opt-cost { font-size: 0.82rem; font-weight: 700; color: <?= $primaryColor ?>; white-space: nowrap; flex-shrink: 0; align-self: center; }

.pv-fields { display: flex; flex-direction: column; gap: 1.2rem; }
.pv-field-group { display: flex; flex-direction: column; gap: 0.4rem; }
.pv-label { font-size: 0.88rem; font-weight: 600; color: <?= $textColor ?>; }
.pv-req { color: #c0392b; margin-left: 0.2rem; }
.pv-input, .pv-select { width: 100%; padding: 0.6rem 0.8rem; font-size: 1rem; font-family: inherit; color: <?= $textColor ?>; background: <?= $bgColor ?>; border: 1px solid <?= $accentColor ?>; outline: none; }
.pv-input:focus, .pv-select:focus { border-color: <?= $primaryColor ?>; box-shadow: 0 0 0 3px <?= $primaryColor ?>20; }

.pv-actions { display: flex; gap: 0.75rem; margin-top: 1.5rem; }
.pv-btn { padding: 0.65rem 1.5rem; font-size: 0.92rem; font-family: inherit; font-weight: 700; cursor: pointer; border: 2px solid transparent; letter-spacing: 0.02em; }
.pv-btn-primary { background: <?= $primaryColor ?>; color: <?= $headerText ?>; border-color: <?= $primaryColor ?>; }
.pv-btn-primary:hover { opacity: 0.85; }
.pv-btn-secondary { background: transparent; color: <?= $primaryColor ?>; border-color: <?= $primaryColor ?>; }
.pv-btn-secondary:hover { background: <?= $primaryColor ?>15; }

.pv-dots { display: flex; gap: 0.5rem; justify-content: center; margin-top: 1.5rem; }
.pv-dot { width: 8px; height: 8px; background: <?= $accentColor ?>40; cursor: pointer; transition: background 0.2s; }
.pv-dot.active { background: <?= $primaryColor ?>; }

.pv-footer { background: <?= $headerBg ?>; color: <?= $headerText ?>99; padding: 1rem 1.5rem; text-align: center; font-size: 0.78rem; border-top: 2px solid <?= $primaryColor ?>; }

/* result styles */
.pv-result-heading { font-size: 1.4rem; font-weight: 700; color: <?= $textColor ?>; margin-bottom: 0.3rem; }
.pv-result-desc { font-size: 0.88rem; color: <?= $accentColor ?>; margin-bottom: 1.5rem; }
.pv-tiers-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
.pv-tier-card { border: 2px solid <?= $accentColor ?>40; padding: 1.5rem 1rem; text-align: center; background: #fff; }
.pv-tier-card.featured { border-color: <?= $primaryColor ?>; box-shadow: 0 2px 12px <?= $primaryColor ?>15; }
.pv-tier-name { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: <?= $accentColor ?>; margin-bottom: 0.6rem; }
.pv-tier-price { font-size: 2rem; font-weight: 700; color: <?= $primaryColor ?>; margin-bottom: 0.35rem; }
.pv-tier-mult { font-size: 0.7rem; color: <?= $accentColor ?>; margin-bottom: 0.5rem; }
.pv-tier-desc { font-size: 0.78rem; color: <?= $textColor ?>; opacity: 0.7; }
.pv-breakdown { margin-top: 1.5rem; border-top: 1px solid <?= $accentColor ?>30; padding-top: 1rem; }
.pv-breakdown-title { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: <?= $accentColor ?>; margin-bottom: 0.5rem; }
.pv-breakdown-row { display: flex; justify-content: space-between; padding: 0.35rem 0; font-size: 0.82rem; color: <?= $textColor ?>; }
.pv-breakdown-label { flex: 1; }
.pv-breakdown-val { font-weight: 700; color: <?= $primaryColor ?>; }
.pv-breakdown-total { display: flex; justify-content: space-between; padding: 0.6rem 0; margin-top: 0.3rem; border-top: 2px solid <?= $primaryColor ?>; font-weight: 700; font-size: 0.95rem; color: <?= $primaryColor ?>; }

/* disclaimer notice */
.pv-disclaimer { margin-top: 1.5rem; padding: 1rem 1.2rem; border-left: 4px solid <?= $accentColor ?>; background: <?= $primaryColor ?>08; font-size: 0.8rem; line-height: 1.6; color: <?= $textColor ?>; }
.pv-disclaimer-title { font-size: 0.72rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: <?= $accentColor ?>; margin-bottom: 0.35rem; }

/* intro / video */
.pv-intro-block { background: #fff; border: 1px solid <?= $accentColor ?>60; padding: 2.5rem 2rem; text-align: center; }
.pv-intro-heading { font-size: 1.5rem; font-weight: 700; color: <?= $textColor ?>; margin-bottom: 0.5rem; }
.pv-intro-text { font-size: 0.92rem; color: <?= $accentColor ?>; margin-bottom: 1.5rem; line-height: 1.5; }
.pv-video-wrap { position: relative; width: 100%; padding-bottom: 56.25%; margin-bottom: 1.5rem; background: <?= $textColor ?>10; }
.pv-video-wrap iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none; }
.pv-intro-btn { display: inline-block; padding: 0.75rem 2rem; font-size: 1rem; font-family: inherit; font-weight: 700; cursor: pointer; border: 2px solid <?= $primaryColor ?>; background: <?= $primaryColor ?>; color: <?= $headerText ?>; letter-spacing: 0.02em; }
.pv-intro-btn:hover { opacity: 0.85; }

/* help system */
.pv-help-toggle { display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px; font-size: 11px; font-weight: 700; color: <?= $accentColor ?>; border: 1px solid <?= $accentColor ?>60; background: <?= $bgColor ?>; cursor: pointer; flex-shrink: 0; margin-left: 0.35rem; vertical-align: middle; font-family: inherit; line-height: 1; padding: 0; }
.pv-help-toggle:hover { background: <?= $primaryColor ?>15; border-color: <?= $primaryColor ?>; color: <?= $primaryColor ?>; }
.pv-help-body { display: none; padding: 0.6rem 0.8rem; margin-top: 0.35rem; font-size: 0.78rem; line-height: 1.5; color: <?= $textColor ?>; background: <?= $primaryColor ?>08; border-left: 3px solid <?= $accentColor ?>; }
.pv-help-body.open { display: block; }

/* validation */
.pv-validation-msg { padding: 0.6rem 0.8rem; margin-top: 0.75rem; font-size: 0.82rem; color: #9b2c2c; background: #fff5f5; border-left: 3px solid #c0392b; display: none; }
.pv-validation-msg.visible { display: block; }
.pv-confirm-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); display: flex; align-items: center; justify-content: center; z-index: 9999; }
.pv-confirm-box { background: #fff; max-width: 380px; width: 90%; padding: 2rem; text-align: center; }
.pv-confirm-box p { font-size: 0.92rem; margin-bottom: 1.2rem; line-height: 1.5; color: <?= $textColor ?>; }
.pv-confirm-actions { display: flex; gap: 0.75rem; justify-content: center; }
.pv-optional-tag { font-weight: 400; font-size: 0.78rem; color: <?= $accentColor ?>; margin-left: 0.3rem; }

/* back-link navigation bar */
.pv-backlink { background: <?= $textColor ?>; padding: 0.5rem 1.5rem; }
.pv-backlink a { color: <?= $bgColor ?>; font-size: 0.8rem; text-decoration: none; display: inline-flex; align-items: center; gap: 0.4rem; font-weight: 500; opacity: 0.85; }
.pv-backlink a:hover { opacity: 1; }
.pv-backlink-arrow { font-size: 0.95rem; line-height: 1; }
</style>
</head>
<body>

<?php if ($isBuilderPreview): ?>
<div class="preview-bar">
    <span>PREVIEW | <?= htmlspecialchars($form['name'] ?? 'Quote Form') ?></span>
    <a href="/form-builder?edit=<?= htmlspecialchars(urlencode($form['id'])) ?>">&larr; Back to Editor</a>
</div>
<?php endif; ?>

<?php if ($backLinkUrl !== ''): ?>
<nav class="pv-backlink">
    <a href="<?= $backLinkUrl ?>">
        <span class="pv-backlink-arrow">&larr;</span>
        <?= $backLinkLabel !== '' ? $backLinkLabel : 'Back' ?>
    </a>
</nav>
<?php endif; ?>

<header class="pv-header">
    <div class="pv-header-title"><?= $headerTitle ?></div>
    <?php if ($headerSub): ?><div class="pv-header-sub"><?= $headerSub ?></div><?php endif; ?>
</header>

<main class="pv-main">
<div class="pv-container" id="pv-app">

<?php if ($hasIntro): ?>
<!-- INTRO -->
<div id="pv-intro">
    <div class="pv-intro-block">
        <?php if ($introHeading): ?><h2 class="pv-intro-heading"><?= $introHeading ?></h2><?php endif; ?>
        <?php if ($introText): ?><p class="pv-intro-text"><?= $introText ?></p><?php endif; ?>
        <?php if ($embedUrl): ?>
        <div class="pv-video-wrap">
            <iframe src="<?= htmlspecialchars($embedUrl) ?>" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
        </div>
        <?php endif; ?>
        <button class="pv-intro-btn" onclick="pvDismissIntro()"><?= $introButton ?></button>
    </div>
</div>
<?php endif; ?>

<!-- STEP 1: Services -->
<div class="pv-step" id="pv-step-0"<?php if ($hasIntro): ?> style="display:none;"<?php endif; ?>>
    <div class="pv-progress-wrap"><div class="pv-progress" style="width:25%"></div></div>
    <p class="pv-step-ind">Step 1 of <?= $totalSteps ?></p>
    <div class="pv-step-block">
        <h2 class="pv-step-title"><?= $svcTitle ?></h2>
        <p class="pv-step-desc"><?= $svcDesc ?></p>
        <div class="pv-options">
        <?php foreach ($services as $si => $svc): ?>
            <label class="pv-radio-opt">
                <input type="radio" name="pv-service" data-price="<?= (float)($svc['price'] ?? 0) ?>" data-label="<?= htmlspecialchars($svc['label'] ?? '') ?>">
                <span class="pv-opt-info">
                    <span class="pv-opt-label"><?= htmlspecialchars($svc['label'] ?? '') ?><?php if (!empty($svc['help'])): ?><button type="button" class="pv-help-toggle" onclick="event.preventDefault();pvToggleHelp('svc-<?= $si ?>')">?</button><?php endif; ?></span>
                    <?php if (!empty($svc['help'])): ?><div class="pv-help-body" id="pv-help-svc-<?= $si ?>"><?= htmlspecialchars($svc['help']) ?></div><?php endif; ?>
                </span>
                <span class="pv-opt-cost"><?= $currency . number_format((float)($svc['price'] ?? 0), 0) ?></span>
            </label>
        <?php endforeach; ?>
        </div>
        <div class="pv-actions">
            <button class="pv-btn pv-btn-primary" onclick="pvGo(1)"><?= $nextLabel ?></button>
        </div>
    </div>
    <div class="pv-dots"><?php for ($i=0;$i<$totalSteps;$i++): ?><div class="pv-dot<?= $i===0?' active':'' ?>" onclick="pvGo(<?=$i?>)"></div><?php endfor; ?></div>
</div>

<!-- STEP 2: Complexity -->
<div class="pv-step" id="pv-step-1" style="display:none;">
    <div class="pv-progress-wrap"><div class="pv-progress" style="width:50%"></div></div>
    <p class="pv-step-ind">Step 2 of <?= $totalSteps ?></p>
    <div class="pv-step-block">
        <h2 class="pv-step-title"><?= $cplxTitle ?></h2>
        <p class="pv-step-desc"><?= $cplxDesc ?></p>
        <div class="pv-options">
        <?php foreach ($complexity as $ci => $c): ?>
            <label class="pv-radio-opt">
                <input type="radio" name="pv-complexity" data-multiplier="<?= (float)($c['multiplier'] ?? 1) ?>" data-label="<?= htmlspecialchars($c['label'] ?? '') ?>">
                <span class="pv-opt-info">
                    <span class="pv-opt-label"><?= htmlspecialchars($c['label'] ?? '') ?><?php if (!empty($c['help'])): ?><button type="button" class="pv-help-toggle" onclick="event.preventDefault();pvToggleHelp('cplx-<?= $ci ?>')">?</button><?php endif; ?></span>
                    <?php if (!empty($c['description'])): ?><div class="pv-opt-sub"><?= htmlspecialchars($c['description']) ?></div><?php endif; ?>
                    <?php if (!empty($c['help'])): ?><div class="pv-help-body" id="pv-help-cplx-<?= $ci ?>"><?= htmlspecialchars($c['help']) ?></div><?php endif; ?>
                </span>
                <span class="pv-opt-cost"><?= (float)($c['multiplier'] ?? 1) ?>x</span>
            </label>
        <?php endforeach; ?>
        </div>
        <div class="pv-actions">
            <button class="pv-btn pv-btn-secondary" onclick="pvGo(0)"><?= $backLabel ?></button>
            <button class="pv-btn pv-btn-primary" onclick="pvGo(2)"><?= $nextLabel ?></button>
        </div>
    </div>
    <div class="pv-dots"><?php for ($i=0;$i<$totalSteps;$i++): ?><div class="pv-dot<?= $i===1?' active':'' ?>" onclick="pvGo(<?=$i?>)"></div><?php endfor; ?></div>
</div>

<!-- STEP 3: Add-Ons -->
<div class="pv-step" id="pv-step-2" style="display:none;">
    <div class="pv-progress-wrap"><div class="pv-progress" style="width:75%"></div></div>
    <p class="pv-step-ind">Step 3 of <?= $totalSteps ?></p>
    <div class="pv-step-block">
        <h2 class="pv-step-title"><?= $addonTitle ?></h2>
        <p class="pv-step-desc"><?= $addonDesc ?></p>
        <div class="pv-options">
        <?php if (empty($addons)): ?>
            <p style="color:<?= $accentColor ?>;font-size:0.88rem;">No add-on services available.</p>
        <?php else: ?>
        <?php foreach ($addons as $ai => $a): ?>
            <label class="pv-check-opt">
                <input type="checkbox" name="pv-addon" data-price="<?= (float)($a['price'] ?? 0) ?>" data-label="<?= htmlspecialchars($a['label'] ?? '') ?>">
                <span class="pv-opt-info">
                    <span class="pv-opt-label"><?= htmlspecialchars($a['label'] ?? '') ?><?php if (!empty($a['help'])): ?><button type="button" class="pv-help-toggle" onclick="event.preventDefault();pvToggleHelp('addon-<?= $ai ?>')">?</button><?php endif; ?></span>
                    <?php if (!empty($a['help'])): ?><div class="pv-help-body" id="pv-help-addon-<?= $ai ?>"><?= htmlspecialchars($a['help']) ?></div><?php endif; ?>
                </span>
                <span class="pv-opt-cost">+<?= $currency . number_format((float)($a['price'] ?? 0), 0) ?></span>
            </label>
        <?php endforeach; ?>
        <?php endif; ?>
        </div>
        <div class="pv-actions">
            <button class="pv-btn pv-btn-secondary" onclick="pvGo(1)"><?= $backLabel ?></button>
            <button class="pv-btn pv-btn-primary" onclick="pvGo(3)"><?= $nextLabel ?></button>
        </div>
    </div>
    <div class="pv-dots"><?php for ($i=0;$i<$totalSteps;$i++): ?><div class="pv-dot<?= $i===2?' active':'' ?>" onclick="pvGo(<?=$i?>)"></div><?php endfor; ?></div>
</div>

<!-- STEP 4: Contact -->
<div class="pv-step" id="pv-step-3" style="display:none;">
    <div class="pv-progress-wrap"><div class="pv-progress" style="width:100%"></div></div>
    <p class="pv-step-ind">Step 4 of <?= $totalSteps ?></p>
    <div class="pv-step-block">
        <h2 class="pv-step-title"><?= $contactTitle ?></h2>
        <p class="pv-step-desc"><?= $contactDesc ?></p>
        <div class="pv-fields">
        <?php foreach ($contact as $f): ?>
            <div class="pv-field-group">
                <label class="pv-label">
                    <?= htmlspecialchars($f['label'] ?? 'Field') ?>
                    <?php if (!empty($f['required'])): ?><span class="pv-req">*</span><?php endif; ?>
                </label>
                <?php if (($f['type'] ?? 'text') === 'select' && !empty($f['options'])): ?>
                    <select class="pv-select" data-key="<?= htmlspecialchars($f['key'] ?? '') ?>"<?php if (!empty($f['required'])): ?> data-required="1" data-label="<?= htmlspecialchars($f['label'] ?? 'Field') ?>"<?php endif; ?>>
                        <option value="">-- Select --</option>
                        <?php foreach ($f['options'] as $opt): ?>
                        <option><?= htmlspecialchars($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input class="pv-input" type="<?= htmlspecialchars($f['type'] ?? 'text') ?>" placeholder="" data-key="<?= htmlspecialchars($f['key'] ?? '') ?>"<?php if (!empty($f['required'])): ?> data-required="1" data-label="<?= htmlspecialchars($f['label'] ?? 'Field') ?>"<?php endif; ?>>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
        <div class="pv-field-group" style="margin-top:1.5rem;">
            <label class="pv-label"><?= $detailsLabel ?><span class="pv-optional-tag">(optional)</span></label>
            <textarea class="pv-input" id="pv-details" rows="4" placeholder="<?= $detailsPlaceholder ?>" style="resize:vertical;min-height:80px;font-family:inherit;"></textarea>
        </div>
        <div class="pv-actions">
            <button class="pv-btn pv-btn-secondary" onclick="pvGo(2)"><?= $backLabel ?></button>
            <button class="pv-btn pv-btn-primary" onclick="pvSubmit()"><?= $submitLabel ?></button>
        </div>
    </div>
    <div class="pv-dots"><?php for ($i=0;$i<$totalSteps;$i++): ?><div class="pv-dot<?= $i===3?' active':'' ?>" onclick="pvGo(<?=$i?>)"></div><?php endfor; ?></div>
</div>

<!-- Result -->
<div id="pv-done" style="display:none;"></div>

</div>
</main>

<footer class="pv-footer"><?= $isBuilderPreview ? 'Preview only -- no data is collected.' : '&copy; ' . date('Y') . ' ' . htmlspecialchars($form['name'] ?? '') ?></footer>

<script>
(function(){
    var total       = <?= $totalSteps ?>;
    var cur         = 0;
    var currency    = <?= json_encode($currency) ?>;
    var tiers       = <?= json_encode(array_values($tiers)) ?>;
    var resultHead  = <?= json_encode($resultHead) ?>;
    var resultDescT = <?= json_encode($resultDescT) ?>;
    var resultDisclaimer = <?= json_encode($resultDisclaimer) ?>;
    var showBreak   = <?= $showBreak ?>;
    var hasIntro    = <?= $hasIntro ? 'true' : 'false' ?>;

    function show(n) {
        var intro = document.getElementById('pv-intro');
        if (intro) intro.style.display = 'none';
        for (var i = 0; i < total; i++) {
            var el = document.getElementById('pv-step-' + i);
            if (el) el.style.display = i === n ? '' : 'none';
        }
        document.getElementById('pv-done').style.display = 'none';
        cur = n;
    }

    function fmtCost(n) {
        return currency + Math.round(n).toLocaleString();
    }

    function calcCost() {
        var svcEl  = document.querySelector('input[name="pv-service"]:checked');
        var cplxEl = document.querySelector('input[name="pv-complexity"]:checked');
        var base       = svcEl  ? parseFloat(svcEl.dataset.price)      || 0 : 0;
        var multiplier = cplxEl ? parseFloat(cplxEl.dataset.multiplier) || 1 : 1;
        var svcName    = svcEl  ? svcEl.dataset.label  : '';
        var cplxName   = cplxEl ? cplxEl.dataset.label : '';

        var addonTotal = 0;
        var addonItems = [];
        document.querySelectorAll('input[name="pv-addon"]:checked').forEach(function(el) {
            var p = parseFloat(el.dataset.price) || 0;
            addonTotal += p;
            addonItems.push({ label: el.dataset.label, price: p });
        });

        var subtotal = (base * multiplier) + addonTotal;

        return {
            base: base,
            multiplier: multiplier,
            addonTotal: addonTotal,
            subtotal: subtotal,
            serviceName: svcName,
            complexityName: cplxName,
            addons: addonItems
        };
    }

    function renderResult(r, emailMsg) {
        var el = document.getElementById('pv-done');
        var html = '<div class="pv-step-block" style="padding:2.5rem 2rem;">';
        html += '<h2 class="pv-result-heading">' + resultHead + '</h2>';
        html += '<p class="pv-result-desc">' + resultDescT + '</p>';

        if (r.subtotal === 0) {
            html += '<p style="text-align:center;color:<?= $accentColor ?>;padding:1.5rem 0;">No service was selected. Go back and make your selections.</p>';
        } else {
            html += '<div class="pv-tiers-grid">';
            tiers.forEach(function(tier, ti) {
                var tierCost = Math.round(r.subtotal * (tier.multiplier || 1));
                var featured = tiers.length >= 3 && ti === Math.floor(tiers.length / 2);
                html += '<div class="pv-tier-card' + (featured ? ' featured' : '') + '">';
                html += '<div class="pv-tier-name">' + (tier.name || 'Tier') + '</div>';
                html += '<div class="pv-tier-price">' + fmtCost(tierCost) + '</div>';
                if (tier.multiplier && tier.multiplier !== 1) {
                    html += '<div class="pv-tier-mult">' + tier.multiplier + 'x base</div>';
                } else {
                    html += '<div class="pv-tier-mult">base rate</div>';
                }
                html += '<div class="pv-tier-desc">' + (tier.description || '') + '</div>';
                html += '</div>';
            });
            html += '</div>';

            if (showBreak) {
                html += '<div class="pv-breakdown">';
                html += '<div class="pv-breakdown-title">Cost Breakdown</div>';
                html += '<div class="pv-breakdown-row"><span class="pv-breakdown-label">Service: ' + r.serviceName + '</span><span class="pv-breakdown-val">' + fmtCost(r.base) + '</span></div>';
                html += '<div class="pv-breakdown-row"><span class="pv-breakdown-label">Complexity: ' + r.complexityName + '</span><span class="pv-breakdown-val">' + r.multiplier + 'x</span></div>';
                r.addons.forEach(function(a) {
                    html += '<div class="pv-breakdown-row"><span class="pv-breakdown-label">' + a.label + '</span><span class="pv-breakdown-val">+' + fmtCost(a.price) + '</span></div>';
                });
                html += '<div class="pv-breakdown-total"><span>Subtotal</span><span>' + fmtCost(r.subtotal) + '</span></div>';
                html += '</div>';
            }
        }

        if (resultDisclaimer) {
            html += '<div class="pv-disclaimer">';
            html += '<div class="pv-disclaimer-title">Important Notice</div>';
            html += resultDisclaimer;
            html += '</div>';
        }

        if (emailMsg) {
            html += '<div style="text-align:center;padding:0.8rem;margin-top:1rem;border:1px solid <?= $accentColor ?>40;background:<?= $primaryColor ?>08;font-size:0.82rem;color:<?= $textColor ?>;">' + emailMsg + '</div>';
        }

        html += '<div style="text-align:center;margin-top:1.5rem;">';
<?php if ($isBuilderPreview): ?>
        html += '<p style="font-size:0.75rem;color:<?= $accentColor ?>;margin-bottom:0.8rem;">This is a preview -- no data was sent.</p>';
<?php endif; ?>
        html += '<button class="pv-btn pv-btn-secondary" onclick="pvReset()">Start Over</button>';
        html += '</div></div>';
        el.innerHTML = html;
    }

    var addonSkipConfirmed = false;

    function getOrCreateMsg(stepIdx) {
        var id = 'pv-vmsg-' + stepIdx;
        var el = document.getElementById(id);
        if (!el) {
            var block = document.querySelector('#pv-step-' + stepIdx + ' .pv-step-block');
            if (!block) return null;
            el = document.createElement('div');
            el.id = id;
            el.className = 'pv-validation-msg';
            var actions = block.querySelector('.pv-actions');
            if (actions) block.insertBefore(el, actions);
            else block.appendChild(el);
        }
        return el;
    }

    function showVMsg(stepIdx, text) {
        var el = getOrCreateMsg(stepIdx);
        if (el) { el.textContent = text; el.classList.add('visible'); }
    }

    function hideVMsg(stepIdx) {
        var el = getOrCreateMsg(stepIdx);
        if (el) { el.textContent = ''; el.classList.remove('visible'); }
    }

    function validateStep(n) {
        hideVMsg(n);
        if (n === 0) {
            if (!document.querySelector('input[name="pv-service"]:checked')) {
                showVMsg(0, 'Please select a service to continue.');
                return false;
            }
        } else if (n === 1) {
            if (!document.querySelector('input[name="pv-complexity"]:checked')) {
                showVMsg(1, 'Please select a complexity level to continue.');
                return false;
            }
        } else if (n === 2) {
            var checked = document.querySelectorAll('input[name="pv-addon"]:checked');
            if (checked.length === 0 && !addonSkipConfirmed) {
                showAddonConfirm();
                return false;
            }
        }
        return true;
    }

    function showAddonConfirm() {
        var overlay = document.createElement('div');
        overlay.className = 'pv-confirm-overlay';
        overlay.id = 'pv-addon-confirm';
        var box = document.createElement('div');
        box.className = 'pv-confirm-box';
        box.innerHTML = '<p>You have not selected any add-on services. Are you sure you want to continue?</p>'
            + '<div class="pv-confirm-actions">'
            + '<button class="pv-btn pv-btn-secondary" onclick="pvConfirmCancel()">Go Back</button>'
            + '<button class="pv-btn pv-btn-primary" onclick="pvConfirmContinue()">Yes, Continue</button>'
            + '</div>';
        overlay.appendChild(box);
        document.body.appendChild(overlay);
    }

    window.pvConfirmContinue = function() {
        var el = document.getElementById('pv-addon-confirm');
        if (el) el.remove();
        addonSkipConfirmed = true;
        show(3);
    };

    window.pvConfirmCancel = function() {
        var el = document.getElementById('pv-addon-confirm');
        if (el) el.remove();
    };

    function validateContact() {
        var fields = document.querySelectorAll('#pv-step-3 [data-required]');
        var missing = [];
        for (var i = 0; i < fields.length; i++) {
            var f = fields[i];
            var val = (f.value || '').trim();
            if (!val) {
                missing.push(f.dataset.label || 'Field');
            } else if (f.type === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
                missing.push((f.dataset.label || 'Email') + ' (invalid format)');
            }
        }
        if (missing.length > 0) {
            showVMsg(3, 'Please fill in: ' + missing.join(', '));
            return false;
        }
        hideVMsg(3);
        return true;
    }

    window.pvGo = function(n) {
        if (n < 0 || n >= total) return;
        if (n > cur) {
            for (var s = cur; s < n; s++) {
                if (!validateStep(s)) return;
            }
        }
        if (n <= 2) addonSkipConfirmed = false;
        show(n);
    };

    var isPreview = <?= $isBuilderPreview ? 'true' : 'false' ?>;

    function collectContact() {
        var data = {};
        document.querySelectorAll('#pv-step-3 .pv-input[data-key], #pv-step-3 .pv-select[data-key]').forEach(function(el) {
            var key = el.dataset.key;
            if (key) data[key] = (el.value || '').trim();
        });
        return data;
    }

    function showSubmitting() {
        for (var i = 0; i < total; i++) {
            var el = document.getElementById('pv-step-' + i);
            if (el) el.style.display = 'none';
        }
        var done = document.getElementById('pv-done');
        done.innerHTML = '<div class="pv-step-block" style="padding:2.5rem 2rem;text-align:center;"><p style="font-size:0.92rem;color:<?= $accentColor ?>;margin:1.5rem 0;">Submitting your request...</p></div>';
        done.style.display = '';
    }

    window.pvSubmit = function() {
        if (!validateContact()) return;
        var result = calcCost();
        var contactData = collectContact();
        var details = (document.getElementById('pv-details') || {}).value || '';

        var tierData = [];
        tiers.forEach(function(t) {
            tierData.push({
                name: t.name || 'Tier',
                price: Math.round(result.subtotal * (t.multiplier || 1)),
                multiplier: t.multiplier || 1,
                description: t.description || ''
            });
        });

        if (isPreview) {
            for (var i = 0; i < total; i++) {
                var el = document.getElementById('pv-step-' + i);
                if (el) el.style.display = 'none';
            }
            renderResult(result);
            document.getElementById('pv-done').style.display = '';
            return;
        }

        showSubmitting();

        var payload = {
            action: 'submit',
            contact: contactData,
            service: result.serviceName,
            complexity: result.complexityName,
            addons: result.addons,
            subtotal: result.subtotal,
            tiers: tierData,
            details: details.trim()
        };

        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            renderResult(result, d.message || '');
            document.getElementById('pv-done').style.display = '';
        })
        .catch(function() {
            renderResult(result, '');
            document.getElementById('pv-done').style.display = '';
        });
    };

    window.pvReset = function() {
        document.querySelectorAll('.pv-input, .pv-select').forEach(function(el) { el.value = ''; });
        document.querySelectorAll('select.pv-select').forEach(function(el) { el.selectedIndex = 0; });
        document.querySelectorAll('input[type=radio], input[type=checkbox]').forEach(function(el) { el.checked = false; });
        document.querySelectorAll('.pv-help-body').forEach(function(el) { el.classList.remove('open'); });
        document.querySelectorAll('.pv-validation-msg').forEach(function(el) { el.classList.remove('visible'); el.textContent = ''; });
        addonSkipConfirmed = false;
        if (hasIntro) {
            for (var i = 0; i < total; i++) {
                var el = document.getElementById('pv-step-' + i);
                if (el) el.style.display = 'none';
            }
            document.getElementById('pv-done').style.display = 'none';
            var intro = document.getElementById('pv-intro');
            if (intro) intro.style.display = '';
        } else {
            show(0);
        }
    };

    window.pvDismissIntro = function() {
        var intro = document.getElementById('pv-intro');
        if (intro) intro.style.display = 'none';
        show(0);
    };

    window.pvToggleHelp = function(id) {
        var el = document.getElementById('pv-help-' + id);
        if (el) el.classList.toggle('open');
    };
}());
</script>
</body>
</html>
