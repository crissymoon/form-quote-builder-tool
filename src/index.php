<?php
declare(strict_types=1);

/**
 * XcaliburMoon Web Development Pricing
 * Multi-step quote estimation tool
 * PHP 8.3+
 *
 * When a form has been built and saved in the form builder, the front-end
 * renders that form using the builder preview renderer so the public-facing
 * form always matches what was configured in the builder.
 */

define('APP_ROOT', __DIR__);
define('CONFIG_PATH', APP_ROOT . '/../config/settings.php');
define('DATA_PATH', APP_ROOT . '/../data/');
define('BUILDER_FORMS_PATH', APP_ROOT . '/../data/forms/');

if (!file_exists(CONFIG_PATH)) {
    http_response_code(500);
    die('Configuration file not found. Please complete setup.');
}

require_once CONFIG_PATH;
require_once APP_ROOT . '/../config/mailer.php';
require_once APP_ROOT . '/lib/QuoteEngine.php';
require_once APP_ROOT . '/lib/QuoteValidator.php';
require_once APP_ROOT . '/lib/ReferenceID.php';
require_once APP_ROOT . '/lib/FormSteps.php';

session_start();

// ── Check for a builder-configured form ──────────────────────────────────────
// If the user has built and saved a form in the form builder, render it instead
// of the hardcoded legacy form. This keeps the front-end in sync with the builder.
function load_latest_builder_form(): ?array
{
    if (!is_dir(BUILDER_FORMS_PATH)) return null;
    $files = glob(BUILDER_FORMS_PATH . '*.json') ?: [];
    if (empty($files)) return null;

    $latest     = null;
    $latestTime = 0;
    foreach ($files as $f) {
        $raw = json_decode(file_get_contents($f), true);
        if (!$raw) continue;
        $updated = (int) ($raw['updated_at'] ?? 0);
        if ($updated > $latestTime) {
            $latestTime = $updated;
            $latest     = $raw;
        }
    }
    return $latest;
}

// Render the builder form if one exists (non-demo, non-step-based requests)
$isDemo      = isset($_GET['demo']);
$isStepNav   = isset($_GET['step']);
$isPostAction = ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action']));

if (!$isDemo && !$isStepNav && !$isPostAction) {
    $form = load_latest_builder_form();
    if ($form) {
        require APP_ROOT . '/builder/preview.php';
        exit;
    }
}

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

    $validator  = new QuoteValidator();
    $addons     = $quoteData['addons'] ?? [];
    if (is_string($addons)) {
        $addons = array_filter(explode(',', $addons));
    }
    $validation = $validator->validate(
        $quoteData['service_type'] ?? 'web_design',
        $quoteData['complexity']   ?? 'simple',
        array_values((array) $addons),
        $estimate['subtotal'],
        $estimate['range_low'],
        $estimate['range_high']
    );

    $refID      = ReferenceID::generate();
    $record     = [
        'ref_id'     => $refID,
        'created_at' => time(),
        'expires_at' => time() + (REFERENCE_EXPIRY_DAYS * 86400),
        'status'     => 'active',
        'quote_data' => $quoteData,
        'estimate'   => $estimate,
        'validation' => $validation,
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
