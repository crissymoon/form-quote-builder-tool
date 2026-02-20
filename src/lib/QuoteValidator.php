<?php
declare(strict_types=1);

/**
 * QuoteValidator
 *
 * Validates that a set of claimed quote numbers match the QuoteEngine formula
 * exactly. Uses two layers:
 *
 *  1. Rule check (PHP, always available) — re-implements the same deterministic
 *     formula that QuoteEngine uses and checks claimed values against it.
 *
 *  2. ML check (Python, optional) — calls ml/validate_quote.py which loads
 *     the trained DecisionTreeClassifier pkl and returns a confidence score.
 *     Falls back gracefully when Python / the pkl is unavailable.
 */
class QuoteValidator
{
    private array $baseRates = [
        'web_design'      => 1500,
        'web_development' => 3500,
        'ecommerce'       => 4500,
        'software'        => 7500,
        'ai_web_app'      => 9500,
        'ai_native_app'   => 14000,
    ];

    private array $complexityMultipliers = [
        'simple'   => 1.0,
        'moderate' => 1.4,
        'complex'  => 2.0,
        'custom'   => 2.8,
    ];

    private array $addonRates = [
        'seo_basic'       => 500,
        'seo_advanced'    => 1200,
        'copywriting'     => 800,
        'branding'        => 1800,
        'maintenance'     => 1200,
        'hosting_setup'   => 350,
        'api_integration' => 1500,
        'automation'      => 2200,
    ];

    /** Absolute path to ml/validate_quote.py */
    private string $mlScript;

    /** Absolute path to ml/quote_math_model.pkl */
    private string $pklPath;

    public function __construct()
    {
        $mlDir          = dirname(__DIR__, 2) . '/ml';
        $this->mlScript = $mlDir . '/validate_quote.py';
        $this->pklPath  = $mlDir . '/quote_math_model.pkl';
    }

    /**
     * Validate a completed estimate.
     *
     * @param  string   $serviceType  e.g. 'web_design'
     * @param  string   $complexity   e.g. 'simple'
     * @param  string[] $addons       e.g. ['branding', 'seo_basic']
     * @param  int      $subtotal     Claimed subtotal from QuoteEngine
     * @param  int      $rangeLow     Claimed low end
     * @param  int      $rangeHigh    Claimed high end
     * @return array{
     *   valid:       bool,
     *   rule_ok:     bool,
     *   ml_ok:       bool|null,
     *   confidence:  float|null,
     *   expected:    array,
     *   error_fields: string[],
     *   ml_available: bool,
     * }
     */
    public function validate(
        string $serviceType,
        string $complexity,
        array  $addons,
        int    $subtotal,
        int    $rangeLow,
        int    $rangeHigh
    ): array {
        // --- 1. Rule check ---
        $base       = $this->baseRates[$serviceType]              ?? 0;
        $multiplier = $this->complexityMultipliers[$complexity]   ?? 1.0;
        $addonTotal = 0;
        foreach ($addons as $a) {
            $addonTotal += $this->addonRates[trim($a)] ?? 0;
        }

        $expectedSubtotal = (int) round($base * $multiplier + $addonTotal);
        $expectedLow      = (int) round($expectedSubtotal * 0.9);
        $expectedHigh     = (int) round($expectedSubtotal * 1.2);

        $errorFields = [];
        if ($subtotal  !== $expectedSubtotal) $errorFields[] = 'subtotal';
        if ($rangeLow  !== $expectedLow)      $errorFields[] = 'range_low';
        if ($rangeHigh !== $expectedHigh)     $errorFields[] = 'range_high';

        $ruleOk = empty($errorFields);

        // --- 2. ML check ---
        $mlOk        = null;
        $confidence  = null;
        $mlAvailable = false;

        if (file_exists($this->pklPath) && file_exists($this->mlScript)) {
            $mlResult = $this->callMlModel(
                $serviceType, $complexity, $addons,
                $subtotal, $rangeLow, $rangeHigh
            );
            if ($mlResult !== null) {
                $mlOk        = (bool) $mlResult['correct'];
                $confidence  = (float) $mlResult['confidence'];
                $mlAvailable = true;
            }
        }

        return [
            'valid'       => $ruleOk && ($mlAvailable ? $mlOk : true),
            'rule_ok'     => $ruleOk,
            'ml_ok'       => $mlOk,
            'confidence'  => $confidence,
            'expected'    => [
                'subtotal'   => $expectedSubtotal,
                'range_low'  => $expectedLow,
                'range_high' => $expectedHigh,
            ],
            'error_fields' => $errorFields,
            'ml_available' => $mlAvailable,
        ];
    }

    /**
     * Call ml/validate_quote.py and return its decoded JSON output,
     * or null on any error.
     */
    private function callMlModel(
        string $serviceType,
        string $complexity,
        array  $addons,
        int    $subtotal,
        int    $rangeLow,
        int    $rangeHigh
    ): ?array {
        $addonsArg = implode(',', array_map('escapeshellarg', $addons));
        $addonsList = empty($addons) ? '""' : implode(' ', array_map('escapeshellarg', $addons));

        $cmd = sprintf(
            'python3 %s --pkl %s --service %s --complexity %s --subtotal %d --low %d --high %d --addons %s 2>/dev/null',
            escapeshellarg($this->mlScript),
            escapeshellarg($this->pklPath),
            escapeshellarg($serviceType),
            escapeshellarg($complexity),
            $subtotal,
            $rangeLow,
            $rangeHigh,
            $addonsList
        );

        $output = shell_exec($cmd);
        if ($output === null || $output === '') {
            return null;
        }

        $decoded = json_decode(trim($output), true);
        return is_array($decoded) ? $decoded : null;
    }
}
