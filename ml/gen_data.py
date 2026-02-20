import csv, itertools, random, os

BASE_RATES = {
    'web_design':      1500,
    'web_development': 3500,
    'ecommerce':       4500,
    'software':        7500,
    'ai_web_app':      9500,
    'ai_native_app':   14000,
}

COMPLEXITY_MULTIPLIERS = {
    'simple':   1.0,
    'moderate': 1.4,
    'complex':  2.0,
    'custom':   2.8,
}

ADDON_RATES = {
    'seo_basic':       500,
    'seo_advanced':    1200,
    'copywriting':     800,
    'branding':        1800,
    'maintenance':     1200,
    'hosting_setup':   350,
    'api_integration': 1500,
    'automation':      2200,
}

ADDONS = list(ADDON_RATES.keys())

def calculate(service, complexity, addons):
    base        = BASE_RATES[service]
    multiplier  = COMPLEXITY_MULTIPLIERS[complexity]
    addon_total = sum(ADDON_RATES[a] for a in addons)
    subtotal    = base * multiplier + addon_total
    range_low   = round(subtotal * 0.9)
    range_high  = round(subtotal * 1.2)
    return base, multiplier, addon_total, round(subtotal), range_low, range_high

random.seed(42)
rows = []

for service, complexity in itertools.product(BASE_RATES, COMPLEXITY_MULTIPLIERS):
    rows.append((service, complexity, []))
    for a in ADDONS:
        rows.append((service, complexity, [a]))
    for pair in itertools.combinations(ADDONS, 2):
        rows.append((service, complexity, list(pair)))
    for triple in itertools.combinations(ADDONS, 3):
        rows.append((service, complexity, list(triple)))
    for k in (4, 5):
        combos = list(itertools.combinations(ADDONS, k))
        for combo in random.sample(combos, min(6, len(combos))):
            rows.append((service, complexity, list(combo)))
    rows.append((service, complexity, ADDONS[:]))

random.shuffle(rows)

wrong_rows = []
for service, complexity, addons in random.sample(rows, max(1, len(rows) // 7)):
    base, multiplier, addon_total, subtotal, range_low, range_high = calculate(service, complexity, addons)
    err = random.choice(['wrong_multiplier', 'wrong_range_low', 'wrong_range_high'])
    if err == 'wrong_multiplier':
        bad_mult     = multiplier * random.choice([1.1, 0.9, 1.25, 0.8])
        bad_subtotal = round(base * bad_mult + addon_total)
        bad_low      = round(bad_subtotal * 0.9)
        bad_high     = round(bad_subtotal * 1.2)
        wrong_rows.append((service, complexity, addons, base, multiplier, addon_total, bad_subtotal, bad_low, bad_high, 0, err))
    elif err == 'wrong_range_low':
        bad_low = round(subtotal * random.choice([0.8, 0.95, 1.0]))
        wrong_rows.append((service, complexity, addons, base, multiplier, addon_total, subtotal, bad_low, range_high, 0, err))
    else:
        bad_high = round(subtotal * random.choice([1.1, 1.35, 1.5]))
        wrong_rows.append((service, complexity, addons, base, multiplier, addon_total, subtotal, range_low, bad_high, 0, err))

addon_cols = ['addon_' + a for a in ADDONS]
header = ['service_type', 'complexity'] + addon_cols + [
    'base_rate', 'complexity_multiplier', 'addon_total',
    'subtotal', 'range_low', 'range_high', 'math_correct', 'error_type'
]

out = os.path.join(os.path.dirname(__file__), 'quote_math_validation.csv')
with open(out, 'w', newline='') as f:
    w = csv.writer(f)
    w.writerow(header)
    for service, complexity, addons in rows:
        base, multiplier, addon_total, subtotal, range_low, range_high = calculate(service, complexity, addons)
        flags = [1 if a in addons else 0 for a in ADDONS]
        w.writerow([service, complexity] + flags + [base, multiplier, addon_total, subtotal, range_low, range_high, 1, 'none'])
    for r in wrong_rows:
        service, complexity, addons, base, multiplier, addon_total, subtotal, range_low, range_high, label, err = r
        flags = [1 if a in addons else 0 for a in ADDONS]
        w.writerow([service, complexity] + flags + [base, multiplier, addon_total, subtotal, range_low, range_high, label, err])

print(f"OK: {len(rows)} correct + {len(wrong_rows)} wrong = {len(rows)+len(wrong_rows)} total rows")
