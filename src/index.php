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

// Demo mode: renders a pre-populated result view for visual testing
if (isset($_GET['demo'])) {
    $_SESSION['last_quote'] = [
        'ref_id'     => 'XCM-DEMO-EXAMPLE',
        'created_at' => time(),
        'expires_at' => time() + (14 * 86400),
        'status'     => 'active',
        'quote_data' => [
            'service_type'    => 'ai_web_app',
            'project_name'    => 'Example AI-Driven Web Application',
            'complexity'      => 'complex',
            'addons'          => ['seo_advanced', 'api_integration', 'automation'],
            'contact_name'    => 'Jane Smith',
            'contact_email'   => 'jane@example.com',
            'contact_company' => 'Acme Corp',
            'timeline'        => '3-6 months',
        ],
        'estimate'   => [
            'base'        => 9500,
            'multiplier'  => 2.0,
            'addon_total' => 4900,
            'subtotal'    => 23900,
            'range_low'   => 21510,
            'range_high'  => 28680,
            'currency'    => 'USD',
        ],
    ];
    $isResult    = true;
    $step        = 0;
    ob_start();
    require APP_ROOT . '/templates/result.php';
    $pageContent = ob_get_clean();
    require APP_ROOT . '/templates/layout.php';
    exit;
}

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
