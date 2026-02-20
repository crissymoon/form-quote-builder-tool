#!/usr/bin/env python3
"""
validate_quote.py

CLI wrapper around the trained DecisionTreeClassifier pkl.
Called by QuoteValidator.php via shell_exec().

Usage:
  python3 validate_quote.py \
    --pkl  /path/to/quote_math_model.pkl \
    --service web_design \
    --complexity simple \
    --subtotal 1500 \
    --low 1350 \
    --high 1800 \
    [--addons branding seo_basic]

Output (stdout, JSON):
  {"correct": true, "confidence": 1.0, "rule_ok": true, "model_ok": true,
   "expected": {"subtotal": 1500, "range_low": 1350, "range_high": 1800},
   "error_fields": []}
"""
import argparse
import json
import sys
import pickle
from pathlib import Path

import pandas as pd


def build_row(b, service, complexity, addons, sub, lo, hi):
    BR  = b["base_rates"]
    CM  = b["complexity_mult"]
    AR  = b["addon_rates"]
    AL  = list(AR.keys())
    les = b["le_service"]
    lec = b["le_complexity"]

    base = BR[service]
    mult = CM[complexity]
    at   = sum(AR[a] for a in addons if a in AR)
    exp  = base * mult + at

    row = {
        "service_enc"          : les.transform([service])[0],
        "complexity_enc"       : lec.transform([complexity])[0],
        **{f"addon_{a}": (1 if a in addons else 0) for a in AL},
        "base_rate"            : base,
        "complexity_multiplier": mult,
        "addon_total"          : at,
        "subtotal"             : sub,
        "range_low"            : lo,
        "range_high"           : hi,
        "expected_subtotal"    : exp,
        "low_delta"            : lo - round(exp * 0.9),
        "high_delta"           : hi - round(exp * 1.2),
    }
    return pd.DataFrame([row])[b["features"]], exp


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--pkl",        required=True)
    parser.add_argument("--service",    required=True)
    parser.add_argument("--complexity", required=True)
    parser.add_argument("--subtotal",   required=True, type=int)
    parser.add_argument("--low",        required=True, type=int)
    parser.add_argument("--high",       required=True, type=int)
    parser.add_argument("--addons",     nargs="*", default=[])
    args = parser.parse_args()

    pkl_path = Path(args.pkl)
    if not pkl_path.exists():
        print(json.dumps({"error": f"pkl not found: {pkl_path}"}))
        sys.exit(1)

    with open(pkl_path, "rb") as f:
        b = pickle.load(f)

    model = b["model"]

    try:
        row_df, exp = build_row(
            b, args.service, args.complexity, args.addons,
            args.subtotal, args.low, args.high
        )
    except (KeyError, Exception) as e:
        print(json.dumps({"error": str(e)}))
        sys.exit(1)

    expected_sub  = round(exp)
    expected_low  = round(exp * 0.9)
    expected_high = round(exp * 1.2)

    rule_ok = (
        args.subtotal == expected_sub  and
        args.low      == expected_low  and
        args.high     == expected_high
    )

    proba      = model.predict_proba(row_df)[0]
    confidence = float(proba[1])
    model_ok   = confidence >= 0.5

    error_fields = []
    if args.subtotal != expected_sub:  error_fields.append("subtotal")
    if args.low      != expected_low:  error_fields.append("range_low")
    if args.high     != expected_high: error_fields.append("range_high")

    result = {
        "correct"     : rule_ok and model_ok,
        "confidence"  : round(confidence, 4),
        "rule_ok"     : rule_ok,
        "model_ok"    : model_ok,
        "expected"    : {
            "subtotal"  : expected_sub,
            "range_low" : expected_low,
            "range_high": expected_high,
        },
        "error_fields": error_fields,
    }

    print(json.dumps(result))


if __name__ == "__main__":
    main()
