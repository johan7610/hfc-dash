<?php
    $r = $rollup ?? [];

    // MONEY (ex VAT) — Company ledger vs Team
    $money = $r['totals']['actuals'] ?? [];
    $ledgerCompanyIncome   = (float)($money['ledger_company_income'] ?? 0);
    $ledgerAgentIncome     = (float)($money['ledger_agent_income'] ?? 0);
    $ledgerCompanyRetained = (float)($money['ledger_company_retained'] ?? max(0, $ledgerCompanyIncome - $ledgerAgentIncome));

    $teamCompanyIncome   = (float)($money['team_company_income'] ?? 0);
    $teamAgentIncome     = (float)($money['team_agent_income'] ?? 0);
    $teamCompanyRetained = (float)($money['team_company_retained'] ?? 0);

    // Points / pace (company aggregated)
    $pts = $r['points'] ?? ['actual'=>0,'target'=>0,'pct'=>0,'status'=>'—','remaining'=>0,'per_day_needed'=>0,'today_points'=>0,'days_left'=>0];
    $pointsActual = (float)($pts['actual'] ?? 0);
    $pointsTarget = (float)($pts['target'] ?? 0);
    $pointsPct = (float)($pts['pct'] ?? 0);
    $pointsStatus = (string)($pts['status'] ?? '—');
    $pointsRemaining = (float)($pts['remaining'] ?? 0);
    $pointsPerDayNeeded = (float)($pts['per_day_needed'] ?? 0);
    $todayPoints = (float)($pts['today_points'] ?? 0);

    $pointsBarClass = 'bg-gray-900';
    if ($pointsTarget > 0) {
        if ($pointsActual >= $pointsTarget) $pointsBarClass = 'bg-green-600';
        elseif ($pointsStatus === 'Ahead' || $pointsStatus === 'On pace') $pointsBarClass = 'bg-green-600';
        elseif ($pointsPct >= 75) $pointsBarClass = 'bg-amber-500';
        else $pointsBarClass = 'bg-red-600';
    }

    // Company targets/actuals (agent target sum -> progress)
    $companyValueTarget_agentsum = (float)($r['totals']['targets']['value'] ?? 0);
    $companyDealsTarget_agentsum = (int)($r['totals']['targets']['deals'] ?? 0);

    $companyValueActual = (float)($r['totals']['actuals']['value'] ?? $r['totals']['actuals']['sales_value'] ?? 0);
    $companyDealsActual_rollup = (int)($r['totals']['actuals']['deals'] ?? $r['totals']['actuals']['deals_count'] ?? 0);

    // ADMIN PERFORMANCE: deal counts must be DISTINCT deals (not per-agent rows).
    // Use statusSummary period counts (Pending + Granted + Registered). Declined excluded.
    $companyDealsActual_distinct = (int)($statusSummary['pending_period'] ?? 0)
                                + (int)($statusSummary['granted_period'] ?? 0)
                                + (int)($statusSummary['registered_period'] ?? 0);

    $companyDealsActual = $companyDealsActual_distinct > 0 ? $companyDealsActual_distinct : $companyDealsActual_rollup;

    $valuePct = $companyValueTarget_agentsum > 0 ? (($companyValueActual / $companyValueTarget_agentsum) * 100) : 0;
    $dealsPct = $companyDealsTarget_agentsum > 0 ? (($companyDealsActual / $companyDealsTarget_agentsum) * 100) : 0;

    $valueBar = $valuePct >= 95 ? 'bg-green-600' : ($valuePct >= 75 ? 'bg-amber-500' : 'bg-red-600');
    $dealsBar = $dealsPct >= 95 ? 'bg-green-600' : ($dealsPct >= 75 ? 'bg-amber-500' : 'bg-red-600');

    $statusSummary = $statusSummary ?? [];
?>

<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\AppLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
     <?php $__env->slot('header', null, []); ?> 
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-200 leading-tight">
                    Company Dashboard — <?php echo e($r['period'] ?? now()->format('Y-m')); ?>

                </h2>
                <div class="text-sm text-gray-400">Admin view (BM-clone layout)</div>
            </div>

            <div class="flex flex-wrap md:flex-nowrap items-center gap-2">
                <form method="GET" action="<?php echo e(route('admin.performance')); ?>" class="flex flex-wrap md:flex-nowrap items-center gap-2">
                    <label class="text-sm font-semibold text-gray-200">Period</label>
                    <input type="month" name="period" value="<?php echo e($r['period'] ?? now()->format('Y-m')); ?>" />
                    <button type="submit" class="btn-primary px-4 py-2">Go</button>
                </form>
            </div>
        </div>
     <?php $__env->endSlot(); ?>

    <div class="space-y-6">

        
        <div class="space-y-3">
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">

                <a href="/admin/deals?status=Declined&period=<?php echo e($r['period'] ?? now()->format('Y-m')); ?>" class="block">
                    <div class="rounded-2xl bg-red-900 text-white p-4 shadow">
                        <div class="text-xs opacity-80 mb-2">Declined</div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <div class="text-[11px] opacity-80">Period</div>
                                <div class="text-2xl font-extrabold leading-tight"><?php echo e($statusSummary['declined_period'] ?? 0); ?></div>
                            </div>
                            <div class="text-right">
                                <div class="text-[11px] opacity-80">All time</div>
                                <div class="text-2xl font-extrabold leading-tight opacity-60">—</div>
                            </div>
                        </div>
                    </div>
                </a>

                <a href="/admin/deals?status=Pending&period=<?php echo e($r['period'] ?? now()->format('Y-m')); ?>" class="block">
                    <div class="rounded-2xl bg-amber-700 text-white p-4 shadow">
                        <div class="text-xs opacity-80 mb-2">Pending</div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <div class="text-[11px] opacity-80">Period</div>
                                <div class="text-2xl font-extrabold leading-tight"><?php echo e($statusSummary['pending_period'] ?? 0); ?></div>
                            </div>
                            <div class="text-right">
                                <div class="text-[11px] opacity-80">All time</div>
                                <div class="text-2xl font-extrabold leading-tight"><?php echo e($statusSummary['pending_total'] ?? 0); ?></div>
                            </div>
                        </div>
                    </div>
                </a>

                <a href="/admin/deals?status=Granted&period=<?php echo e($r['period'] ?? now()->format('Y-m')); ?>" class="block">
                    <div class="rounded-2xl bg-blue-800 text-white p-4 shadow">
                        <div class="text-xs opacity-80 mb-2">Granted</div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <div class="text-[11px] opacity-80">Period</div>
                                <div class="text-2xl font-extrabold leading-tight"><?php echo e($statusSummary['granted_period'] ?? 0); ?></div>
                            </div>
                            <div class="text-right">
                                <div class="text-[11px] opacity-80">All time</div>
                                <div class="text-2xl font-extrabold leading-tight"><?php echo e($statusSummary['granted_total'] ?? 0); ?></div>
                            </div>
                        </div>
                    </div>
                </a>

                <a href="/admin/deals?status=Registered&period=<?php echo e($r['period'] ?? now()->format('Y-m')); ?>" class="block">
                    <div class="rounded-2xl bg-green-800 text-white p-4 shadow">
                        <div class="text-xs opacity-80 mb-2">Registered</div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <div class="text-[11px] opacity-80">Period</div>
                                <div class="text-2xl font-extrabold leading-tight"><?php echo e($statusSummary['registered_period'] ?? 0); ?></div>
                            </div>
                            <div class="text-right">
                                <div class="text-[11px] opacity-80">All time</div>
                                <div class="text-2xl font-extrabold leading-tight opacity-60">—</div>
                            </div>
                        </div>
                    </div>
                </a>

            </div>

            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <a href="/admin/deals?status=Pending&commission_status=Not%20Paid" class="block">
                    <div class="rounded-2xl bg-amber-700 text-white p-4 shadow">
                        <div class="text-xs opacity-80">Pending (Not Paid) — Company ex VAT</div>
                        <div class="text-3xl font-extrabold">
                            R <?php echo e(number_format((float)($statusSummary['pending_unpaid_company_ex_vat'] ?? 0), 0)); ?>

                        </div>
                    </div>
                </a>

                <a href="/admin/deals?status=Granted&commission_status=Not%20Paid" class="block">
                    <div class="rounded-2xl bg-blue-800 text-white p-4 shadow">
                        <div class="text-xs opacity-80">Granted (Not Paid) — Company ex VAT</div>
                        <div class="text-3xl font-extrabold">
                            R <?php echo e(number_format((float)($statusSummary['granted_unpaid_company_ex_vat'] ?? 0), 0)); ?>

                        </div>
                        <div class="mt-1 text-xs opacity-80">
                            Paid this period: R <?php echo e(number_format((float)($statusSummary['granted_paid_company_ex_vat_period'] ?? 0), 0)); ?>

                        </div>
                    </div>
                </a>

                <a href="/admin/deals?status=Registered&commission_status=Not%20Paid" class="block">
                    <div class="rounded-2xl bg-green-800 text-white p-4 shadow">
                        <div class="text-xs opacity-80">Registered (Not Paid) — Company ex VAT</div>
                        <div class="text-3xl font-extrabold">
                            R <?php echo e(number_format((float)($statusSummary['registered_unpaid_company_ex_vat'] ?? 0), 0)); ?>

                        </div>
                        <div class="mt-1 text-xs opacity-80">
                            Paid this period: R <?php echo e(number_format((float)($statusSummary['registered_paid_company_ex_vat_period'] ?? 0), 0)); ?>

                        </div>
                    </div>
                </a>
            </div>
        </div>

        <?php if(session("status")): ?>
            <div class="bg-green-500/10 border border-green-500/20 text-green-200 rounded-xl p-3 text-sm">
                <?php echo e(session("status")); ?>

            </div>
        <?php endif; ?>

        <?php if(isset($errors) && $errors->any()): ?>
            <div class="bg-red-500/10 border border-red-500/20 text-red-200 rounded-xl p-3 text-sm">
                <?php echo e(implode(", ", $errors->all())); ?>

            </div>
        <?php endif; ?>

        
        <div class="card">
            <div class="flex items-center justify-between mb-3">
                <div class="text-gray-900 font-semibold text-lg">Listing Stock (Company)</div>
                <a href="<?php echo e(route('admin.listings.stock')); ?>" class="text-sm text-blue-600 hover:underline">View all</a>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                <a href="<?php echo e(route('admin.listings.stock', ['filter' => 'active'])); ?>" class="p-4 rounded-2xl bg-gray-50 border border-black/10 hover:bg-gray-100 transition block">
                    <div class="text-xs text-gray-500">Active</div>
                    <div class="text-2xl font-extrabold text-gray-900"><?php echo e((int)($listingStats['total'] ?? 0)); ?></div>
                </a>

                <a href="<?php echo e(route('admin.listings.stock', ['filter' => 'dom'])); ?>" class="p-4 rounded-2xl bg-gray-50 border border-black/10 hover:bg-gray-100 transition block">
                    <div class="text-xs text-gray-500">Avg DOM</div>
                    <div class="text-2xl font-extrabold text-gray-900"><?php echo e((int)($listingStats['avg_days_on_market'] ?? 0)); ?></div>
                </a>

                <a href="<?php echo e(route('admin.listings.stock', ['filter' => 'stale'])); ?>" class="p-4 rounded-2xl bg-gray-50 border border-black/10 hover:bg-gray-100 transition block">
                    <div class="text-xs text-gray-500">Stale</div>
                    <div class="text-2xl font-extrabold text-gray-900"><?php echo e((int)($listingStats['stale'] ?? 0)); ?></div>
                </a>

                <a href="<?php echo e(route('admin.listings.stock', ['filter' => 'expiring'])); ?>" class="p-4 rounded-2xl bg-gray-50 border border-black/10 hover:bg-gray-100 transition block">
                    <div class="text-xs text-gray-500">Expiring</div>
                    <div class="text-2xl font-extrabold text-gray-900"><?php echo e((int)($listingStats['expiring_soon'] ?? 0)); ?></div>
                </a>

                <a href="<?php echo e(route('admin.listings.stock', ['filter' => 'expired'])); ?>" class="p-4 rounded-2xl bg-gray-50 border border-black/10 hover:bg-gray-100 transition block">
                    <div class="text-xs text-gray-500">Expired</div>
                    <div class="text-2xl font-extrabold text-gray-900"><?php echo e((int)($listingStats['expired'] ?? 0)); ?></div>
                </a>
            </div>
        </div>


        
        <div class="card">
            <div class="text-2xl md:text-3xl font-extrabold text-gray-900 leading-tight">Company focus — Money</div>
            <div class="text-sm text-gray-600 mt-1">
                Value is priority. Targets below are based on what agents planned for the month (agent target sum).
            </div>

            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="rounded-2xl border border-black/10 bg-gray-50 p-4">
                    <div class="text-sm text-gray-600 font-semibold">Company Value (Actual / Agent-Sum Target)</div>
                    <div class="text-3xl font-extrabold text-gray-900 leading-tight">
                        R <?php echo e(number_format($companyValueActual, 0)); ?>

                        <span class="text-gray-400 font-bold">/ R <?php echo e(number_format($companyValueTarget_agentsum, 0)); ?></span>
                    </div>
                    <div class="mt-2 h-3 rounded bg-gray-200 overflow-hidden">
                        <div class="h-3 <?php echo e($valueBar); ?>" style="width: <?php echo e(min(100, max(0, $valuePct))); ?>%"></div>
                    </div>
                    <div class="mt-2 text-sm text-gray-700 font-semibold">Progress <?php echo e(number_format($valuePct, 1)); ?>%</div>
                </div>

                <div class="rounded-2xl border border-black/10 bg-gray-50 p-4">
                    <div class="text-sm text-gray-600 font-semibold">Company Deals (Actual / Agent-Sum Target)</div>
                    <div class="text-3xl font-extrabold text-gray-900 leading-tight">
                        <?php echo e((int)$companyDealsActual); ?>

                        <span class="text-gray-400 font-bold">/ <?php echo e((int)$companyDealsTarget_agentsum); ?></span>
                    </div>
                    <div class="mt-2 h-3 rounded bg-gray-200 overflow-hidden">
                        <div class="h-3 <?php echo e($dealsBar); ?>" style="width: <?php echo e(min(100, max(0, $dealsPct))); ?>%"></div>
                    </div>
                    <div class="mt-2 text-sm text-gray-700 font-semibold">Progress <?php echo e(number_format($dealsPct, 1)); ?>%</div>
                </div>
            </div>
        </div>

        
        <div class="card">
            <div class="text-2xl md:text-3xl font-extrabold text-gray-900 leading-tight">Branches — Progress</div>
            <div class="text-sm text-gray-600 mt-1">Value and points progress per branch for the selected period.</div>

            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                <?php $__currentLoopData = ($r['branches'] ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $b): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <?php
                        $bid = (int)($b['branch_id'] ?? 0);
                        $bName = (string)($b['branch_name'] ?? 'Branch');

                        // Branch rows from CompanyPerformanceService are shaped as:
                        //   $b['targets'], $b['actuals'], $b['progress']
                        // Older admin cards used $b['totals']['targets|actuals'].
                        // Support both, but prefer the new shape.
                        $bTotals = $b['totals'] ?? ['actuals'=>[], 'targets'=>[]];
                        $bA = $b['actuals'] ?? ($bTotals['actuals'] ?? []);
                        $bT = $b['targets'] ?? ($bTotals['targets'] ?? []);

                        $bValueTarget = (float)($bT['value'] ?? 0);
                        $bValueActual = (float)($bA['value'] ?? $bA['sales_value'] ?? 0);
                        $bValuePct = $bValueTarget > 0 ? (($bValueActual / $bValueTarget) * 100) : 0;
                        $bValueBar  = $bValuePct >= 95 ? 'bg-green-600' : ($bValuePct >= 75 ? 'bg-amber-500' : 'bg-red-600');

                        $bPointsTarget = (float)($bT['points'] ?? 0);
                        $bPointsActual = (float)($bA['points'] ?? 0);
                        $bPointsPct = $bPointsTarget > 0 ? (($bPointsActual / $bPointsTarget) * 100) : 0;
                        $bPointsBar  = $bPointsPct >= 95 ? 'bg-green-600' : ($bPointsPct >= 75 ? 'bg-amber-500' : 'bg-red-600');
                    ?>

                    <a href="<?php echo e(route('admin.branch.performance', ['branchId' => $bid, 'period' => ($r['period'] ?? now()->format('Y-m'))])); ?>"
                       class="block rounded-2xl border border-black/10 bg-gray-50 p-4 hover:bg-black/5">
                        <div class="text-xs font-bold text-gray-500 tracking-wide">BRANCH</div>
                        <div class="text-xl font-extrabold text-gray-900 leading-tight"><?php echo e($bName); ?></div>

                        <div class="mt-4">
                            <div class="text-sm text-gray-600 font-semibold">Value</div>
                            <div class="mt-1 h-3 rounded bg-gray-200 overflow-hidden">
                                <div class="h-3 <?php echo e($bValueBar); ?>" style="width: <?php echo e(min(100, max(0, $bValuePct))); ?>%"></div>
                            </div>
                            <div class="mt-1 text-xs text-gray-600">
                                R <?php echo e(number_format($bValueActual,0)); ?> / R <?php echo e(number_format($bValueTarget,0)); ?> (<?php echo e(number_format($bValuePct,1)); ?>%)
                            </div>
                        </div>

                        <div class="mt-4">
                            <div class="text-sm text-gray-600 font-semibold">Points</div>
                            <div class="mt-1 h-3 rounded bg-gray-200 overflow-hidden">
                                <div class="h-3 <?php echo e($bPointsBar); ?>" style="width: <?php echo e(min(100, max(0, $bPointsPct))); ?>%"></div>
                            </div>
                            <div class="mt-1 text-xs text-gray-600">
                                <?php echo e(number_format($bPointsActual,0)); ?> / <?php echo e(number_format($bPointsTarget,0)); ?> (<?php echo e(number_format($bPointsPct,1)); ?>%)
                            </div>
                        </div>
                    </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>

        
        <div class="card overflow-hidden">
            <div class="p-4">
                <div class="text-gray-900 font-semibold text-lg">Agents (targets vs actuals)</div>
                <div class="text-sm text-gray-600">This is the management view: who is on pace, who is behind, and where to intervene.</div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm text-gray-900">
                    <thead class="bg-gray-50 text-gray-700">
                        <tr>
                            <th class="text-left px-4 py-3">Agent</th>
                            <th class="text-right px-4 py-3">Deals (A/T)</th>
                            <th class="text-right px-4 py-3">Sales Value (A/T)</th>
                            <th class="text-right px-4 py-3">Points (A/T)</th>
                            <th class="text-right px-4 py-3">Company Retained</th>
                            <th class="text-right px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-t border-black/10 bg-gray-50">
                            <td class="px-4 py-3 font-extrabold">COMPANY TOTAL</td>
                            <td class="px-4 py-3 text-right font-bold">
                                <?php echo e((int)($companyDealsActual ?? 0)); ?> / <?php echo e((int)($r['totals']['targets']['deals'] ?? 0)); ?>

                            </td>
                            <td class="px-4 py-3 text-right font-bold">
                                R <?php echo e(number_format((float)($r['totals']['actuals']['value'] ?? $r['totals']['actuals']['sales_value'] ?? 0), 0)); ?>

                                / R <?php echo e(number_format((float)($r['totals']['targets']['value'] ?? 0), 0)); ?>

                            </td>
                            <td class="px-4 py-3 text-right font-bold">
                                <?php echo e(number_format((float)($r['totals']['actuals']['points'] ?? 0), 0)); ?>

                                / <?php echo e(number_format((float)($r['totals']['targets']['points'] ?? 0), 0)); ?>

                            </td>
                            <td class="px-4 py-3 text-right font-extrabold">
                                R <?php echo e(number_format((float)($r['totals']['actuals']['team_company_retained'] ?? 0), 0)); ?>

                                <div class="text-[11px] text-gray-500 font-semibold">
                                    Ledger: R <?php echo e(number_format((float)($r['totals']['actuals']['ledger_company_retained'] ?? 0), 0)); ?>

                                </div>
                            </td>
                            <td class="px-4 py-3 text-right text-gray-700 font-bold">—</td>
                        </tr>

                        <?php $__currentLoopData = ($r['rows'] ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php
                                $pointsTargetRow = (float)($row['targets']['points'] ?? 0);
                                $pointsActualRow = (float)($row['actuals']['points'] ?? 0);
                                $pct = ($pointsTargetRow > 0) ? round(($pointsActualRow/$pointsTargetRow)*100, 1) : 0;
                                $status = (string)($row['progress']['points_status'] ?? '—');

                                $statusClass = 'text-gray-700';
                                if (in_array($status, ['Achieved','Ahead','On pace'])) $statusClass = 'text-green-700';
                                if ($status === 'Behind') $statusClass = 'text-red-700';

                                $retained = (float)($row['actuals']['company_retained'] ?? 0);
                                $agentIncome = (float)($row['actuals']['agent_income'] ?? 0);
                                $valueActualRow = (float)($row['actuals']['value'] ?? $row['actuals']['sales_value'] ?? 0);
                                $valueTargetRow = (float)($row['targets']['value'] ?? 0);
                            ?>

                            <tr class="border-t border-black/10">
                                <td class="px-4 py-3">
                                    <div class="font-semibold">
                                        <a class="text-indigo-600 hover:underline"
                                           href="<?php echo e(route('admin.agent.performance', ['userId' => $row['user_id'], 'period' => ($r['period'] ?? now()->format('Y-m'))])); ?>">
                                            <?php echo e($row['name']); ?>

                                        </a>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        Per-day needed: <?php echo e(number_format((float)($row['progress']['points_per_day_needed'] ?? 0), 1)); ?>

                                    </div>
                                </td>

                                <td class="px-4 py-3 text-right font-semibold">
                                    <?php echo e((int)($row['actuals']['deals'] ?? 0)); ?> / <?php echo e((int)($row['targets']['deals'] ?? 0)); ?>

                                </td>

                                <td class="px-4 py-3 text-right font-semibold">
                                    R <?php echo e(number_format($valueActualRow, 0)); ?> / R <?php echo e(number_format($valueTargetRow, 0)); ?>

                                </td>

                                <td class="px-4 py-3 text-right font-semibold">
                                    <?php echo e(number_format($pointsActualRow, 0)); ?> / <?php echo e(number_format($pointsTargetRow, 0)); ?>

                                    <div class="mt-1 h-2 rounded bg-gray-200 overflow-hidden">
                                        <div class="h-2 <?php echo e($pct >= 95 ? 'bg-green-600' : ($pct >= 75 ? 'bg-amber-500' : 'bg-red-600')); ?>"
                                             style="width: <?php echo e(min(100, max(0, $pct))); ?>%"></div>
                                    </div>
                                </td>

                                <td class="px-4 py-3 text-right font-extrabold">
                                    R <?php echo e(number_format($retained, 0)); ?>

                                    <div class="text-[11px] text-gray-500 font-semibold">
                                        Agent: R <?php echo e(number_format($agentIncome, 0)); ?>

                                    </div>
                                </td>

                                <td class="px-4 py-3 text-right font-extrabold <?php echo e($statusClass); ?>">
                                    <?php echo e($status); ?>

                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="text-xs text-gray-500">
            Privacy: This page shows derived targets + activity + deal actuals. No worksheet net-income fields are exposed.
        </div>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php /**PATH C:\Users\johan\OneDrive\Documents\GitHub\hfc-dash\resources\views/admin/performance.blade.php ENDPATH**/ ?>