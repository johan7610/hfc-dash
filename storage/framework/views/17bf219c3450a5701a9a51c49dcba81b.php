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
    <div style="background:#0b2a4a;" class="rounded-2xl px-6 py-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
            <div>
                <h2 class="text-xl font-bold text-white leading-tight">Worksheet Market &mdash; Branch</h2>
                <div class="text-sm text-white/60">Set market average sale price per agent</div>
            </div>
            <div class="flex items-center gap-2">
                <form method="GET" action="<?php echo e(route('bm.worksheet.market')); ?>" class="flex items-center gap-2">
                    <input type="month" name="period" value="<?php echo e($period); ?>" class="h-8 text-sm rounded border border-white/20 bg-white/10 text-white px-2" />
                    <button type="submit" class="px-3 py-1.5 text-sm font-semibold rounded bg-white/20 text-white hover:bg-white/30">Go</button>
                </form>
            </div>
        </div>
    </div>
 <?php $__env->endSlot(); ?>

<div class="max-w-7xl mx-auto px-4 py-6 space-y-6">

    <?php if(session('status')): ?>
        <div class="mb-4 p-3 rounded-lg bg-green-100 text-green-800 font-medium text-sm">
            <?php echo e(session('status')); ?>

        </div>
    <?php endif; ?>

    <?php
        $ma = $marketAverages ?? [];
        $aw = $avgWindow ?? 'period';
        $sf = $stageFilter ?? ['pending'=>true,'granted'=>true,'registered'=>true];
        $am = $agentMarket ?? [];
    ?>

    
    <div class="ds-status-card" style="border-left-color: var(--ds-cyan);">
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-4">
            <div>
                <h3 class="ds-section-header" style="margin-bottom:0;">Deal Register Market Averages</h3>
                <div class="text-xs text-gray-600 mt-1">
                    Uses Deal Register deals for your branch. Window + stage filters apply.
                    <?php if(!empty($dateFrom) && !empty($dateTo)): ?>
                        <span class="ml-2"><b>Window:</b> <?php echo e($dateFrom); ?> &rarr; <?php echo e($dateTo); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <form method="GET" action="<?php echo e(route('bm.worksheet.market')); ?>" class="flex flex-wrap gap-3 items-end">
                <input type="hidden" name="period" value="<?php echo e($period); ?>" />

                <div>
                    <label class="ds-label block mb-1">Window</label>
                    <select name="avg_window" class="border rounded-lg p-2 text-sm">
                        <option value="period" <?php echo e($aw==='period'?'selected':''); ?>>This month</option>
                        <option value="3m" <?php echo e($aw==='3m'?'selected':''); ?>>Last 3 months</option>
                        <option value="6m" <?php echo e($aw==='6m'?'selected':''); ?>>Last 6 months</option>
                        <option value="all" <?php echo e($aw==='all'?'selected':''); ?>>All time</option>
                    </select>
                </div>

                <div class="flex gap-3">
                    <label class="text-sm flex items-center gap-2">
                        <input type="checkbox" name="st_pending" value="1" <?php echo e(!empty($sf['pending'])?'checked':''); ?>>
                        Pending
                    </label>
                    <label class="text-sm flex items-center gap-2">
                        <input type="checkbox" name="st_granted" value="1" <?php echo e(!empty($sf['granted'])?'checked':''); ?>>
                        Granted
                    </label>
                    <label class="text-sm flex items-center gap-2">
                        <input type="checkbox" name="st_registered" value="1" <?php echo e(!empty($sf['registered'])?'checked':''); ?>>
                        Registered
                    </label>
                </div>

                <button class="px-4 py-2 rounded-lg text-sm font-semibold text-white" style="background:#0b2a4a;">Apply</button>
            </form>
        </div>

        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-5">
            <div class="bg-gray-50 border rounded-lg p-3">
                <div class="ds-label">Deals counted</div>
                <div class="ds-value-lg"><?php echo e((int)($ma['deals_count'] ?? 0)); ?></div>
            </div>
            <div class="bg-gray-50 border rounded-lg p-3">
                <div class="ds-label">Avg Sale Price (Incl VAT)</div>
                <div class="ds-value-lg">R <?php echo e(number_format((float)($ma['avg_sale_price_inc_vat'] ?? 0), 2)); ?></div>
                <div class="text-xs text-gray-500 mt-1">Ex VAT: R <?php echo e(number_format((float)($ma['avg_sale_price_ex_vat'] ?? 0), 2)); ?></div>
            </div>
            <div class="bg-gray-50 border rounded-lg p-3">
                <div class="ds-label">Effective Comm % (Ex VAT)</div>
                <div class="ds-value-lg"><?php echo e(number_format((float)($ma['effective_commission_percent_ex_vat'] ?? 0), 2)); ?>%</div>
            </div>
        </div>
    </div>

    
    <div class="ds-status-card" style="border-left-color: var(--ds-navy);">
        <h3 class="ds-section-header" style="margin-bottom:0.75rem;">Agent Overrides</h3>

        <form method="POST" action="<?php echo e(route('bm.worksheet.market.save')); ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="period" value="<?php echo e($period); ?>" />

            <div class="table-scroll overflow-x-auto">
                <table class="w-full text-sm table-sticky ds-table">
                    <thead>
                        <tr>
                            <th class="text-left p-2">Agent</th>
                            <th class="text-left p-2">Avg Sales Override</th>
                            <th class="text-left p-2">Actual Deals</th>
                            <th class="text-left p-2">Actual Avg Sale (Inc)</th>
                            <th class="text-left p-2">Actual Eff Comm % (Ex)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $agents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $a): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php
                                $w = $worksheets->get($a->id);
                                $planned = $w->avg_sale_price ?? null;
                                $cur = $w->avg_sale_price_admin ?? null;

                                $m = $am[(int)$a->id] ?? ['deals_count'=>0,'avg_sale_price_inc_vat'=>0,'effective_commission_percent_ex_vat'=>0];
                            ?>
                            <tr>
                                <td class="p-2 font-semibold whitespace-nowrap min-w-[220px]" style="color:#0b2a4a;"><?php echo e($a->name); ?></td>

                                <td class="p-2">
                                    <input type="number" step="0.01" name="avg[<?php echo e($a->id); ?>]" value="<?php echo e(old('avg.'.$a->id, $cur)); ?>"
                                           class="w-32 border rounded-lg p-2 text-sm" placeholder="e.g. 1200000" />
                                    <div class="text-xs text-gray-500 mt-1">
                                        Current: <?php echo e($cur === null ? 'NULL' : ('R ' . number_format((float)$cur, 2))); ?>

                                    </div>
                                </td>

                                <td class="p-2 text-gray-700"><?php echo e((int)($m['deals_count'] ?? 0)); ?></td>
                                <td class="p-2 ds-value">R <?php echo e(number_format((float)($m['avg_sale_price_inc_vat'] ?? 0), 2)); ?></td>
                                <td class="p-2 ds-value"><?php echo e(number_format((float)($m['effective_commission_percent_ex_vat'] ?? 0), 2)); ?>%</td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <button class="px-6 py-3 rounded-lg font-bold text-white" style="background:#059669;">
                    Save Market Avg Prices
                </button>
            </div>
        </form>
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
<?php /**PATH C:\Users\johan\OneDrive\Documents\GitHub\hfc-dash\resources\views/bm/worksheet_market.blade.php ENDPATH**/ ?>