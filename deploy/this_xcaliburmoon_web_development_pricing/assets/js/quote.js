/**
 * XcaliburMoon Quote System - Client-Side Logic
 * Uses Math.js for precision arithmetic and Safari compatibility.
 */

(function () {
    'use strict';

    // Base rates mirror QuoteEngine.php
    var BASE_RATES = {
        web_design:      1500,
        web_development: 3500,
        ecommerce:       4500,
        software:        7500,
        ai_web_app:      9500,
        ai_native_app:   14000
    };

    var COMPLEXITY_MULTIPLIERS = {
        simple:   1.0,
        moderate: 1.4,
        complex:  2.0,
        custom:   2.8
    };

    var ADDON_RATES = {
        seo_basic:       500,
        seo_advanced:    1200,
        copywriting:     800,
        branding:        1800,
        maintenance:     1200,
        hosting_setup:   350,
        api_integration: 1500,
        automation:      2200
    };

    function calculateEstimate(serviceType, complexity, addons) {
        if (typeof math === 'undefined') {
            // Math.js not loaded; fall back to native arithmetic
            var base       = BASE_RATES[serviceType]   || BASE_RATES.web_design;
            var multiplier = COMPLEXITY_MULTIPLIERS[complexity] || 1.0;
            var addonTotal = 0;
            if (Array.isArray(addons)) {
                addons.forEach(function (a) {
                    addonTotal += ADDON_RATES[a] || 0;
                });
            }
            var subtotal = (base * multiplier) + addonTotal;
            return {
                low:  Math.round(subtotal * 0.9),
                high: Math.round(subtotal * 1.2)
            };
        }

        var base       = BASE_RATES[serviceType]   || BASE_RATES.web_design;
        var multiplier = COMPLEXITY_MULTIPLIERS[complexity] || 1.0;
        var addonTotal = 0;

        if (Array.isArray(addons)) {
            addons.forEach(function (a) {
                addonTotal = math.add(addonTotal, ADDON_RATES[a] || 0);
            });
        }

        var subtotal = math.add(math.multiply(base, multiplier), addonTotal);
        var low      = Math.round(math.multiply(subtotal, 0.9));
        var high     = Math.round(math.multiply(subtotal, 1.2));

        return { low: low, high: high };
    }

    function formatMoney(amount) {
        return '$' + amount.toLocaleString('en-US', { maximumFractionDigits: 0 });
    }

    function updateLiveEstimate() {
        var serviceEl    = document.querySelector('[name="service_type"]');
        var complexityEl = document.querySelector('[name="complexity"]:checked');
        var addonEls     = document.querySelectorAll('[name="addons[]"]');
        var displayEl    = document.getElementById('live-estimate-display');

        if (!displayEl) { return; }

        var serviceType = serviceEl ? serviceEl.value : 'web_design';
        var complexity  = complexityEl ? complexityEl.value : 'simple';
        var addons      = [];

        addonEls.forEach(function (el) {
            if (el.checked) { addons.push(el.value); }
        });

        if (!serviceType) { return; }

        var result = calculateEstimate(serviceType, complexity, addons);
        displayEl.textContent = formatMoney(result.low) + ' - ' + formatMoney(result.high);
    }

    function attachListeners() {
        var form = document.querySelector('.step-form');
        if (!form) { return; }

        form.addEventListener('change', function () {
            updateLiveEstimate();
        });

        form.addEventListener('input', function () {
            updateLiveEstimate();
        });

        updateLiveEstimate();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', attachListeners);
    } else {
        attachListeners();
    }
}());
