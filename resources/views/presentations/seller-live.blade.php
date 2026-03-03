<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Seller Live — {{ $presentation->property_address ?? 'Presentation' }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: #0a0a0a;
            color: #f0f0f0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', system-ui, sans-serif;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* ── Header ── */
        .sl-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem 2rem;
        }
        .sl-address {
            font-size: 1.1rem;
            font-weight: 500;
            color: #a0a0a0;
            letter-spacing: 0.02em;
        }
        .sl-back {
            color: #666;
            text-decoration: none;
            font-size: 0.85rem;
            padding: 0.4rem 1rem;
            border: 1px solid #333;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .sl-back:hover { color: #ccc; border-color: #555; }

        /* ── Main content ── */
        .sl-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 0 2rem;
            gap: 2rem;
        }

        /* ── Price display ── */
        .sl-price {
            font-size: 4rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            text-align: center;
            transition: color 0.3s;
        }
        @media (max-width: 640px) { .sl-price { font-size: 2.5rem; } }

        /* ── Adjustment buttons ── */
        .sl-adjustments {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            justify-content: center;
        }
        .sl-adj-btn {
            background: #1a1a1a;
            border: 1px solid #333;
            color: #ccc;
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.15s;
            user-select: none;
        }
        .sl-adj-btn:hover { background: #252525; border-color: #555; color: #fff; }
        .sl-adj-btn:active { transform: scale(0.96); }
        .sl-adj-btn.sl-minus { color: #ef4444; }
        .sl-adj-btn.sl-plus { color: #22c55e; }

        /* ── Probability card ── */
        .sl-prob-card {
            text-align: center;
            padding: 1.5rem 3rem;
            background: #111;
            border-radius: 12px;
            border: 1px solid #222;
            min-width: 340px;
        }
        .sl-prob-label {
            font-size: 2rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            transition: color 0.3s;
        }
        .sl-prob-subtitle {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
        .sl-prob-stats {
            display: flex;
            justify-content: center;
            gap: 2.5rem;
            margin-top: 1rem;
        }
        .sl-stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #fff;
        }
        .sl-stat-label {
            font-size: 0.75rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        /* ── Net proceeds bars ── */
        .sl-net-card {
            width: 100%;
            max-width: 600px;
            background: #111;
            border-radius: 12px;
            border: 1px solid #222;
            padding: 1.25rem 1.5rem;
        }
        .sl-net-title {
            font-size: 0.75rem;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 1rem;
        }
        .sl-bar-row {
            margin-bottom: 0.75rem;
        }
        .sl-bar-row:last-child { margin-bottom: 0; }
        .sl-bar-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.3rem;
        }
        .sl-bar-label {
            font-size: 0.8rem;
            color: #999;
        }
        .sl-bar-value {
            font-size: 0.8rem;
            font-weight: 600;
            color: #fff;
        }
        .sl-bar-track {
            height: 24px;
            background: #1a1a1a;
            border-radius: 6px;
            overflow: hidden;
        }
        .sl-bar-fill {
            height: 100%;
            border-radius: 6px;
            transition: width 0.5s ease, background-color 0.3s;
        }
        .sl-bar-fill.sl-current { background: #3b82f6; }
        .sl-bar-fill.sl-cma { background: #666; }
        .sl-bar-fill.sl-asking { background: #444; }

        /* ── Capture button ── */
        .sl-footer {
            padding: 1.25rem 2rem;
            text-align: center;
        }
        .sl-capture-btn {
            background: #1a1a1a;
            border: 1px solid #333;
            color: #ccc;
            padding: 0.7rem 2rem;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .sl-capture-btn:hover { background: #252525; border-color: #555; color: #fff; }
        .sl-capture-btn:disabled { opacity: 0.4; cursor: not-allowed; }
        .sl-capture-btn.sl-captured {
            border-color: #22c55e;
            color: #22c55e;
        }

        /* ── Probability colors ── */
        .prob-very-likely  { color: #22c55e; }
        .prob-likely       { color: #84cc16; }
        .prob-possible     { color: #eab308; }
        .prob-unlikely     { color: #f97316; }
        .prob-very-unlikely { color: #ef4444; }
    </style>
</head>
<body>

    <div class="sl-header">
        <div class="sl-address" id="slAddress"></div>
        <a href="{{ route('presentations.show', $presentation) }}" class="sl-back">Back</a>
    </div>

    <div class="sl-main">
        <!-- Price Display -->
        <div class="sl-price" id="slPrice"></div>

        <!-- Adjustment Buttons -->
        <div class="sl-adjustments">
            <button class="sl-adj-btn sl-minus" onclick="adjustPrice(-100000)">-100k</button>
            <button class="sl-adj-btn sl-minus" onclick="adjustPrice(-50000)">-50k</button>
            <button class="sl-adj-btn sl-minus" onclick="adjustPrice(-10000)">-10k</button>
            <button class="sl-adj-btn sl-plus"  onclick="adjustPrice(10000)">+10k</button>
            <button class="sl-adj-btn sl-plus"  onclick="adjustPrice(50000)">+50k</button>
            <button class="sl-adj-btn sl-plus"  onclick="adjustPrice(100000)">+100k</button>
        </div>

        <!-- Probability Card -->
        <div class="sl-prob-card">
            <div class="sl-prob-subtitle">Probability of Sale</div>
            <div class="sl-prob-label" id="slProbLabel"></div>
            <div class="sl-prob-stats">
                <div>
                    <div class="sl-stat-value" id="slCompeting">0</div>
                    <div class="sl-stat-label">Competing Listings</div>
                </div>
                <div>
                    <div class="sl-stat-value" id="slMonths">0</div>
                    <div class="sl-stat-label">Est. Months to Sell</div>
                </div>
            </div>
        </div>

        <!-- Net Proceeds Comparison -->
        <div class="sl-net-card">
            <div class="sl-net-title">Net Proceeds Comparison</div>

            <div class="sl-bar-row">
                <div class="sl-bar-header">
                    <span class="sl-bar-label">At this price</span>
                    <span class="sl-bar-value" id="slNetCurrent"></span>
                </div>
                <div class="sl-bar-track">
                    <div class="sl-bar-fill sl-current" id="slBarCurrent"></div>
                </div>
            </div>

            <div class="sl-bar-row">
                <div class="sl-bar-header">
                    <span class="sl-bar-label">At CMA Middle</span>
                    <span class="sl-bar-value" id="slNetCma"></span>
                </div>
                <div class="sl-bar-track">
                    <div class="sl-bar-fill sl-cma" id="slBarCma"></div>
                </div>
            </div>

            <div class="sl-bar-row">
                <div class="sl-bar-header">
                    <span class="sl-bar-label">At Asking Price</span>
                    <span class="sl-bar-value" id="slNetAsking"></span>
                </div>
                <div class="sl-bar-track">
                    <div class="sl-bar-fill sl-asking" id="slBarAsking"></div>
                </div>
            </div>
        </div>
    </div>

    <div class="sl-footer">
        <button class="sl-capture-btn" id="slCaptureBtn" onclick="capturePrice()">
            Capture &amp; Include in Pack
        </button>
    </div>

    <script>
        // ── Data from server ──
        const DATA = @json($jsData);

        const CAPTURE_URL = @json(route('presentations.seller-live.capture', $presentation));
        const CSRF_TOKEN  = document.querySelector('meta[name="csrf-token"]').content;

        // ── State ──
        let currentPrice = DATA.asking_price || 0;

        // ── Pre-computed reference values (fixed on load) ──
        const cmaMiddleNet = DATA.cma_middle ? computeNet(DATA.cma_middle) : null;
        const askingNet    = DATA.asking_price ? computeNet(DATA.asking_price) : null;

        // ── Formatting ──
        function formatZar(val) {
            if (val == null) return '—';
            const neg = val < 0;
            const abs = Math.abs(Math.round(val));
            const str = abs.toLocaleString('en-ZA');
            return (neg ? '-R ' : 'R ') + str;
        }

        // ── Competing count ──
        function competingCount(price) {
            let count = 0;
            for (let i = 0; i < DATA.listing_prices.length; i++) {
                if (DATA.listing_prices[i] <= price) count++;
            }
            return count;
        }

        // ── Est months ──
        function estMonths(competing) {
            if (!DATA.monthly_sales_rate || DATA.monthly_sales_rate <= 0) return 12;
            let months = (competing + 1) / DATA.monthly_sales_rate;
            if (months > 12) months = 12;
            return Math.round(months * 10) / 10;
        }

        // ── Compute net proceeds for a given price ──
        function computeNet(price) {
            const competing = competingCount(price);
            const months = estMonths(competing);
            const commission = Math.round(price * DATA.commission_pct / 100);
            const transfer   = Math.round(price * DATA.transfer_cost_pct / 100);
            const holding    = Math.round(months * DATA.monthly_holding_cost);
            return price - commission - transfer - holding;
        }

        // ── Probability label ──
        function probabilityLabel(price) {
            if (DATA.cma_lower  && price <= DATA.cma_lower)          return 'Very Likely';
            if (DATA.cma_middle && price <= DATA.cma_middle)         return 'Likely';
            if (DATA.cma_upper  && price <= DATA.cma_upper)          return 'Possible';
            if (DATA.cma_upper  && price <= DATA.cma_upper * 1.10)   return 'Unlikely';
            return 'Very Unlikely';
        }

        // ── Probability CSS class ──
        function probClass(label) {
            const map = {
                'Very Likely':   'prob-very-likely',
                'Likely':        'prob-likely',
                'Possible':      'prob-possible',
                'Unlikely':      'prob-unlikely',
                'Very Unlikely': 'prob-very-unlikely',
            };
            return map[label] || '';
        }

        // ── Update everything ──
        function update() {
            if (currentPrice < 0) currentPrice = 0;

            const competing = competingCount(currentPrice);
            const months    = estMonths(competing);
            const netNow    = computeNet(currentPrice);
            const prob      = probabilityLabel(currentPrice);
            const cls       = probClass(prob);

            // Price
            document.getElementById('slPrice').textContent = formatZar(currentPrice);
            document.getElementById('slPrice').className = 'sl-price ' + cls;

            // Probability
            const probEl = document.getElementById('slProbLabel');
            probEl.textContent = prob;
            probEl.className = 'sl-prob-label ' + cls;

            // Stats
            document.getElementById('slCompeting').textContent = competing;
            document.getElementById('slMonths').textContent = months;

            // Net proceeds bars
            const vals = [netNow];
            if (cmaMiddleNet != null) vals.push(cmaMiddleNet);
            if (askingNet != null) vals.push(askingNet);
            const maxNet = Math.max(...vals.map(v => Math.max(v, 0)), 1);

            document.getElementById('slNetCurrent').textContent = formatZar(netNow);
            document.getElementById('slBarCurrent').style.width = Math.max((netNow / maxNet) * 100, 0) + '%';

            if (cmaMiddleNet != null) {
                document.getElementById('slNetCma').textContent = formatZar(cmaMiddleNet);
                document.getElementById('slBarCma').style.width = Math.max((cmaMiddleNet / maxNet) * 100, 0) + '%';
            } else {
                document.getElementById('slNetCma').textContent = '—';
                document.getElementById('slBarCma').style.width = '0%';
            }

            if (askingNet != null) {
                document.getElementById('slNetAsking').textContent = formatZar(askingNet);
                document.getElementById('slBarAsking').style.width = Math.max((askingNet / maxNet) * 100, 0) + '%';
            } else {
                document.getElementById('slNetAsking').textContent = '—';
                document.getElementById('slBarAsking').style.width = '0%';
            }
        }

        // ── Price adjustment ──
        function adjustPrice(delta) {
            currentPrice += delta;
            update();
        }

        // ── Capture ──
        async function capturePrice() {
            const btn = document.getElementById('slCaptureBtn');
            btn.disabled = true;
            btn.textContent = 'Saving...';

            try {
                const resp = await fetch(CAPTURE_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': CSRF_TOKEN,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        price: currentPrice,
                        probability: probabilityLabel(currentPrice),
                        net_proceeds: computeNet(currentPrice),
                    }),
                });

                const data = await resp.json();
                if (data.success) {
                    btn.textContent = 'Captured \u2713 ' + formatZar(currentPrice);
                    btn.classList.add('sl-captured');
                } else {
                    btn.textContent = 'Error — try again';
                    btn.disabled = false;
                }
            } catch (e) {
                btn.textContent = 'Error — try again';
                btn.disabled = false;
            }
        }

        // ── Init ──
        document.getElementById('slAddress').textContent = DATA.address;
        update();
    </script>
</body>
</html>
