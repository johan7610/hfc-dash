# SaleProbabilityService — Locked v1 Spec

> Status: LOCKED — do not modify without bumping model version and updating this spec.
> Written: 2026-02-20. No code exists yet; this is the pre-implementation contract.

---

## 1. File Structure

```
app/Services/SaleProbability/
├── SaleProbabilityService.php
├── DTOs/
│   ├── SaleProbabilityInput.php
│   └── SaleProbabilityResult.php
└── Support/
    ├── ModelConfig.php
    ├── Sigmoid.php
    ├── Clamp.php
    └── SensitivityRunner.php
```

### Namespace root
`App\Services\SaleProbability`

### No Contracts/ folder in v1
All dependencies injected are concrete (no interfaces needed until a second data source exists).

---

## 2. Database Table: `sale_probability_runs`

```sql
CREATE TABLE sale_probability_runs (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    model_version       VARCHAR(20)         NOT NULL,
    inputs_hash         CHAR(64)            NOT NULL,   -- SHA-256 of canonical inputs JSON
    inputs_json         JSON                NOT NULL,
    outputs_json        JSON                NOT NULL,   -- probabilities + expected_days (top-level only)
    breakdown_json      JSON                NOT NULL,   -- signals, weights, score
    data_sources_json   JSON                NOT NULL,   -- market_analytics reference + any future sources
    created_by          BIGINT UNSIGNED     NULL,
    created_at          TIMESTAMP           NULL,
    updated_at          TIMESTAMP           NULL,

    INDEX idx_inputs_hash   (inputs_hash),
    INDEX idx_model_version (model_version)
);
```

### `data_sources_json` shape
```json
{
    "market_analytics": {
        "run_id":        42,
        "model_version": "v1.0.0",
        "inputs_hash":   "abc123..."
    }
}
```

`run_id` is the PK of `market_analytics_runs`. `model_version` and `inputs_hash` are
denormalised at write-time so the audit trail is stable even if the referenced row is
later superseded.

---

## 3. DTOs

### 3.1 `SaleProbabilityInput`

All fields readonly. Canonical array used for SHA-256 hash (fixed key order, nulls explicit).

| Field                          | Type     | Required | Notes                                                |
|-------------------------------|----------|----------|------------------------------------------------------|
| `subjectPriceInc`              | float    | yes      | Asking/sale price incl. VAT                          |
| `subjectSizeM2`                | ?int     | no       | Floor area m² — needed for price/m² signal           |
| `daysOnMarket`                 | ?int     | no       | Current DOM; null = just listed (treated as 0)       |
| `priceReductionPct`            | ?float   | no       | Cumulative reduction % from original; null = 0       |
| `suburb`                       | string   | yes      | Passed through to market lookup                      |
| `propertyType`                 | string   | yes      | 'house' / 'unit' etc.                               |
| `bedrooms`                     | ?int     | no       |                                                      |
| `referenceDate`                | ?string  | no       | YYYY-MM-DD; null = today                             |
| `sourceBranchId`               | ?int     | no       |                                                      |
| `marketAnalyticsRunId`         | ?int     | no       | Explicit FK — skips re-running MarketAnalyticsService|
| `marketAnalyticsModelVersion`  | ?string  | no       | Denormalised — required if `marketAnalyticsRunId` set|
| `marketAnalyticsInputsHash`    | ?string  | no       | Denormalised — required if `marketAnalyticsRunId` set|

When `marketAnalyticsRunId` is null, the service calls `MarketAnalyticsService::run()` first
and captures the resulting run record before proceeding.

### 3.2 `SaleProbabilityResult`

All properties start null. Methods mirror `MarketAnalyticsResult` pattern:
- `toValuesArray()` — flat outputs for `outputs_json`
- `toBreakdownArray()` — signal breakdown for `breakdown_json`
- `toDataSourcesArray()` — source refs for `data_sources_json`
- Static factory: `SaleProbabilityResult::empty()`

Top-level output fields:

| Property        | Type    | Skip-safe |
|----------------|---------|-----------|
| `p30`          | ?float  | yes       |
| `p60`          | ?float  | yes       |
| `p90`          | ?float  | yes       |
| `expectedDays` | ?int    | yes       |
| `skipReason`   | ?string | —         |

---

## 4. Support Classes

### 4.1 `ModelConfig`

Holds ALL tunable constants. Changing any constant requires a version bump.

```
MODEL_VERSION = 'v1.0.0'

WEIGHTS (must sum to 1.0):
  WEIGHT_PRICE      = 0.30
  WEIGHT_ABSORPTION = 0.25
  WEIGHT_PRESSURE   = 0.20
  WEIGHT_DOM        = 0.15
  WEIGHT_REDUCTION  = 0.10

SIGMOID PARAMS (centre, steepness) per horizon:
  P30: centre = 0.70, steepness = 8
  P60: centre = 0.50, steepness = 7
  P90: centre = 0.30, steepness = 6

SENSITIVITY STEP = 0.05   (0.00 → 1.00 inclusive = 21 points)

NORMALISATION ANCHORS (in ModelConfig constants):
  PRICE_DEVIATION_RANGE = 60  (±30% maps to 0..1)
  ABSORPTION_MAX_MONTHS = 6
  DOM_OVERSHOOT_FACTOR  = 1.5
  REDUCTION_MAX_PCT     = 15
  EXPECTED_DAYS_MIN     = 1
  EXPECTED_DAYS_MAX     = 730
```

### 4.2 `Sigmoid`

Pure static utility. No state.

```
sigmoid(x): float
    return 1.0 / (1.0 + exp(-x))

apply(value, centre, steepness): float
    return sigmoid((value - centre) * steepness)
```

### 4.3 `Clamp`

Pure static utility.

```
clamp(value, min, max): float
    return max(min, min(value, max))
```

### 4.4 `SensitivityRunner`

Takes a `ModelConfig` instance.

```
generate(domMedian, domP75): array
    for score in 0.00, 0.05, ..., 1.00:
        p30, p60, p90 = computeProbabilities(score, config)
        expected_days = computeExpectedDays(score, domMedian, domP75, config)
        append { score, p30, p60, p90, expected_days }
    return array of 21 entries
```

`SensitivityRunner` does NOT accept market signals — it sweeps `composite_score` directly.
Expected-days computation within the runner uses only `domMedian`, `domP75`, and
`composite_score` (no elasticity adjustment, to keep curves clean).

---

## 5. V1 Deterministic Formulas

### 5.1 Signal Normalisation (→ 0..1)

#### Signal 1 — Price-to-Market (`s_price`)
- **Raw input:** `pricePerSqmDeviationPct` from `MarketAnalyticsResult`
- **Meaning:** negative = subject cheaper than market = more attractive
- **Formula:** `s_price = clamp(0.5 - (deviation_pct / 60), 0, 1)`
- **Anchors:** −30% deviation → 1.0 (cheap); +30% deviation → 0.0 (expensive)
- **Skip if:** `pricePerSqmDeviationPct` is null OR `subjectSizeM2` is null
- **Skip reason:** `'insufficient_price_sqm_data'`
- **Classification:** required

#### Signal 2 — Market Absorption (`s_absorption`)
- **Raw input:** `monthsOfInventory` from `MarketAnalyticsResult`
- **Meaning:** low MOI = seller's market = faster sale
- **Formula:** `s_absorption = clamp((6 - months_of_inventory) / 5, 0, 1)`
- **Anchors:** ≤1 month → 1.0; ≥6 months → 0.0
- **Skip if:** `monthsOfInventory` is null
- **Skip reason:** `'insufficient_absorption_data'`
- **Classification:** required

#### Signal 3 — Demand-Supply Pressure (`s_pressure`)
- **Raw input:** `demandSupplyRatio` from `MarketAnalyticsResult`
- **Meaning:** >1 = more demand than supply
- **Formula:** `s_pressure = Sigmoid::apply(demand_supply_ratio - 1.0, centre: 0, steepness: 3)`
  - i.e. `sigmoid((dsr - 1.0) * 3)`
- **Anchors:** DSR 1.0 → 0.5; DSR 2.0 → ≈0.95; DSR 0.0 → ≈0.05
- **Skip if:** `demandSupplyRatio` is null
- **Skip reason:** `'insufficient_stock_pressure_data'`
- **Classification:** required

#### Signal 4 — DOM Position (`s_dom`)
- **Raw inputs:** `daysOnMarket` (subject, nullable), `domCurve['p50']` from `MarketAnalyticsResult`
- **Meaning:** subject DOM < market p50 → early in campaign → higher probability
- **Pre-step:** if `daysOnMarket` is null → treat as 0 (just listed)
- **Formula:** `s_dom = clamp(1 - (subject_dom / (dom_p50 * 1.5)), 0, 1)`
- **Anchors:** DOM 0 → 1.0; DOM ≥ p50×1.5 → 0.0
- **Skip if:** `domCurve` is null OR `domCurve['p50']` is null
- **Skip reason:** `'insufficient_dom_data'`
- **Classification:** required

#### Signal 5 — Price Reduction Fatigue (`s_reduction`)
- **Raw input:** `priceReductionPct` (nullable, from input DTO)
- **Meaning:** cumulative reduction signals market resistance
- **Pre-step:** if `priceReductionPct` is null → treat as 0.0
- **Formula:** `s_reduction = clamp(1 - (reduction_pct / 15), 0, 1)`
- **Anchors:** 0% → 1.0; ≥15% → 0.0
- **Skip if:** never — always computable (defaults to 0% if null)
- **Classification:** optional

### 5.2 Composite Score

```
active_signals = all signals where skip == false

weighted_sum  = sum(signal.normalized * signal.weight for active_signals)
weight_total  = sum(signal.weight for active_signals)
composite_score = weighted_sum / weight_total   -- renormalised if any required skipped

composite_score = clamp(composite_score, 0, 1)
```

Weights come from `ModelConfig`. When a required signal is skipped, its weight is
redistributed proportionally across remaining active signals. This is the ONLY place
weight redistribution happens.

If 2 or more required signals are skipped → entire probabilities output returns null;
`skipReason` = comma-joined list of individual skip reasons.

### 5.3 Probability Mapping

```
p30_raw = Sigmoid::apply(composite_score, centre: 0.70, steepness: 8)
p60_raw = Sigmoid::apply(composite_score, centre: 0.50, steepness: 7)
p90_raw = Sigmoid::apply(composite_score, centre: 0.30, steepness: 6)

-- Monotonic enforcement (required — never skip):
p30 = clamp(p30_raw, 0, 1)
p60 = max(p60_raw, p30)
p90 = max(p90_raw, p60)

-- Round to 4 decimal places:
p30 = round(p30, 4)
p60 = round(p60, 4)
p90 = round(p90, 4)
```

p30 ≤ p60 ≤ p90 is a hard invariant. The implementation MUST assert this before returning.

### 5.4 Expected Days to Sell

```
dom_median = domCurve['p50']     -- from MarketAnalyticsResult
dom_p75    = domCurve['p75']     -- from MarketAnalyticsResult

-- Base interpolation (lower score = pulled toward p75)
base_days = dom_p75 - ((dom_p75 - dom_median) * composite_score)

-- Elasticity adjustment (only if both values available)
if elasticityDaysPerPct != null AND pricePerSqmDeviationPct != null:
    deviation_positive = max(pricePerSqmDeviationPct, 0)   -- only penalise overpricing
    base_days += elasticityDaysPerPct * deviation_positive

expected_days = clamp(base_days, EXPECTED_DAYS_MIN, EXPECTED_DAYS_MAX)
expected_days = round(expected_days)   -- integer
```

**Skip if:** `domCurve` is null OR `domCurve['p50']` or `domCurve['p75']` missing
→ `expectedDays` = null; skip_reason appended: `'insufficient_dom_data'`

### 5.5 Sensitivity Curve

`SensitivityRunner::generate()` sweeps `composite_score` 0.00→1.00 in 0.05 steps (21 points).

For each step it applies sections 5.3 (probabilities) and 5.4 (expected_days, without
elasticity adjustment). Output is an array of 21 objects (see Section 6).

---

## 6. Output JSON Shapes

### `outputs_json` (stored in `sale_probability_runs.outputs_json`)
```json
{
    "probabilities": {
        "p30": 0.4821,
        "p60": 0.7134,
        "p90": 0.8967
    },
    "expected_days": 42
}
```
Nulls are explicit when skipped (e.g. `"p30": null`).

### `breakdown_json` (stored in `sale_probability_runs.breakdown_json`)
```json
{
    "composite_score": 0.6314,
    "weight_redistribution": false,
    "signals": {
        "price": {
            "raw":          -8.3,
            "normalized":   0.6383,
            "weight":       0.30,
            "contribution": 0.1915,
            "skip":         false,
            "skip_reason":  null
        },
        "absorption": {
            "raw":          2.1,
            "normalized":   0.7800,
            "weight":       0.25,
            "contribution": 0.1950,
            "skip":         false,
            "skip_reason":  null
        },
        "pressure": {
            "raw":          1.4,
            "normalized":   0.8320,
            "weight":       0.20,
            "contribution": 0.1664,
            "skip":         false,
            "skip_reason":  null
        },
        "dom": {
            "raw":          18,
            "normalized":   0.7000,
            "weight":       0.15,
            "contribution": 0.1050,
            "skip":         false,
            "skip_reason":  null
        },
        "reduction": {
            "raw":          2.5,
            "normalized":   0.8333,
            "weight":       0.10,
            "contribution": 0.0833,
            "skip":         false,
            "skip_reason":  null
        }
    },
    "weights_used": {
        "price": 0.30, "absorption": 0.25,
        "pressure": 0.20, "dom": 0.15, "reduction": 0.10
    },
    "skip_reasons": []
}
```

### `sensitivity` — returned on `SaleProbabilityResult` but NOT stored in any column
```json
[
    {"score": 0.00, "p30": 0.0012, "p60": 0.0067, "p90": 0.0474, "expected_days": 112},
    {"score": 0.05, "p30": 0.0018, "p60": 0.0096, "p90": 0.0632, "expected_days": 109},
    ...
    {"score": 1.00, "p30": 0.9526, "p60": 0.9933, "p90": 0.9988, "expected_days": 21}
]
```
21 entries total. `expected_days` uses base interpolation only (no elasticity — see 5.5).

---

## 7. Skip Discipline Rules

1. Each signal returns `['normalized' => null, 'skip' => true, 'skip_reason' => '...']` when
   its required inputs are null.
2. Optional signal (`reduction`) NEVER skips — defaults to 0% reduction.
3. `expectedDays` is independently skippable — its skip_reason is separate from probabilities.
4. **Never return 0** for a probability when the reason is missing data — return null.
5. Probabilities go null when ≥2 required signals are skipped (not when 0 or 1 required
   signal is skipped — weight redistribution handles that case).
6. When probabilities are null, `expectedDays` should also be null (cannot interpret DOM
   without a composite score).
7. `SaleProbabilityResult::skipReason` holds the final aggregate (comma-joined) reason string.

---

## 8. Versioning Policy

| Change type                                        | Version bump      |
|----------------------------------------------------|-------------------|
| Formula constant tweak (weight, anchor, steepness) | Patch: v1.0.x     |
| New optional signal added                          | Minor: v1.x.0     |
| New required signal added                          | Minor: v1.x.0     |
| Skip discipline change                             | Minor: v1.x.0     |
| Structural formula change (new normalisation type) | Major: vX.0.0     |
| New probability horizon (e.g. p120)                | Minor: v1.x.0     |

- `MODEL_VERSION` constant lives in `ModelConfig`.
- Every `sale_probability_runs` row records the version at write-time.
- Rows are NEVER retroactively recomputed — run the service fresh for a new version.
- Cross-service: `data_sources_json.market_analytics.model_version` records the MA version
  used as input, enabling audit of compound version interactions.

---

## 9. Service Flow (pseudocode — not final code)

```
SaleProbabilityService::run(SaleProbabilityInput $input): SaleProbabilityResult

1. Compute inputsHash from $input->toCanonicalArray()

2. Resolve market analytics:
   if $input->marketAnalyticsRunId != null:
       load MarketAnalyticsRun by id → extract outputs_json into MarketAnalyticsResult-like struct
       maRunId      = $input->marketAnalyticsRunId
       maVersion    = $input->marketAnalyticsModelVersion
       maInputsHash = $input->marketAnalyticsInputsHash
   else:
       build MarketAnalyticsInput from $input fields
       $maResult = MarketAnalyticsService::run(MarketAnalyticsInput)
       load last inserted MarketAnalyticsRun to get id/version/hash
       maRunId      = that run id
       maVersion    = MarketAnalyticsService::MODEL_VERSION
       maInputsHash = InputHasher::hash(MarketAnalyticsInput)

3. Compute signals (Section 5.1)
   Each returns: [raw, normalized, weight, contribution, skip, skip_reason]

4. Compute composite_score (Section 5.2)
   Check skip threshold → short-circuit if ≥2 required signals skipped

5. Compute probabilities (Section 5.3)
   Assert monotonic invariant

6. Compute expectedDays (Section 5.4)

7. Generate sensitivity curve via SensitivityRunner (Section 5.5)

8. Assemble SaleProbabilityResult

9. Persist SaleProbabilityRun::create([...])

10. Return $result
```

---

## 10. What This Spec Does NOT Cover (deferred to later phases)

- Caching / deduplication by `inputs_hash` (not in v1 — always runs fresh)
- HTTP endpoint / controller wiring
- UI/frontend consumption of sensitivity array
- Batch runs or queued jobs
- User-facing explanation text generation
- Confidence intervals or uncertainty bands
- Integration with deal/listing records via FK (currently inputs are freeform)
