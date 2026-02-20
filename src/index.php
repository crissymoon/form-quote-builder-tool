<?php
declare(strict_types=1);

/**
 * XcaliburMoon Web Development Pricing
 * Multi-step quote estimation tool
 * PHP 8.3+
 */

define('APP_ROOT', __DIR__);
define('CONFIG_PATH', APP_ROOT . '/../config/settings.php');
define('DATA_PATH', APP_ROOT . '/../data/');

if (!file_exists(CONFIG_PATH)) {
    http_response_code(500);
    die('Configuration file not found. Please complete setup.');
}

require_once CONFIG_PATH;
require_once APP_ROOT . '/../config/mailer.php';
require_once APP_ROOT . '/lib/QuoteEngine.php';
require_once APP_ROOT . '/lib/ReferenceID.php';
require_once APP_ROOT . '/lib/FormSteps.php';

session_start();

$step = isset($_GET['step']) ? (int) $_GET['step'] : 1;
$totalSteps = FormSteps::count();
$step = max(1, min($step, $totalSteps + 1));

$action = $_POST['action'] ?? '';

if ($action === 'submit_step' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedStep = (int) ($_POST['current_step'] ?? 1);
    $stepData   = FormSteps::getStep($postedStep);
    $errors     = FormSteps::validate($postedStep, $_POST);

    if (empty($errors)) {
        if (!isset($_SESSION['quote_data'])) {
            $_SESSION['quote_data'] = [];
        }
        foreach ($stepData['fields'] as $field) {
            $key = $field['name'];
            $_SESSION['quote_data'][$key] = $_POST[$key] ?? '';
        }
        $nextStep = $postedStep + 1;
        header('Location: ?step=' . $nextStep);
        exit;
    }
}

if ($action === 'submit_quote' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $quoteData  = $_SESSION['quote_data'] ?? [];
    $engine     = new QuoteEngine($quoteData);
    $estimate   = $engine->calculate();
    $refID      = ReferenceID::generate();
    $record     = [
        'ref_id'     => $refID,
        'created_at' => time(),
        'expires_at' => time() + (REFERENCE_EXPIRY_DAYS * 86400),
        'status'     => 'active',
        'quote_data' => $quoteData,
        'estimate'   => $estimate,
    ];

    $stored = ReferenceID::store($record, DATA_PATH);

    if ($stored) {
        $emailSent = sendQuoteEmail($record);
    }

    $_SESSION['last_quote'] = $record;
    unset($_SESSION['quote_data']);
    header('Location: ?step=result');
    exit;
}

$isResult = ($_GET['step'] ?? '') === 'result';

ob_start();
if ($isResult) {
    require APP_ROOT . '/templates/result.php';
} else {
    require APP_ROOT . '/templates/step.php';
}
$pageContent = ob_get_clean();

require APP_ROOT . '/templates/layout.php';
