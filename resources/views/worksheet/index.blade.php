<x-app-layout>

<x-slot name="header">
    <div style="background: var(--brand-default, #0b2a4a);" class="rounded-md px-6 py-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
            <div>
                <h2 class="text-xl font-bold text-white leading-tight">Worksheet &mdash; {{ $user->name }}</h2>
                <div class="text-sm text-white/60">Income &rarr; Sales &rarr; Stock</div>
            </div>
            <div class="text-sm text-white/80 font-medium">{{ $w->period ?? now()->format('Y-m') }}</div>
        </div>
    </div>
</x-slot>

<div class="max-w-6xl mx-auto px-4 py-6 space-y-6">

    @if (session('status'))
        <div class="mb-4 p-3 rounded-md text-sm font-medium"
             style="background: color-mix(in srgb, var(--ds-green) 12%, transparent); color: var(--ds-green); border: 1px solid color-mix(in srgb, var(--ds-green) 25%, transparent);">
            {{ session('status') }}
        </div>
    @endif

    @php
        $w = $latest;
        $calc = $w ? \App\Http\Controllers\WorksheetController::calculate($w) : null;

        // Admin-controlled defaults from user record
        $agentCut = ($user->agent_cut_percent === null || $user->agent_cut_percent === '') ? 50 : (float)$user->agent_cut_percent;
        $payeMethod = $user->paye_method ?? 'percentage';
        $payeValue = ($user->paye_value === null || $user->paye_value === '') ? 0 : (float)$user->paye_value;

        $payeDisplay = $payeMethod === 'fixed'
            ? 'Fixed: R ' . number_format($payeValue, 2)
            : 'Percentage: ' . number_format($payeValue, 2) . '%';
    @endphp

    @php
        $latestNet = 0;
        if (!empty($latest)) {
            $latestNet = (float)$latest->personal_net_target + (float)$latest->business_net_target + (float)$latest->want_net_target;
        }
    @endphp

    {{-- ============================================================ --}}
    {{-- RENTALS (conditional) --}}
    {{-- ============================================================ --}}
    @if(auth()->user()->can_capture_rentals || auth()->user()->hasPermission('rentals.create'))
    @php
        $rentalsActive = (int)($calc['rentals_active_count'] ?? 0);
        $rentalsAssist = (int)($calc['rentals_assist_count'] ?? 0);
        $rentalsCommExcl = (float)($calc['rentals_commission_excl_total'] ?? 0);
    @endphp

    <div class="ds-status-card" style="border-left-color: var(--brand-default, #0b2a4a);">
        <div class="flex items-center justify-between mb-3">
            <h3 class="ds-section-header" style="margin-bottom:0;">Rentals (This Period)</h3>
            <span class="ds-badge ds-badge-default">Ex VAT</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <div class="ds-label">Active Rentals</div>
                <div class="ds-value text-lg">{{ $rentalsActive }}</div>
            </div>
            <div>
                <div class="ds-label">Rental Assist</div>
                <div class="ds-value text-lg">{{ $rentalsAssist }}</div>
            </div>
            <div>
                <div class="ds-label">Commission (Excl VAT)</div>
                <div class="ds-value text-lg">R {{ number_format($rentalsCommExcl, 2) }}</div>
            </div>
        </div>

        <div class="text-xs mt-3" style="color: var(--text-muted);">
            Display-only: rentals are not yet integrated into budgets.
        </div>
    </div>
    @endif

    {{-- ============================================================ --}}
    {{-- COMPANY REQUIREMENT --}}
    {{-- ============================================================ --}}
    @if(!empty($companyRequirement))
    <div class="ds-status-card {{ (data_get($companyRequirement, 'shortfall', 0) > 0) ? 'ds-status-declined' : 'ds-status-granted' }}">
        <h3 class="ds-section-header" style="margin-bottom:0.75rem;">Company Requirement</h3>

        <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
            <div>
                <div class="ds-label">Branch Budget</div>
                <div class="ds-value">R {{ number_format($companyRequirement['branch_budget'], 2) }}</div>
                <div class="text-xs" style="color: var(--text-muted);">Ex VAT</div>
            </div>
            <div>
                <div class="ds-label">Agents in Branch</div>
                <div class="ds-value">{{ $companyRequirement['agents'] }}</div>
            </div>
            <div>
                <div class="ds-label">Required per Agent</div>
                <div class="ds-value">R {{ number_format($companyRequirement['required_per_agent'], 2) }}</div>
                <div class="text-xs" style="color: var(--text-muted);">Ex VAT</div>
            </div>
            <div>
                <div class="ds-label">Company Earns from You</div>
                <div class="ds-value">R {{ number_format($companyRequirement['current_company_income'], 2) }}</div>
                <div class="text-xs" style="color: var(--text-muted);">Ex VAT &bull; if you hit your current budget</div>
            </div>
            <div>
                <div class="ds-label">Shortfall</div>
                <div class="ds-value {{ $companyRequirement['shortfall'] > 0 ? 'text-red-600' : 'text-green-600' }}">
                    R {{ number_format($companyRequirement['shortfall'], 2) }}
                </div>
                <div class="text-xs" style="color: var(--text-muted);">Ex VAT</div>
            </div>
        </div>

        @if($companyRequirement['shortfall'] > 0)
            <div class="mt-3 text-sm text-red-600 font-medium">
                Your targets do not currently meet the branch/company requirement.
            </div>
        @else
            <div class="mt-3 text-sm text-green-600 font-medium">
                Your targets meet or exceed the company requirement.
            </div>
        @endif
    </div>
    @endif

    {{-- ============================================================ --}}
    {{-- MAIN FORM --}}
    {{-- ============================================================ --}}
    @if(!empty($companyRequirement))
    <form method="POST" action="{{ route('worksheet.store') }}">
        @csrf

        {{-- Section: Period + Inputs --}}
        <div class="ds-status-card mb-6" style="border-left-color: var(--brand-default, #0b2a4a);">
            <h3 class="ds-section-header" style="margin-bottom:0.5rem;">Planning Inputs</h3>
            <p class="text-sm mb-4" style="color: var(--text-muted);">Fill in your numbers for the month. Save. Your required sales and stock levels will calculate automatically.</p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="ds-label block mb-1">Period (YYYY-MM)</label>
                    <input type="month" name="period" value="{{ old('period', $w->period ?? now()->format('Y-m')) }}"
                           class="w-full rounded-md p-2 text-sm transition-all duration-300"
                           style="background: var(--surface-2); color: var(--text-primary); border: 1px solid var(--border);" />
                    @error('period') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
                </div>

                <div>
                    <label class="ds-label block mb-1">Current Active Listings</label>
                    @php $lockedListings = isset($activeListings) ? (int)$activeListings : (int)($w->current_listings ?? 0); @endphp
                    <input type="hidden" name="current_listings" value="{{ $lockedListings }}" />
                    <input type="number" value="{{ $lockedListings }}"
                           class="w-full rounded-md p-2 text-sm cursor-not-allowed"
                           style="background: var(--surface-2); color: var(--text-muted); border: 1px solid var(--border);"
                           readonly disabled />
                    <div class="text-xs mt-1" style="color: var(--text-muted);">Locked from imported stock (Propcon).</div>
                </div>

                <div>
                    <label class="ds-label block mb-1">Correctly Priced Stock (%)</label>
                    @if(isset($cmaCount) && (int)$cmaCount > 0)
                        <input type="hidden" name="correctly_priced_percent" value="{{ (float)$cmaCorrectlyPricedPercent }}" />
                        <input type="number" step="0.01" value="{{ (float)$cmaCorrectlyPricedPercent }}"
                               class="w-full rounded-md p-2 text-sm cursor-not-allowed"
                               style="background: var(--surface-2); color: var(--text-muted); border: 1px solid var(--border);"
                               readonly disabled />
                        <div class="text-xs mt-1" style="color: var(--text-muted);">Calculated from listings with CMA captured.</div>
                    @else
                        <input type="number" step="0.01" name="correctly_priced_percent" value="{{ old('correctly_priced_percent', $w->correctly_priced_percent ?? 40) }}"
                               class="w-full rounded-md p-2 text-sm transition-all duration-300"
                               style="background: var(--surface-2); color: var(--text-primary); border: 1px solid var(--border);" />
                        @error('correctly_priced_percent') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
                    @endif

                    @include('worksheet._cma_pricing_info')
                </div>
            </div>
        </div>

        {{-- Section: Net Monthly Targets --}}
        <div class="ds-status-card mb-6" style="border-left-color: var(--brand-default, #0b2a4a);">
            <h3 class="ds-section-header" style="margin-bottom:0.75rem;">Net Monthly Targets</h3>

            @if(!empty($companyRequirement))
                @php
                    $canApplyBranchDefault = (!empty($companyRequirement) && $latestNet <= 0.0 && ($companyRequirement['required_per_agent'] ?? 0) > 0);
                    $canAlignToCompany = (!empty($companyRequirement) && ($companyRequirement['shortfall'] ?? 0) > 1);
                @endphp

                <div class="rounded-md p-4 mb-4" style="background: var(--surface-2); border: 1px solid var(--border);">
                    <div class="text-sm font-semibold mb-2" style="color: var(--text-primary);">Budget / Company Requirement Actions</div>

                    <div class="flex flex-col md:flex-row gap-3">
                        <button type="submit"
                                formaction="{{ route('worksheet.applyBranchDefault') }}"
                                formmethod="POST"
                                class="px-4 py-2 rounded-md text-sm font-semibold text-white transition-all duration-300 {{ $canApplyBranchDefault ? '' : 'opacity-50 cursor-not-allowed' }}"
                                style="background: var(--brand-default, #0b2a4a);"
                                {{ $canApplyBranchDefault ? '' : 'disabled' }}>
                            Set my budget to the required minimum
                        </button>

                        <button type="submit"
                                formaction="{{ route('worksheet.align') }}"
                                formmethod="POST"
                                class="px-4 py-2 rounded-md text-sm font-semibold text-white transition-all duration-300 {{ $canAlignToCompany ? '' : 'opacity-50 cursor-not-allowed' }}"
                                style="background: var(--ds-crimson, #c41e3a);"
                                {{ $canAlignToCompany ? '' : 'disabled' }}>
                            Scale my targets to meet company requirement
                        </button>
                    </div>

                    <div class="text-xs mt-2 space-y-0.5" style="color: var(--text-muted);">
                        <div><strong>Set my budget</strong> is only available when your Net Monthly Targets are still zero (no budget captured yet).</div>
                        <div><strong>Scale my targets</strong> is available when you have a remaining shortfall against the company requirement.</div>
                    </div>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="ds-label block mb-1">Personal (Take-home)</label>
                    <input type="number" step="0.01" name="personal_net_target" value="{{ old('personal_net_target', $w->personal_net_target ?? 0) }}"
                           class="w-full rounded-md p-2 text-sm transition-all duration-300"
                           style="background: var(--surface-2); color: var(--text-primary); border: 1px solid var(--border);" />
                    @error('personal_net_target') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="ds-label block mb-1">Business (Fuel/Marketing/etc.)</label>
                    <input type="number" step="0.01" name="business_net_target" value="{{ old('business_net_target', $w->business_net_target ?? 0) }}"
                           class="w-full rounded-md p-2 text-sm transition-all duration-300"
                           style="background: var(--surface-2); color: var(--text-primary); border: 1px solid var(--border);" />
                    @error('business_net_target') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="ds-label block mb-1">Want (Savings/Holiday/Buffer)</label>
                    <input type="number" step="0.01" name="want_net_target" value="{{ old('want_net_target', $w->want_net_target ?? 0) }}"
                           class="w-full rounded-md p-2 text-sm transition-all duration-300"
                           style="background: var(--surface-2); color: var(--text-primary); border: 1px solid var(--border);" />
                    @error('want_net_target') <div class="text-red-600 text-sm mt-1">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>

        {{-- ============================================================ --}}
        {{-- DEAL REGISTER SUMMARY --}}
        {{-- ============================================================ --}}
        <div class="ds-status-card mb-6" style="border-left-color: var(--brand-default, #0b2a4a);">
            <h3 class="ds-section-header" style="margin-bottom:0.25rem;">Deal Register Summary (What's Happening)</h3>
            <div class="ds-section-sub mb-4">
                This section reports your captured Deal Register performance for the selected period (sales, commission, stages, and pipeline).
            </div>

            @php
                // All-time block added by dealRegisterStats()
                $all = $dealStats['all_time'] ?? [];
                $pipeAll = $dealStats['pipeline_not_paid_all_time_counts'] ?? ($all['pipeline_not_paid_counts'] ?? []);

                // Tiny helpers (Blade-only)
                $fmtR = fn($v) => 'R ' . number_format((float)($v ?? 0), 2);
                $fmtPct = fn($v) => number_format((float)($v ?? 0), 2) . '%';
                $stageLine = function(array $arr, string $k) use ($fmtR) {
                    return $fmtR(($arr[$k] ?? 0));
                };
            @endphp

            {{-- Column Headers --}}
            <div class="grid grid-cols-3 gap-3 mb-3">
                <div></div>
                <div class="text-xs font-bold uppercase tracking-wide text-white rounded-t-md px-3 py-2" style="background: var(--brand-default, #0b2a4a);">Plan Inputs (BM / Worksheet)</div>
                <div class="text-xs font-bold uppercase tracking-wide text-white rounded-t-md px-3 py-2" style="background: var(--brand-default, #0b2a4a);">Deal Register (Actuals)</div>
            </div>

            {{-- ROW: Avg Sale Price --}}
            <div class="grid grid-cols-3 gap-3 items-start py-3" style="border-bottom: 1px solid var(--border);">
                <div class="ds-label self-center">Avg Sale Price</div>

                <div class="rounded-md p-3" style="background: var(--surface-2); border: 1px solid var(--border);">
                    <div class="ds-value">R {{ number_format((float)($w->avg_sale_price_admin ?? $w->avg_sale_price ?? 1060000), 2) }}</div>
                    <div class="text-xs mt-1" style="color: var(--text-muted);">Set by Branch Manager (per agent, per month).</div>
                    <input type="hidden" name="avg_sale_price" value="{{ old('avg_sale_price', $w->avg_sale_price ?? 1060000) }}" />
                    @error('avg_sale_price') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
                </div>

                <div class="space-y-2">
                    <div class="rounded-md p-3" style="background: var(--surface-2); border: 1px solid var(--border);">
                        <div class="ds-value">R {{ number_format((float)($dealStats['avg_sale_price_inc_vat'] ?? 0), 2) }}</div>
                        <div class="text-xs mt-1" style="color: var(--text-muted);"><b>Ex VAT:</b> R {{ number_format((float)($dealStats['avg_sale_price_ex_vat'] ?? 0), 2) }}</div>
                        <div class="text-xs mt-1" style="color: var(--text-muted);">
                            From Deal Register ({{ (int)(($dealStats['counts']['total'] ?? 0)) }} deals in {{ $dealStats['period'] ?? '' }}).
                        </div>
                    </div>

                    <div class="rounded-md p-3" style="background: var(--surface-2); border: 1px solid var(--border);">
                        <div class="text-xs mb-1" style="color: var(--text-secondary);"><b>All-time</b></div>
                        <div class="ds-value">{{ $fmtR($all['avg_sale_price_inc_vat'] ?? 0) }}</div>
                        <div class="text-xs mt-1" style="color: var(--text-muted);"><b>Ex VAT:</b> {{ $fmtR($all['avg_sale_price_ex_vat'] ?? 0) }}</div>
                        <div class="text-xs mt-1" style="color: var(--text-muted);">
                            From Deal Register ({{ (int)($all['counts']['total'] ?? 0) }} deals all time).
                        </div>
                    </div>
                </div>
            </div>

            {{-- ROW: Commission % --}}
            <div class="grid grid-cols-3 gap-3 items-start py-3" style="border-bottom: 1px solid var(--border);">
                <div class="ds-label self-center">Commission % (Excl VAT)</div>

                <div class="rounded-md p-3" style="background: var(--surface-2); border: 1px solid var(--border);">
                    <input type="number" step="0.01" name="commission_percent"
                           value="{{ old('commission_percent', $w->commission_percent ?? 7.5) }}"
                           class="w-full rounded-md p-2 text-sm transition-all duration-300"
                           style="background: var(--surface); color: var(--text-primary); border: 1px solid var(--border);" />
                    @error('commission_percent') <div class="text-red-600 text-sm">{{ $message }}</div> @enderror
                    <div class="text-xs mt-1" style="color: var(--text-muted);">Planning % (default 7.5%).</div>
                </div>

                <div class="space-y-2">
                    <div class="rounded-md p-3" style="background: var(--surface-2); border: 1px solid var(--border);">
                        <div class="ds-value">{{ number_format((float)($dealStats['effective_commission_percent_ex_vat'] ?? 0), 2) }}%</div>
                        <div class="text-xs mt-1" style="color: var(--text-muted);">
                            Actual commission / sales value (based on captured totals).
                        </div>
                        <div class="text-xs mt-2" style="color: var(--text-secondary);">
                            Lost vs 7.5%: <b>R {{ number_format((float)($dealStats['lost_commission_ex_vat_vs_7_5'] ?? 0), 2) }}</b>
                        </div>
                    </div>

                    <div class="rounded-md p-3" style="background: var(--surface-2); border: 1px solid var(--border);">
                        <div class="text-xs mb-1" style="color: var(--text-secondary);"><b>All-time</b></div>
                        <div class="ds-value">{{ $fmtPct($all['effective_commission_percent_ex_vat'] ?? 0) }}</div>
                        <div class="text-xs mt-1" style="color: var(--text-muted);">
                            Actual commission / sales value (all captured totals).
                        </div>
                        @if(array_key_exists('lost_commission_ex_vat_vs_7_5', $all))
                            <div class="text-xs mt-2" style="color: var(--text-secondary);">
                                Lost vs 7.5%: <b>{{ $fmtR($all['lost_commission_ex_vat_vs_7_5'] ?? 0) }}</b>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- ROW: Deals --}}
            <div class="grid grid-cols-3 gap-3 items-start py-3" style="border-bottom: 1px solid var(--border);">
                <div class="ds-label self-center">Deals (count)</div>
                <div class="rounded-md p-3 text-sm" style="background: var(--surface-2); color: var(--text-muted); border: 1px solid var(--border);">&mdash;</div>

                <div class="text-sm" style="color: var(--text-primary);">
                    <div><b>Period total:</b> {{ (int)($dealStats['counts']['total'] ?? 0) }}</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                        Pending: {{ (int)($dealStats['counts']['pending'] ?? 0) }} |
                        Granted: {{ (int)($dealStats['counts']['granted'] ?? 0) }} |
                        Registered: {{ (int)($dealStats['counts']['registered'] ?? 0) }} |
                        Declined: {{ (int)($dealStats['counts']['declined'] ?? 0) }}
                    </div>

                    <div class="mt-2">
                        <div class="text-xs mb-1" style="color: var(--text-secondary);"><b>All-time</b></div>
                        <div><b>Total:</b> {{ (int)($all['counts']['total'] ?? 0) }}</div>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                            Pending: {{ (int)($all['counts']['pending'] ?? 0) }} |
                            Granted: {{ (int)($all['counts']['granted'] ?? 0) }} |
                            Registered: {{ (int)($all['counts']['registered'] ?? 0) }} |
                            Declined: {{ (int)($all['counts']['declined'] ?? 0) }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- ROW: Sales Value --}}
            <div class="grid grid-cols-3 gap-3 items-start py-3" style="border-bottom: 1px solid var(--border);">
                <div class="ds-label self-center">Sales Value</div>
                <div class="rounded-md p-3 text-sm" style="background: var(--surface-2); color: var(--text-muted); border: 1px solid var(--border);">&mdash;</div>

                <div class="text-sm" style="color: var(--text-primary);">
                    <div><b>Period (Sale Price):</b> R {{ number_format((float)($dealStats['sales_value_inc_vat'] ?? 0), 2) }}</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);">
                        Pending: R {{ number_format((float)($dealStats['stage_sales_inc_vat']['pending'] ?? 0), 2) }} |
                        Granted: R {{ number_format((float)($dealStats['stage_sales_inc_vat']['granted'] ?? 0), 2) }} |
                        Registered: R {{ number_format((float)($dealStats['stage_sales_inc_vat']['registered'] ?? 0), 2) }} |
                        Declined: R {{ number_format((float)($dealStats['stage_sales_inc_vat']['declined'] ?? 0), 2) }}
                    </div>

                    <div class="mt-2">
                        <div class="text-xs mb-1" style="color: var(--text-secondary);"><b>All-time</b></div>
                        <div><b>Sale Price:</b> {{ $fmtR($all['sales_value_inc_vat'] ?? 0) }}</div>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);">
                            Pending: {{ $fmtR(($all['stage_sales_inc_vat']['pending'] ?? 0)) }} |
                            Granted: {{ $fmtR(($all['stage_sales_inc_vat']['granted'] ?? 0)) }} |
                            Registered: {{ $fmtR(($all['stage_sales_inc_vat']['registered'] ?? 0)) }} |
                            Declined: {{ $fmtR(($all['stage_sales_inc_vat']['declined'] ?? 0)) }}
                        </div>
                    </div>
                </div>
            </div>

            {{-- ROW: Total Commission --}}
            <div class="grid grid-cols-3 gap-3 items-start py-3" style="border-bottom: 1px solid var(--border);">
                <div class="ds-label self-center">Total Commission</div>
                <div class="rounded-md p-3 text-sm" style="background: var(--surface-2); color: var(--text-muted); border: 1px solid var(--border);">&mdash;</div>

                <div class="text-sm" style="color: var(--text-primary);">
                    <div><b>Period Incl VAT:</b> R {{ number_format((float)($dealStats['total_commission_inc_vat'] ?? 0), 2) }}</div>
                    <div class="text-xs mt-1" style="color: var(--text-secondary);"><b>Period Ex VAT:</b> R {{ number_format((float)($dealStats['total_commission_ex_vat'] ?? 0), 2) }}</div>

                    <div class="mt-2">
                        <div class="text-xs mb-1" style="color: var(--text-secondary);"><b>All-time</b></div>
                        <div><b>Incl VAT:</b> {{ $fmtR($all['total_commission_inc_vat'] ?? 0) }}</div>
                        <div class="text-xs mt-1" style="color: var(--text-secondary);"><b>Ex VAT:</b> {{ $fmtR($all['total_commission_ex_vat'] ?? 0) }}</div>
                    </div>
                </div>
            </div>

            {{-- ROW: Pipeline (ALL-TIME, NOT PAID) --}}
            <div class="grid grid-cols-3 gap-3 items-start py-3">
                <div>
                    <div class="ds-label">Pipeline (Net)</div>
                    <div class="text-xs" style="color: var(--text-muted);">Always ALL-TIME &bull; only NOT PAID deals</div>
                </div>

                <div class="rounded-md p-3 text-sm" style="background: var(--surface-2); color: var(--text-muted); border: 1px solid var(--border);">&mdash;</div>

                <div class="text-sm" style="color: var(--text-primary);">
                    @php
                        $pipe = $dealStats['pipeline_not_paid_all_time'] ?? [];
                        $pipeMoney = $pipe['agent_net_ex_vat_by_stage'] ?? [];
                        $pipeCounts = $pipe['counts'] ?? [];
                    @endphp

                    <div class="text-xs mb-1" style="color: var(--text-secondary);"><b>Outstanding (ex VAT)</b></div>

                    <div class="ds-value">
                        Total: <b>{{ $fmtR($pipe['agent_net_ex_vat_total'] ?? 0) }}</b>
                    </div>

                    <div class="mt-2 text-xs space-y-1" style="color: var(--text-secondary);">
                        <div>
                            <b>Pending:</b> {{ $fmtR($pipeMoney['pending'] ?? 0) }}
                            <span style="color: var(--text-muted);">({{ (int)($pipeCounts['pending'] ?? 0) }} deals)</span>
                        </div>
                        <div>
                            <b>Granted:</b> {{ $fmtR($pipeMoney['granted'] ?? 0) }}
                            <span style="color: var(--text-muted);">({{ (int)($pipeCounts['granted'] ?? 0) }} deals)</span>
                        </div>
                        <div>
                            <b>Registered:</b> {{ $fmtR($pipeMoney['registered'] ?? 0) }}
                            <span style="color: var(--text-muted);">({{ (int)($pipeCounts['registered'] ?? 0) }} deals)</span>
                        </div>
                    </div>

                    @if(($pipeCounts['total'] ?? 0) > 0)
                    <div class="text-xs mt-2" style="color: var(--text-secondary);">
                        Includes <b>{{ (int)($pipeCounts['total'] ?? 0) }}</b> not-paid deals (all-time).
                    </div>
                    @endif
                </div>
            </div>

            <div class="mt-3 text-xs pt-3" style="color: var(--text-muted); border-top: 1px solid var(--border);">
                Stock model used: <b>1 sale per {{ (float)\App\Models\PerformanceSetting::get('listings_per_sale', 5) }} correctly priced listings</b>
            </div>
        </div>

        {{-- Section: Admin Controls --}}
        <div class="ds-status-card mb-6" style="border-left-color: var(--brand-default, #0b2a4a);">
            <h3 class="ds-section-header" style="margin-bottom:0.75rem;">Admin Controls</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="ds-label block mb-1">PAYE (Admin)</label>
                    <div class="rounded-md p-3 text-sm" style="background: var(--surface-2); color: var(--text-primary); border: 1px solid var(--border);">
                        {{ $payeDisplay }}
                        <div class="text-xs mt-1" style="color: var(--text-muted);">This value is set by admin on the User record.</div>
                    </div>
                </div>
                <div>
                    <label class="ds-label block mb-1">Agent Cut % (Admin)</label>
                    <div class="rounded-md p-3 text-sm" style="background: var(--surface-2); color: var(--text-primary); border: 1px solid var(--border);">
                        {{ number_format($agentCut, 2) }}%
                        <div class="text-xs mt-1" style="color: var(--text-muted);">This value is set by admin on the User record.</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Save Button --}}
        <div class="flex flex-col md:flex-row items-start md:items-center gap-4 mb-6">
            <button id="saveWorksheetBtn" class="text-white px-8 py-4 rounded-md font-bold text-lg shadow-lg transition-all duration-300"
                    style="background: var(--brand-button, #0ea5e9);">
                SAVE WORKSHEET
            </button>

            <div class="text-sm rounded-md px-3 py-2" style="background: var(--surface-2); color: var(--text-secondary); border: 1px solid var(--border);">
                After changing any numbers, click <b>SAVE WORKSHEET</b> to update your results.
            </div>
        </div>
    </form>
    @endif

    {{-- ============================================================ --}}
    {{-- TARGET REQUIREMENTS (Plan vs Market Reality) --}}
    {{-- ============================================================ --}}
    <div class="ds-status-card" style="border-left-color: var(--brand-default, #0b2a4a);">
        <h3 class="ds-section-header" style="margin-bottom:0.25rem;">Target Requirements (Plan vs Market Reality)</h3>
        <div class="ds-section-sub mb-4">
            This section recalculates what you need to do (sales/listings/gap) based on (1) your plan inputs vs (2) market reality from Deal Register averages.
        </div>

        @if(!isset($w) || !$w)
            <p style="color: var(--text-muted);">No worksheet saved yet.</p>
        @else
            @php
                $planned = $calc;
                $actual = \App\Http\Controllers\WorksheetController::calculateWithOverrides(
                    $w,
                    (float)($dealStats['avg_sale_price_inc_vat'] ?? 0),
                    (float)($dealStats['effective_commission_percent_ex_vat'] ?? 0),
                    true // commission percent is already ex-VAT from dealRegisterStats
                );

                // -------------------------------------------------------
                // Budget-driven targets (with company floor)
                // -------------------------------------------------------
                $req = (float)($companyRequirement['required_per_agent'] ?? 0);
                $listingsPerSale = (float) \App\Models\PerformanceSetting::get('listings_per_sale', 5);

                $cp = (isset($cmaCorrectlyPricedPercent) && $cmaCorrectlyPricedPercent !== null)
                    ? (float)$cmaCorrectlyPricedPercent
                    : (float)($w->correctly_priced_percent ?? 0);
                $cp = max(0.01, $cp);
                $cpFactor = ($cp / 100.0);
                $currentListings = (int)($w->current_listings ?? 0);

                $plannedNetNeed = (float)($planned['net_need'] ?? 0);
                $actualNetNeed  = (float)($actual['net_need'] ?? 0);

                $plannedAgentNetPerSale = (float)($planned['agent_net_per_sale'] ?? 0);
                $actualAgentNetPerSale  = (float)($actual['agent_net_per_sale'] ?? 0);

                $plannedCompanyIncomePerSale = (float)($planned['company_income_per_sale'] ?? 0);
                $actualCompanyIncomePerSale  = (float)($actual['company_income_per_sale'] ?? 0);

                // Company-floor
                $payePercent = (float)($w->paye_percent ?? 0);
                $payeFactor = 1.0 - ($payePercent / 100.0);

                $agentSplitPercent = (float)($w->agent_split_percent ?? 0);
                $agentShareFactor = ($agentSplitPercent / 100.0);
                $companyShareFactor = 1.0 - $agentShareFactor;

                $commissionPoolFloorEx = ($companyShareFactor > 0) ? ($req / $companyShareFactor) : 0;
                $agentGrossFloorEx = $commissionPoolFloorEx * $agentShareFactor;
                $netFloor = $agentGrossFloorEx * $payeFactor;

                $plannedNetFloor = $netFloor;
                $actualNetFloor  = $netFloor;

                $plannedBudgetUsed = max($plannedNetNeed, $plannedNetFloor);
                $actualBudgetUsed  = max($actualNetNeed,  $actualNetFloor);

                $plannedSalesNeeded = ($plannedAgentNetPerSale > 0) ? ($plannedBudgetUsed / $plannedAgentNetPerSale) : 0;
                $actualSalesNeeded  = ($actualAgentNetPerSale  > 0) ? ($actualBudgetUsed  / $actualAgentNetPerSale)  : 0;

                $plannedListingsNeeded = ($cpFactor > 0) ? (($plannedSalesNeeded * $listingsPerSale) / $cpFactor) : 0;
                $actualListingsNeeded  = ($cpFactor > 0) ? (($actualSalesNeeded  * $listingsPerSale) / $cpFactor) : 0;

                $plannedGap = $plannedListingsNeeded - $currentListings;
                $actualGap  = $actualListingsNeeded  - $currentListings;

                // Deltas (Market - Plan)
                $deltaBudgetUsed   = $actualBudgetUsed - $plannedBudgetUsed;
                $deltaSalesNeeded  = $actualSalesNeeded - $plannedSalesNeeded;
                $deltaListingsNeed = $actualListingsNeeded - $plannedListingsNeeded;
                $deltaGap          = $actualGap - $plannedGap;

                // -------------------------------------------------------
                // Reverse-calc: Net budget -> Gross agent -> Total commission -> Sales value
                // -------------------------------------------------------
                $vatRatePercent = (float) \App\Models\PerformanceSetting::get('vat_rate', 15);
                $vatRate = $vatRatePercent / 100.0;
                $vatDiv = 1.0 + $vatRate;

                $payePercent = (float)($w->paye_percent ?? 0);
                $payeFactor = 1.0 - ($payePercent / 100.0);

                $agentSplitPercent = (float)($w->agent_split_percent ?? 0);
                $agentShareFactor = ($agentSplitPercent / 100.0);

                $plannedAgentGrossNeeded = ($payeFactor > 0) ? ($plannedBudgetUsed / $payeFactor) : 0;
                $actualAgentGrossNeeded  = ($payeFactor > 0) ? ($actualBudgetUsed  / $payeFactor) : 0;

                $plannedTotalCommissionNeededEx = ($agentShareFactor > 0) ? ($plannedAgentGrossNeeded / $agentShareFactor) : 0;
                $actualTotalCommissionNeededEx  = ($agentShareFactor > 0) ? ($actualAgentGrossNeeded  / $agentShareFactor) : 0;

                $planCommissionPercent = (float)($w->commission_percent ?? 0);
                $marketCommissionPercentEx = (float)($dealStats['effective_commission_percent_ex_vat'] ?? 0);

                $plannedSalesValueNeededEx = ($planCommissionPercent > 0) ? ($plannedTotalCommissionNeededEx / ($planCommissionPercent / 100.0)) : 0;
                $actualSalesValueNeededEx  = ($marketCommissionPercentEx > 0) ? ($actualTotalCommissionNeededEx / ($marketCommissionPercentEx / 100.0)) : 0;

                $plannedSalesValueNeededInc = $plannedSalesValueNeededEx * $vatDiv;
                $actualSalesValueNeededInc  = $actualSalesValueNeededEx  * $vatDiv;
            @endphp

            {{-- Three Column Headers --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-1">
                <div class="text-xs font-bold uppercase tracking-wide text-white rounded-t-md px-3 py-2" style="background: var(--brand-default, #0b2a4a);">Plan (Worksheet Inputs)</div>
                <div class="text-xs font-bold uppercase tracking-wide text-white rounded-t-md px-3 py-2" style="background: var(--brand-default, #0b2a4a);">Market-Based (Deal Register Averages)</div>
                <div class="text-xs font-bold uppercase tracking-wide text-white rounded-t-md px-3 py-2" style="background: var(--brand-default, #0b2a4a);">Difference (Market - Plan)</div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                {{-- PLANNED --}}
                <div class="rounded-b-md p-4" style="background: var(--surface); border: 1px solid var(--border); border-top: 3px solid var(--brand-default, #0b2a4a);">
                    <div class="space-y-2 text-sm" style="color: var(--text-primary);">
                        <div><span class="ds-label">Period:</span> <span class="ds-value">{{ $w->period }}</span></div>

                        <div class="mt-2"><span class="ds-label">Nett Take-home at Company Budget:</span></div>
                        <div class="ds-value-lg">R {{ number_format($plannedBudgetUsed, 2) }}</div>

                        <div class="text-xs {{ ($plannedBudgetUsed > $plannedNetNeed) ? 'text-red-600' : '' }}" style="{{ ($plannedBudgetUsed <= $plannedNetNeed) ? 'color: var(--text-muted);' : '' }}">
                            @if($plannedBudgetUsed > $plannedNetNeed)
                                Budget lifted to meet company requirement.
                            @else
                                Uses your budget (above company requirement).
                            @endif
                        </div>
                        <div class="mt-2"><span class="ds-label">Gross Commission Needed (Ex VAT):</span> <span class="ds-value">R {{ number_format($plannedTotalCommissionNeededEx, 2) }}</span></div>
                        <div><span class="ds-label">Sales Value Needed:</span> <span class="ds-value">R {{ number_format($plannedSalesValueNeededInc, 2) }}</span> <span class="text-xs" style="color: var(--text-muted);">(Inc VAT)</span></div>
                        <div class="text-xs" style="color: var(--text-muted);">Ex VAT: R {{ number_format($plannedSalesValueNeededEx, 2) }} &bull; Comm% used: {{ number_format($planCommissionPercent, 2) }}%</div>

                        <div class="text-xs" style="color: var(--text-muted);">
                            Uses your budget unless the company-floor requires more.
                        </div>

                        <div class="pt-2 mt-2" style="border-top: 1px solid var(--border);">
                            <div><span class="ds-label">Sales Needed / Month:</span> <span class="ds-value">{{ number_format($plannedSalesNeeded, 2) }}</span></div>
                            <div><span class="ds-label">Total Listings Needed:</span> <span class="ds-value">{{ number_format($plannedListingsNeeded, 2) }}</span></div>
                            <div><span class="ds-label">Gap (Needed - Current):</span> <span class="ds-value {{ $plannedGap > 0 ? 'text-red-600' : 'text-green-600' }}">{{ number_format($plannedGap, 2) }}</span></div>
                        </div>
                    </div>
                </div>

                {{-- ACTUAL / MARKET --}}
                <div class="rounded-b-md p-4" style="background: var(--surface); border: 1px solid var(--border); border-top: 3px solid var(--brand-default, #0b2a4a);">
                    <div class="space-y-2 text-sm" style="color: var(--text-primary);">
                        <div><span class="ds-label">Period:</span> <span class="ds-value">{{ $dealStats['period'] ?? $w->period }}</span></div>

                        <div class="mt-2"><span class="ds-label">Nett Take-home at Company Budget:</span></div>
                        <div class="ds-value-lg">R {{ number_format($actualBudgetUsed, 2) }}</div>

                        <div class="text-xs {{ ($actualBudgetUsed > $actualNetNeed) ? 'text-red-600' : '' }}" style="{{ ($actualBudgetUsed <= $actualNetNeed) ? 'color: var(--text-muted);' : '' }}">
                            @if($actualBudgetUsed > $actualNetNeed)
                                Budget lifted to meet company requirement.
                            @else
                                Uses your budget (above company requirement).
                            @endif
                        </div>
                        <div class="mt-2"><span class="ds-label">Gross Commission Needed (Ex VAT):</span> <span class="ds-value">R {{ number_format($actualTotalCommissionNeededEx, 2) }}</span></div>
                        <div><span class="ds-label">Sales Value Needed:</span> <span class="ds-value">R {{ number_format($actualSalesValueNeededInc, 2) }}</span> <span class="text-xs" style="color: var(--text-muted);">(Inc VAT)</span></div>
                        <div class="text-xs" style="color: var(--text-muted);">Ex VAT: R {{ number_format($actualSalesValueNeededEx, 2) }} &bull; Comm% used: {{ number_format($marketCommissionPercentEx, 2) }}%</div>

                        <div class="text-xs" style="color: var(--text-muted);">
                            Same budget logic, but per-sale performance comes from Deal Register averages.
                        </div>

                        <div class="pt-2 mt-2" style="border-top: 1px solid var(--border);">
                            <div><span class="ds-label">Sales Needed / Month:</span> <span class="ds-value">{{ number_format($actualSalesNeeded, 2) }}</span></div>
                            <div><span class="ds-label">Total Listings Needed:</span> <span class="ds-value">{{ number_format($actualListingsNeeded, 2) }}</span></div>
                            <div><span class="ds-label">Gap (Needed - Current):</span> <span class="ds-value {{ $actualGap > 0 ? 'text-red-600' : 'text-green-600' }}">{{ number_format($actualGap, 2) }}</span></div>
                        </div>
                    </div>

                    <div class="mt-3 text-xs rounded-md p-2" style="background: var(--surface-2); color: var(--text-secondary); border: 1px solid var(--border);">
                        Uses: Avg Price = R {{ number_format((float)($dealStats['avg_sale_price_inc_vat'] ?? 0), 2) }}
                        <div class="text-xs mt-1" style="color: var(--text-muted);"><b>Ex VAT:</b> R {{ number_format((float)($dealStats['avg_sale_price_ex_vat'] ?? 0), 2) }}</div>,
                        Comm % = {{ number_format((float)($dealStats['effective_commission_percent_ex_vat'] ?? 0), 2) }}%
                    </div>
                </div>

                {{-- DIFFERENCE --}}
                @php
                    $delta_comm_per_sale = (float)($actual['commission_per_sale'] ?? 0) - (float)($planned['commission_per_sale'] ?? 0);
                    $delta_net_per_sale = (float)($actual['agent_net_per_sale'] ?? 0) - (float)($planned['agent_net_per_sale'] ?? 0);
                    $delta_net_need = (float)($actualNetNeed ?? 0) - (float)($plannedNetNeed ?? 0);
                @endphp

                <div class="rounded-b-md p-4" style="background: var(--surface); border: 1px solid var(--border); border-top: 3px solid var(--brand-default, #0b2a4a);">
                    <div class="space-y-2 text-sm" style="color: var(--text-primary);">
                        <div><span class="ds-label">Period:</span> <span class="ds-value">{{ $dealStats['period'] ?? $w->period }}</span></div>
                        <div><span class="ds-label">Nett Take-home at Company Budget:</span> <span class="ds-value">R {{ number_format($deltaBudgetUsed, 2) }}</span></div>
                        <div class="mt-2"><span class="ds-label">Commission / Sale (Ex VAT):</span> <span class="ds-value">R {{ number_format($delta_comm_per_sale, 2) }}</span></div>
                        <div><span class="ds-label">Your Net / Sale (Ex VAT):</span> <span class="ds-value">R {{ number_format($delta_net_per_sale, 2) }}</span></div>

                        <div class="pt-2 mt-2" style="border-top: 1px solid var(--border);">
                            <div><span class="ds-label">Sales Needed / Month:</span> <span class="ds-value">{{ number_format($deltaSalesNeeded, 2) }}</span></div>
                            <div><span class="ds-label">Total Listings Needed:</span> <span class="ds-value">{{ number_format($deltaListingsNeed, 2) }}</span></div>
                            <div><span class="ds-label">Gap (Needed - Current):</span> <span class="ds-value">{{ number_format($deltaGap, 2) }}</span></div>
                        </div>
                    </div>

                    <div class="mt-2 text-xs" style="color: var(--text-muted);">
                        Negative = Market requires <b>less</b>. Positive = Market requires <b>more</b>.
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- ============================================================ --}}
    {{-- SAVED MONTHS --}}
    {{-- ============================================================ --}}
    <div class="ds-status-card" style="border-left-color: var(--brand-default, #0b2a4a);">
        <h3 class="ds-section-header" style="margin-bottom:0.75rem;">Your Saved Months</h3>

        @if($worksheets->isEmpty())
            <p style="color: var(--text-muted);">No saved records yet.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm ds-table">
                    <thead>
                        <tr>
                            <th class="text-left p-2">Period</th>
                            <th class="text-left p-2">Net Need</th>
                            <th class="text-left p-2">Current Listings</th>
                            <th class="text-left p-2">Correctly Priced %</th>
                            <th class="text-left p-2">Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($worksheets as $row)
                            @php $c = \App\Http\Controllers\WorksheetController::calculate($row); @endphp
                            <tr>
                                <td class="p-2 ds-value">{{ $row->period }}</td>
                                <td class="p-2 ds-value">R {{ number_format($c['net_need'], 2) }}</td>
                                <td class="p-2">{{ $row->current_listings }}</td>
                                <td class="p-2">{{ number_format($row->correctly_priced_percent, 2) }}%</td>
                                <td class="p-2" style="color: var(--text-muted);">{{ $row->updated_at->format('Y-m-d H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</div>

</x-app-layout>
