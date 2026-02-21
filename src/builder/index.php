<?php
declare(strict_types=1);

/**
 * Form Builder — visual form editor
 * Route: /form-builder
 * Handles:
 *   GET  /form-builder              — builder UI
 *   GET  /form-builder?preview=1&id= — live preview
 *   POST /form-builder  action=api  — JSON API (save, load, list, delete, duplicate)
 */

define('BUILDER_ROOT', __DIR__);
define('BUILDER_STORE', __DIR__ . '/../../data/forms/');

if (!is_dir(BUILDER_STORE)) {
    mkdir(BUILDER_STORE, 0755, true);
}

// ── helpers ──────────────────────────────────────────────────────────────────

function builder_json_ok(mixed $data): never
{
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function builder_json_err(string $msg, int $code = 400): never
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['error' => $msg]);
    exit;
}

function builder_list_forms(): array
{
    $files = glob(BUILDER_STORE . '*.json') ?: [];
    $list  = [];
    foreach ($files as $f) {
        $raw = json_decode(file_get_contents($f), true);
        if (!$raw) continue;
        $list[] = [
            'id'         => $raw['id']         ?? '',
            'name'       => $raw['name']        ?? 'Untitled',
            'updated_at' => $raw['updated_at']  ?? 0,
            'step_count' => count($raw['steps'] ?? []),
        ];
    }
    usort($list, fn($a, $b) => $b['updated_at'] - $a['updated_at']);
    return $list;
}

function builder_load_form(string $id): ?array
{
    $id   = preg_replace('/[^a-zA-Z0-9_\-]/', '', $id);
    $path = BUILDER_STORE . $id . '.json';
    if (!file_exists($path)) return null;
    return json_decode(file_get_contents($path), true);
}

function builder_save_form(array $data): array
{
    if (empty($data['id'])) {
        $data['id'] = 'form_' . bin2hex(random_bytes(6));
    }
    $data['id']         = preg_replace('/[^a-zA-Z0-9_\-]/', '', $data['id']);
    $data['updated_at'] = time();
    if (empty($data['created_at'])) {
        $data['created_at'] = time();
    }
    $path = BUILDER_STORE . $data['id'] . '.json';
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return $data;
}

function builder_delete_form(string $id): bool
{
    $id   = preg_replace('/[^a-zA-Z0-9_\-]/', '', $id);
    $path = BUILDER_STORE . $id . '.json';
    if (file_exists($path)) {
        unlink($path);
        return true;
    }
    return false;
}

function builder_duplicate_form(string $id): ?array
{
    $orig = builder_load_form($id);
    if (!$orig) return null;
    $orig['id']         = 'form_' . bin2hex(random_bytes(6));
    $orig['name']       = ($orig['name'] ?? 'Untitled') . ' (Copy)';
    $orig['created_at'] = time();
    return builder_save_form($orig);
}

// ── blank form template ───────────────────────────────────────────────────────
function builder_blank(): array
{
    return [
        'id'          => '',
        'name'        => 'XcaliburMoon Web Development Pricing',
        'description' => '',
        'videoUrl'    => '',
        'services'    => [
            ['key' => 'web_design',      'label' => 'Web Design',                    'price' => 1500,  'help' => 'A professionally designed website tailored to your brand. Includes layout design, responsive styling, color palette, typography, and up to 5 pages. Ideal for businesses that need a polished online presence.'],
            ['key' => 'web_development', 'label' => 'Web Development',               'price' => 3500,  'help' => 'Full-stack web development with custom functionality. Includes front-end and back-end coding, database setup, form handling, and deployment. Best for interactive sites that go beyond static pages.'],
            ['key' => 'ecommerce',       'label' => 'E-Commerce',                    'price' => 4500,  'help' => 'A complete online store with product listings, shopping cart, secure checkout, payment gateway integration, and order management. Built on reliable platforms for scalability.'],
            ['key' => 'software',        'label' => 'Custom Software',               'price' => 7500,  'help' => 'Bespoke software solutions built to your specifications. Includes requirements analysis, architecture design, development, testing, and deployment. For businesses with unique workflow needs.'],
            ['key' => 'ai_web_app',      'label' => 'AI-Driven Web Application',     'price' => 9500,  'help' => 'A web application powered by AI and machine learning. Includes intelligent features such as natural language processing, recommendation engines, predictive analytics, or computer vision integrated into a web interface.'],
            ['key' => 'ai_native_app',   'label' => 'AI-Driven Native Application',  'price' => 14000, 'help' => 'A native mobile or desktop application with embedded AI capabilities. Includes platform-specific development (iOS, Android, or desktop), on-device or cloud AI models, and app store deployment.'],
        ],
        'complexity'  => [
            ['key' => 'simple',   'label' => 'Simple',   'description' => 'Basic pages, minimal interactions',         'multiplier' => 1.0, 'help' => 'Straightforward project with standard layouts and minimal custom logic. Typically 1-5 pages with basic navigation and contact forms.'],
            ['key' => 'moderate', 'label' => 'Moderate',  'description' => 'Custom features, some integrations',       'multiplier' => 1.4, 'help' => 'Involves custom interactive elements, third-party service integrations, or dynamic content. May include user accounts, dashboards, or API connections.'],
            ['key' => 'complex',  'label' => 'Complex',   'description' => 'Advanced logic, multiple integrations',    'multiplier' => 2.0, 'help' => 'Multi-layered project with advanced business logic, multiple external integrations, real-time features, or complex data processing workflows.'],
            ['key' => 'custom',   'label' => 'Custom',    'description' => 'AI, automation, or enterprise-scale work', 'multiplier' => 2.8, 'help' => 'Enterprise-grade work involving AI/ML pipelines, large-scale automation, microservices architecture, or systems designed for high availability and throughput.'],
        ],
        'addons'      => [
            ['key' => 'seo_basic',       'label' => 'SEO Setup - Basic',               'price' => 500,  'help' => 'On-page SEO fundamentals: meta tags, sitemap, robots.txt, page speed optimization, and Google Search Console setup.'],
            ['key' => 'seo_advanced',    'label' => 'SEO Setup - Advanced',            'price' => 1200, 'help' => 'Comprehensive SEO strategy: keyword research, content optimization, structured data markup, backlink audit, and analytics integration.'],
            ['key' => 'copywriting',     'label' => 'Copywriting',                     'price' => 800,  'help' => 'Professional copywriting for all site pages. Includes brand voice development, headlines, body copy, and calls to action optimized for engagement.'],
            ['key' => 'branding',        'label' => 'Branding and Identity',           'price' => 1800, 'help' => 'Complete brand identity package: logo design, color palette, typography guide, brand guidelines document, and social media assets.'],
            ['key' => 'maintenance',     'label' => 'Ongoing Maintenance',             'price' => 1200, 'help' => 'Monthly maintenance plan covering security updates, performance monitoring, content updates, backups, and priority support for 12 months.'],
            ['key' => 'hosting_setup',   'label' => 'Hosting Configuration',           'price' => 350,  'help' => 'Server setup and deployment: domain configuration, SSL certificate, hosting environment optimization, and DNS management.'],
            ['key' => 'api_integration', 'label' => 'Third-Party API Integration',     'price' => 1500, 'help' => 'Connect your application to external services such as payment processors, CRMs, email platforms, mapping services, or social media APIs.'],
            ['key' => 'automation',      'label' => 'Business Process Automation',     'price' => 2200, 'help' => 'Automate repetitive workflows: email sequences, data synchronization, report generation, invoice processing, or custom workflow triggers.'],
        ],
        'contact'     => [
            ['key' => 'name',     'label' => 'Full Name',                    'type' => 'text',   'required' => true],
            ['key' => 'email',    'label' => 'Email Address',                'type' => 'email',  'required' => true],
            ['key' => 'company',  'label' => 'Company or Organization',      'type' => 'text',   'required' => false],
            ['key' => 'timeline', 'label' => 'Desired Timeline',             'type' => 'select', 'required' => true,
             'options' => ['As soon as possible', 'Within 1 month', 'Within 3 months', 'Within 6 months', 'Flexible']],
        ],
        'style'       => [
            'primaryColor' => '#244c47',
            'accentColor'  => '#459289',
            'bgColor'      => '#fcfdfd',
            'textColor'    => '#182523',
            'headerBg'     => '#244c47',
            'headerText'   => '#eaf5f4',
            'font'         => 'system',
            'fontSize'     => '16',
        ],
        'language'    => [
            'headerTitle'          => 'Request a Quote',
            'headerSubtitle'       => 'Get an accurate estimate for your project',
            'introHeading'         => 'Welcome',
            'introText'            => 'Watch the video below to learn about our services, then click the button to get started with your custom estimate.',
            'introButtonLabel'     => 'Get Started',
            'serviceStepTitle'     => 'Tell us about your project',
            'serviceStepDesc'      => 'Select the primary service type that best describes what you need.',
            'complexityStepTitle'  => 'Project Complexity',
            'complexityStepDesc'   => 'How would you describe the scope and complexity of your project?',
            'addonStepTitle'       => 'Add-On Services',
            'addonStepDesc'        => 'Select any additional services you would like included in your estimate.',
            'contactStepTitle'     => 'Contact Information',
            'contactStepDesc'      => 'Provide your details so we can follow up with your formal quote.',
            'nextLabel'            => 'Next',
            'backLabel'            => 'Back',
            'submitLabel'          => 'Get Estimate',
            'resultHeading'        => 'Your Estimate',
            'resultDesc'           => 'Based on your selections, here are your pricing options.',
            'resultDisclaimer'     => 'Please note: these figures are ballpark estimates generated by our pricing tool. Once a developer reviews your submission, you will receive a detailed quote via email within 1 to 7 business days depending on project scope.',
            'detailsLabel'         => 'Additional Details',
            'detailsPlaceholder'   => 'Describe any specific requirements, features, deadlines, or other details that would help us understand your project better.',
            'currency'             => '$',
            'backLinkUrl'          => '',
            'backLinkLabel'        => '',
        ],
        'tiers'       => [
            ['name' => 'Basic',    'multiplier' => 0.9, 'description' => 'Essential features only'],
            ['name' => 'Standard', 'multiplier' => 1.0, 'description' => 'Recommended for most projects'],
            ['name' => 'Premium',  'multiplier' => 1.3, 'description' => 'Full-service with priority support'],
        ],
        'showBreakdown' => true,
    ];
}

// ── API ───────────────────────────────────────────────────────────────────────
$rawBody   = file_get_contents('php://input');
$jsonBody  = json_decode($rawBody, true);
$isJsonApi = ($_SERVER['REQUEST_METHOD'] === 'POST') &&
             ($jsonBody !== null) &&
             (isset($jsonBody['cmd']) || (isset($jsonBody['action']) && $jsonBody['action'] === 'api'));

if ($isJsonApi) {
    $payload = $jsonBody;
    $cmd     = $payload['cmd'] ?? '';

    match ($cmd) {
        'list'      => builder_json_ok(['forms' => builder_list_forms()]),
        'load'      => (function () use ($payload) {
            $f = builder_load_form($payload['id'] ?? '');
            $f ? builder_json_ok(['form' => $f]) : builder_json_err('Form not found', 404);
        })(),
        'save'      => (function () use ($payload) {
            $data = $payload['form'] ?? null;
            if (!$data || empty($data['name'])) builder_json_err('Missing form data');
            builder_json_ok(['form' => builder_save_form($data)]);
        })(),
        'delete'    => (function () use ($payload) {
            $ok = builder_delete_form($payload['id'] ?? '');
            $ok ? builder_json_ok(['ok' => true]) : builder_json_err('Not found', 404);
        })(),
        'duplicate' => (function () use ($payload) {
            $f = builder_duplicate_form($payload['id'] ?? '');
            $f ? builder_json_ok(['form' => $f]) : builder_json_err('Not found', 404);
        })(),
        'blank'     => builder_json_ok(['form' => builder_blank()]),
        default     => builder_json_err('Unknown command'),
    };
}

// ── Preview ───────────────────────────────────────────────────────────────────
if (isset($_GET['preview'])) {
    $id   = $_GET['id'] ?? '';
    $form = $id ? builder_load_form($id) : null;
    if (!$form) {
        http_response_code(404);
        echo '<p style="font-family:sans-serif;padding:2rem;">Form not found.</p>';
        exit;
    }
    $isBuilderPreview = true;
    require __DIR__ . '/preview.php';
    exit;
}

// ── Builder UI ────────────────────────────────────────────────────────────────
require __DIR__ . '/ui.php';
