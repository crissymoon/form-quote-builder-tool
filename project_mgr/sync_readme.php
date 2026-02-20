<?php
declare(strict_types=1);

/**
 * sync_readme.php
 *
 * Reads the last four dated note files from project_mgr/notes/,
 * builds a "Recent Updates" block, and inserts or replaces it near
 * the top of README.md (right after the first --- separator).
 *
 * Can be called directly:
 *   php project_mgr/sync_readme.php
 *
 * Or included from add_note.php:
 *   require_once __DIR__ . '/sync_readme.php';
 *   $code = syncReadme(__DIR__ . '/notes', __DIR__ . '/../README.md');
 */

const README_START = '<!-- xcm:recent-updates:start -->';
const README_END   = '<!-- xcm:recent-updates:end -->';

function parseNote(string $filePath): ?array
{
    $raw = file_get_contents($filePath);
    if ($raw === false) {
        return null;
    }

    $lines  = explode("\n", $raw);
    $title  = trim(ltrim($lines[0] ?? 'Untitled', '# '));
    $date   = '';
    $author = '';
    $inNotes = false;
    $bodyLines = [];

    foreach ($lines as $i => $line) {
        if ($i === 0) {
            continue;
        }
        if (preg_match('/^- Date:\s*(.+)$/', $line, $m)) {
            $date = trim($m[1]);
            continue;
        }
        if (preg_match('/^- Author:\s*(.+)$/', $line, $m)) {
            $author = trim($m[1]);
            continue;
        }
        if (trim($line) === '## Notes') {
            $inNotes = true;
            continue;
        }
        if ($inNotes) {
            $bodyLines[] = $line;
        }
    }

    // Strip leading and trailing blank lines from body
    while (!empty($bodyLines) && trim($bodyLines[0]) === '') {
        array_shift($bodyLines);
    }
    while (!empty($bodyLines) && trim((string) end($bodyLines)) === '') {
        array_pop($bodyLines);
    }

    $fullBody = implode(' ', array_filter(array_map('trim', $bodyLines)));
    $preview  = mb_strlen($fullBody) > 160
        ? rtrim(mb_substr($fullBody, 0, 157)) . '...'
        : $fullBody;

    return [
        'title'   => $title ?: 'Untitled',
        'date'    => $date,
        'author'  => $author,
        'preview' => $preview,
    ];
}

function buildUpdatesBlock(array $notes): string
{
    $block  = README_START . "\n\n";
    $block .= "## Recent Updates\n\n";

    if (empty($notes)) {
        $block .= "_No updates recorded yet._\n\n";
        $block .= README_END . "\n";
        return $block;
    }

    foreach ($notes as $note) {
        $block .= "**" . $note['title'] . "**\n";

        $meta = array_filter([
            $note['date'],
            $note['author'] !== '' ? 'by ' . $note['author'] : '',
        ]);
        if (!empty($meta)) {
            $block .= implode(' | ', $meta) . "\n";
        }

        if ($note['preview'] !== '') {
            $block .= $note['preview'] . "\n";
        }

        $block .= "\n";
    }

    $block .= README_END . "\n";
    return $block;
}

function syncReadme(string $notesDir, string $readmeFile): int
{
    // Collect only date-prefixed note files
    $pattern = rtrim($notesDir, '/') . '/[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]-*.md';
    $files   = glob($pattern);
    if ($files === false) {
        $files = [];
    }

    sort($files);
    $recent = array_slice($files, -4);

    // Parse, reverse so newest is first
    $notes = [];
    foreach (array_reverse($recent) as $file) {
        $note = parseNote($file);
        if ($note !== null) {
            $notes[] = $note;
        }
    }

    $block = buildUpdatesBlock($notes);

    $readme = file_get_contents($readmeFile);
    if ($readme === false) {
        fwrite(STDERR, "sync_readme: Cannot read {$readmeFile}\n");
        return 1;
    }

    if (str_contains($readme, README_START)) {
        // Replace existing block
        $updated = preg_replace(
            '/' . preg_quote(README_START, '/') . '.*?' . preg_quote(README_END, '/') . '\n?/s',
            $block,
            $readme
        );
    } else {
        // Insert after the first standalone --- line
        $updated = preg_replace('/^(---)$/m', "$1\n\n" . $block, $readme, 1);
    }

    if ($updated === null || $updated === $readme) {
        fwrite(STDERR, "sync_readme: No insertion point found in README.md. Add a --- separator after the header.\n");
        return 1;
    }

    if (file_put_contents($readmeFile, $updated) === false) {
        fwrite(STDERR, "sync_readme: Cannot write {$readmeFile}\n");
        return 1;
    }

    $count = count($notes);
    fwrite(STDOUT, "README.md synced with {$count} recent note(s).\n");
    return 0;
}

// Run directly as CLI script
if (basename(__FILE__) === basename($argv[0] ?? '')) {
    $notesDir   = __DIR__ . '/notes';
    $readmeFile = __DIR__ . '/../README.md';
    exit(syncReadme($notesDir, $readmeFile));
}
