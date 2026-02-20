<?php
declare(strict_types=1);

function printUsage(): void
{
    $usage = <<<TXT
Usage:
  php project_mgr/add_note.php --user "Your Name" --title "Note title" --body "Your note text" [--tags "tag1,tag2"] [--source "link"] [--id "custom-id"]

Options:
  --user        Required. Contributor name.
  --title       Required. Note title.
  --body        Required. Note content.
  --tags        Optional. Comma-separated tags.
  --source      Optional. Reference link or source.
  --id          Optional. Custom ID for the note filename.
  --notes-dir   Optional. Override notes directory.
  --contributors-file Optional. Override contributors file path.

TXT;
    fwrite(STDERR, $usage);
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    return trim($text, '-');
}

function ensureDirectory(string $dir): void
{
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException("Unable to create directory: {$dir}");
    }
}

function normalizeDate(string $date): string
{
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return date('Y-m-d');
    }
    return date('Y-m-d', $timestamp);
}

$options = getopt('', [
    'user:',
    'title:',
    'body:',
    'tags::',
    'source::',
    'id::',
    'notes-dir::',
    'contributors-file::',
]);

$user = $options['user'] ?? '';
$title = $options['title'] ?? '';
$body = $options['body'] ?? '';
$tags = $options['tags'] ?? '';
$source = $options['source'] ?? '';
$customId = $options['id'] ?? '';

if ($user === '' || $title === '' || $body === '') {
    printUsage();
    exit(1);
}

$notesDir = $options['notes-dir'] ?? (__DIR__ . '/notes');
$contributorsFile = $options['contributors-file'] ?? (__DIR__ . '/CONTRIBUTORS.md');

ensureDirectory($notesDir);

$date = normalizeDate(date('Y-m-d'));
$slug = $customId !== '' ? slugify($customId) : slugify($title);
$filename = $date . '-' . ($slug !== '' ? $slug : 'note') . '.md';
$notePath = rtrim($notesDir, '/') . '/' . $filename;

$tagsList = array_filter(array_map('trim', explode(',', (string)$tags)));
$tagsLine = $tagsList ? implode(', ', $tagsList) : 'none';

$noteContent = "# {$title}\n\n";
$noteContent .= "- Date: {$date}\n";
$noteContent .= "- Author: {$user}\n";
$noteContent .= "- Tags: {$tagsLine}\n";
if ($source !== '') {
    $noteContent .= "- Source: {$source}\n";
}
$noteContent .= "\n## Notes\n\n{$body}\n";

if (file_exists($notePath)) {
    fwrite(STDERR, "Note already exists: {$notePath}\n");
    exit(1);
}

if (file_put_contents($notePath, $noteContent) === false) {
    fwrite(STDERR, "Failed to write note: {$notePath}\n");
    exit(1);
}

if (!file_exists($contributorsFile)) {
    $header = "# Contributors\n\n";
    $header .= "This list tracks contributors who add notes in project_mgr/notes.\n\n";
    $header .= "| Contributor | First Added | Notes |\n";
    $header .= "| --- | --- | --- |\n\n";
    file_put_contents($contributorsFile, $header);
}

$contributors = file_get_contents($contributorsFile);
if ($contributors === false) {
    fwrite(STDERR, "Failed to read contributors file.\n");
    exit(1);
}

$pattern = '/\|\s*' . preg_quote($user, '/') . '\s*\|/i';
if (!preg_match($pattern, $contributors)) {
    $entry = "| {$user} | {$date} | Added note: {$filename} |\n";
    $contributors .= $entry;
    if (file_put_contents($contributorsFile, $contributors) === false) {
        fwrite(STDERR, "Failed to update contributors file.\n");
        exit(1);
    }
}

fwrite(STDOUT, "Note created: {$notePath}\n");
fwrite(STDOUT, "Contributor recorded: {$user}\n");

// Sync README with the latest notes
require_once __DIR__ . '/sync_readme.php';
$readmeFile  = __DIR__ . '/../README.md';
$syncResult  = syncReadme(rtrim($notesDir, '/'), $readmeFile);
if ($syncResult !== 0) {
    fwrite(STDERR, "Warning: README sync failed.\n");
}

exit(0);