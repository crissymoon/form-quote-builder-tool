<?php
declare(strict_types=1);
$currentStep   = $step ?? 1;
$totalSteps    = FormSteps::count();
$progressPct   = $isResult ? 100 : (int) round(($currentStep / $totalSteps) * 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XcaliburMoon Web Development Pricing</title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <script src="/assets/js/vendor/math.min.js" defer></script>
    <script src="/assets/js/quote.js" defer></script>
</head>
<body>

    <div id="load-overlay" class="load-overlay" aria-hidden="true">
        <div class="load-spinner"></div>
    </div>

    <header class="site-header">
        <div class="header-inner">
            <a href="/" class="site-logo">XcaliburMoon</a>
            <span class="site-tagline">Web Development Pricing</span>
        </div>
    </header>

    <main class="main-content">
        <div class="form-container">

            <?php if (!$isResult): ?>
            <div class="progress-bar-wrap" role="progressbar" aria-valuenow="<?= $progressPct ?>" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar" style="width: <?= $progressPct ?>%"></div>
            </div>
            <p class="step-indicator">Step <?= $currentStep ?> of <?= $totalSteps ?></p>
            <?php endif; ?>

            <?= $pageContent ?>

        </div>
    </main>

    <footer class="site-footer">
        <p>&copy; <?= date('Y') ?> XcaliburMoon Web Development. All rights reserved.</p>
        <p><a href="https://crissymoon.com" target="_blank" rel="noopener noreferrer">crissymoon.com</a></p>
    </footer>

    <script>
    window.addEventListener('load', function() {
        var overlay = document.getElementById('load-overlay');
        if (overlay) {
            overlay.classList.add('load-overlay--hidden');
        }
    });
    </script>

</body>
</html>
