<?php

namespace App\Services\SaleProbability\Support;

class ModelConfig
{
    public const MODEL_VERSION = 'prob-v1.0.0';

    // ── Weights (must sum to 1.0) ─────────────────────────────────────────────
    public const WEIGHT_PRICE      = 0.30;
    public const WEIGHT_ABSORPTION = 0.25;
    public const WEIGHT_PRESSURE   = 0.20;
    public const WEIGHT_DOM        = 0.15;
    public const WEIGHT_ELASTICITY = 0.10;

    // ── Signal classification ─────────────────────────────────────────────────
    public const REQUIRED_SIGNALS = ['price', 'absorption', 'pressure', 'dom'];
    public const OPTIONAL_SIGNALS = ['elasticity'];

    // ── Normalisation anchors — Signal: price ─────────────────────────────────
    // Formula: clamp(0.5 - (deviation_pct / PRICE_DEVIATION_RANGE), 0, 1)
    // −30% deviation → 1.0 (cheap vs market); +30% deviation → 0.0 (expensive)
    public const PRICE_DEVIATION_RANGE = 60.0;

    // ── Normalisation anchors — Signal: absorption ────────────────────────────
    // Formula: clamp((BAD - raw) / (BAD - IDEAL), 0, 1)
    // ≤IDEAL months → 1.0 (seller's market); ≥BAD months → 0.0 (buyer's market)
    public const ABSORPTION_IDEAL_MONTHS = 1.0;
    public const ABSORPTION_BAD_MONTHS   = 6.0;

    // ── Normalisation anchors — Signal: pressure ──────────────────────────────
    // Formula: sigmoid((raw - PRESSURE_SIGMOID_OFFSET) * PRESSURE_SIGMOID_STEEPNESS)
    // DSR 1.0 → 0.5 (balanced); DSR 2.0 → ≈0.95 (demand); DSR 0.0 → ≈0.05 (oversupply)
    public const PRESSURE_SIGMOID_OFFSET     = 1.0;
    public const PRESSURE_SIGMOID_STEEPNESS  = 3.0;

    // ── Normalisation anchors — Signal: dom ──────────────────────────────────
    // Formula: clamp(1 - (p50 - IDEAL) / (BAD - IDEAL), 0, 1)
    // ≤IDEAL days → 1.0 (hot market); ≥BAD days → 0.0 (slow market)
    public const DOM_IDEAL_DAYS = 30.0;
    public const DOM_BAD_DAYS   = 180.0;

    // ── Normalisation anchors — Signal: elasticity ────────────────────────────
    // Formula: clamp((CLAMP_MAX - clamp(raw, CLAMP_MIN, CLAMP_MAX)) / (CLAMP_MAX - CLAMP_MIN), 0, 1)
    // ideal=−2 days/% (price drop helps → faster sale); bad=+2 (price cut fails to help)
    public const ELASTICITY_CLAMP_MIN = -5.0;
    public const ELASTICITY_CLAMP_MAX =  5.0;
    public const ELASTICITY_IDEAL     = -2.0;
    public const ELASTICITY_BAD       =  2.0;

    // ── Probability mapping sigmoid params ────────────────────────────────────
    public const P30_CENTRE    = 0.70;
    public const P30_STEEPNESS = 8.0;
    public const P60_CENTRE    = 0.50;
    public const P60_STEEPNESS = 7.0;
    public const P90_CENTRE    = 0.30;
    public const P90_STEEPNESS = 6.0;

    // ── Expected days bounds ──────────────────────────────────────────────────
    public const EXPECTED_DAYS_MIN = 1;
    public const EXPECTED_DAYS_MAX = 730;

    // ── Skip threshold ────────────────────────────────────────────────────────
    // If required signals missing >= this threshold, skip probability computation.
    public const REQUIRED_SIGNALS_SKIP_THRESHOLD = 2;
}
