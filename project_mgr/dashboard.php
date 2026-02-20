<?php
declare(strict_types=1);

if (php_sapi_name() !== 'cli-server') {
    http_response_code(403);
    exit('Dashboard is only accessible through the development server.');
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function dashRun(string $cmd): string
{
    $out = shell_exec($cmd . ' 2>&1');
    return trim((string) $out);
}

function dashFileRead(string $path): string
{
    if (!file_exists($path)) {
        return '';
    }
    return (string) file_get_contents($path);
}

// ── API endpoints (called by dashboard JS) ────────────────────────────────────

$apiAction = $_GET['api'] ?? '';

if ($apiAction !== '') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    $root = dirname(__DIR__);

    switch ($apiAction) {

        // Git status
        case 'git_status':
            $branch  = dashRun('git -C ' . escapeshellarg($root) . ' rev-parse --abbrev-ref HEAD');
            $status  = dashRun('git -C ' . escapeshellarg($root) . ' status --short');
            $log     = dashRun('git -C ' . escapeshellarg($root) . ' log --oneline -8');
            $remote  = dashRun('git -C ' . escapeshellarg($root) . ' remote get-url origin 2>/dev/null');
            echo json_encode([
                'branch' => $branch,
                'status' => $status,
                'log'    => $log,
                'remote' => $remote,
            ]);
            break;

        // Git push (main branch only, no force)
        case 'git_push':
            $branch = dashRun('git -C ' . escapeshellarg($root) . ' rev-parse --abbrev-ref HEAD');
            if (str_contains($branch, "\n") || !preg_match('/^[a-zA-Z0-9_\-\/]+$/', $branch)) {
                echo json_encode(['ok' => false, 'output' => 'Invalid branch name.']);
                break;
            }
            $out = dashRun('git -C ' . escapeshellarg($root) . ' push origin ' . escapeshellarg($branch));
            echo json_encode(['ok' => true, 'output' => $out]);
            break;

        // Git pull
        case 'git_pull':
            $out = dashRun('git -C ' . escapeshellarg($root) . ' pull');
            echo json_encode(['ok' => true, 'output' => $out]);
            break;

        // Git stage all + commit
        case 'git_commit':
            $msg = trim($_POST['message'] ?? '');
            if ($msg === '') {
                echo json_encode(['ok' => false, 'output' => 'Commit message is required.']);
                break;
            }
            $msg = substr($msg, 0, 300);
            $add = dashRun('git -C ' . escapeshellarg($root) . ' add -A');
            $out = dashRun('git -C ' . escapeshellarg($root) . ' commit -m ' . escapeshellarg($msg));
            echo json_encode(['ok' => true, 'output' => $add . "\n" . $out]);
            break;

        // Run build tool
        case 'build':
            $buildDir = $root . '/build_this';
            if (!is_dir($buildDir)) {
                echo json_encode(['ok' => false, 'output' => 'build_this/ directory not found.']);
                break;
            }
            $out = dashRun('cd ' . escapeshellarg($buildDir) . ' && go run main.go');
            echo json_encode(['ok' => true, 'output' => $out]);
            break;

        // Read settings file
        case 'settings_read':
            $file = $root . '/config/settings.php';
            echo json_encode(['content' => dashFileRead($file)]);
            break;

        // Write settings file (restricted to config/settings.php only)
        case 'settings_write':
            $file    = realpath($root . '/config/settings.php');
            $content = $_POST['content'] ?? '';
            if ($file === false || !str_starts_with($file, realpath($root))) {
                echo json_encode(['ok' => false, 'output' => 'Invalid path.']);
                break;
            }
            // Reject any attempt to store credentials in the file
            if (preg_match('/SMTP_PASSWORD\s*[,\)]\s*[\'"][^\'"]{2,}/', $content)) {
                echo json_encode(['ok' => false, 'output' => 'Do not store SMTP passwords directly in settings.php. Store them outside the web root.']);
                break;
            }
            $ok = file_put_contents($file, $content) !== false;
            echo json_encode(['ok' => $ok, 'output' => $ok ? 'Settings saved.' : 'Write failed.']);
            break;

        // List notes
        case 'notes_list':
            $notesDir = $root . '/project_mgr/notes';
            $pattern  = $notesDir . '/[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]-*.md';
            $files    = glob($pattern) ?: [];
            rsort($files);
            $notes = [];
            foreach ($files as $f) {
                $raw   = (string) file_get_contents($f);
                $lines = explode("\n", $raw);
                $title = trim(ltrim($lines[0] ?? '', '# '));
                $date  = '';
                $author = '';
                foreach ($lines as $l) {
                    if (preg_match('/^- Date:\s*(.+)$/', $l, $m))   { $date   = trim($m[1]); }
                    if (preg_match('/^- Author:\s*(.+)$/', $l, $m)) { $author = trim($m[1]); }
                }
                $notes[] = ['title' => $title, 'date' => $date, 'author' => $author, 'file' => basename($f)];
            }
            echo json_encode($notes);
            break;

        // Add note (delegates to add_note.php CLI)
        case 'notes_add':
            $user  = trim($_POST['user']  ?? '');
            $title = trim($_POST['title'] ?? '');
            $body  = trim($_POST['body']  ?? '');
            $tags  = trim($_POST['tags']  ?? '');
            if ($user === '' || $title === '' || $body === '') {
                echo json_encode(['ok' => false, 'output' => 'user, title, and body are required.']);
                break;
            }
            $cmd = 'php ' . escapeshellarg($root . '/project_mgr/add_note.php')
                . ' --user '  . escapeshellarg($user)
                . ' --title ' . escapeshellarg($title)
                . ' --body '  . escapeshellarg($body)
                . ($tags !== '' ? ' --tags ' . escapeshellarg($tags) : '');
            $out = dashRun($cmd);
            echo json_encode(['ok' => true, 'output' => $out]);
            break;

        // Project info
        case 'info':
            $composer = json_decode(dashFileRead($root . '/composer.json'), true) ?? [];
            $phpVer   = phpversion();
            $noteCount = count(glob($root . '/project_mgr/notes/[0-9]*-*.md') ?: []);
            echo json_encode([
                'php_version'  => $phpVer,
                'project_name' => $composer['name'] ?? 'Unknown',
                'project_desc' => $composer['description'] ?? '',
                'note_count'   => $noteCount,
                'vendor_ready' => is_dir($root . '/vendor'),
                'mathjs_ready' => file_exists($root . '/assets/js/vendor/math.min.js'),
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown API action.']);
    }
    exit;
}

// ── Full page ─────────────────────────────────────────────────────────────────
$root = dirname(__DIR__);
$remote = dashRun('git -C ' . escapeshellarg($root) . ' remote get-url origin 2>/dev/null');
$repoUrl = '';
if (preg_match('#github\.com[:/](.+?)(?:\.git)?$#', $remote, $m)) {
    $repoUrl = 'https://github.com/' . $m[1];
}
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dev Dashboard - XcaliburMoon</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --dash-primary:     #244c47;
            --dash-primary-dk:  #1a3835;
            --dash-accent:      #459289;
            --dash-bg:          #fcfdfd;
            --dash-surface:     #eaf5f4;
            --dash-border:      #c8dedd;
            --dash-text:        #182523;
            --dash-muted:       #556b69;
            --dash-header-bg:   #081110;
            --dash-header-text: #eaf5f4;
            --dash-tab-bg:      #181f1e;
            --dash-term-bg:     #081110;
            --dash-term-text:   #a8ccc9;
            --dash-radius:      0px;
            --dash-font:        -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }

        html { font-size: 15px; font-family: var(--dash-font); color: var(--dash-text); background: var(--dash-bg); }
        body { min-height: 100vh; display: flex; flex-direction: column; }
        a { color: var(--dash-primary); }

        /* header */
        .dash-header {
            background: var(--dash-header-bg);
            color: var(--dash-header-text);
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            border-bottom: 2px solid var(--dash-primary);
        }
        .dash-header-left { display: flex; align-items: baseline; gap: 1rem; }
        .dash-title { font-size: 1rem; font-weight: 700; letter-spacing: 0.03em; color: var(--dash-header-text); }
        .dash-subtitle { font-size: 0.72rem; color: var(--dash-muted); }
        .dash-status { font-size: 0.72rem; color: var(--dash-accent); font-family: monospace; }

        /* tabs */
        .tab-bar {
            background: var(--dash-tab-bg);
            display: flex;
            gap: 0;
            border-bottom: 2px solid var(--dash-primary);
            overflow-x: auto;
        }
        .tab-btn {
            background: none;
            border: none;
            color: var(--dash-muted);
            padding: 0.65rem 1.2rem;
            font-size: 0.78rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: color 0.15s;
            white-space: nowrap;
        }
        .tab-btn:hover { color: var(--dash-term-text); }
        .tab-btn.active { color: var(--dash-header-text); border-bottom-color: var(--dash-accent); }

        /* panels */
        .tab-panel { display: none; flex: 1; }
        .tab-panel.active { display: block; }

        .panel-inner { max-width: 960px; margin: 0 auto; padding: 1.5rem; }

        /* section title */
        .sec-title {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #244c47;
            border-bottom: 2px solid #244c47;
            padding-bottom: 0.3rem;
            margin-bottom: 1rem;
        }

        /* info cards */
        .info-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 0.75rem; margin-bottom: 1.5rem; }
        .info-card {
            background: var(--dash-surface);
            border-left: 4px solid var(--dash-primary);
            padding: 0.65rem 0.85rem;
            border-radius: var(--dash-radius);
        }
        .info-card-label { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--dash-muted); margin-bottom: 0.2rem; }
        .info-card-value { font-size: 0.92rem; font-weight: 700; color: var(--dash-text); }
        .info-card-value.ok { color: var(--dash-primary); }
        .info-card-value.warn { color: #8a4a00; }

        /* shortcut links */
        .shortcut-row { display: flex; flex-wrap: wrap; gap: 0.6rem; margin-bottom: 1.5rem; }
        .shortcut-link {
            font-size: 0.78rem;
            font-weight: 600;
            color: var(--dash-header-text);
            background: var(--dash-primary);
            padding: 0.4rem 0.85rem;
            text-decoration: none;
            border: 1px solid var(--dash-primary);
            border-radius: var(--dash-radius);
        }
        .shortcut-link:hover { background: var(--dash-primary-dk); }

        /* terminal output */
        .terminal {
            background: var(--dash-term-bg);
            color: var(--dash-term-text);
            font-family: 'SFMono-Regular', 'Consolas', monospace;
            font-size: 0.76rem;
            padding: 0.85rem 1rem;
            min-height: 80px;
            max-height: 320px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-all;
            margin-bottom: 1rem;
        }
        .terminal.empty { color: var(--dash-muted); font-style: italic; }

        /* forms / inputs */
        .field-row { margin-bottom: 0.85rem; }
        .field-label { display: block; font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: var(--dash-muted); margin-bottom: 0.3rem; }
        .field-input, .field-textarea, .field-select {
            width: 100%;
            padding: 0.5rem 0.65rem;
            border: 1px solid var(--dash-border);
            background: var(--dash-bg);
            color: var(--dash-text);
            font-size: 0.85rem;
            font-family: inherit;
            border-radius: var(--dash-radius);
        }
        .field-textarea { font-family: 'SFMono-Regular', 'Consolas', monospace; font-size: 0.76rem; resize: vertical; }
        .field-input:focus, .field-textarea:focus, .field-select:focus { outline: 2px solid var(--dash-primary); }

        /* buttons */
        .btn {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 0.5rem 1rem;
            border: none;
            cursor: pointer;
            border-radius: var(--dash-radius);
        }
        .btn-primary { background: var(--dash-primary); color: var(--dash-header-text); }
        .btn-primary:hover { background: var(--dash-primary-dk); }
        .btn-secondary { background: var(--dash-surface); color: var(--dash-primary); border: 1px solid var(--dash-primary); }
        .btn-secondary:hover { background: var(--dash-border); }
        .btn-danger { background: #5a1a1a; color: #fce8e8; }
        .btn-danger:hover { background: #3d0f0f; }
        .btn-row { display: flex; gap: 0.6rem; flex-wrap: wrap; margin-bottom: 1rem; }

        /* git log — must be light text inside dark terminal */
        .terminal .git-log-line { padding: 0.3rem 0; border-bottom: 1px solid #1e2e2c; font-size: 0.8rem; font-family: monospace; color: var(--dash-term-text); }
        .terminal .git-log-line:last-child { border-bottom: none; }
        .terminal .git-hash { color: var(--dash-accent); font-weight: 700; margin-right: 0.5rem; }
        /* git log outside terminal (overview log rendered as plain divs) */
        .git-log-line { padding: 0.3rem 0; border-bottom: 1px solid var(--dash-border); font-size: 0.8rem; font-family: monospace; color: var(--dash-text); }
        .git-log-line:last-child { border-bottom: none; }
        .git-hash { color: var(--dash-primary); font-weight: 700; margin-right: 0.5rem; }
        .git-branch-badge {
            display: inline-block;
            background: var(--dash-primary);
            color: var(--dash-header-text);
            font-size: 0.65rem;
            font-weight: 700;
            padding: 0.1rem 0.4rem;
            font-family: monospace;
            margin-right: 0.5rem;
            vertical-align: middle;
        }

        /* notes list */
        .note-row {
            border-bottom: 1px solid var(--dash-surface);
            padding: 0.55rem 0;
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .note-row-title { font-size: 0.85rem; font-weight: 600; color: var(--dash-text); }
        .note-row-meta { font-size: 0.72rem; color: var(--dash-muted); font-family: monospace; }

        /* settings textarea */
        #settings-content { min-height: 360px; }

        /* separator */
        .row-gap { margin-bottom: 1.75rem; }

        /* status badge */
        .badge { font-size: 0.65rem; font-weight: 700; padding: 0.1rem 0.4rem; text-transform: uppercase; letter-spacing: 0.06em; display: inline-block; }
        .badge-ok   { background: var(--dash-surface); color: var(--dash-primary); border: 1px solid var(--dash-primary); }
        .badge-warn { background: #fff3e0; color: #8a4a00; border: 1px solid #b06a00; }

        .dash-footer {
            padding: 0.5rem 1.5rem;
            background: var(--dash-header-bg);
            color: var(--dash-muted);
            font-size: 0.68rem;
            text-align: center;
        }

        /* appearance panel */
        .appearance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }
        .appearance-card {
            border: 2px solid var(--dash-border);
            padding: 0.85rem 1rem;
            cursor: pointer;
            background: var(--dash-bg);
        }
        .appearance-card:hover { border-color: var(--dash-primary); }
        .appearance-card.selected { border-color: var(--dash-primary); background: var(--dash-surface); }
        .appearance-card-name { font-weight: 700; font-size: 0.85rem; color: var(--dash-text); }
        .appearance-card-desc { font-size: 0.72rem; color: var(--dash-muted); margin-top: 0.2rem; }
        .appearance-swatches { display: flex; gap: 4px; margin-top: 0.5rem; }
        .appearance-swatch { width: 18px; height: 18px; display: inline-block; }
        .appearance-inline { display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap; }
        .appearance-inline label { font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--dash-muted); min-width: 90px; }
        .appearance-inline input[type=color] { width: 38px; height: 28px; padding: 0; border: 1px solid var(--dash-border); cursor: pointer; background: none; }
        .appearance-inline input[type=range] { flex: 1; min-width: 120px; max-width: 220px; cursor: pointer; }
        .appearance-inline select { padding: 0.3rem 0.5rem; border: 1px solid var(--dash-border); font-size: 0.82rem; color: var(--dash-text); background: var(--dash-bg); font-family: inherit; }
        .appearance-inline .range-val { font-size: 0.78rem; font-family: monospace; min-width: 36px; color: var(--dash-text); }
    </style>
</head>
<body>

<header class="dash-header">
    <div class="dash-header-left">
        <span class="dash-title">Dev Dashboard</span>
        <span class="dash-subtitle">XcaliburMoon - Development only</span>
    </div>
    <span class="dash-status" id="dash-status">Loading...</span>
</header>

<nav class="tab-bar" role="tablist">
    <button class="tab-btn active" data-panel="panel-overview"    role="tab">Overview</button>
    <button class="tab-btn"        data-panel="panel-github"      role="tab">GitHub</button>
    <button class="tab-btn"        data-panel="panel-build"       role="tab">Build</button>
    <button class="tab-btn"        data-panel="panel-notes"       role="tab">Notes</button>
    <button class="tab-btn"        data-panel="panel-settings"    role="tab">Settings</button>
    <button class="tab-btn"        data-panel="panel-appearance"  role="tab">Appearance</button>
</nav>

<!-- OVERVIEW ------------------------------------------------------------------>
<div id="panel-overview" class="tab-panel active">
<div class="panel-inner">
    <div class="row-gap">
        <p class="sec-title">Project</p>
        <div class="info-grid" id="info-grid">
            <div class="info-card"><div class="info-card-label">PHP</div><div class="info-card-value" id="info-php">...</div></div>
            <div class="info-card"><div class="info-card-label">Vendor</div><div class="info-card-value" id="info-vendor">...</div></div>
            <div class="info-card"><div class="info-card-label">Math.js</div><div class="info-card-value" id="info-mathjs">...</div></div>
            <div class="info-card"><div class="info-card-label">Notes</div><div class="info-card-value" id="info-notes">...</div></div>
        </div>
    </div>

    <div class="row-gap">
        <p class="sec-title">Open</p>
        <div class="shortcut-row">
            <a href="/" target="_blank" class="shortcut-link">Form Builder</a>
            <a href="/?demo=1" target="_blank" class="shortcut-link">Form Example</a>
            <a href="/project-mgr" target="_blank" class="shortcut-link">Project Manager</a>
            <?php if ($repoUrl): ?>
            <a href="<?= htmlspecialchars($repoUrl) ?>" target="_blank" rel="noopener" class="shortcut-link">GitHub Repo</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="row-gap">
        <p class="sec-title">Recent Commits</p>
        <div id="overview-log" class="terminal empty">Loading...</div>
    </div>
</div>
</div>

<!-- GITHUB -------------------------------------------------------------------->
<div id="panel-github" class="tab-panel">
<div class="panel-inner">
    <div class="row-gap">
        <p class="sec-title">Repository Status</p>
        <div style="margin-bottom:0.65rem;">
            <span id="git-branch-badge" class="git-branch-badge">...</span>
            <?php if ($repoUrl): ?>
            <a href="<?= htmlspecialchars($repoUrl) ?>" target="_blank" rel="noopener" style="font-size:0.78rem;"><?= htmlspecialchars($remote) ?></a>
            <?php endif; ?>
        </div>
        <div id="git-status-out" class="terminal empty">Loading...</div>
    </div>

    <div class="row-gap">
        <p class="sec-title">Commit</p>
        <div class="field-row">
            <label class="field-label" for="commit-msg">Commit message</label>
            <input id="commit-msg" class="field-input" type="text" placeholder="Describe what changed" maxlength="300">
        </div>
        <div class="btn-row">
            <button class="btn btn-primary" onclick="gitAction('git_commit')">Stage All + Commit</button>
            <button class="btn btn-secondary" onclick="refreshGit()">Refresh Status</button>
        </div>
        <div id="git-commit-out" class="terminal empty" style="display:none;"></div>
    </div>

    <div class="row-gap">
        <p class="sec-title">Push / Pull</p>
        <div class="btn-row">
            <button class="btn btn-primary" onclick="gitAction('git_push')">Push</button>
            <button class="btn btn-secondary" onclick="gitAction('git_pull')">Pull</button>
        </div>
        <div id="git-push-out" class="terminal empty" style="display:none;"></div>
    </div>

    <div class="row-gap">
        <p class="sec-title">Recent Commits</p>
        <div id="git-log-out" class="terminal empty">Loading...</div>
    </div>
</div>
</div>

<!-- BUILD --------------------------------------------------------------------->
<div id="panel-build" class="tab-panel">
<div class="panel-inner">
    <div class="row-gap">
        <p class="sec-title">xcm-build-this</p>
        <p style="font-size:0.85rem; color:#556b69; margin-bottom:1rem;">
            Runs the Go build tool in <code>build_this/</code>. Output is placed in <code>deploy/this/</code>.
        </p>
        <div class="btn-row">
            <button class="btn btn-primary" id="build-btn" onclick="runBuild()">Run Build</button>
        </div>
        <div id="build-out" class="terminal empty">No build run yet.</div>
    </div>
</div>
</div>

<!-- NOTES --------------------------------------------------------------------->
<div id="panel-notes" class="tab-panel">
<div class="panel-inner">
    <div class="row-gap">
        <p class="sec-title">Add Note</p>
        <div class="field-row">
            <label class="field-label" for="note-user">Your name</label>
            <input id="note-user" class="field-input" type="text" maxlength="80">
        </div>
        <div class="field-row">
            <label class="field-label" for="note-title">Title</label>
            <input id="note-title" class="field-input" type="text" maxlength="120">
        </div>
        <div class="field-row">
            <label class="field-label" for="note-body">Body</label>
            <textarea id="note-body" class="field-textarea" rows="5" maxlength="4000"></textarea>
        </div>
        <div class="field-row">
            <label class="field-label" for="note-tags">Tags (comma separated, optional)</label>
            <input id="note-tags" class="field-input" type="text" maxlength="200">
        </div>
        <div class="btn-row">
            <button class="btn btn-primary" onclick="addNote()">Add Note</button>
        </div>
        <div id="note-out" class="terminal empty" style="display:none;"></div>
    </div>

    <div>
        <p class="sec-title">All Notes</p>
        <div id="notes-list">Loading...</div>
    </div>
</div>
</div>

<!-- SETTINGS ------------------------------------------------------------------>
<div id="panel-settings" class="tab-panel">
<div class="panel-inner">
    <div class="row-gap">
        <p class="sec-title">config/settings.php</p>
        <p style="font-size:0.82rem; color:#556b69; margin-bottom:0.85rem;">
            Editing is restricted to <code>config/settings.php</code>. Do not store SMTP passwords here.
        </p>
        <textarea id="settings-content" class="field-textarea" rows="22" spellcheck="false"></textarea>
        <div class="btn-row" style="margin-top:0.65rem;">
            <button class="btn btn-primary" onclick="saveSettings()">Save</button>
            <button class="btn btn-secondary" onclick="loadSettings()">Reload</button>
        </div>
        <div id="settings-out" class="terminal empty" style="display:none;"></div>
    </div>
</div>
</div>

<!-- APPEARANCE ------------------------------------------------------------->
<div id="panel-appearance" class="tab-panel">
<div class="panel-inner">

    <div class="row-gap">
        <p class="sec-title">Presets</p>
        <div class="appearance-grid" id="preset-grid">
            <div class="appearance-card selected" data-preset="default">
                <div class="appearance-card-name">Default Dark</div>
                <div class="appearance-card-desc">Dark header, light body, teal primary</div>
                <div class="appearance-swatches">
                    <span class="appearance-swatch" style="background:#081110"></span>
                    <span class="appearance-swatch" style="background:#244c47"></span>
                    <span class="appearance-swatch" style="background:#eaf5f4"></span>
                    <span class="appearance-swatch" style="background:#fcfdfd"></span>
                </div>
            </div>
            <div class="appearance-card" data-preset="light">
                <div class="appearance-card-name">Light</div>
                <div class="appearance-card-desc">White background, navy primary</div>
                <div class="appearance-swatches">
                    <span class="appearance-swatch" style="background:#1a2744"></span>
                    <span class="appearance-swatch" style="background:#2d4fa3"></span>
                    <span class="appearance-swatch" style="background:#e8edf8"></span>
                    <span class="appearance-swatch" style="background:#ffffff"></span>
                </div>
            </div>
            <div class="appearance-card" data-preset="midnight">
                <div class="appearance-card-name">Midnight</div>
                <div class="appearance-card-desc">Full dark, purple accent</div>
                <div class="appearance-swatches">
                    <span class="appearance-swatch" style="background:#0d0d14"></span>
                    <span class="appearance-swatch" style="background:#5c3d8f"></span>
                    <span class="appearance-swatch" style="background:#2a1f3d"></span>
                    <span class="appearance-swatch" style="background:#13111e"></span>
                </div>
            </div>
            <div class="appearance-card" data-preset="slate">
                <div class="appearance-card-name">Slate</div>
                <div class="appearance-card-desc">Blue-grey, high contrast</div>
                <div class="appearance-swatches">
                    <span class="appearance-swatch" style="background:#1e2a38"></span>
                    <span class="appearance-swatch" style="background:#3b6ea5"></span>
                    <span class="appearance-swatch" style="background:#dde8f5"></span>
                    <span class="appearance-swatch" style="background:#f4f7fb"></span>
                </div>
            </div>
            <div class="appearance-card" data-preset="forest">
                <div class="appearance-card-name">Forest</div>
                <div class="appearance-card-desc">Deep green on cream</div>
                <div class="appearance-swatches">
                    <span class="appearance-swatch" style="background:#1a2e1a"></span>
                    <span class="appearance-swatch" style="background:#3a6b3a"></span>
                    <span class="appearance-swatch" style="background:#eaf2ea"></span>
                    <span class="appearance-swatch" style="background:#f8fbf8"></span>
                </div>
            </div>
            <div class="appearance-card" data-preset="mono">
                <div class="appearance-card-name">Monochrome</div>
                <div class="appearance-card-desc">Black, white, grey only</div>
                <div class="appearance-swatches">
                    <span class="appearance-swatch" style="background:#111111"></span>
                    <span class="appearance-swatch" style="background:#333333"></span>
                    <span class="appearance-swatch" style="background:#eeeeee"></span>
                    <span class="appearance-swatch" style="background:#ffffff"></span>
                </div>
            </div>
        </div>
    </div>

    <div class="row-gap">
        <p class="sec-title">Custom Colors</p>
        <div style="display:flex; flex-direction:column; gap:0.7rem;">
            <div class="appearance-inline">
                <label>Primary</label>
                <input type="color" id="ap-primary" value="#244c47">
                <span style="font-size:0.75rem;color:var(--dash-muted);">Header bar, borders, buttons</span>
            </div>
            <div class="appearance-inline">
                <label>Accent</label>
                <input type="color" id="ap-accent" value="#459289">
                <span style="font-size:0.75rem;color:var(--dash-muted);">Active tab indicator, links</span>
            </div>
            <div class="appearance-inline">
                <label>Background</label>
                <input type="color" id="ap-bg" value="#fcfdfd">
                <span style="font-size:0.75rem;color:var(--dash-muted);">Page background</span>
            </div>
            <div class="appearance-inline">
                <label>Header bg</label>
                <input type="color" id="ap-header-bg" value="#081110">
                <span style="font-size:0.75rem;color:var(--dash-muted);">Top header and tab bar</span>
            </div>
            <div class="appearance-inline">
                <label>Terminal bg</label>
                <input type="color" id="ap-term-bg" value="#081110">
                <span style="font-size:0.75rem;color:var(--dash-muted);">Output terminal background</span>
            </div>
            <div class="appearance-inline">
                <label>Terminal text</label>
                <input type="color" id="ap-term-text" value="#a8ccc9">
            </div>
        </div>
    </div>

    <div class="row-gap">
        <p class="sec-title">Typography</p>
        <div class="appearance-inline">
            <label>Font</label>
            <select id="ap-font">
                <option value="-apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif">System UI (default)</option>
                <option value="'Inter', 'Helvetica Neue', Arial, sans-serif">Inter</option>
                <option value="'IBM Plex Mono', 'SFMono-Regular', 'Consolas', monospace">IBM Plex Mono</option>
                <option value="Georgia, 'Times New Roman', serif">Georgia (serif)</option>
                <option value="'Trebuchet MS', 'Lucida Grande', sans-serif">Trebuchet</option>
            </select>
        </div>
        <div class="appearance-inline" style="margin-top:0.7rem;">
            <label>Font size</label>
            <input type="range" id="ap-fontsize" min="12" max="20" step="1" value="15">
            <span class="range-val" id="ap-fontsize-val">15px</span>
        </div>
    </div>

    <div class="row-gap">
        <p class="sec-title">Shape</p>
        <div class="appearance-inline">
            <label>Border radius</label>
            <input type="range" id="ap-radius" min="0" max="12" step="1" value="0">
            <span class="range-val" id="ap-radius-val">0px</span>
        </div>
    </div>

    <div class="row-gap">
        <div class="btn-row">
            <button class="btn btn-primary" onclick="applyAppearance()">Apply</button>
            <button class="btn btn-secondary" onclick="resetAppearance()">Reset to Default</button>
        </div>
        <div id="appearance-out" class="terminal empty" style="display:none;"></div>
    </div>

</div>
</div>

<footer class="dash-footer">
    Dev Dashboard - development use only. Not included in build output.
</footer>

<script>
(function () {
    'use strict';

    // ── tab switching ────────────────────────────────────────────────────────
    var tabs = document.querySelectorAll('.tab-btn');
    tabs.forEach(function (btn) {
        btn.addEventListener('click', function () {
            tabs.forEach(function (b) { b.classList.remove('active'); });
            document.querySelectorAll('.tab-panel').forEach(function (p) { p.classList.remove('active'); });
            btn.classList.add('active');
            var panel = document.getElementById(btn.dataset.panel);
            if (panel) { panel.classList.add('active'); }
            if (btn.dataset.panel === 'panel-github') { refreshGit(); }
            if (btn.dataset.panel === 'panel-notes')  { loadNotes(); }
            if (btn.dataset.panel === 'panel-settings') { loadSettings(); }
        });
    });

    // ── API helper ───────────────────────────────────────────────────────────
    function api(action, body, callback) {
        var url = '/dashboard?api=' + encodeURIComponent(action);
        var opts = { method: 'GET', headers: {} };
        if (body) {
            opts.method = 'POST';
            opts.headers['Content-Type'] = 'application/x-www-form-urlencoded';
            opts.body = Object.keys(body).map(function (k) {
                return encodeURIComponent(k) + '=' + encodeURIComponent(body[k]);
            }).join('&');
        }
        fetch(url, opts)
            .then(function (r) { return r.json(); })
            .then(callback)
            .catch(function (e) { callback({ error: String(e) }); });
    }

    function term(el, text, empty) {
        if (!el) { return; }
        el.style.display = 'block';
        el.classList.toggle('empty', !!empty);
        el.textContent = text || '';
    }

    // ── overview ─────────────────────────────────────────────────────────────
    function loadOverview() {
        api('info', null, function (d) {
            var el = function (id) { return document.getElementById(id); };
            if (d.error) { return; }
            var php = el('info-php');
            if (php) { php.textContent = d.php_version || '?'; }

            var vendor = el('info-vendor');
            if (vendor) {
                vendor.textContent = d.vendor_ready ? 'Ready' : 'Missing';
                vendor.className = 'info-card-value ' + (d.vendor_ready ? 'ok' : 'warn');
            }
            var mjs = el('info-mathjs');
            if (mjs) {
                mjs.textContent = d.mathjs_ready ? 'Ready' : 'Missing';
                mjs.className = 'info-card-value ' + (d.mathjs_ready ? 'ok' : 'warn');
            }
            var notes = el('info-notes');
            if (notes) { notes.textContent = d.note_count; }

            var status = document.getElementById('dash-status');
            if (status) { status.textContent = d.project_name || ''; }
        });

        api('git_status', null, function (d) {
            var el = document.getElementById('overview-log');
            if (!d.log) { term(el, 'No commits yet.', true); return; }
            renderLog(el, d.log);
        });
    }

    // ── git ──────────────────────────────────────────────────────────────────
    window.refreshGit = function () {
        api('git_status', null, function (d) {
            var badge = document.getElementById('git-branch-badge');
            if (badge) { badge.textContent = d.branch || '?'; }

            var sout = document.getElementById('git-status-out');
            term(sout, d.status || 'Working tree clean.', !d.status);

            var lout = document.getElementById('git-log-out');
            if (d.log) { renderLog(lout, d.log); } else { term(lout, 'No commits yet.', true); }
        });
    };

    function renderLog(el, raw) {
        if (!el) { return; }
        el.style.display = 'block';
        el.classList.remove('empty');
        el.innerHTML = '';
        raw.split('\n').forEach(function (line) {
            if (!line.trim()) { return; }
            var parts = line.match(/^([a-f0-9]+)\s(.+)$/);
            var row = document.createElement('div');
            row.className = 'git-log-line';
            if (parts) {
                var hash = document.createElement('span');
                hash.className = 'git-hash';
                hash.textContent = parts[1];
                row.appendChild(hash);
                row.appendChild(document.createTextNode(parts[2]));
            } else {
                row.textContent = line;
            }
            el.appendChild(row);
        });
    }

    window.gitAction = function (action) {
        var outId = action === 'git_commit' ? 'git-commit-out' : 'git-push-out';
        var out = document.getElementById(outId);
        var body = null;
        if (action === 'git_commit') {
            var msg = (document.getElementById('commit-msg') || {}).value || '';
            if (!msg.trim()) { term(out, 'Enter a commit message.', false); return; }
            body = { message: msg };
        }
        term(out, 'Running...', false);
        api(action, body, function (d) {
            term(out, d.output || d.error || JSON.stringify(d), false);
            if (action === 'git_commit' || action === 'git_push' || action === 'git_pull') {
                refreshGit();
            }
        });
    };

    // ── build ─────────────────────────────────────────────────────────────────
    window.runBuild = function () {
        var out = document.getElementById('build-out');
        var btn = document.getElementById('build-btn');
        term(out, 'Building...', false);
        if (btn) { btn.disabled = true; }
        api('build', null, function (d) {
            term(out, d.output || d.error || JSON.stringify(d), false);
            if (btn) { btn.disabled = false; }
        });
    };

    // ── notes ─────────────────────────────────────────────────────────────────
    window.loadNotes = function () {
        var list = document.getElementById('notes-list');
        if (!list) { return; }
        list.textContent = 'Loading...';
        api('notes_list', null, function (notes) {
            if (!Array.isArray(notes) || notes.length === 0) {
                list.innerHTML = '<p style="color:#556b69;font-size:0.85rem;font-style:italic;">No notes yet.</p>';
                return;
            }
            list.innerHTML = '';
            notes.forEach(function (n) {
                var row = document.createElement('div');
                row.className = 'note-row';
                var title = document.createElement('span');
                title.className = 'note-row-title';
                title.textContent = n.title;
                var meta = document.createElement('span');
                meta.className = 'note-row-meta';
                meta.textContent = [n.date, n.author ? 'by ' + n.author : ''].filter(Boolean).join(' | ');
                row.appendChild(title);
                row.appendChild(meta);
                list.appendChild(row);
            });
        });
    };

    window.addNote = function () {
        var out = document.getElementById('note-out');
        var user  = (document.getElementById('note-user')  || {}).value || '';
        var title = (document.getElementById('note-title') || {}).value || '';
        var body  = (document.getElementById('note-body')  || {}).value || '';
        var tags  = (document.getElementById('note-tags')  || {}).value || '';
        if (!user || !title || !body) {
            term(out, 'Name, title, and body are required.', false);
            return;
        }
        term(out, 'Saving...', false);
        api('notes_add', { user: user, title: title, body: body, tags: tags }, function (d) {
            term(out, d.output || d.error || JSON.stringify(d), false);
            if (d.ok) { loadNotes(); }
        });
    };

    // ── settings ──────────────────────────────────────────────────────────────
    window.loadSettings = function () {
        api('settings_read', null, function (d) {
            var ta = document.getElementById('settings-content');
            if (ta) { ta.value = d.content || ''; }
        });
    };

    window.saveSettings = function () {
        var ta  = document.getElementById('settings-content');
        var out = document.getElementById('settings-out');
        var content = ta ? ta.value : '';
        term(out, 'Saving...', false);
        api('settings_write', { content: content }, function (d) {
            term(out, d.output || d.error || JSON.stringify(d), false);
        });
    };

    // ── appearance ────────────────────────────────────────────────────────────
    var PRESETS = {
        'default':  { primary:'#244c47', primaryDk:'#1a3835', accent:'#459289', bg:'#fcfdfd',
                      surface:'#eaf5f4', border:'#c8dedd', text:'#182523', muted:'#556b69',
                      headerBg:'#081110', headerText:'#eaf5f4', tabBg:'#181f1e',
                      termBg:'#081110', termText:'#a8ccc9' },
        'light':    { primary:'#1a2744', primaryDk:'#111a2e', accent:'#2d4fa3', bg:'#ffffff',
                      surface:'#e8edf8', border:'#b8c8e8', text:'#0e1724', muted:'#3d5275',
                      headerBg:'#1a2744', headerText:'#e8edf8', tabBg:'#111a2e',
                      termBg:'#0a0e18', termText:'#9ab4d8' },
        'midnight': { primary:'#5c3d8f', primaryDk:'#3d2460', accent:'#a07fd8', bg:'#13111e',
                      surface:'#2a1f3d', border:'#3d2e5e', text:'#cec4e8', muted:'#7060a0',
                      headerBg:'#0d0d14', headerText:'#cec4e8', tabBg:'#0d0d14',
                      termBg:'#080810', termText:'#b0a0d8' },
        'slate':    { primary:'#1e2a38', primaryDk:'#131c28', accent:'#3b6ea5', bg:'#f4f7fb',
                      surface:'#dde8f5', border:'#b0c8e5', text:'#0e1824', muted:'#3a5070',
                      headerBg:'#1e2a38', headerText:'#dde8f5', tabBg:'#131c28',
                      termBg:'#0a0e14', termText:'#90b4d0' },
        'forest':   { primary:'#1a2e1a', primaryDk:'#0f1e0f', accent:'#3a6b3a', bg:'#f8fbf8',
                      surface:'#eaf2ea', border:'#bbd8bb', text:'#0f1e0f', muted:'#3a5a3a',
                      headerBg:'#1a2e1a', headerText:'#eaf2ea', tabBg:'#0f1e0f',
                      termBg:'#0a1208', termText:'#90c890' },
        'mono':     { primary:'#111111', primaryDk:'#000000', accent:'#555555', bg:'#ffffff',
                      surface:'#eeeeee', border:'#cccccc', text:'#111111', muted:'#666666',
                      headerBg:'#111111', headerText:'#eeeeee', tabBg:'#000000',
                      termBg:'#111111', termText:'#dddddd' }
    };

    function applyVars(p, radius, fontFamily, fontSize) {
        var r = document.documentElement;
        r.style.setProperty('--dash-primary',     p.primary);
        r.style.setProperty('--dash-primary-dk',  p.primaryDk);
        r.style.setProperty('--dash-accent',      p.accent);
        r.style.setProperty('--dash-bg',          p.bg);
        r.style.setProperty('--dash-surface',     p.surface);
        r.style.setProperty('--dash-border',      p.border);
        r.style.setProperty('--dash-text',        p.text);
        r.style.setProperty('--dash-muted',       p.muted);
        r.style.setProperty('--dash-header-bg',   p.headerBg);
        r.style.setProperty('--dash-header-text', p.headerText);
        r.style.setProperty('--dash-tab-bg',      p.tabBg);
        r.style.setProperty('--dash-term-bg',     p.termBg);
        r.style.setProperty('--dash-term-text',   p.termText);
        r.style.setProperty('--dash-radius',      (radius || 0) + 'px');
        r.style.setProperty('--dash-font',        fontFamily || PRESETS['default'].font || '-apple-system, BlinkMacSystemFont, \'Segoe UI\', sans-serif');
        r.style.fontSize = (fontSize || 15) + 'px';
    }

    function syncInputsFromPreset(key) {
        var p = PRESETS[key];
        if (!p) return;
        document.getElementById('ap-primary').value    = p.primary;
        document.getElementById('ap-accent').value     = p.accent;
        document.getElementById('ap-bg').value         = p.bg;
        document.getElementById('ap-header-bg').value  = p.headerBg;
        document.getElementById('ap-term-bg').value    = p.termBg;
        document.getElementById('ap-term-text').value  = p.termText;
    }

    function currentCustom() {
        return {
            primary:    document.getElementById('ap-primary').value,
            primaryDk:  blendHex(document.getElementById('ap-primary').value, '#000000', 0.25),
            accent:     document.getElementById('ap-accent').value,
            bg:         document.getElementById('ap-bg').value,
            surface:    blendHex(document.getElementById('ap-bg').value, document.getElementById('ap-primary').value, 0.08),
            border:     blendHex(document.getElementById('ap-bg').value, document.getElementById('ap-primary').value, 0.25),
            text:       blendHex(document.getElementById('ap-bg').value, '#000000', 0.88),
            muted:      blendHex(document.getElementById('ap-bg').value, document.getElementById('ap-primary').value, 0.55),
            headerBg:   document.getElementById('ap-header-bg').value,
            headerText: blendHex(document.getElementById('ap-header-bg').value, '#ffffff', 0.85),
            tabBg:      blendHex(document.getElementById('ap-header-bg').value, '#000000', 0.25),
            termBg:     document.getElementById('ap-term-bg').value,
            termText:   document.getElementById('ap-term-text').value
        };
    }

    function blendHex(hex1, hex2, t) {
        function parse(h) {
            h = h.replace('#','');
            if (h.length === 3) h = h[0]+h[0]+h[1]+h[1]+h[2]+h[2];
            return [parseInt(h.slice(0,2),16), parseInt(h.slice(2,4),16), parseInt(h.slice(4,6),16)];
        }
        var a = parse(hex1), b = parse(hex2);
        var r = Math.round(a[0]*(1-t)+b[0]*t);
        var g = Math.round(a[1]*(1-t)+b[1]*t);
        var bv= Math.round(a[2]*(1-t)+b[2]*t);
        return '#'+[r,g,bv].map(function(x){return ('0'+x.toString(16)).slice(-2);}).join('');
    }

    function getRadius()   { return parseInt(document.getElementById('ap-radius').value, 10) || 0; }
    function getFontSize() { return parseInt(document.getElementById('ap-fontsize').value, 10) || 15; }
    function getFont()     { return document.getElementById('ap-font').value; }

    window.applyAppearance = function () {
        var p      = currentCustom();
        var radius = getRadius();
        var fs     = getFontSize();
        var font   = getFont();
        applyVars(p, radius, font, fs);
        var saved  = { preset: 'custom', custom: p, radius: radius, fontSize: fs, font: font };
        localStorage.setItem('dashAppearance', JSON.stringify(saved));
        var out = document.getElementById('appearance-out');
        out.style.display = 'block';
        term(out, '✔ Appearance applied and saved to localStorage.', false);
    };

    window.resetAppearance = function () {
        localStorage.removeItem('dashAppearance');
        var def = PRESETS['default'];
        applyVars(def, 0, null, 15);
        syncInputsFromPreset('default');
        document.getElementById('ap-radius').value = 0;
        document.getElementById('ap-radius-val').textContent = '0px';
        document.getElementById('ap-fontsize').value = 15;
        document.getElementById('ap-fontsize-val').textContent = '15px';
        document.getElementById('ap-font').value = '-apple-system, BlinkMacSystemFont, \'Segoe UI\', sans-serif';
        document.querySelectorAll('#preset-grid .appearance-card').forEach(function(c){ c.classList.remove('selected'); });
        document.querySelector('#preset-grid [data-preset="default"]').classList.add('selected');
        var out = document.getElementById('appearance-out');
        out.style.display = 'block';
        term(out, '✔ Reset to default.', false);
    };

    (function initAppearance() {
        // preset card clicks
        document.querySelectorAll('#preset-grid .appearance-card').forEach(function(card) {
            card.addEventListener('click', function() {
                var key = this.dataset.preset;
                document.querySelectorAll('#preset-grid .appearance-card').forEach(function(c){ c.classList.remove('selected'); });
                this.classList.add('selected');
                syncInputsFromPreset(key);
                applyVars(PRESETS[key], getRadius(), getFont(), getFontSize());
            }.bind(card));
        });

        // range display
        document.getElementById('ap-radius').addEventListener('input', function() {
            document.getElementById('ap-radius-val').textContent = this.value + 'px';
        });
        document.getElementById('ap-fontsize').addEventListener('input', function() {
            document.getElementById('ap-fontsize-val').textContent = this.value + 'px';
        });

        // restore saved settings
        try {
            var saved = JSON.parse(localStorage.getItem('dashAppearance') || 'null');
            if (saved) {
                var p = (saved.preset !== 'custom' && PRESETS[saved.preset]) ? PRESETS[saved.preset] : saved.custom;
                applyVars(p, saved.radius || 0, saved.font || null, saved.fontSize || 15);
                // sync UI
                if (saved.preset !== 'custom' && PRESETS[saved.preset]) {
                    syncInputsFromPreset(saved.preset);
                    document.querySelectorAll('#preset-grid .appearance-card').forEach(function(c){ c.classList.remove('selected'); });
                    var card = document.querySelector('#preset-grid [data-preset="' + saved.preset + '"]');
                    if (card) card.classList.add('selected');
                } else if (saved.custom) {
                    document.getElementById('ap-primary').value   = saved.custom.primary   || '#244c47';
                    document.getElementById('ap-accent').value    = saved.custom.accent    || '#459289';
                    document.getElementById('ap-bg').value        = saved.custom.bg        || '#fcfdfd';
                    document.getElementById('ap-header-bg').value = saved.custom.headerBg  || '#081110';
                    document.getElementById('ap-term-bg').value   = saved.custom.termBg    || '#081110';
                    document.getElementById('ap-term-text').value = saved.custom.termText  || '#a8ccc9';
                }
                if (saved.radius !== undefined) {
                    document.getElementById('ap-radius').value       = saved.radius;
                    document.getElementById('ap-radius-val').textContent = saved.radius + 'px';
                }
                if (saved.fontSize) {
                    document.getElementById('ap-fontsize').value       = saved.fontSize;
                    document.getElementById('ap-fontsize-val').textContent = saved.fontSize + 'px';
                }
                if (saved.font) {
                    document.getElementById('ap-font').value = saved.font;
                }
            }
        } catch(e) {}
    }());

    // ── init ─────────────────────────────────────────────────────────────────
    loadOverview();

}());
</script>

</body>
</html>
