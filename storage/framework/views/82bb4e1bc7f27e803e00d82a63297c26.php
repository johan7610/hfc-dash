<?php $__env->startSection('nexus-content'); ?>


<div class="mb-6 flex items-start justify-between">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Market Analysis Results</h1>
        <p class="text-sm text-gray-500 mt-1">
            Presentation #<?php echo e($presentation->id); ?>

            &nbsp;·&nbsp; MA run #<?php echo e($maRun->id); ?>

            &nbsp;·&nbsp; SP run #<?php echo e($spRun->id); ?>

        </p>
    </div>
    <a href="<?php echo e(route('presentations.index')); ?>"
       class="text-xs text-indigo-600 hover:underline mt-1">← Back to Presentations</a>
</div>


<div class="bg-white rounded-xl shadow p-6 mb-6">
    <h2 class="text-base font-semibold text-gray-700 mb-3">Inputs</h2>
    <form method="POST" action="<?php echo e(route('presentations.compute', $presentation)); ?>">
        <?php echo csrf_field(); ?>
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
            <div>
                <label class="block text-xs text-gray-600 mb-1">Suburb <span class="text-red-500">*</span></label>
                <input type="text" name="suburb" value="<?php echo e($inputs['suburb']); ?>" required
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                <?php $__errorArgs = ['suburb'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-1 text-xs text-red-600"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
            <div>
                <label class="block text-xs text-gray-600 mb-1">Property Type <span class="text-red-500">*</span></label>
                <select name="type" required class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                    <?php $__currentLoopData = ['house','unit','land','other']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $t): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($t); ?>" <?php echo e(($inputs['type'] ?? '') === $t ? 'selected' : ''); ?>><?php echo e(ucfirst($t)); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-600 mb-1">Period <span class="text-red-500">*</span></label>
                <select name="period_months" required class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                    <?php $__currentLoopData = [6,12,24]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($m); ?>" <?php echo e(($inputs['period_months'] ?? 12) == $m ? 'selected' : ''); ?>><?php echo e($m); ?> months</option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <div>
                <label class="block text-xs text-gray-600 mb-1">Price (R)</label>
                <input type="number" name="price" value="<?php echo e($inputs['price'] ?? ''); ?>" step="1" min="0"
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-600 mb-1">Floor Area (m²)</label>
                <input type="number" name="size_m2" value="<?php echo e($inputs['size_m2'] ?? ''); ?>" min="0"
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-600 mb-1">Bedrooms</label>
                <input type="number" name="bedrooms" value="<?php echo e($inputs['bedrooms'] ?? ''); ?>" min="0" max="20"
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs text-gray-600 mb-1">Branch</label>
                <select name="branch_id" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                    <option value="">— Any —</option>
                    <?php $__currentLoopData = $branches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $branch): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($branch->id); ?>" <?php echo e(($inputs['branch_id'] ?? null) == $branch->id ? 'selected' : ''); ?>>
                            <?php echo e($branch->name); ?>

                        </option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
        </div>
        <div class="mt-4">
            <button type="submit"
                    class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded hover:bg-indigo-700">
                Re-run Analysis
            </button>
        </div>
    </form>
</div>


<div class="bg-gradient-to-br from-indigo-700 to-indigo-900 rounded-xl shadow-lg p-6 mb-6 text-white">
    <p class="text-indigo-200 text-xs font-semibold uppercase tracking-widest mb-1">Seller Summary</p>
    <h2 class="text-xl font-bold mb-5">Sale Probability at Your Price</h2>

    <?php if($spResult->skipReason): ?>
        <div class="bg-white/10 border border-white/20 rounded-lg px-4 py-3 text-sm text-indigo-100">
            <strong class="text-white">Insufficient data:</strong>
            <?php echo e($spResult->skipReason); ?>

        </div>
    <?php else: ?>
        
        <div class="grid grid-cols-3 gap-3 mb-5">
            
            <div class="bg-white/10 border border-white/20 rounded-lg p-4 text-center">
                <p class="text-indigo-200 text-xs mb-1 font-medium">Sold in 30 days</p>
                <p class="text-3xl font-bold">
                    <?php if($spResult->p30 !== null): ?>
                        <?php echo e(number_format($spResult->p30 * 100, 0)); ?><span class="text-xl">%</span>
                    <?php else: ?>
                        <span class="text-base font-normal text-indigo-300 italic">—</span>
                    <?php endif; ?>
                </p>
            </div>
            
            <div class="bg-white/15 border border-white/30 rounded-lg p-4 text-center ring-1 ring-white/30">
                <p class="text-indigo-200 text-xs mb-1 font-medium">Sold in 60 days</p>
                <p class="text-3xl font-bold">
                    <?php if($spResult->p60 !== null): ?>
                        <?php echo e(number_format($spResult->p60 * 100, 0)); ?><span class="text-xl">%</span>
                    <?php else: ?>
                        <span class="text-base font-normal text-indigo-300 italic">—</span>
                    <?php endif; ?>
                </p>
            </div>
            
            <div class="bg-white/10 border border-white/20 rounded-lg p-4 text-center">
                <p class="text-indigo-200 text-xs mb-1 font-medium">Sold in 90 days</p>
                <p class="text-3xl font-bold">
                    <?php if($spResult->p90 !== null): ?>
                        <?php echo e(number_format($spResult->p90 * 100, 0)); ?><span class="text-xl">%</span>
                    <?php else: ?>
                        <span class="text-base font-normal text-indigo-300 italic">—</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        
        <div class="bg-white/10 border border-white/20 rounded-lg px-4 py-3 flex items-center justify-between">
            <span class="text-indigo-200 text-sm">Estimated time to sell</span>
            <?php if($spResult->expectedDays !== null): ?>
                <span class="text-white font-bold text-lg"><?php echo e($spResult->expectedDays); ?> days</span>
            <?php else: ?>
                <span class="text-indigo-300 text-sm italic">
                    <?php echo e($spResult->skipReason ?? 'Insufficient data'); ?>

                </span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    
    <p class="mt-4 text-indigo-300 text-xs text-right font-mono">
        MA <?php echo e($maRun->model_version); ?> · SP <?php echo e($spRun->model_version); ?> · run #<?php echo e($spRun->id); ?>

    </p>
</div>


<?php
    $signalLabels = [
        'price'      => 'Price Position',
        'absorption' => 'Months of Inventory',
        'pressure'   => 'Demand vs Supply',
        'dom'        => 'Market DOM',
        'elasticity' => 'Elasticity',
    ];

    $rawSignals = $spRun->breakdown_json['signals'] ?? [];
    $activeSignals = array_filter(
        $rawSignals,
        fn($s) => !($s['skip'] ?? true) && isset($s['contribution']) && $s['contribution'] !== null
    );
    uasort($activeSignals, fn($a, $b) => abs($b['contribution']) <=> abs($a['contribution']));
    $topSignals = array_slice($activeSignals, 0, 3, true);
?>

<div class="bg-white rounded-xl shadow p-6 mb-6">
    <h2 class="text-base font-semibold text-gray-800 mb-1">What's Driving This</h2>
    <p class="text-xs text-gray-400 mb-4">Top 3 signals by influence on the probability score.</p>

    <?php if(empty($topSignals)): ?>
        <div class="py-6 text-center">
            <p class="text-sm text-gray-400 italic">Not enough market evidence yet.</p>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php $__currentLoopData = $topSignals; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $name => $signal): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                    $label = $signalLabels[$name] ?? ucfirst($name);
                    $raw   = $signal['raw'] ?? null;
                    $contribPct = round($signal['contribution'] * 100, 1);
                    $barWidth   = min(100, round($signal['contribution'] * 300));

                    // Static interpretation strings — no derived formulas
                    if ($name === 'price') {
                        if ($raw === null)        $interp = 'No price data';
                        elseif ($raw <= -10)      $interp = 'Well below market avg — buyer advantage';
                        elseif ($raw < 0)         $interp = 'Slightly below market avg';
                        elseif ($raw === 0.0)     $interp = 'At market price';
                        elseif ($raw <= 10)       $interp = 'Slightly above market avg';
                        else                      $interp = 'Above market price';
                    } elseif ($name === 'absorption') {
                        if ($raw === null)        $interp = 'No inventory data';
                        elseif ($raw <= 2)        $interp = "Seller's market — low inventory";
                        elseif ($raw <= 4)        $interp = 'Balanced market';
                        else                      $interp = "Buyer's market — high inventory";
                    } elseif ($name === 'pressure') {
                        if ($raw === null)        $interp = 'No demand data';
                        elseif ($raw > 1.2)       $interp = 'More buyers than available stock';
                        elseif ($raw < 0.8)       $interp = 'More stock than active buyers';
                        else                      $interp = 'Balanced demand and supply';
                    } elseif ($name === 'dom') {
                        if ($raw === null)        $interp = 'No DOM data';
                        elseif ($raw <= 30)       $interp = 'Fast-moving market';
                        elseif ($raw <= 60)       $interp = 'Moderate market pace';
                        else                      $interp = 'Slow-moving market';
                    } elseif ($name === 'elasticity') {
                        if ($raw === null)        $interp = 'No elasticity data';
                        elseif ($raw < -1)        $interp = 'Price reductions accelerate sales';
                        elseif ($raw > 1)         $interp = 'Market is price-inelastic';
                        else                      $interp = 'Moderate price sensitivity';
                    } else {
                        $interp = 'See breakdown for detail';
                    }
                ?>
                <div class="flex items-start gap-4">
                    
                    <div class="shrink-0 w-7 h-7 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold">
                        <?php echo e($loop->iteration); ?>

                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm font-medium text-gray-800"><?php echo e($label); ?></span>
                            <span class="text-xs font-semibold text-indigo-700 ml-2 shrink-0"><?php echo e($contribPct); ?>%</span>
                        </div>
                        <div class="flex items-center gap-2 mb-1.5">
                            <span class="text-xs text-gray-500">
                                <?php if($raw !== null): ?>
                                    <?php if($name === 'price'): ?>
                                        Raw: <?php echo e(number_format($raw, 1)); ?>%
                                    <?php elseif($name === 'pressure'): ?>
                                        Raw: <?php echo e(number_format($raw, 2)); ?>×
                                    <?php elseif($name === 'dom'): ?>
                                        Raw: <?php echo e(number_format($raw, 0)); ?> days
                                    <?php elseif($name === 'absorption'): ?>
                                        Raw: <?php echo e(number_format($raw, 1)); ?> mo
                                    <?php elseif($name === 'elasticity'): ?>
                                        Raw: <?php echo e(number_format($raw, 2)); ?> d/%
                                    <?php else: ?>
                                        Raw: <?php echo e(number_format($raw, 2)); ?>

                                    <?php endif; ?>
                                <?php else: ?>
                                    Raw: —
                                <?php endif; ?>
                            </span>
                            <span class="text-xs text-indigo-600 bg-indigo-50 rounded px-1.5 py-0.5 truncate"><?php echo e($interp); ?></span>
                        </div>
                        <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden">
                            <div class="h-full bg-indigo-500 rounded-full" style="width: <?php echo e($barWidth); ?>%"></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    <?php endif; ?>
</div>


<?php if(!empty($spResult->sensitivity)): ?>
<?php
    $baseRow  = null;
    $drop50k  = null;
    $drop100k = null;
    $drop150k = null;
    foreach ($spResult->sensitivity as $row) {
        if ($row['delta_rands'] === 0)       $baseRow  = $row;
        if ($row['delta_rands'] === -50000)  $drop50k  = $row;
        if ($row['delta_rands'] === -100000) $drop100k = $row;
        if ($row['delta_rands'] === -150000) $drop150k = $row;
    }
    $quickDrops = [
        ['label' => 'Drop R50,000',  'row' => $drop50k],
        ['label' => 'Drop R100,000', 'row' => $drop100k],
        ['label' => 'Drop R150,000', 'row' => $drop150k],
    ];
?>

<div class="bg-white rounded-xl shadow p-6 mb-6">
    <h2 class="text-base font-semibold text-gray-800 mb-1">Price Sensitivity</h2>
    <p class="text-xs text-gray-400 mb-4">Effect of a price reduction on 60-day probability and expected sale time.</p>

    
    <div class="grid grid-cols-3 gap-3 mb-4">
        <?php $__currentLoopData = $quickDrops; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $drop): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                $row = $drop['row'];
                $hasData = $row !== null && $row['p60'] !== null && $baseRow !== null && $baseRow['p60'] !== null;
                if ($hasData) {
                    $p60Delta    = ($row['p60'] - $baseRow['p60']) * 100;
                    $daysDelta   = ($row['expected_days'] !== null && $baseRow['expected_days'] !== null)
                                    ? ($row['expected_days'] - $baseRow['expected_days'])
                                    : null;
                }
            ?>
            <div class="border border-gray-200 rounded-lg p-4">
                <p class="text-xs text-gray-500 font-medium mb-2"><?php echo e($drop['label']); ?></p>
                <?php if($hasData): ?>
                    <p class="text-2xl font-bold text-gray-800 mb-1">
                        <?php echo e(number_format($row['p60'] * 100, 0)); ?><span class="text-base">%</span>
                        <span class="text-sm text-gray-400 font-normal ml-1">p60</span>
                    </p>
                    <p class="text-xs <?php if($p60Delta >= 0): ?> text-green-600 <?php else: ?> text-red-500 <?php endif; ?> font-medium">
                        <?php if($p60Delta >= 0): ?>
                            +<?php echo e(number_format($p60Delta, 1)); ?> pp vs base
                        <?php else: ?>
                            <?php echo e(number_format($p60Delta, 1)); ?> pp vs base
                        <?php endif; ?>
                    </p>
                    <?php if($daysDelta !== null): ?>
                        <p class="text-xs <?php if($daysDelta <= 0): ?> text-green-600 <?php else: ?> text-red-500 <?php endif; ?> mt-0.5">
                            <?php if($daysDelta < 0): ?>
                                <?php echo e($daysDelta); ?> days faster
                            <?php elseif($daysDelta > 0): ?>
                                +<?php echo e($daysDelta); ?> days slower
                            <?php else: ?>
                                No change in days
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-sm text-gray-400 italic">—</p>
                <?php endif; ?>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>

    
    <details>
        <summary class="cursor-pointer text-xs text-indigo-600 hover:text-indigo-800 select-none font-medium">
            Show full price sensitivity curve (21 steps)
        </summary>
        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-xs text-left text-gray-700">
                <thead>
                    <tr class="border-b bg-gray-50 text-gray-500">
                        <th class="py-2 px-3">Price Delta</th>
                        <th class="py-2 px-3">Dev %</th>
                        <th class="py-2 px-3">Score</th>
                        <th class="py-2 px-3">P30</th>
                        <th class="py-2 px-3">P60</th>
                        <th class="py-2 px-3">P90</th>
                        <th class="py-2 px-3">Exp. Days</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $__currentLoopData = $spResult->sensitivity; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $row): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php $isBase = $row['delta_rands'] === 0; ?>
                        <tr class="border-b last:border-0 <?php echo e($isBase ? 'bg-indigo-50 font-semibold' : ''); ?>">
                            <td class="py-1.5 px-3">
                                <?php if($row['delta_rands'] > 0): ?>
                                    +R<?php echo e(number_format($row['delta_rands'], 0, '.', ',')); ?>

                                <?php elseif($row['delta_rands'] < 0): ?>
                                    −R<?php echo e(number_format(abs($row['delta_rands']), 0, '.', ',')); ?>

                                <?php else: ?>
                                    Base
                                <?php endif; ?>
                            </td>
                            <td class="py-1.5 px-3">
                                <?php if($row['skip_reason'] ?? null): ?>
                                    <span class="text-gray-400 italic">N/A</span>
                                <?php elseif(isset($row['adjusted_deviation_pct'])): ?>
                                    <?php echo e(number_format($row['adjusted_deviation_pct'], 1)); ?>%
                                <?php else: ?>
                                    <span class="text-gray-400">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-1.5 px-3">
                                <?php if(($row['skip_reason'] ?? null) || $row['composite_score'] === null): ?>
                                    <span class="text-gray-400">—</span>
                                <?php else: ?>
                                    <?php echo e(number_format($row['composite_score'], 3)); ?>

                                <?php endif; ?>
                            </td>
                            <td class="py-1.5 px-3">
                                <?php if($row['p30'] !== null): ?>
                                    <?php echo e(number_format($row['p30'] * 100, 1)); ?>%
                                <?php else: ?>
                                    <span class="text-gray-400">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-1.5 px-3">
                                <?php if($row['p60'] !== null): ?>
                                    <?php echo e(number_format($row['p60'] * 100, 1)); ?>%
                                <?php else: ?>
                                    <span class="text-gray-400">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-1.5 px-3">
                                <?php if($row['p90'] !== null): ?>
                                    <?php echo e(number_format($row['p90'] * 100, 1)); ?>%
                                <?php else: ?>
                                    <span class="text-gray-400">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-1.5 px-3">
                                <?php if($row['expected_days'] !== null): ?>
                                    <?php echo e($row['expected_days']); ?>

                                <?php else: ?>
                                    <span class="text-gray-400">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        </div>
    </details>
</div>
<?php endif; ?>


<?php $domCurve = $maResult->domCurve; ?>
<div class="bg-white rounded-xl shadow p-6 mb-6">
    <h2 class="text-base font-semibold text-gray-700 mb-1">Market Evidence</h2>
    <p class="text-xs text-gray-400 mb-4">
        <?php echo e($inputs['suburb']); ?> · <?php echo e(ucfirst($inputs['type'])); ?> · <?php echo e($inputs['period_months']); ?> month window
    </p>
    <dl class="grid grid-cols-2 gap-x-8 gap-y-3 text-sm md:grid-cols-3">
        <div>
            <dt class="text-xs text-gray-400 mb-0.5">Months of Inventory</dt>
            <dd class="font-semibold text-gray-800">
                <?php if($maResult->monthsOfInventory !== null): ?>
                    <?php echo e(number_format($maResult->monthsOfInventory, 1)); ?> mo
                <?php else: ?>
                    <span class="text-gray-300">—</span>
                <?php endif; ?>
            </dd>
        </div>
        <div>
            <dt class="text-xs text-gray-400 mb-0.5">Demand / Supply Ratio</dt>
            <dd class="font-semibold text-gray-800">
                <?php if($maResult->demandSupplyRatio !== null): ?>
                    <?php echo e(number_format($maResult->demandSupplyRatio, 2)); ?>×
                <?php else: ?>
                    <span class="text-gray-300">—</span>
                <?php endif; ?>
            </dd>
        </div>
        <div>
            <dt class="text-xs text-gray-400 mb-0.5">Price/m² vs Market</dt>
            <dd class="font-semibold text-gray-800">
                <?php if($maResult->pricePerSqmDeviationPct !== null): ?>
                    <?php echo e(number_format($maResult->pricePerSqmDeviationPct, 1)); ?>%
                <?php else: ?>
                    <span class="text-gray-300">—</span>
                <?php endif; ?>
            </dd>
        </div>
        <div>
            <dt class="text-xs text-gray-400 mb-0.5">DOM Median (p50)</dt>
            <dd class="font-semibold text-gray-800">
                <?php if(is_array($domCurve) && isset($domCurve['p50'])): ?>
                    <?php echo e($domCurve['p50']); ?> days
                <?php else: ?>
                    <span class="text-gray-300">—</span>
                <?php endif; ?>
            </dd>
        </div>
        <div>
            <dt class="text-xs text-gray-400 mb-0.5">DOM p75</dt>
            <dd class="font-semibold text-gray-800">
                <?php if(is_array($domCurve) && isset($domCurve['p75'])): ?>
                    <?php echo e($domCurve['p75']); ?> days
                <?php else: ?>
                    <span class="text-gray-300">—</span>
                <?php endif; ?>
            </dd>
        </div>
        <div>
            <dt class="text-xs text-gray-400 mb-0.5">Elasticity (days/%)</dt>
            <dd class="font-semibold text-gray-800">
                <?php if($maResult->elasticityDaysPerPct !== null): ?>
                    <?php echo e(number_format($maResult->elasticityDaysPerPct, 2)); ?>

                <?php else: ?>
                    <span class="text-gray-300">—</span>
                <?php endif; ?>
            </dd>
        </div>
    </dl>
</div>


<?php
    $snapInputsJson = json_encode($inputs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    // Build output summary for snapshot storage
    $snapBase     = null;
    $snapDrop50k  = null;
    $snapDrop100k = null;
    $snapDrop150k = null;
    foreach ($spResult->sensitivity as $row) {
        if ($row['delta_rands'] === 0)       $snapBase     = $row;
        if ($row['delta_rands'] === -50000)  $snapDrop50k  = $row;
        if ($row['delta_rands'] === -100000) $snapDrop100k = $row;
        if ($row['delta_rands'] === -150000) $snapDrop150k = $row;
    }
    $snapDomCurve = is_array($maResult->domCurve) ? $maResult->domCurve : [];
    $snapOutputSummary = [
        'p30'           => $spResult->p30,
        'p60'           => $spResult->p60,
        'p90'           => $spResult->p90,
        'expected_days' => $spResult->expectedDays,
        'skip_reason'   => $spResult->skipReason,
        'months_of_inventory'         => $maResult->monthsOfInventory,
        'demand_supply_ratio'         => $maResult->demandSupplyRatio,
        'price_per_sqm_deviation_pct' => $maResult->pricePerSqmDeviationPct,
        'dom_p50'                     => $snapDomCurve['p50'] ?? null,
        'dom_p75'                     => $snapDomCurve['p75'] ?? null,
        'elasticity_days_per_pct'     => $maResult->elasticityDaysPerPct,
        'sensitivity_drop_50k'  => $snapDrop50k  ? ['p60' => $snapDrop50k['p60'],  'expected_days' => $snapDrop50k['expected_days']]  : null,
        'sensitivity_drop_100k' => $snapDrop100k ? ['p60' => $snapDrop100k['p60'], 'expected_days' => $snapDrop100k['expected_days']] : null,
        'sensitivity_drop_150k' => $snapDrop150k ? ['p60' => $snapDrop150k['p60'], 'expected_days' => $snapDrop150k['expected_days']] : null,
    ];
    $snapOutputJson = json_encode($snapOutputSummary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>

<div class="bg-white rounded-xl shadow p-6 mb-6">
    <h2 class="text-base font-semibold text-gray-700 mb-1">Save Snapshot</h2>
    <p class="text-xs text-gray-400 mb-4">
        Lock these results as an immutable snapshot attached to Presentation #<?php echo e($presentation->id); ?>.
        MA run #<?php echo e($maRun->id); ?> · SP run #<?php echo e($spRun->id); ?>.
    </p>
    <form method="POST" action="<?php echo e(route('presentations.snapshots.save', $presentation)); ?>">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="market_run_id"       value="<?php echo e($maRun->id); ?>">
        <input type="hidden" name="prob_run_id"         value="<?php echo e($spRun->id); ?>">
        <input type="hidden" name="inputs_json"         value="<?php echo e($snapInputsJson); ?>">
        <input type="hidden" name="output_summary_json" value="<?php echo e($snapOutputJson); ?>">
        <button type="submit"
                class="px-5 py-2 bg-emerald-600 text-white text-sm font-medium rounded hover:bg-emerald-700">
            Save Snapshot
        </button>
    </form>
</div>


<div class="mb-2">
    <h2 class="text-xs font-semibold text-gray-400 uppercase tracking-widest">Audit Mode</h2>
</div>

<details class="mb-3">
    <summary class="cursor-pointer text-sm text-gray-500 hover:text-gray-700 select-none">
        Market Analytics breakdown (MA run #<?php echo e($maRun->id); ?> · <?php echo e($maRun->model_version); ?>)
    </summary>
    <div class="mt-2 bg-gray-50 border border-gray-200 rounded p-4 overflow-x-auto">
        <pre class="text-xs text-gray-700 whitespace-pre-wrap"><?php echo e(json_encode($maRun->breakdown_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></pre>
    </div>
</details>

<details class="mb-8">
    <summary class="cursor-pointer text-sm text-gray-500 hover:text-gray-700 select-none">
        Sale Probability breakdown (SP run #<?php echo e($spRun->id); ?> · <?php echo e($spRun->model_version); ?>)
    </summary>
    <div class="mt-2 bg-gray-50 border border-gray-200 rounded p-4 overflow-x-auto">
        <pre class="text-xs text-gray-700 whitespace-pre-wrap"><?php echo e(json_encode($spRun->breakdown_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></pre>
    </div>
</details>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.nexus', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\johan\OneDrive\Documents\GitHub\hfc-dash\resources\views/presentations/compute.blade.php ENDPATH**/ ?>