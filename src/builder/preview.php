<?php
declare(strict_types=1);
/**
 * Form Builder — Preview Renderer
 * $form is already loaded by index.php
 */
$d     = $form['design'] ?? [];
$steps = $form['steps'] ?? [];

$fonts = [
    'system'  => "-apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
    'mono'    => "'SFMono-Regular', 'Consolas', 'Liberation Mono', monospace",
    'serif'   => "Georgia, 'Times New Roman', serif",
    'inter'   => "'Inter', 'Helvetica Neue', Arial, sans-serif",
    'trebuchet' => "'Trebuchet MS', 'Lucida Grande', sans-serif",
];

$fontFamily   = $fonts[$d['font'] ?? 'system'] ?? $fonts['system'];
$primaryColor = htmlspecialchars($d['primaryColor'] ?? '#244c47');
$accentColor  = htmlspecialchars($d['accentColor']  ?? '#459289');
$bgColor      = htmlspecialchars($d['bgColor']      ?? '#fcfdfd');
$textColor    = htmlspecialchars($d['textColor']    ?? '#182523');
$headerBg     = htmlspecialchars($d['headerBg']     ?? '#244c47');
$headerText   = htmlspecialchars($d['headerText']   ?? '#eaf5f4');
$fontSize     = (int) ($d['fontSize'] ?? 16);
$radius       = (int) ($d['borderRadius'] ?? 0);
$headerTitle  = htmlspecialchars($d['headerTitle']  ?? $form['name'] ?? 'Form');
$headerSub    = htmlspecialchars($d['headerSubtitle'] ?? '');
$submitLabel  = htmlspecialchars($d['submitLabel']  ?? 'Submit');
$nextLabel    = htmlspecialchars($d['nextLabel']    ?? 'Next');
$backLabel    = htmlspecialchars($d['backLabel']    ?? 'Back');
$totalSteps   = count($steps);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Preview: <?= htmlspecialchars($form['name'] ?? 'Form') ?></title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: <?= $fontSize ?>px; font-family: <?= $fontFamily ?>; color: <?= $textColor ?>; background: <?= $bgColor ?>; }
body { min-height: 100vh; display: flex; flex-direction: column; }
a { color: <?= $primaryColor ?>; }

.preview-bar {
    background: #111;
    color: #ccc;
    font-family: monospace;
    font-size: 12px;
    padding: 0.4rem 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}
.preview-bar a { color: #90c8c5; text-decoration: none; font-weight: 600; }
.preview-bar a:hover { color: #fff; }

.pv-header {
    background: <?= $headerBg ?>;
    color: <?= $headerText ?>;
    padding: 1rem 1.5rem;
    border-bottom: 3px solid <?= $primaryColor ?>;
}
.pv-header-title { font-size: 1.3rem; font-weight: 700; }
.pv-header-sub { font-size: 0.85rem; opacity: 0.8; margin-top: 0.2rem; }

.pv-main { flex: 1; padding: 2.5rem 1.5rem; }
.pv-container { max-width: 640px; margin: 0 auto; }

.pv-progress-wrap {
    height: 6px;
    background: <?= $accentColor ?>30;
    border: 1px solid <?= $accentColor ?>50;
    margin-bottom: 0.5rem;
    overflow: hidden;
}
.pv-progress { height: 100%; background: <?= $primaryColor ?>; transition: width 0.4s; }
.pv-step-ind {
    font-size: 0.75rem;
    color: <?= $accentColor ?>;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 600;
    margin-bottom: 1.2rem;
}

.pv-step-block {
    background: #fff;
    border: 1px solid <?= $accentColor ?>60;
    padding: 2rem;
    border-radius: <?= $radius ?>px;
}
.pv-step-title {
    font-size: 1.35rem;
    font-weight: 700;
    color: <?= $textColor ?>;
    margin-bottom: 0.35rem;
}
.pv-step-desc {
    font-size: 0.9rem;
    color: <?= $accentColor ?>;
    margin-bottom: 1.5rem;
}
.pv-fields { display: flex; flex-direction: column; gap: 1.2rem; }
.pv-field-group { display: flex; flex-direction: column; gap: 0.4rem; }
.pv-label { font-size: 0.88rem; font-weight: 600; color: <?= $textColor ?>; }
.pv-req { color: #c0392b; margin-left: 0.2rem; }
.pv-input, .pv-select, .pv-textarea {
    width: 100%;
    padding: 0.6rem 0.8rem;
    font-size: 1rem;
    font-family: inherit;
    color: <?= $textColor ?>;
    background: <?= $bgColor ?>;
    border: 1px solid <?= $accentColor ?>;
    outline: none;
    border-radius: <?= $radius ?>px;
}
.pv-input:focus, .pv-select:focus, .pv-textarea:focus {
    border-color: <?= $primaryColor ?>;
    box-shadow: 0 0 0 3px <?= $primaryColor ?>20;
}
.pv-textarea { resize: vertical; min-height: 80px; }
.pv-radio-group, .pv-check-group { display: flex; flex-direction: column; gap: 0.5rem; }
.pv-radio-opt, .pv-check-opt {
    display: flex;
    align-items: flex-start;
    gap: 0.55rem;
    cursor: pointer;
    padding: 0.5rem 0.7rem;
    border: 1px solid <?= $accentColor ?>50;
    border-radius: <?= $radius ?>px;
}
.pv-radio-opt:hover, .pv-check-opt:hover { background: <?= $primaryColor ?>10; }
.pv-radio-opt input, .pv-check-opt input { accent-color: <?= $primaryColor ?>; margin-top: 3px; flex-shrink: 0; }
.pv-opt-text { font-size: 0.9rem; color: <?= $textColor ?>; }
.pv-actions {
    display: flex;
    gap: 0.75rem;
    margin-top: 1.5rem;
    flex-wrap: wrap;
}
.pv-btn {
    padding: 0.65rem 1.5rem;
    font-size: 0.92rem;
    font-family: inherit;
    font-weight: 700;
    cursor: pointer;
    border: 2px solid transparent;
    letter-spacing: 0.02em;
    border-radius: <?= $radius ?>px;
}
.pv-btn-primary {
    background: <?= $primaryColor ?>;
    color: <?= $headerText ?>;
    border-color: <?= $primaryColor ?>;
}
.pv-btn-primary:hover { opacity: 0.85; }
.pv-btn-secondary {
    background: transparent;
    color: <?= $primaryColor ?>;
    border-color: <?= $primaryColor ?>;
}
.pv-btn-secondary:hover { background: <?= $primaryColor ?>15; }

.pv-dots {
    display: flex;
    gap: 0.5rem;
    justify-content: center;
    margin-top: 1.5rem;
}
.pv-dot {
    width: 8px; height: 8px;
    background: <?= $accentColor ?>40;
    cursor: pointer;
    transition: background 0.2s;
}
.pv-dot.active { background: <?= $primaryColor ?>; }

.pv-footer {
    background: <?= $headerBg ?>;
    color: <?= $headerText ?>99;
    padding: 1rem 1.5rem;
    text-align: center;
    font-size: 0.78rem;
    border-top: 2px solid <?= $primaryColor ?>;
}
</style>
</head>
<body>

<div class="preview-bar">
    <span>PREVIEW &nbsp;|&nbsp; <?= htmlspecialchars($form['name'] ?? 'Form') ?></span>
    <a href="/form-builder?edit=<?= htmlspecialchars(urlencode($form['id'])) ?>">&larr; Back to Editor</a>
</div>

<header class="pv-header">
    <div class="pv-header-title"><?= $headerTitle ?></div>
    <?php if ($headerSub): ?><div class="pv-header-sub"><?= $headerSub ?></div><?php endif; ?>
</header>

<main class="pv-main">
<div class="pv-container" id="pv-app">

<?php foreach ($steps as $si => $step):
    $stepNum  = $si + 1;
    $pct      = (int) round($stepNum / $totalSteps * 100);
    $isFirst  = $si === 0;
    $isLast   = $si === $totalSteps - 1;
    $display  = $si === 0 ? '' : 'display:none;';
?>
<div class="pv-step" id="pv-step-<?= $si ?>" style="<?= $display ?>">
    <div class="pv-progress-wrap">
        <div class="pv-progress" style="width:<?= $pct ?>%"></div>
    </div>
    <p class="pv-step-ind">Step <?= $stepNum ?> of <?= $totalSteps ?></p>

    <div class="pv-step-block">
        <h2 class="pv-step-title"><?= htmlspecialchars($step['title'] ?? '') ?></h2>
        <?php if (!empty($step['description'])): ?>
        <p class="pv-step-desc"><?= htmlspecialchars($step['description']) ?></p>
        <?php endif; ?>

        <div class="pv-fields">
        <?php foreach ($step['fields'] ?? [] as $fi => $field):
            $fid  = 'f_' . $si . '_' . $fi;
            $req  = !empty($field['required']);
            $type = $field['type'] ?? 'text';
        ?>
        <div class="pv-field-group">
            <label class="pv-label" for="<?= $fid ?>">
                <?= htmlspecialchars($field['label'] ?? 'Field') ?>
                <?php if ($req): ?><span class="pv-req">*</span><?php endif; ?>
            </label>

            <?php if ($type === 'text' || $type === 'email' || $type === 'number' || $type === 'tel' || $type === 'url'): ?>
                <input class="pv-input" id="<?= $fid ?>" type="<?= $type ?>" placeholder="<?= htmlspecialchars($field['placeholder'] ?? '') ?>">

            <?php elseif ($type === 'textarea'): ?>
                <textarea class="pv-textarea" id="<?= $fid ?>" placeholder="<?= htmlspecialchars($field['placeholder'] ?? '') ?>"></textarea>

            <?php elseif ($type === 'select'): ?>
                <select class="pv-select" id="<?= $fid ?>">
                    <option value="">-- Select --</option>
                    <?php foreach ($field['options'] ?? [] as $opt): ?>
                    <option><?= htmlspecialchars($opt['label'] ?? $opt) ?></option>
                    <?php endforeach; ?>
                </select>

            <?php elseif ($type === 'radio'): ?>
                <div class="pv-radio-group">
                <?php foreach ($field['options'] ?? [] as $oi => $opt): ?>
                    <label class="pv-radio-opt">
                        <input type="radio" name="<?= $fid ?>">
                        <span class="pv-opt-text"><?= htmlspecialchars($opt['label'] ?? $opt) ?></span>
                    </label>
                <?php endforeach; ?>
                </div>

            <?php elseif ($type === 'checkbox_group'): ?>
                <div class="pv-check-group">
                <?php foreach ($field['options'] ?? [] as $oi => $opt): ?>
                    <label class="pv-check-opt">
                        <input type="checkbox">
                        <span class="pv-opt-text"><?= htmlspecialchars($opt['label'] ?? $opt) ?></span>
                    </label>
                <?php endforeach; ?>
                </div>

            <?php elseif ($type === 'heading'): ?>
                <div style="padding: 0.5rem 0; font-size: 1.1rem; font-weight: 700; color: <?= $primaryColor ?>; border-bottom: 2px solid <?= $primaryColor ?>; margin-bottom: 0.25rem;">
                    <?= htmlspecialchars($field['label'] ?? '') ?>
                </div>

            <?php elseif ($type === 'paragraph'): ?>
                <p style="font-size: 0.9rem; color: <?= $accentColor ?>; line-height: 1.55;">
                    <?= nl2br(htmlspecialchars($field['placeholder'] ?? $field['label'] ?? '')) ?>
                </p>

            <?php elseif ($type === 'divider'): ?>
                <hr style="border: none; border-top: 1px solid <?= $accentColor ?>40; margin: 0.25rem 0;">
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        </div>

        <div class="pv-actions">
            <?php if (!$isFirst): ?>
            <button class="pv-btn pv-btn-secondary" onclick="pvGo(<?= $si - 1 ?>)"><?= $backLabel ?></button>
            <?php endif; ?>
            <?php if (!$isLast): ?>
            <button class="pv-btn pv-btn-primary" onclick="pvGo(<?= $si + 1 ?>)"><?= $nextLabel ?></button>
            <?php else: ?>
            <button class="pv-btn pv-btn-primary" onclick="pvSubmit()"><?= $submitLabel ?></button>
            <?php endif; ?>
        </div>
    </div>

    <div class="pv-dots">
    <?php for ($d2 = 0; $d2 < $totalSteps; $d2++): ?>
        <div class="pv-dot <?= $d2 === $si ? 'active' : '' ?>" onclick="pvGo(<?= $d2 ?>)"></div>
    <?php endfor; ?>
    </div>
</div>
<?php endforeach; ?>

<!-- submitted state -->
<div id="pv-done" style="display:none;">
    <div class="pv-step-block" style="text-align:center; padding: 3rem 2rem;">
        <div style="font-size: 2.5rem; margin-bottom: 1rem; color: <?= $primaryColor ?>;">&#10003;</div>
        <h2 class="pv-step-title" style="margin-bottom: 0.5rem;">Submitted</h2>
        <p class="pv-step-desc">This is a preview — no data was sent.</p>
        <button class="pv-btn pv-btn-secondary" style="margin-top: 1.5rem;" onclick="pvReset()">Reset Preview</button>
    </div>
</div>

</div><!-- /pv-container -->
</main>

<footer class="pv-footer">Preview only — no data is collected.</footer>

<script>
(function(){
    var total  = <?= $totalSteps ?>;
    var cur    = 0;

    function show(n) {
        for (var i = 0; i < total; i++) {
            var el = document.getElementById('pv-step-' + i);
            if (el) el.style.display = i === n ? '' : 'none';
        }
        document.getElementById('pv-done').style.display = 'none';
        cur = n;
    }

    window.pvGo = function(n) {
        if (n >= 0 && n < total) show(n);
    };

    window.pvSubmit = function() {
        for (var i = 0; i < total; i++) {
            var el = document.getElementById('pv-step-' + i);
            if (el) el.style.display = 'none';
        }
        document.getElementById('pv-done').style.display = '';
    };

    window.pvReset = function() {
        show(0);
    };
}());
</script>
</body>
</html>
