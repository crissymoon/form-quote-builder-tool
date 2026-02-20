import json, numpy as np, pandas as pd
from pathlib import Path
from sklearn.model_selection import train_test_split
from sklearn.tree import DecisionTreeClassifier
from sklearn.preprocessing import LabelEncoder

CSV = Path('/Users/mac/Documents/form-builder/ml/quote_math_validation.csv')
df = pd.read_csv(CSV)

le_service    = LabelEncoder().fit(df["service_type"])
le_complexity = LabelEncoder().fit(df["complexity"])
df["service_enc"]    = le_service.transform(df["service_type"])
df["complexity_enc"] = le_complexity.transform(df["complexity"])

ADDON_COLS = [c for c in df.columns if c.startswith("addon_") and c != "addon_total"]
NUM_COLS   = ["base_rate","complexity_multiplier","addon_total","subtotal","range_low","range_high"]
CAT_COLS   = ["service_enc","complexity_enc"]
FEATURES   = CAT_COLS + NUM_COLS + ADDON_COLS

def add_derived(frame):
    f = frame.copy().reset_index(drop=True)
    base=f["base_rate"].values; mult=f["complexity_multiplier"].values
    addons=f["addon_total"].values; low=f["range_low"].values; high=f["range_high"].values
    exp=base*mult+addons
    f["expected_subtotal"]=exp; f["low_delta"]=low-np.round(exp*0.9); f["high_delta"]=high-np.round(exp*1.2)
    return f

X=df[FEATURES]; y=df["math_correct"]
X_train,X_test,y_train,y_test=train_test_split(X,y,test_size=0.2,random_state=42,stratify=y)
X_train_fe=add_derived(X_train); X_test_fe=add_derived(X_test)
FEATURES_FE=FEATURES+["expected_subtotal","low_delta","high_delta"]
model=DecisionTreeClassifier(max_depth=5,min_samples_leaf=2,random_state=42,class_weight="balanced")
model.fit(X_train_fe[FEATURES_FE],y_train)

BASE_RATES={"web_design":1500,"web_development":3500,"ecommerce":4500,"software":7500,"ai_web_app":9500,"ai_native_app":14000}
COMPLEXITY_MULTIPLIERS={"simple":1.0,"moderate":1.4,"complex":2.0,"custom":2.8}
ADDON_RATES={"seo_basic":500,"seo_advanced":1200,"copywriting":800,"branding":1800,"maintenance":1200,"hosting_setup":350,"api_integration":1500,"automation":2200}
ADDONS_LIST=list(ADDON_RATES.keys())

def _build_row(service_type,complexity,addons,claimed_subtotal,claimed_range_low,claimed_range_high):
    base=BASE_RATES[service_type]; multiplier=COMPLEXITY_MULTIPLIERS[complexity]
    addon_total=sum(ADDON_RATES[a] for a in addons if a in ADDON_RATES); exp=base*multiplier+addon_total
    row={"service_enc":le_service.transform([service_type])[0],"complexity_enc":le_complexity.transform([complexity])[0],
         **{f"addon_{a}":(1 if a in addons else 0) for a in ADDONS_LIST},
         "base_rate":base,"complexity_multiplier":multiplier,"addon_total":addon_total,
         "subtotal":claimed_subtotal,"range_low":claimed_range_low,"range_high":claimed_range_high,
         "expected_subtotal":exp,"low_delta":claimed_range_low-round(exp*0.9),"high_delta":claimed_range_high-round(exp*1.2)}
    return pd.DataFrame([row])[FEATURES_FE],base,multiplier,addon_total,exp

def predict_quote_detail(service_type,complexity,addons,claimed_subtotal,claimed_range_low,claimed_range_high):
    row_df,base,multiplier,addon_total,exp=_build_row(service_type,complexity,addons,claimed_subtotal,claimed_range_low,claimed_range_high)
    es=round(exp); el=round(exp*0.9); eh=round(exp*1.2)
    rule_ok=(claimed_subtotal==es and claimed_range_low==el and claimed_range_high==eh)
    proba=model.predict_proba(row_df)[0]; confidence=float(proba[1]); model_ok=confidence>=0.5
    error_flags=[]
    if claimed_subtotal!=es: error_flags.append("subtotal")
    if claimed_range_low!=el: error_flags.append("range_low")
    if claimed_range_high!=eh: error_flags.append("range_high")
    return {"correct":rule_ok and model_ok,"confidence":round(confidence,4),"rule_ok":rule_ok,"model_ok":model_ok,
            "expected":{"subtotal":es,"range_low":el,"range_high":eh},
            "deltas":{"subtotal":claimed_subtotal-es,"range_low":claimed_range_low-el,"range_high":claimed_range_high-eh},
            "error_flags":error_flags}

test_cases=[
    {"label":"ai_web_app/complex/branding+api","service_type":"ai_web_app","complexity":"complex","addons":["branding","api_integration"],"claimed_subtotal":22300,"claimed_range_low":20070,"claimed_range_high":26760,"expect_correct":True},
    {"label":"web_design/simple/none","service_type":"web_design","complexity":"simple","addons":[],"claimed_subtotal":1500,"claimed_range_low":1350,"claimed_range_high":1800,"expect_correct":True},
    {"label":"ecommerce/moderate/seo_adv+branding","service_type":"ecommerce","complexity":"moderate","addons":["seo_advanced","branding"],"claimed_subtotal":9300,"claimed_range_low":8370,"claimed_range_high":11160,"expect_correct":True},
    {"label":"software/simple/none","service_type":"software","complexity":"simple","addons":[],"claimed_subtotal":7500,"claimed_range_low":6750,"claimed_range_high":9000,"expect_correct":True},
    {"label":"web_development/moderate/wrong high","service_type":"web_development","complexity":"moderate","addons":["seo_basic"],"claimed_subtotal":5400,"claimed_range_low":4860,"claimed_range_high":9999,"expect_correct":False},
    {"label":"web_design/simple/wrong subtotal","service_type":"web_design","complexity":"simple","addons":[],"claimed_subtotal":9999,"claimed_range_low":1350,"claimed_range_high":1800,"expect_correct":False},
    {"label":"ecommerce/complex/wrong low","service_type":"ecommerce","complexity":"complex","addons":["hosting_setup"],"claimed_subtotal":9350,"claimed_range_low":1000,"claimed_range_high":11220,"expect_correct":False},
    {"label":"software/custom/wrong mult","service_type":"software","complexity":"custom","addons":["automation"],"claimed_subtotal":9700,"claimed_range_low":8730,"claimed_range_high":11640,"expect_correct":False},
    {"label":"ai_web_app/moderate/all off by 1","service_type":"ai_web_app","complexity":"moderate","addons":[],"claimed_subtotal":13301,"claimed_range_low":11971,"claimed_range_high":15961,"expect_correct":False},
]

# verify correct expected values
import itertools
def correct_vals(s,c,a):
    b=BASE_RATES[s]; m=COMPLEXITY_MULTIPLIERS[c]; at=sum(ADDON_RATES[x] for x in a)
    st=round(b*m+at); return st,round(st*0.9),round(st*1.2)

passed=failed=0
for tc in test_cases:
    args={k:v for k,v in tc.items() if k not in("label","expect_correct")}
    d=predict_quote_detail(**args)
    ok=d["correct"]==tc["expect_correct"]
    if ok: passed+=1
    else: failed+=1; print(f"FAIL: {tc['label']} | conf={d['confidence']} correct={d['correct']} expected={tc['expect_correct']} flags={d['error_flags']}")

print(f"\n{passed}/{len(test_cases)} passed, {failed} failed")
