<?php
declare(strict_types=1);

class QuoteEngine
{
    private array $data;

    private array $baseRates = [
        'web_design'        => 1500,
        'web_development'   => 3500,
        'ecommerce'         => 4500,
        'software'          => 7500,
        'ai_web_app'        => 9500,
        'ai_native_app'     => 14000,
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

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function calculate(): array
    {
        $serviceType = $this->data['service_type'] ?? 'web_design';
        $complexity  = $this->data['complexity']   ?? 'simple';
        $addons      = $this->data['addons']        ?? [];

        if (is_string($addons)) {
            $addons = array_filter(explode(',', $addons));
        }

        $base       = $this->baseRates[$serviceType]    ?? $this->baseRates['web_design'];
        $multiplier = $this->complexityMultipliers[$complexity] ?? 1.0;
        $addonTotal = 0;

        foreach ($addons as $addon) {
            $addon       = trim($addon);
            $addonTotal += $this->addonRates[$addon] ?? 0;
        }

        $subtotal = ($base * $multiplier) + $addonTotal;
        $low      = (int) round($subtotal * 0.9);
        $high     = (int) round($subtotal * 1.2);

        return [
            'base'         => $base,
            'multiplier'   => $multiplier,
            'addon_total'  => $addonTotal,
            'subtotal'     => (int) round($subtotal),
            'range_low'    => $low,
            'range_high'   => $high,
            'currency'     => 'USD',
        ];
    }
}
