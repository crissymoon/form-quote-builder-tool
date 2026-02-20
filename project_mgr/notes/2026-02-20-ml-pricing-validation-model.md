# ML pricing validation model

- Date: 2026-02-20
- Author: CrissyMoon
- Tags: none

## Notes

Added ml/ directory with gen_data.py (generates 2907 labelled rows), quote_math_validation.csv, and quote_math_validator.ipynb (Kaggle notebook). Notebook trains a DecisionTreeClassifier to verify QuoteEngine math. Sections cover data loading, preprocessing, feature engineering, training, evaluation, inference with predict_quote_detail(), confidence scoring, extended test suite, model save to pkl, and reload smoke test. Pushed to Kaggle dataset crissymoon/quote-math-validation.
