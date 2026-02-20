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
        html { font-size: 15px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #182523; background: #fcfdfd; }
        body { min-height: 100vh; display: flex; flex-direction: column; }
        a { color: #244c47; }

        /* header */
        .dash-header {
            background: #081110;
            color: #eaf5f4;
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            border-bottom: 2px solid #244c47;
        }
        .dash-header-left { display: flex; align-items: baseline; gap: 1rem; }
        .dash-title { font-size: 1rem; font-weight: 700; letter-spacing: 0.03em; color: #eaf5f4; }
        .dash-subtitle { font-size: 0.72rem; color: #556b69; }
        .dash-status { font-size: 0.72rem; color: #459289; font-family: monospace; }

        /* tabs */
        .tab-bar {
            background: #181f1e;
            display: flex;
            gap: 0;
            border-bottom: 2px solid #244c47;
            overflow-x: auto;
        }
        .tab-btn {
            background: none;
            border: none;
            color: #556b69;
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
        .tab-btn:hover { color: #a8ccc9; }
        .tab-btn.active { color: #eaf5f4; border-bottom-color: #459289; }

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
            background: #eaf5f4;
            border-left: 4px solid #244c47;
            padding: 0.65rem 0.85rem;
        }
        .info-card-label { font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.08em; color: #556b69; margin-bottom: 0.2rem; }
        .info-card-value { font-size: 0.92rem; font-weight: 700; color: #182523; }
        .info-card-value.ok { color: #244c47; }
        .info-card-value.warn { color: #8a4a00; }

        /* shortcut links */
        .shortcut-row { display: flex; flex-wrap: wrap; gap: 0.6rem; margin-bottom: 1.5rem; }
        .shortcut-link {
            font-size: 0.78rem;
            font-weight: 600;
            color: #eaf5f4;
            background: #244c47;
            padding: 0.4rem 0.85rem;
            text-decoration: none;
            border: 1px solid #244c47;
        }
        .shortcut-link:hover { background: #1a3835; }

        /* terminal output */
        .terminal {
            background: #081110;
            color: #a8ccc9;
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
        .terminal.empty { color: #556b69; font-style: italic; }

        /* forms / inputs */
        .field-row { margin-bottom: 0.85rem; }
        .field-label { display: block; font-size: 0.72rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; color: #556b69; margin-bottom: 0.3rem; }
        .field-input, .field-textarea, .field-select {
            width: 100%;
            padding: 0.5rem 0.65rem;
            border: 1px solid #c8dedd;
            background: #fff;
            color: #182523;
            font-size: 0.85rem;
            font-family: inherit;
        }
        .field-textarea { font-family: 'SFMono-Regular', 'Consolas', monospace; font-size: 0.76rem; resize: vertical; }
        .field-input:focus, .field-textarea:focus { outline: 2px solid #244c47; }

        /* buttons */
        .btn {
            font-size: 0.78rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 0.5rem 1rem;
            border: none;
            cursor: pointer;
        }
        .btn-primary { background: #244c47; color: #eaf5f4; }
        .btn-primary:hover { background: #1a3835; }
        .btn-secondary { background: #eaf5f4; color: #244c47; border: 1px solid #244c47; }
        .btn-secondary:hover { background: #d4e8e6; }
        .btn-danger { background: #5a1a1a; color: #fce8e8; }
        .btn-danger:hover { background: #3d0f0f; }
        .btn-row { display: flex; gap: 0.6rem; flex-wrap: wrap; margin-bottom: 1rem; }

        /* git log */
        .git-log-line { padding: 0.3rem 0; border-bottom: 1px solid #eaf5f4; font-size: 0.8rem; font-family: monospace; color: #182523; }
        .git-log-line:last-child { border-bottom: none; }
        .git-hash { color: #244c47; font-weight: 700; margin-right: 0.5rem; }
        .git-branch-badge {
            display: inline-block;
            background: #244c47;
            color: #eaf5f4;
            font-size: 0.65rem;
            font-weight: 700;
            padding: 0.1rem 0.4rem;
            font-family: monospace;
            margin-right: 0.5rem;
            vertical-align: middle;
        }

        /* notes list */
        .note-row {
            border-bottom: 1px solid #eaf5f4;
            padding: 0.55rem 0;
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .note-row-title { font-size: 0.85rem; font-weight: 600; color: #182523; }
        .note-row-meta { font-size: 0.72rem; color: #556b69; font-family: monospace; }

        /* settings textarea */
        #settings-content { min-height: 360px; }

        /* separator */
        .row-gap { margin-bottom: 1.75rem; }

        /* status badge */
        .badge { font-size: 0.65rem; font-weight: 700; padding: 0.1rem 0.4rem; text-transform: uppercase; letter-spacing: 0.06em; display: inline-block; }
        .badge-ok   { background: #eaf5f4; color: #244c47; border: 1px solid #244c47; }
        .badge-warn { background: #fff3e0; color: #8a4a00; border: 1px solid #b06a00; }

        .dash-footer {
            padding: 0.5rem 1.5rem;
            background: #081110;
            color: #556b69;
            font-size: 0.68rem;
            text-align: center;
        }
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
    <button class="tab-btn active" data-panel="panel-overview"   role="tab">Overview</button>
    <button class="tab-btn"        data-panel="panel-github"     role="tab">GitHub</button>
    <button class="tab-btn"        data-panel="panel-build"      role="tab">Build</button>
    <button class="tab-btn"        data-panel="panel-notes"      role="tab">Notes</button>
    <button class="tab-btn"        data-panel="panel-settings"   role="tab">Settings</button>
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

    // ── init ─────────────────────────────────────────────────────────────────
    loadOverview();

}());
</script>

</body>
</html>
