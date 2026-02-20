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
        'name'        => 'Untitled Form',
        'description' => '',
        'design'      => [
            'primaryColor'   => '#244c47',
            'accentColor'    => '#459289',
            'bgColor'        => '#fcfdfd',
            'textColor'      => '#182523',
            'headerBg'       => '#244c47',
            'headerText'     => '#eaf5f4',
            'font'           => 'system',
            'fontSize'       => '16',
            'borderRadius'   => '0',
            'headerTitle'    => 'Request a Quote',
            'headerSubtitle' => '',
            'submitLabel'    => 'Submit',
            'nextLabel'      => 'Next',
            'backLabel'      => 'Back',
        ],
        'steps' => [
            [
                'title'       => 'Step 1',
                'description' => '',
                'fields'      => [],
            ],
        ],
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
    require __DIR__ . '/preview.php';
    exit;
}

// ── Builder UI ────────────────────────────────────────────────────────────────
require __DIR__ . '/ui.php';
