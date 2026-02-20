# QuoteValidator and ML model integration

- Date: 2026-02-20
- Author: CrissyMoon
- Tags: none

## Notes

Integrated the trained model into the PHP app. Added src/lib/QuoteValidator.php which runs a two-layer check: (1) deterministic rule check re-implementing the QuoteEngine formula in PHP, (2) optional ML check via shell_exec calling ml/validate_quote.py which loads quote_math_model.pkl and returns JSON. index.php now validates every submitted estimate before storing the record. result.php shows a Math Verified or Calculation Error Detected badge with rule check status, ML confidence score, and wrong field names when applicable. Badge styles added to main.css.
