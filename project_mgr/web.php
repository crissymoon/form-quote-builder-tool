<?php
declare(strict_types=1);

if (php_sapi_name() !== 'cli-server') {
    http_response_code(403);
    exit('Project manager is only accessible through the development server.');
}

define('MGR_NOTES_DIR',        __DIR__ . '/notes');
define('MGR_CONTRIBUTORS_FILE', __DIR__ . '/CONTRIBUTORS.md');

function parseMgrNotes(string $dir): array
{
    $pattern = rtrim($dir, '/') . '/[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]-*.md';
    $files   = glob($pattern) ?: [];
    rsort($files);

    $notes = [];
    foreach ($files as $file) {
        $raw = file_get_contents($file);
        if ($raw === false) {
            continue;
        }
        $lines   = explode("\n", $raw);
        $title   = trim(ltrim($lines[0] ?? 'Untitled', '# '));
        $date    = '';
        $author  = '';
        $tags    = '';
        $inNotes = false;
        $body    = [];

        foreach ($lines as $i => $line) {
            if ($i === 0) {
                continue;
            }
            if (preg_match('/^- Date:\s*(.+)$/', $line, $m))   { $date   = trim($m[1]); continue; }
            if (preg_match('/^- Author:\s*(.+)$/', $line, $m)) { $author = trim($m[1]); continue; }
            if (preg_match('/^- Tags:\s*(.+)$/', $line, $m))   { $tags   = trim($m[1]); continue; }
            if (trim($line) === '## Notes')                     { $inNotes = true; continue; }
            if ($inNotes) {
                $body[] = $line;
            }
        }

        while (!empty($body) && trim($body[0]) === '') {
            array_shift($body);
        }
        while (!empty($body) && trim((string) end($body)) === '') {
            array_pop($body);
        }

        $notes[] = [
            'title'    => $title ?: 'Untitled',
            'date'     => htmlspecialchars($date,   ENT_QUOTES, 'UTF-8'),
            'author'   => htmlspecialchars($author, ENT_QUOTES, 'UTF-8'),
            'tags'     => htmlspecialchars($tags,   ENT_QUOTES, 'UTF-8'),
            'body'     => htmlspecialchars(implode("\n", $body), ENT_QUOTES, 'UTF-8'),
            'filename' => basename($file),
        ];
    }
    return $notes;
}

function parseMgrContributors(string $file): array
{
    if (!file_exists($file)) {
        return [];
    }
    $raw = file_get_contents($file);
    if ($raw === false) {
        return [];
    }

    $contributors = [];
    $inTable      = false;

    foreach (explode("\n", $raw) as $line) {
        $line = trim($line);
        if (str_starts_with($line, '| Contributor')) { $inTable = true; continue; }
        if ($inTable && str_starts_with($line, '| ---')) { continue; }
        if ($inTable && str_starts_with($line, '|')) {
            $parts = array_map('trim', explode('|', trim($line, '|')));
            if (!empty($parts[0])) {
                $contributors[] = [
                    'name'  => htmlspecialchars($parts[0],      ENT_QUOTES, 'UTF-8'),
                    'date'  => htmlspecialchars($parts[1] ?? '', ENT_QUOTES, 'UTF-8'),
                    'notes' => htmlspecialchars($parts[2] ?? '', ENT_QUOTES, 'UTF-8'),
                ];
            }
        }
    }
    return $contributors;
}

$notes        = parseMgrNotes(MGR_NOTES_DIR);
$contributors = parseMgrContributors(MGR_CONTRIBUTORS_FILE);
$noteCount    = count($notes);
$contribCount = count($contributors);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Manager - XcaliburMoon</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        html { font-size: 16px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; color: #182523; background: #fcfdfd; }
        body { min-height: 100vh; display: flex; flex-direction: column; }
        a { color: #244c47; }
        a:hover { color: #459289; }

        .mgr-header {
            background: #244c47;
            color: #eaf5f4;
            padding: 1rem 1.5rem;
            border-bottom: 3px solid #081110;
        }
        .mgr-header h1 { font-size: 1.2rem; font-weight: 700; letter-spacing: 0.02em; }
        .mgr-header p { font-size: 0.78rem; color: #a8ccc9; margin-top: 0.2rem; }

        .mgr-main { max-width: 940px; margin: 0 auto; padding: 2rem 1.5rem; width: 100%; flex: 1; }

        .mgr-meta {
            display: flex;
            gap: 2rem;
            padding: 0.85rem 1.25rem;
            background: #eaf5f4;
            border-left: 4px solid #244c47;
            margin-bottom: 2rem;
            font-size: 0.85rem;
        }
        .mgr-meta strong { color: #244c47; }

        .mgr-section-title {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #244c47;
            border-bottom: 2px solid #244c47;
            padding-bottom: 0.35rem;
            margin-bottom: 1.25rem;
        }

        .mgr-cmd {
            background: #081110;
            color: #a8ccc9;
            padding: 0.85rem 1.1rem;
            font-family: 'SFMono-Regular', 'Consolas', monospace;
            font-size: 0.78rem;
            margin-bottom: 2rem;
            overflow-x: auto;
        }
        .mgr-cmd .cmd-label {
            color: #556b69;
            font-size: 0.68rem;
            display: block;
            margin-bottom: 0.4rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .mgr-cmd code { color: #eaf5f4; }

        .note-card { border: 1px solid #c8dedd; margin-bottom: 1.1rem; background: #fff; }
        .note-card-header {
            padding: 0.65rem 1rem;
            background: #eaf5f4;
            border-bottom: 1px solid #c8dedd;
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .note-title { font-weight: 700; font-size: 0.92rem; color: #182523; }
        .note-meta { font-size: 0.74rem; color: #556b69; display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap; }
        .note-tag {
            font-size: 0.68rem;
            color: #244c47;
            background: #d4e8e6;
            padding: 0.1rem 0.4rem;
            font-family: monospace;
        }
        .note-body {
            padding: 0.85rem 1rem;
            font-size: 0.88rem;
            line-height: 1.65;
            white-space: pre-wrap;
            color: #182523;
        }

        .contrib-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        .contrib-table th {
            background: #244c47;
            color: #eaf5f4;
            text-align: left;
            padding: 0.5rem 0.85rem;
            font-weight: 600;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .contrib-table td { padding: 0.5rem 0.85rem; border-bottom: 1px solid #c8dedd; }
        .contrib-table tr:last-child td { border-bottom: none; }
        .contrib-table tr:nth-child(even) td { background: #f4fafa; }

        .empty-state { color: #556b69; font-size: 0.85rem; font-style: italic; padding: 0.5rem 0 1rem; }

        .section-gap { margin-bottom: 2.5rem; }

        .mgr-footer {
            padding: 0.75rem 1.5rem;
            background: #081110;
            color: #556b69;
            font-size: 0.72rem;
            text-align: center;
        }
    </style>
</head>
<body>

<header class="mgr-header">
    <h1>Project Manager</h1>
    <p>XcaliburMoon Web Development - Development view only</p>
</header>

<main class="mgr-main">

    <div class="mgr-meta">
        <span><strong><?= $noteCount ?></strong> note<?= $noteCount !== 1 ? 's' : '' ?></span>
        <span><strong><?= $contribCount ?></strong> contributor<?= $contribCount !== 1 ? 's' : '' ?></span>
    </div>

    <div class="mgr-cmd">
        <span class="cmd-label">Add a note</span>
        <code>php project_mgr/add_note.php --user "Name" --title "Title" --body "Body text" --tags "tag1,tag2"</code>
    </div>

    <div class="section-gap">
        <p class="mgr-section-title">Notes (<?= $noteCount ?>)</p>
        <?php if (empty($notes)): ?>
            <p class="empty-state">No notes yet. Use the command above to add one.</p>
        <?php else: ?>
            <?php foreach ($notes as $note): ?>
            <div class="note-card">
                <div class="note-card-header">
                    <span class="note-title"><?= htmlspecialchars($note['title'], ENT_QUOTES, 'UTF-8') ?></span>
                    <span class="note-meta">
                        <?php if ($note['date']): ?><span><?= $note['date'] ?></span><?php endif; ?>
                        <?php if ($note['author']): ?><span>by <?= $note['author'] ?></span><?php endif; ?>
                        <?php if ($note['tags'] && $note['tags'] !== 'none'): ?>
                            <span class="note-tag"><?= $note['tags'] ?></span>
                        <?php endif; ?>
                    </span>
                </div>
                <?php if ($note['body']): ?>
                    <div class="note-body"><?= $note['body'] ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="section-gap">
        <p class="mgr-section-title">Contributors (<?= $contribCount ?>)</p>
        <?php if (empty($contributors)): ?>
            <p class="empty-state">No contributors recorded yet.</p>
        <?php else: ?>
            <table class="contrib-table">
                <thead>
                    <tr>
                        <th>Contributor</th>
                        <th>First Added</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contributors as $c): ?>
                    <tr>
                        <td><?= $c['name'] ?></td>
                        <td><?= $c['date'] ?></td>
                        <td><?= $c['notes'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</main>

<footer class="mgr-footer">
    Project Manager - Development use only. Not included in build output.
</footer>

</body>
</html>
