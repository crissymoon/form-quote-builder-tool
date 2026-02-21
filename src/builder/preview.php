<?php
declare(strict_types=1);
/**
 * Quote Form Builder -- Live Preview Renderer
 * Reads configured services, complexity, add-ons, contact fields
 * and calculates an accurate quote with budget tier options.
 * $form is already loaded by index.php or src/index.php (live mode).
 *
 * $isBuilderPreview: true when opened from the builder, false when
 * rendered as the live public-facing form.
 */
$isBuilderPreview = $isBuilderPreview ?? false;
$sty   = $form['style']    ?? [];
$lang  = $form['language']  ?? [];
$tiers = $form['tiers']     ?? [
    ['name' => 'Basic',    'multiplier' => 0.9, 'description' => 'Essential features only'],
    ['name' => 'Standard', 'multiplier' => 1.0, 'description' => 'Recommended for most projects'],
    ['name' => 'Premium',  'multiplier' => 1.3, 'description' => 'Full-service with priority support'],
];

$fonts = [
    'system'    => "-apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif",
    'mono'      => "'SFMono-Regular', 'Consolas', 'Liberation Mono', monospace",
    'serif'     => "Georgia, 'Times New Roman', serif",
    'inter'     => "'Inter', 'Helvetica Neue', Arial, sans-serif",
    'trebuchet' => "'Trebuchet MS', 'Lucida Grande', sans-serif",
];

$fontFamily   = $fonts[$sty['font'] ?? 'system'] ?? $fonts['system'];
$primaryColor = htmlspecialchars($sty['primaryColor'] ?? '#244c47');
$accentColor  = htmlspecialchars($sty['accentColor']  ?? '#459289');
$bgColor      = htmlspecialchars($sty['bgColor']      ?? '#fcfdfd');
$textColor    = htmlspecialchars($sty['textColor']     ?? '#182523');
$headerBg     = htmlspecialchars($sty['headerBg']      ?? '#244c47');
$headerText   = htmlspecialchars($sty['headerText']    ?? '#eaf5f4');
$fontSize     = (int) ($sty['fontSize'] ?? 16);

$headerTitle  = htmlspecialchars($lang['headerTitle']    ?? $form['name'] ?? 'Request a Quote');
$headerSub    = htmlspecialchars($lang['headerSubtitle'] ?? '');
$svcTitle     = htmlspecialchars($lang['serviceStepTitle']    ?? 'Tell us about your project');
$svcDesc      = htmlspecialchars($lang['serviceStepDesc']     ?? 'Select the primary service type.');
$cplxTitle    = htmlspecialchars($lang['complexityStepTitle'] ?? 'Project Complexity');
$cplxDesc     = htmlspecialchars($lang['complexityStepDesc']  ?? 'How complex is your project?');
$addonTitle   = htmlspecialchars($lang['addonStepTitle']      ?? 'Add-On Services');
$addonDesc    = htmlspecialchars($lang['addonStepDesc']       ?? 'Select any additional services.');
$contactTitle = htmlspecialchars($lang['contactStepTitle']    ?? 'Contact Information');
$contactDesc  = htmlspecialchars($lang['contactStepDesc']     ?? 'How can we reach you?');
$nextLabel    = htmlspecialchars($lang['nextLabel']    ?? 'Next');
$backLabel    = htmlspecialchars($lang['backLabel']    ?? 'Back');
$submitLabel  = htmlspecialchars($lang['submitLabel']  ?? 'Get Estimate');
$resultHead   = htmlspecialchars($lang['resultHeading'] ?? 'Your Estimate');
$resultDescT  = htmlspecialchars($lang['resultDesc']    ?? 'Based on your selections, here are your pricing options.');
$currency     = htmlspecialchars($lang['currency'] ?? '$');
$showBreak    = ($form['showBreakdown'] ?? true) ? 'true' : 'false';

$videoUrl       = trim($form['videoUrl'] ?? '');
$introHeading   = htmlspecialchars($lang['introHeading']    ?? 'Welcome');
$introText      = htmlspecialchars($lang['introText']       ?? '');
$introButton    = htmlspecialchars($lang['introButtonLabel'] ?? 'Get Started');
$hasIntro       = ($videoUrl !== '' || ($lang['introHeading'] ?? '') !== '' || ($lang['introText'] ?? '') !== '');

// Convert YouTube / Vimeo URLs to embeddable form
$embedUrl = '';
if ($videoUrl !== '') {
    if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([\w\-]+)/', $videoUrl, $m)) {
        $embedUrl = 'https://www.youtube.com/embed/' . $m[1];
    } elseif (preg_match('/vimeo\.com\/(\d+)/', $videoUrl, $m)) {
        $embedUrl = 'https://player.vimeo.com/video/' . $m[1];
    } else {
        $embedUrl = $videoUrl; // assume already an embed URL
    }
}

$services   = $form['services']   ?? [];
$complexity = $form['complexity']  ?? [];
$addons     = $form['addons']      ?? [];
$contact    = $form['contact']     ?? [];
$totalSteps = 4;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $isBuilderPreview ? 'Preview: ' : '' ?><?= htmlspecialchars($form['name'] ?? 'Quote Form') ?></title>
<link rel="icon" type="image/png" href="/assets/favicon.png">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { font-size: <?= $fontSize ?>px; font-family: <?= $fontFamily ?>; color: <?= $textColor ?>; background: <?= $bgColor ?>; }
body { min-height: 100vh; display: flex; flex-direction: column; }

.preview-bar { background: #111; color: #ccc; font-family: monospace; font-size: 12px; padding: 0.4rem 1rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; }
.preview-bar a { color: #90c8c5; text-decoration: none; font-weight: 600; }
.preview-bar a:hover { color: #fff; }

.pv-header { background: <?= $headerBg ?>; color: <?= $headerText ?>; padding: 1rem 1.5rem; border-bottom: 3px solid <?= $primaryColor ?>; }
.pv-header-title { font-size: 1.3rem; font-weight: 700; }
.pv-header-sub { font-size: 0.85rem; opacity: 0.8; margin-top: 0.2rem; }

.pv-main { flex: 1; padding: 2.5rem 1.5rem; }
.pv-container { max-width: 640px; margin: 0 auto; }

.pv-progress-wrap { height: 6px; background: <?= $accentColor ?>30; border: 1px solid <?= $accentColor ?>50; margin-bottom: 0.5rem; overflow: hidden; }
.pv-progress { height: 100%; background: <?= $primaryColor ?>; transition: width 0.4s; }
.pv-step-ind { font-size: 0.75rem; color: <?= $accentColor ?>; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 600; margin-bottom: 1.2rem; }

.pv-step-block { background: #fff; border: 1px solid <?= $accentColor ?>60; padding: 2rem; }
.pv-step-title { font-size: 1.35rem; font-weight: 700; color: <?= $textColor ?>; margin-bottom: 0.35rem; }
.pv-step-desc { font-size: 0.9rem; color: <?= $accentColor ?>; margin-bottom: 1.5rem; }
.pv-options { display: flex; flex-direction: column; gap: 0.5rem; }

.pv-radio-opt, .pv-check-opt { display: flex; align-items: flex-start; gap: 0.55rem; cursor: pointer; padding: 0.6rem 0.8rem; border: 1px solid <?= $accentColor ?>50; transition: background 0.15s; }
.pv-radio-opt:hover, .pv-check-opt:hover { background: <?= $primaryColor ?>10; }
.pv-radio-opt input, .pv-check-opt input { accent-color: <?= $primaryColor ?>; margin-top: 3px; flex-shrink: 0; }
.pv-opt-info { flex: 1; }
.pv-opt-label { font-size: 0.9rem; color: <?= $textColor ?>; }
.pv-opt-sub { font-size: 0.75rem; color: <?= $accentColor ?>; margin-top: 0.1rem; }
.pv-opt-cost { font-size: 0.82rem; font-weight: 700; color: <?= $primaryColor ?>; white-space: nowrap; flex-shrink: 0; align-self: center; }

.pv-fields { display: flex; flex-direction: column; gap: 1.2rem; }
.pv-field-group { display: flex; flex-direction: column; gap: 0.4rem; }
.pv-label { font-size: 0.88rem; font-weight: 600; color: <?= $textColor ?>; }
.pv-req { color: #c0392b; margin-left: 0.2rem; }
.pv-input, .pv-select { width: 100%; padding: 0.6rem 0.8rem; font-size: 1rem; font-family: inherit; color: <?= $textColor ?>; background: <?= $bgColor ?>; border: 1px solid <?= $accentColor ?>; outline: none; }
.pv-input:focus, .pv-select:focus { border-color: <?= $primaryColor ?>; box-shadow: 0 0 0 3px <?= $primaryColor ?>20; }

.pv-actions { display: flex; gap: 0.75rem; margin-top: 1.5rem; }
.pv-btn { padding: 0.65rem 1.5rem; font-size: 0.92rem; font-family: inherit; font-weight: 700; cursor: pointer; border: 2px solid transparent; letter-spacing: 0.02em; }
.pv-btn-primary { background: <?= $primaryColor ?>; color: <?= $headerText ?>; border-color: <?= $primaryColor ?>; }
.pv-btn-primary:hover { opacity: 0.85; }
.pv-btn-secondary { background: transparent; color: <?= $primaryColor ?>; border-color: <?= $primaryColor ?>; }
.pv-btn-secondary:hover { background: <?= $primaryColor ?>15; }

.pv-dots { display: flex; gap: 0.5rem; justify-content: center; margin-top: 1.5rem; }
.pv-dot { width: 8px; height: 8px; background: <?= $accentColor ?>40; cursor: pointer; transition: background 0.2s; }
.pv-dot.active { background: <?= $primaryColor ?>; }

.pv-footer { background: <?= $headerBg ?>; color: <?= $headerText ?>99; padding: 1rem 1.5rem; text-align: center; font-size: 0.78rem; border-top: 2px solid <?= $primaryColor ?>; }

/* result styles */
.pv-result-heading { font-size: 1.4rem; font-weight: 700; color: <?= $textColor ?>; margin-bottom: 0.3rem; }
.pv-result-desc { font-size: 0.88rem; color: <?= $accentColor ?>; margin-bottom: 1.5rem; }
.pv-tiers-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
.pv-tier-card { border: 2px solid <?= $accentColor ?>40; padding: 1.5rem 1rem; text-align: center; background: #fff; }
.pv-tier-card.featured { border-color: <?= $primaryColor ?>; box-shadow: 0 2px 12px <?= $primaryColor ?>15; }
.pv-tier-name { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: <?= $accentColor ?>; margin-bottom: 0.6rem; }
.pv-tier-price { font-size: 2rem; font-weight: 700; color: <?= $primaryColor ?>; margin-bottom: 0.35rem; }
.pv-tier-mult { font-size: 0.7rem; color: <?= $accentColor ?>; margin-bottom: 0.5rem; }
.pv-tier-desc { font-size: 0.78rem; color: <?= $textColor ?>; opacity: 0.7; }
.pv-breakdown { margin-top: 1.5rem; border-top: 1px solid <?= $accentColor ?>30; padding-top: 1rem; }
.pv-breakdown-title { font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: <?= $accentColor ?>; margin-bottom: 0.5rem; }
.pv-breakdown-row { display: flex; justify-content: space-between; padding: 0.35rem 0; font-size: 0.82rem; color: <?= $textColor ?>; }
.pv-breakdown-label { flex: 1; }
.pv-breakdown-val { font-weight: 700; color: <?= $primaryColor ?>; }
.pv-breakdown-total { display: flex; justify-content: space-between; padding: 0.6rem 0; margin-top: 0.3rem; border-top: 2px solid <?= $primaryColor ?>; font-weight: 700; font-size: 0.95rem; color: <?= $primaryColor ?>; }

/* intro / video */
.pv-intro-block { background: #fff; border: 1px solid <?= $accentColor ?>60; padding: 2.5rem 2rem; text-align: center; }
.pv-intro-heading { font-size: 1.5rem; font-weight: 700; color: <?= $textColor ?>; margin-bottom: 0.5rem; }
.pv-intro-text { font-size: 0.92rem; color: <?= $accentColor ?>; margin-bottom: 1.5rem; line-height: 1.5; }
.pv-video-wrap { position: relative; width: 100%; padding-bottom: 56.25%; margin-bottom: 1.5rem; background: <?= $textColor ?>10; }
.pv-video-wrap iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: none; }
.pv-intro-btn { display: inline-block; padding: 0.75rem 2rem; font-size: 1rem; font-family: inherit; font-weight: 700; cursor: pointer; border: 2px solid <?= $primaryColor ?>; background: <?= $primaryColor ?>; color: <?= $headerText ?>; letter-spacing: 0.02em; }
.pv-intro-btn:hover { opacity: 0.85; }

/* help system */
.pv-help-toggle { display: inline-flex; align-items: center; justify-content: center; width: 18px; height: 18px; font-size: 11px; font-weight: 700; color: <?= $accentColor ?>; border: 1px solid <?= $accentColor ?>60; background: <?= $bgColor ?>; cursor: pointer; flex-shrink: 0; margin-left: 0.35rem; vertical-align: middle; font-family: inherit; line-height: 1; padding: 0; }
.pv-help-toggle:hover { background: <?= $primaryColor ?>15; border-color: <?= $primaryColor ?>; color: <?= $primaryColor ?>; }
.pv-help-body { display: none; padding: 0.6rem 0.8rem; margin-top: 0.35rem; font-size: 0.78rem; line-height: 1.5; color: <?= $textColor ?>; background: <?= $primaryColor ?>08; border-left: 3px solid <?= $accentColor ?>; }
.pv-help-body.open { display: block; }
</style>
</head>
<body>

<?php if ($isBuilderPreview): ?>
<div class="preview-bar">
    <span>PREVIEW | <?= htmlspecialchars($form['name'] ?? 'Quote Form') ?></span>
    <a href="/form-builder?edit=<?= htmlspecialchars(urlencode($form['id'])) ?>">&larr; Back to Editor</a>
</div>
<?php endif; ?>

<header class="pv-header">
    <div class="pv-header-title"><?= $headerTitle ?></div>
    <?php if ($headerSub): ?><div class="pv-header-sub"><?= $headerSub ?></div><?php endif; ?>
</header>

<main class="pv-main">
<div class="pv-container" id="pv-app">

<?php if ($hasIntro): ?>
<!-- INTRO -->
<div id="pv-intro">
    <div class="pv-intro-block">
        <?php if ($introHeading): ?><h2 class="pv-intro-heading"><?= $introHeading ?></h2><?php endif; ?>
        <?php if ($introText): ?><p class="pv-intro-text"><?= $introText ?></p><?php endif; ?>
        <?php if ($embedUrl): ?>
        <div class="pv-video-wrap">
            <iframe src="<?= htmlspecialchars($embedUrl) ?>" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
        </div>
        <?php endif; ?>
        <button class="pv-intro-btn" onclick="pvDismissIntro()"><?= $introButton ?></button>
    </div>
</div>
<?php endif; ?>

<!-- STEP 1: Services -->
<div class="pv-step" id="pv-step-0"<?php if ($hasIntro): ?> style="display:none;"<?php endif; ?>>
    <div class="pv-progress-wrap"><div class="pv-progress" style="width:25%"></div></div>
    <p class="pv-step-ind">Step 1 of <?= $totalSteps ?></p>
    <div class="pv-step-block">
        <h2 class="pv-step-title"><?= $svcTitle ?></h2>
        <p class="pv-step-desc"><?= $svcDesc ?></p>
        <div class="pv-options">
        <?php foreach ($services as $si => $svc): ?>
            <label class="pv-radio-opt">
                <input type="radio" name="pv-service" data-price="<?= (float)($svc['price'] ?? 0) ?>" data-label="<?= htmlspecialchars($svc['label'] ?? '') ?>">
                <span class="pv-opt-info">
                    <span class="pv-opt-label"><?= htmlspecialchars($svc['label'] ?? '') ?><?php if (!empty($svc['help'])): ?><button type="button" class="pv-help-toggle" onclick="event.preventDefault();pvToggleHelp('svc-<?= $si ?>')">?</button><?php endif; ?></span>
                    <?php if (!empty($svc['help'])): ?><div class="pv-help-body" id="pv-help-svc-<?= $si ?>"><?= htmlspecialchars($svc['help']) ?></div><?php endif; ?>
                </span>
                <span class="pv-opt-cost"><?= $currency . number_format((float)($svc['price'] ?? 0), 0) ?></span>
            </label>
        <?php endforeach; ?>
        </div>
        <div class="pv-actions">
            <button class="pv-btn pv-btn-primary" onclick="pvGo(1)"><?= $nextLabel ?></button>
        </div>
    </div>
    <div class="pv-dots"><?php for ($i=0;$i<$totalSteps;$i++): ?><div class="pv-dot<?= $i===0?' active':'' ?>" onclick="pvGo(<?=$i?>)"></div><?php endfor; ?></div>
</div>

<!-- STEP 2: Complexity -->
<div class="pv-step" id="pv-step-1" style="display:none;">
    <div class="pv-progress-wrap"><div class="pv-progress" style="width:50%"></div></div>
    <p class="pv-step-ind">Step 2 of <?= $totalSteps ?></p>
    <div class="pv-step-block">
        <h2 class="pv-step-title"><?= $cplxTitle ?></h2>
        <p class="pv-step-desc"><?= $cplxDesc ?></p>
        <div class="pv-options">
        <?php foreach ($complexity as $ci => $c): ?>
            <label class="pv-radio-opt">
                <input type="radio" name="pv-complexity" data-multiplier="<?= (float)($c['multiplier'] ?? 1) ?>" data-label="<?= htmlspecialchars($c['label'] ?? '') ?>">
                <span class="pv-opt-info">
                    <span class="pv-opt-label"><?= htmlspecialchars($c['label'] ?? '') ?><?php if (!empty($c['help'])): ?><button type="button" class="pv-help-toggle" onclick="event.preventDefault();pvToggleHelp('cplx-<?= $ci ?>')">?</button><?php endif; ?></span>
                    <?php if (!empty($c['description'])): ?><div class="pv-opt-sub"><?= htmlspecialchars($c['description']) ?></div><?php endif; ?>
                    <?php if (!empty($c['help'])): ?><div class="pv-help-body" id="pv-help-cplx-<?= $ci ?>"><?= htmlspecialchars($c['help']) ?></div><?php endif; ?>
                </span>
                <span class="pv-opt-cost"><?= (float)($c['multiplier'] ?? 1) ?>x</span>
            </label>
        <?php endforeach; ?>
        </div>
        <div class="pv-actions">
            <button class="pv-btn pv-btn-secondary" onclick="pvGo(0)"><?= $backLabel ?></button>
            <button class="pv-btn pv-btn-primary" onclick="pvGo(2)"><?= $nextLabel ?></button>
        </div>
    </div>
    <div class="pv-dots"><?php for ($i=0;$i<$totalSteps;$i++): ?><div class="pv-dot<?= $i===1?' active':'' ?>" onclick="pvGo(<?=$i?>)"></div><?php endfor; ?></div>
</div>

<!-- STEP 3: Add-Ons -->
<div class="pv-step" id="pv-step-2" style="display:none;">
    <div class="pv-progress-wrap"><div class="pv-progress" style="width:75%"></div></div>
    <p class="pv-step-ind">Step 3 of <?= $totalSteps ?></p>
    <div class="pv-step-block">
        <h2 class="pv-step-title"><?= $addonTitle ?></h2>
        <p class="pv-step-desc"><?= $addonDesc ?></p>
        <div class="pv-options">
        <?php if (empty($addons)): ?>
            <p style="color:<?= $accentColor ?>;font-size:0.88rem;">No add-on services available.</p>
        <?php else: ?>
        <?php foreach ($addons as $ai => $a): ?>
            <label class="pv-check-opt">
                <input type="checkbox" name="pv-addon" data-price="<?= (float)($a['price'] ?? 0) ?>" data-label="<?= htmlspecialchars($a['label'] ?? '') ?>">
                <span class="pv-opt-info">
                    <span class="pv-opt-label"><?= htmlspecialchars($a['label'] ?? '') ?><?php if (!empty($a['help'])): ?><button type="button" class="pv-help-toggle" onclick="event.preventDefault();pvToggleHelp('addon-<?= $ai ?>')">?</button><?php endif; ?></span>
                    <?php if (!empty($a['help'])): ?><div class="pv-help-body" id="pv-help-addon-<?= $ai ?>"><?= htmlspecialchars($a['help']) ?></div><?php endif; ?>
                </span>
                <span class="pv-opt-cost">+<?= $currency . number_format((float)($a['price'] ?? 0), 0) ?></span>
            </label>
        <?php endforeach; ?>
        <?php endif; ?>
        </div>
        <div class="pv-actions">
            <button class="pv-btn pv-btn-secondary" onclick="pvGo(1)"><?= $backLabel ?></button>
            <button class="pv-btn pv-btn-primary" onclick="pvGo(3)"><?= $nextLabel ?></button>
        </div>
    </div>
    <div class="pv-dots"><?php for ($i=0;$i<$totalSteps;$i++): ?><div class="pv-dot<?= $i===2?' active':'' ?>" onclick="pvGo(<?=$i?>)"></div><?php endfor; ?></div>
</div>

<!-- STEP 4: Contact -->
<div class="pv-step" id="pv-step-3" style="display:none;">
    <div class="pv-progress-wrap"><div class="pv-progress" style="width:100%"></div></div>
    <p class="pv-step-ind">Step 4 of <?= $totalSteps ?></p>
    <div class="pv-step-block">
        <h2 class="pv-step-title"><?= $contactTitle ?></h2>
        <p class="pv-step-desc"><?= $contactDesc ?></p>
        <div class="pv-fields">
        <?php foreach ($contact as $f): ?>
            <div class="pv-field-group">
                <label class="pv-label">
                    <?= htmlspecialchars($f['label'] ?? 'Field') ?>
                    <?php if (!empty($f['required'])): ?><span class="pv-req">*</span><?php endif; ?>
                </label>
                <?php if (($f['type'] ?? 'text') === 'select' && !empty($f['options'])): ?>
                    <select class="pv-select">
                        <option value="">-- Select --</option>
                        <?php foreach ($f['options'] as $opt): ?>
                        <option><?= htmlspecialchars($opt) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input class="pv-input" type="<?= htmlspecialchars($f['type'] ?? 'text') ?>" placeholder="">
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
        <div class="pv-actions">
            <button class="pv-btn pv-btn-secondary" onclick="pvGo(2)"><?= $backLabel ?></button>
            <button class="pv-btn pv-btn-primary" onclick="pvSubmit()"><?= $submitLabel ?></button>
        </div>
    </div>
    <div class="pv-dots"><?php for ($i=0;$i<$totalSteps;$i++): ?><div class="pv-dot<?= $i===3?' active':'' ?>" onclick="pvGo(<?=$i?>)"></div><?php endfor; ?></div>
</div>

<!-- Result -->
<div id="pv-done" style="display:none;"></div>

</div>
</main>

<footer class="pv-footer"><?= $isBuilderPreview ? 'Preview only -- no data is collected.' : '&copy; ' . date('Y') . ' ' . htmlspecialchars($form['name'] ?? '') ?></footer>

<script>
(function(){
    var total       = <?= $totalSteps ?>;
    var cur         = 0;
    var currency    = <?= json_encode($currency) ?>;
    var tiers       = <?= json_encode(array_values($tiers)) ?>;
    var resultHead  = <?= json_encode($resultHead) ?>;
    var resultDescT = <?= json_encode($resultDescT) ?>;
    var showBreak   = <?= $showBreak ?>;
    var hasIntro    = <?= $hasIntro ? 'true' : 'false' ?>;

    function show(n) {
        var intro = document.getElementById('pv-intro');
        if (intro) intro.style.display = 'none';
        for (var i = 0; i < total; i++) {
            var el = document.getElementById('pv-step-' + i);
            if (el) el.style.display = i === n ? '' : 'none';
        }
        document.getElementById('pv-done').style.display = 'none';
        cur = n;
    }

    function fmtCost(n) {
        return currency + Math.round(n).toLocaleString();
    }

    function calcCost() {
        var svcEl  = document.querySelector('input[name="pv-service"]:checked');
        var cplxEl = document.querySelector('input[name="pv-complexity"]:checked');
        var base       = svcEl  ? parseFloat(svcEl.dataset.price)      || 0 : 0;
        var multiplier = cplxEl ? parseFloat(cplxEl.dataset.multiplier) || 1 : 1;
        var svcName    = svcEl  ? svcEl.dataset.label  : '';
        var cplxName   = cplxEl ? cplxEl.dataset.label : '';

        var addonTotal = 0;
        var addonItems = [];
        document.querySelectorAll('input[name="pv-addon"]:checked').forEach(function(el) {
            var p = parseFloat(el.dataset.price) || 0;
            addonTotal += p;
            addonItems.push({ label: el.dataset.label, price: p });
        });

        var subtotal = (base * multiplier) + addonTotal;

        return {
            base: base,
            multiplier: multiplier,
            addonTotal: addonTotal,
            subtotal: subtotal,
            serviceName: svcName,
            complexityName: cplxName,
            addons: addonItems
        };
    }

    function renderResult(r) {
        var el = document.getElementById('pv-done');
        var html = '<div class="pv-step-block" style="padding:2.5rem 2rem;">';
        html += '<h2 class="pv-result-heading">' + resultHead + '</h2>';
        html += '<p class="pv-result-desc">' + resultDescT + '</p>';

        if (r.subtotal === 0) {
            html += '<p style="text-align:center;color:<?= $accentColor ?>;padding:1.5rem 0;">No service was selected. Go back and make your selections.</p>';
        } else {
            html += '<div class="pv-tiers-grid">';
            tiers.forEach(function(tier, ti) {
                var tierCost = Math.round(r.subtotal * (tier.multiplier || 1));
                var featured = tiers.length >= 3 && ti === Math.floor(tiers.length / 2);
                html += '<div class="pv-tier-card' + (featured ? ' featured' : '') + '">';
                html += '<div class="pv-tier-name">' + (tier.name || 'Tier') + '</div>';
                html += '<div class="pv-tier-price">' + fmtCost(tierCost) + '</div>';
                if (tier.multiplier && tier.multiplier !== 1) {
                    html += '<div class="pv-tier-mult">' + tier.multiplier + 'x base</div>';
                } else {
                    html += '<div class="pv-tier-mult">base rate</div>';
                }
                html += '<div class="pv-tier-desc">' + (tier.description || '') + '</div>';
                html += '</div>';
            });
            html += '</div>';

            if (showBreak) {
                html += '<div class="pv-breakdown">';
                html += '<div class="pv-breakdown-title">Cost Breakdown</div>';
                html += '<div class="pv-breakdown-row"><span class="pv-breakdown-label">Service: ' + r.serviceName + '</span><span class="pv-breakdown-val">' + fmtCost(r.base) + '</span></div>';
                html += '<div class="pv-breakdown-row"><span class="pv-breakdown-label">Complexity: ' + r.complexityName + '</span><span class="pv-breakdown-val">' + r.multiplier + 'x</span></div>';
                r.addons.forEach(function(a) {
                    html += '<div class="pv-breakdown-row"><span class="pv-breakdown-label">' + a.label + '</span><span class="pv-breakdown-val">+' + fmtCost(a.price) + '</span></div>';
                });
                html += '<div class="pv-breakdown-total"><span>Subtotal</span><span>' + fmtCost(r.subtotal) + '</span></div>';
                html += '</div>';
            }
        }

        html += '<div style="text-align:center;margin-top:1.5rem;">';
<?php if ($isBuilderPreview): ?>
        html += '<p style="font-size:0.75rem;color:<?= $accentColor ?>;margin-bottom:0.8rem;">This is a preview -- no data was sent.</p>';
<?php endif; ?>
        html += '<button class="pv-btn pv-btn-secondary" onclick="pvReset()">Start Over</button>';
        html += '</div></div>';
        el.innerHTML = html;
    }

    window.pvGo = function(n) {
        if (n >= 0 && n < total) show(n);
    };

    window.pvSubmit = function() {
        var result = calcCost();
        for (var i = 0; i < total; i++) {
            var el = document.getElementById('pv-step-' + i);
            if (el) el.style.display = 'none';
        }
        renderResult(result);
        document.getElementById('pv-done').style.display = '';
    };

    window.pvReset = function() {
        document.querySelectorAll('.pv-input, .pv-select').forEach(function(el) { el.value = ''; });
        document.querySelectorAll('select.pv-select').forEach(function(el) { el.selectedIndex = 0; });
        document.querySelectorAll('input[type=radio], input[type=checkbox]').forEach(function(el) { el.checked = false; });
        document.querySelectorAll('.pv-help-body').forEach(function(el) { el.classList.remove('open'); });
        if (hasIntro) {
            for (var i = 0; i < total; i++) {
                var el = document.getElementById('pv-step-' + i);
                if (el) el.style.display = 'none';
            }
            document.getElementById('pv-done').style.display = 'none';
            var intro = document.getElementById('pv-intro');
            if (intro) intro.style.display = '';
        } else {
            show(0);
        }
    };

    window.pvDismissIntro = function() {
        var intro = document.getElementById('pv-intro');
        if (intro) intro.style.display = 'none';
        show(0);
    };

    window.pvToggleHelp = function(id) {
        var el = document.getElementById('pv-help-' + id);
        if (el) el.classList.toggle('open');
    };
}());
</script>
</body>
</html>
