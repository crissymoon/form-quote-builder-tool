<?php
declare(strict_types=1);
$quote    = $_SESSION['last_quote'] ?? null;
$estimate = $quote['estimate']   ?? null;
$refID    = $quote['ref_id']     ?? 'N/A';
$expires  = $quote['expires_at'] ?? null;
$data     = $quote['quote_data'] ?? [];

if (!$quote || !$estimate) {
    header('Location: ?step=1');
    exit;
}

$formatMoney = fn(int $amount): string => '$' . number_format($amount, 0, '.', ',');
$expiresDate = $expires ? date('F j, Y', $expires) : 'N/A';
$validation  = $quote['validation'] ?? null;
?>

<div class="result-block">
    <div class="result-header">
        <h1 class="result-title">Your Estimate</h1>
        <p class="result-subtitle">This is a reference estimate based on your selections. A formal quote follows after review.</p>
    </div>

    <div class="estimate-range">
        <span class="estimate-label">Estimated Range</span>
        <div class="estimate-numbers">
            <span class="estimate-low"><?= $formatMoney($estimate['range_low']) ?></span>
            <span class="estimate-separator">to</span>
            <span class="estimate-high"><?= $formatMoney($estimate['range_high']) ?></span>
        </div>
    </div>

    <div class="ref-id-block">

<?php if ($validation): ?>
<?php
    $isValid    = $validation['valid']      ?? false;
    $ruleOk     = $validation['rule_ok']    ?? false;
    $mlOk       = $validation['ml_ok'];
    $conf       = $validation['confidence'];
    $mlAvail    = $validation['ml_available'] ?? false;
    $badgeClass = $isValid ? 'validation-badge--valid' : 'validation-badge--invalid';
    $badgeLabel = $isValid ? 'Math Verified' : 'Calculation Error Detected';
?>
    <div class="validation-badge <?= $badgeClass ?>">
        <span class="validation-badge__label"><?= $badgeLabel ?></span>
        <ul class="validation-badge__detail">
            <li>Rule check: <?= $ruleOk ? 'pass' : 'fail' ?></li>
<?php if ($mlAvail): ?>
            <li>ML model: <?= $mlOk ? 'pass' : 'fail' ?> &nbsp; confidence <?= number_format($conf * 100, 0) ?>%</li>
<?php else: ?>
            <li>ML model: not available</li>
<?php endif; ?>
<?php if (!empty($validation['error_fields'])): ?>
            <li>Wrong fields: <?= htmlspecialchars(implode(', ', $validation['error_fields'])) ?></li>
<?php endif; ?>
        </ul>
    </div>
<?php endif; ?>

        <p class="ref-id-label">Reference ID</p>
        <p class="ref-id-value"><?= htmlspecialchars($refID) ?></p>
        <p class="ref-id-expires">Valid until <?= htmlspecialchars($expiresDate) ?></p>
    </div>

    <div class="selection-summary">
        <h2 class="summary-title">Your Selections</h2>
        <ul class="summary-list">
            <?php foreach ($data as $key => $value): ?>
                <?php if (!empty($value)): ?>
                <li class="summary-item">
                    <span class="summary-key"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $key))) ?></span>
                    <span class="summary-value"><?= htmlspecialchars(is_array($value) ? implode(', ', $value) : (string)$value) ?></span>
                </li>
                <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="result-actions">
        <form method="POST" action="">
            <input type="hidden" name="action" value="submit_quote">
            <button type="submit" class="btn btn--primary">Submit for Formal Review</button>
        </form>
        <a href="?step=1" class="btn btn--secondary">Start a New Estimate</a>
    </div>

    <p class="result-disclaimer">
        This estimate is based on current service rates and is subject to change after formal discovery.
        Reference IDs expire after <?= REFERENCE_EXPIRY_DAYS ?> days. Contact
        <a href="mailto:crissy@xcaliburmoon.net">crissy@xcaliburmoon.net</a> with your reference ID to proceed.
    </p>
</div>
