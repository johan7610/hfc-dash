<?php $__env->startSection('nexus-content'); ?>

<?php
    $statusClasses = match($presentation->status) {
        'presented' => 'bg-blue-100 text-blue-700',
        'locked'    => 'bg-green-100 text-green-700',
        default     => 'bg-gray-100 text-gray-600',
    };
    $lastSummary = $latestSnapshot ? $latestSnapshot->getOutputSummaryArray() : null;
?>


<div class="mb-6 flex items-start justify-between">
    <div>
        <div class="flex items-center gap-3 mb-1">
            <h1 class="text-2xl font-bold text-gray-800"><?php echo e($presentation->title); ?></h1>
            <span class="px-2 py-0.5 rounded text-xs font-medium <?php echo e($statusClasses); ?>">
                <?php echo e(ucfirst($presentation->status)); ?>

            </span>
        </div>
        <p class="text-sm text-gray-600"><?php echo e($presentation->property_address ?? 'No address set'); ?></p>

        
        <?php
            $propDetails = array_filter([
                $presentation->suburb,
                $presentation->property_type ? ucfirst($presentation->property_type) : null,
                $presentation->bedrooms ? $presentation->bedrooms . ' bed' : null,
                $presentation->floor_area_m2 ? $presentation->floor_area_m2 . ' m²' : null,
            ]);
        ?>
        <?php if(!empty($propDetails)): ?>
            <p class="text-xs text-gray-500 mt-0.5"><?php echo e(implode(' · ', $propDetails)); ?></p>
        <?php endif; ?>

        <?php if($presentation->seller_name): ?>
            <p class="text-xs text-gray-400 mt-0.5">Seller: <?php echo e($presentation->seller_name); ?></p>
        <?php endif; ?>
        <p class="text-xs text-gray-400 mt-0.5">Created <?php echo e($presentation->created_at->format('Y-m-d')); ?></p>
    </div>
    <a href="<?php echo e(route('presentations.index')); ?>"
       class="text-xs text-indigo-600 hover:underline mt-1">← All Presentations</a>
</div>

<?php if(session('success')): ?>
    <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded text-sm">
        <?php echo e(session('success')); ?>

    </div>
<?php endif; ?>


<div class="flex flex-wrap gap-3 mb-6">
    <?php if($readiness['can_compile']): ?>
        <a href="<?php echo e(route('presentations.analysis', $presentation)); ?>"
           class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded hover:bg-indigo-700">
            <?php echo e($latestSnapshot ? 'Re-run Analysis' : 'Run Analysis'); ?>

        </a>
    <?php else: ?>
        <span class="px-4 py-2 bg-gray-400 text-white text-sm font-medium rounded cursor-not-allowed"
              title="Complete the required evidence items below before running analysis">
            <?php echo e($latestSnapshot ? 'Re-run Analysis' : 'Run Analysis'); ?>

        </span>
    <?php endif; ?>
    <?php if($latestSnapshot): ?>
        <a href="<?php echo e(route('presentations.snapshots.show', [$presentation, $latestSnapshot])); ?>"
           class="px-4 py-2 border border-gray-300 text-gray-600 text-sm font-medium rounded hover:bg-gray-50">
            Latest Snapshot →
        </a>
    <?php endif; ?>
    <?php if(config('features.presentation_brain_ui_v1')): ?>
        <?php if($latestSnapshot): ?>
            <a href="<?php echo e(route('presentations.brain', $presentation)); ?>"
               class="px-4 py-2 bg-purple-600 text-white text-sm font-medium rounded hover:bg-purple-700">
                Brain Simulation
            </a>
        <?php else: ?>
            <span class="px-4 py-2 bg-gray-400 text-white text-sm font-medium rounded cursor-not-allowed"
                  title="Run analysis and save a snapshot first">
                Brain Simulation
            </span>
        <?php endif; ?>
    <?php endif; ?>
    <?php if(config('features.presentation_blueprint')): ?>
        <form method="POST" action="<?php echo e(route('presentations.compile', $presentation)); ?>" class="inline">
            <?php echo csrf_field(); ?>
            <button type="submit"
                    class="px-4 py-2 <?php echo e($readiness['can_compile'] ? 'bg-green-600 hover:bg-green-700' : 'bg-gray-400 cursor-not-allowed'); ?> text-white text-sm font-medium rounded"
                    <?php echo e($readiness['can_compile'] ? '' : 'disabled title="Missing required evidence — see checklist below"'); ?>>
                Compile Pack
            </button>
        </form>
    <?php endif; ?>
</div>

<?php if(session('error')): ?>
    <div class="mb-4 px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded text-sm">
        <?php echo e(session('error')); ?>

    </div>
<?php endif; ?>


<div class="mb-6 bg-white rounded-xl shadow p-5">
    <div class="flex items-center justify-between mb-3">
        <h2 class="text-sm font-semibold text-gray-700">Pack Readiness</h2>
        <span class="text-xs font-medium <?php echo e($readiness['completed_percent'] >= 100 ? 'text-green-600' : ($readiness['completed_percent'] >= 57 ? 'text-amber-600' : 'text-red-500')); ?>">
            <?php echo e($readiness['completed_percent']); ?>% complete
        </span>
    </div>

    
    <div class="w-full bg-gray-100 rounded-full h-1.5 mb-4">
        <div class="h-1.5 rounded-full <?php echo e($readiness['can_compile'] ? 'bg-green-500' : 'bg-amber-400'); ?>"
             style="width: <?php echo e($readiness['completed_percent']); ?>%"></div>
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        
        <div>
            <p class="text-xs font-medium text-gray-500 mb-2 uppercase tracking-wide">Required</p>
            <ul class="space-y-1.5">
                <?php $__currentLoopData = $readiness['required_items']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li class="flex items-start gap-2 text-xs">
                        <span class="<?php echo e($item['satisfied'] ? 'text-green-500' : 'text-red-400'); ?> mt-0.5 shrink-0">
                            <?php echo e($item['satisfied'] ? '✓' : '✗'); ?>

                        </span>
                        <span class="<?php echo e($item['satisfied'] ? 'text-gray-600' : 'text-gray-700 font-medium'); ?>">
                            <?php echo e($item['label']); ?>

                        </span>
                    </li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ul>
        </div>

        
        <div>
            <p class="text-xs font-medium text-gray-500 mb-2 uppercase tracking-wide">Optional</p>
            <ul class="space-y-1.5">
                <?php $__currentLoopData = $readiness['optional_items']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li class="flex items-start gap-2 text-xs">
                        <span class="<?php echo e($item['satisfied'] ? 'text-green-500' : 'text-gray-300'); ?> mt-0.5 shrink-0">
                            <?php echo e($item['satisfied'] ? '✓' : '○'); ?>

                        </span>
                        <span class="text-gray-500"><?php echo e($item['label']); ?></span>
                    </li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ul>
        </div>
    </div>

    <?php if($readiness['can_compile']): ?>
        <p class="mt-3 text-xs text-green-600 font-medium">All required items present — ready to compile.</p>
    <?php else: ?>
        <p class="mt-3 text-xs text-red-500">
            Missing: <?php echo e(implode(', ', array_column($readiness['missing_required'], 'label'))); ?>

        </p>
    <?php endif; ?>
</div>


<?php if($powerPanel): ?>
<div class="mb-6 bg-white rounded-xl shadow p-5">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-sm font-semibold text-gray-700">Power Panel</h2>
        <span class="text-xs text-gray-400">Snapshot <?php echo e($powerPanel['snapshot_at']->format('Y-m-d H:i')); ?></span>
    </div>

    
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6 mb-4">
        
        <div class="text-center">
            <p class="text-xs text-gray-400 mb-1">P30</p>
            <p class="text-lg font-bold <?php echo e(($powerPanel['p30'] ?? 0) >= 0.5 ? 'text-green-600' : 'text-gray-800'); ?>">
                <?php if($powerPanel['p30'] !== null): ?>
                    <?php echo e(number_format($powerPanel['p30'] * 100, 0)); ?>%
                <?php else: ?>
                    <span class="text-gray-300">--</span>
                <?php endif; ?>
            </p>
        </div>
        
        <div class="text-center">
            <p class="text-xs text-gray-400 mb-1">P60</p>
            <p class="text-lg font-bold <?php echo e(($powerPanel['p60'] ?? 0) >= 0.5 ? 'text-green-600' : 'text-gray-800'); ?>">
                <?php if($powerPanel['p60'] !== null): ?>
                    <?php echo e(number_format($powerPanel['p60'] * 100, 0)); ?>%
                <?php else: ?>
                    <span class="text-gray-300">--</span>
                <?php endif; ?>
            </p>
        </div>
        
        <div class="text-center">
            <p class="text-xs text-gray-400 mb-1">P90</p>
            <p class="text-lg font-bold <?php echo e(($powerPanel['p90'] ?? 0) >= 0.65 ? 'text-green-600' : 'text-gray-800'); ?>">
                <?php if($powerPanel['p90'] !== null): ?>
                    <?php echo e(number_format($powerPanel['p90'] * 100, 0)); ?>%
                <?php else: ?>
                    <span class="text-gray-300">--</span>
                <?php endif; ?>
            </p>
        </div>
        
        <div class="text-center">
            <p class="text-xs text-gray-400 mb-1">Exp. Days</p>
            <p class="text-lg font-bold text-gray-800">
                <?php if($powerPanel['expected_days'] !== null): ?>
                    <?php echo e($powerPanel['expected_days']); ?>

                <?php else: ?>
                    <span class="text-gray-300">--</span>
                <?php endif; ?>
            </p>
        </div>
        
        <div class="text-center">
            <p class="text-xs text-gray-400 mb-1">Confidence</p>
            <?php if($powerPanel['confidence']): ?>
                <?php
                    $confScore = $powerPanel['confidence']['confidence_score'] ?? 0;
                    $confGrade = $powerPanel['confidence']['confidence_grade'] ?? '-';
                    $confColor = match($confGrade) {
                        'A' => 'text-green-600',
                        'B' => 'text-blue-600',
                        'C' => 'text-amber-600',
                        default => 'text-red-500',
                    };
                ?>
                <p class="text-lg font-bold <?php echo e($confColor); ?>"><?php echo e($confScore); ?> <span class="text-xs">(<?php echo e($confGrade); ?>)</span></p>
            <?php else: ?>
                <p class="text-lg font-bold text-gray-300">--</p>
            <?php endif; ?>
        </div>
        
        <div class="text-center">
            <p class="text-xs text-gray-400 mb-1">PPI</p>
            <?php if($powerPanel['ppi']): ?>
                <?php
                    $ppiScore = $powerPanel['ppi']['ppi_score'] ?? 0;
                    $ppiLabel = $powerPanel['ppi']['ppi_label'] ?? '-';
                    $ppiColor = match($ppiLabel) {
                        'Strong' => 'text-green-600',
                        'Balanced' => 'text-amber-600',
                        default => 'text-red-500',
                    };
                ?>
                <p class="text-lg font-bold <?php echo e($ppiColor); ?>"><?php echo e($ppiScore); ?> <span class="text-xs">(<?php echo e($ppiLabel); ?>)</span></p>
            <?php else: ?>
                <p class="text-lg font-bold text-gray-300">--</p>
            <?php endif; ?>
        </div>
    </div>

    
    <?php
        $compStock = $powerPanel['competitive_stock'] ?? null;
        $holdingCost = $powerPanel['holding_cost'] ?? null;
    ?>
    <?php if($compStock || $holdingCost): ?>
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 mb-4 pt-3 border-t border-gray-100">
        <?php if($compStock): ?>
            <div>
                <p class="text-xs text-gray-400">Active Stock</p>
                <p class="text-sm font-semibold text-gray-700"><?php echo e($compStock['total_active_stock'] ?? '--'); ?></p>
            </div>
            <div>
                <p class="text-xs text-gray-400">Below Subject</p>
                <p class="text-sm font-semibold text-gray-700"><?php echo e($compStock['below_subject_count'] ?? '--'); ?></p>
            </div>
            <div>
                <p class="text-xs text-gray-400">Above Subject</p>
                <p class="text-sm font-semibold text-gray-700"><?php echo e($compStock['above_subject_count'] ?? '--'); ?></p>
            </div>
        <?php endif; ?>
        <?php if($holdingCost): ?>
            <div>
                <p class="text-xs text-gray-400">Monthly Hold Cost</p>
                <p class="text-sm font-semibold text-gray-700">R<?php echo e(number_format($holdingCost['monthly_total'] ?? 0, 0)); ?></p>
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    
    <?php if($powerPanel['explainability']): ?>
        <?php $explain = $powerPanel['explainability']; ?>
        <div class="pt-3 border-t border-gray-100">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                
                <?php if(!empty($explain['key_drivers'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500 mb-1 uppercase tracking-wide">Key Drivers</p>
                        <ul class="space-y-1">
                            <?php $__currentLoopData = $explain['key_drivers']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $driver): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <li class="text-xs text-gray-600 flex items-start gap-1.5">
                                    <span class="text-green-500 mt-0.5 shrink-0">+</span>
                                    <?php echo e($driver); ?>

                                </li>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <?php if(!empty($explain['risk_factors'])): ?>
                    <div>
                        <p class="text-xs font-medium text-gray-500 mb-1 uppercase tracking-wide">Risk Factors</p>
                        <ul class="space-y-1">
                            <?php $__currentLoopData = $explain['risk_factors']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $risk): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <li class="text-xs text-gray-600 flex items-start gap-1.5">
                                    <span class="text-red-400 mt-0.5 shrink-0">!</span>
                                    <?php echo e($risk); ?>

                                </li>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if(!empty($explain['position_summary'])): ?>
                <p class="mt-2 text-xs text-gray-500 italic"><?php echo e($explain['position_summary']); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 gap-6 md:grid-cols-2">

    
    <div class="bg-white rounded-xl shadow p-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Last Analysis</h2>
        <?php if($lastSummary): ?>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-400 text-xs">60-day sale probability</dt>
                    <dd class="font-semibold text-gray-800">
                        <?php if(isset($lastSummary['p60']) && $lastSummary['p60'] !== null): ?>
                            <?php echo e(number_format($lastSummary['p60'] * 100, 0)); ?>%
                        <?php else: ?>
                            <span class="text-gray-300">—</span>
                        <?php endif; ?>
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-400 text-xs">Expected Days to Sell</dt>
                    <dd class="font-semibold text-gray-800">
                        <?php if(isset($lastSummary['expected_days']) && $lastSummary['expected_days'] !== null): ?>
                            <?php echo e($lastSummary['expected_days']); ?> days
                        <?php else: ?>
                            <span class="text-gray-300">—</span>
                        <?php endif; ?>
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-400 text-xs">Months of Inventory</dt>
                    <dd class="font-semibold text-gray-800">
                        <?php if(isset($lastSummary['months_of_inventory']) && $lastSummary['months_of_inventory'] !== null): ?>
                            <?php echo e(number_format($lastSummary['months_of_inventory'], 1)); ?> mo
                        <?php else: ?>
                            <span class="text-gray-300">—</span>
                        <?php endif; ?>
                    </dd>
                </div>
            </dl>
            <p class="mt-3 text-xs text-gray-400">
                Snapshot saved <?php echo e($latestSnapshot->created_at->format('Y-m-d H:i')); ?>

            </p>
        <?php else: ?>
            <p class="text-sm text-gray-400 italic">No analysis run yet.</p>
            <?php if($readiness['can_compile']): ?>
                <a href="<?php echo e(route('presentations.analysis', $presentation)); ?>"
                   class="mt-3 inline-block text-xs text-indigo-600 hover:underline">
                    Run first analysis →
                </a>
            <?php else: ?>
                <p class="mt-2 text-xs text-gray-400">Complete the required evidence items above to unlock analysis.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    
    <div class="bg-white rounded-xl shadow p-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Snapshots</h2>
        <p class="text-2xl font-bold text-gray-800 mb-1"><?php echo e($snapshotCount); ?></p>
        <p class="text-xs text-gray-400">
            <?php echo e($snapshotCount === 1 ? 'snapshot saved' : 'snapshots saved'); ?>

        </p>
        <?php if($latestSnapshot): ?>
            <a href="<?php echo e(route('presentations.snapshots.show', [$presentation, $latestSnapshot])); ?>"
               class="mt-3 inline-block text-xs text-indigo-600 hover:underline">
                View latest →
            </a>
        <?php endif; ?>
    </div>

</div>



    
    <div class="mt-6 bg-white rounded-xl shadow p-5">
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-sm font-semibold text-gray-700">Property Links</h2>
            <?php if(config('features.portal_extension_capture_v1')): ?>
                <div class="flex gap-2">
                    <a href="https://www.property24.com" target="_blank" rel="noopener noreferrer"
                       class="px-2 py-1 bg-blue-600 text-white text-xs font-medium rounded hover:bg-blue-700">
                        Property24
                    </a>
                    <a href="https://www.privateproperty.co.za" target="_blank" rel="noopener noreferrer"
                       class="px-2 py-1 bg-blue-600 text-white text-xs font-medium rounded hover:bg-blue-700">
                        PrivateProperty
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <?php if($links->isEmpty()): ?>
            <p class="text-xs text-gray-400 italic mb-3">No links added yet.</p>
        <?php else: ?>
            <?php
                $linkTypeLabels = [
                    'property24'         => 'Property24',
                    'lightstone'         => 'Lightstone',
                    'active_listing'     => 'Active Listing',
                    'competitor_listing'  => 'Competitor',
                    'market_article'     => 'Article',
                    'other'              => 'Other',
                ];
            ?>
            <ul class="space-y-3 mb-4" id="links-list">
                <?php $__currentLoopData = $links; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $link): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li class="border border-gray-100 rounded-lg p-2 text-xs" data-link-id="<?php echo e($link->id); ?>">
                        
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0 flex items-center gap-1 flex-wrap">
                                <?php
                                    $linkColor = in_array($link->type, ['active_listing', 'competitor_listing'])
                                        ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500';
                                ?>
                                <span class="inline-block px-1.5 py-0.5 rounded text-xs font-medium <?php echo e($linkColor); ?>">
                                    <?php echo e($linkTypeLabels[$link->type] ?? ucfirst($link->type)); ?>

                                </span>

                                
                                <?php
                                    $lHasCapture = !empty($link->portal_capture_id);
                                    $lExtStatus = $link->extraction_status ?? 'pending';
                                    if ($lHasCapture) {
                                        $lExtBadge = 'bg-blue-100 text-blue-700';
                                        $lExtLabel = 'Captured';
                                    } else {
                                        $lExtBadge = match($lExtStatus) {
                                            'ok'     => 'bg-green-100 text-green-700',
                                            'failed' => 'bg-red-100 text-red-600',
                                            default  => 'bg-yellow-100 text-yellow-700',
                                        };
                                        $lExtLabel = match($lExtStatus) {
                                            'ok'     => 'Extracted',
                                            'failed' => 'Failed',
                                            default  => 'Pending',
                                        };
                                    }
                                ?>
                                <span class="inline-block px-1.5 py-0.5 rounded text-xs font-medium <?php echo e($lExtBadge); ?>" data-link-badge="<?php echo e($link->id); ?>">
                                    <?php echo e($lExtLabel); ?>

                                </span>

                                <?php if (! (config('features.portal_extension_capture_v1') && $link->type === 'property24')): ?>
                                    <form method="POST"
                                          action="<?php echo e(route('presentations.links.re-extract', [$presentation, $link])); ?>"
                                          class="inline">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit"
                                                class="inline-block px-1 py-0.5 text-xs text-indigo-500 hover:text-indigo-700"
                                                title="Re-run extraction">&#x27F3;</button>
                                    </form>
                                <?php endif; ?>

                                <?php if($link->isOverridden()): ?>
                                    <span class="inline-block px-1.5 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-700">
                                        Override
                                    </span>
                                <?php endif; ?>

                                <a href="<?php echo e($link->url); ?>" target="_blank" rel="noopener noreferrer"
                                   class="text-indigo-600 hover:underline break-all">
                                    <?php echo e(\Illuminate\Support\Str::limit($link->url, 50)); ?>

                                </a>
                                <?php if($link->notes): ?>
                                    <span class="text-gray-400"> — <?php echo e($link->notes); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="flex items-center gap-1 shrink-0">
                                <form method="POST"
                                      action="<?php echo e(route('presentations.links.update-type', [$presentation, $link])); ?>"
                                      class="flex items-center gap-1">
                                    <?php echo csrf_field(); ?>
                                    <?php echo method_field('PATCH'); ?>
                                    <select name="type" class="border border-gray-200 rounded px-1 py-0.5 text-xs">
                                        <?php $__currentLoopData = $linkTypeLabels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <option value="<?php echo e($val); ?>" <?php echo e($link->type === $val ? 'selected' : ''); ?>><?php echo e($label); ?></option>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </select>
                                    <button type="submit"
                                            class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">Save</button>
                                </form>
                                <form method="POST"
                                      action="<?php echo e(route('presentations.links.destroy', [$presentation, $link])); ?>">
                                    <?php echo csrf_field(); ?>
                                    <?php echo method_field('DELETE'); ?>
                                    <button type="submit"
                                            class="text-red-400 hover:text-red-600 text-xs"
                                            onclick="return confirm('Remove this link?')">✕</button>
                                </form>
                            </div>
                        </div>

                        
                        <?php
                            $lVerified = $link->getVerifiedData();
                            $lPageType = $lVerified['_page_type'] ?? null;
                            // Also check capture page_type for classification
                            if (!$lPageType && $lHasCapture && $link->portalCapture) {
                                $lPageType = $link->portalCapture->page_type === 'search' ? 'search' : ($link->portalCapture->page_type === 'property' ? 'listing' : null);
                            }
                            // Legacy fallback
                            if (!$lPageType && $lVerified && ($lVerified['link_subtype'] ?? '') === 'search_results') {
                                $lPageType = 'search';
                            }
                        ?>
                        <?php if($lVerified && $lPageType === 'search'): ?>
                            
                            <?php
                                $lParts = [];
                                $lListingsFound = $lVerified['listing_urls_count'] ?? $lVerified['search']['items_on_page'] ?? $lVerified['results_count'] ?? null;
                                if ($lListingsFound) $lParts[] = 'Listings: ' . $lListingsFound;
                                if (!empty($lVerified['price_min']) && !empty($lVerified['price_max'])) {
                                    $lParts[] = 'Range: R' . number_format($lVerified['price_min'], 0) . ' – R' . number_format($lVerified['price_max'], 0);
                                }
                                if (!empty($lVerified['price_median'])) $lParts[] = 'Median: R' . number_format($lVerified['price_median'], 0);
                            ?>
                            <?php if(!empty($lParts)): ?>
                                <div class="mt-1.5 text-xs text-gray-600 bg-purple-50 rounded px-2 py-1">
                                    Search capture | <?php echo e(implode(' | ', $lParts)); ?>

                                </div>
                            <?php else: ?>
                                <div class="mt-1.5 text-xs text-gray-600 bg-purple-50 rounded px-2 py-1">
                                    Search capture
                                </div>
                            <?php endif; ?>
                        <?php elseif($lVerified && ($lPageType === 'listing' || !empty($lVerified['asking_price']) || !empty($lVerified['price']))): ?>
                            
                            <?php
                                $lParts = [];
                                $lPrice = $lVerified['asking_price'] ?? $lVerified['price'] ?? null;
                                if ($lPrice) $lParts[] = 'R' . number_format($lPrice, 0);
                                $lBeds = $lVerified['beds'] ?? $lVerified['bedrooms'] ?? null;
                                $lBaths = $lVerified['baths'] ?? $lVerified['bathrooms'] ?? null;
                                if ($lBeds) $lParts[] = $lBeds . ' bed';
                                if ($lBaths) $lParts[] = $lBaths . ' bath';
                                $lFloor = $lVerified['floor_area_m2'] ?? $lVerified['floor_m2'] ?? null;
                                if ($lFloor) $lParts[] = $lFloor . 'm²';
                                if (!empty($lVerified['suburb'])) $lParts[] = $lVerified['suburb'];
                            ?>
                            <div class="mt-1.5 text-xs text-gray-600 bg-green-50 rounded px-2 py-1">
                                <?php echo e(implode(' | ', $lParts)); ?>

                            </div>
                        <?php elseif($lVerified): ?>
                            
                            <?php
                                $lSkipKeys = ['extractor_version', 'link_type', 'url', 'source_domain', 'source_site', 'link_subtype', 'snapshot_id', 'extraction_method', 'snapshot_error', 'top_listings', 'blocked_reason', 'timed_out', 'http_status', 'content_bytes', '_page_type', '_extractor', '_extraction', '_capture_source', '_capture_id', 'search', 'listing_urls_count'];
                            ?>
                            <div class="mt-1.5 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-gray-500">
                                <?php $__currentLoopData = $lVerified; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $lKey => $lVal): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <?php if(!in_array($lKey, $lSkipKeys) && $lVal !== null && $lVal !== '' && !is_array($lVal)): ?>
                                        <span>
                                            <span class="text-gray-400"><?php echo e(str_replace('_', ' ', $lKey)); ?>:</span>
                                            <?php if(is_numeric($lVal) && $lVal >= 10000): ?>
                                                R<?php echo e(number_format($lVal, 0)); ?>

                                            <?php else: ?>
                                                <?php echo e($lVal); ?>

                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </div>
                        <?php endif; ?>
                        <?php if($lHasCapture): ?>
                            <?php $lCapture = $link->portalCapture; ?>
                            <?php if($lCapture): ?>
                                <div class="mt-1.5 bg-blue-50 border border-blue-200 rounded px-2 py-1.5 text-xs text-blue-700 flex items-center justify-between gap-2">
                                    <div class="flex-1">
                                        <span class="font-semibold">Captured via extension</span>
                                        — <?php echo e(number_format($lCapture->html_bytes)); ?> bytes
                                        <?php if($lCapture->screenshot_path): ?>
                                            | screenshot saved
                                        <?php endif; ?>
                                        | <?php echo e($lCapture->captured_at->format('Y-m-d H:i')); ?>

                                    </div>
                                </div>
                                <?php $lPriceChanges = $lCapture->priceChangeCount(); ?>
                                <div class="mt-1 bg-amber-50 border border-amber-300 rounded px-2 py-1 text-xs text-amber-800 font-medium <?php echo e($lPriceChanges > 0 ? '' : 'hidden'); ?>" data-price-change="<?php echo e($link->id); ?>">
                                    Price Change Detected — <span data-price-change-count="<?php echo e($link->id); ?>"><?php echo e($lPriceChanges); ?></span> listing<?php echo e($lPriceChanges > 1 ? 's' : ''); ?> changed
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if($lExtStatus === 'failed' && !$lHasCapture): ?>
                            <?php if(config('features.portal_extension_capture_v1') && $link->type === 'property24'): ?>
                                
                                <div class="mt-1.5 bg-blue-50 border border-blue-200 rounded px-2 py-1.5 text-xs text-blue-700 flex items-center justify-between gap-2">
                                    <div class="flex-1">
                                        <span class="font-semibold">Capture via Browser Extension</span> — open the portal and use the capture extension
                                    </div>
                                    <a href="<?php echo e($link->url); ?>" target="_blank" rel="noopener noreferrer"
                                       class="px-2 py-0.5 bg-blue-600 text-white text-xs rounded hover:bg-blue-700 font-medium shrink-0">
                                        Open Portal
                                    </a>
                                </div>
                            <?php else: ?>
                                <?php
                                    $lBlockedReason = $lVerified['blocked_reason'] ?? null;
                                    $lHttpStatus    = $lVerified['http_status'] ?? null;
                                    $lTimedOut      = $lVerified['timed_out'] ?? false;
                                    $lErrorMsg      = $link->extraction_error ?? 'check link type';

                                    // Determine error category for styling
                                    $lIsBlocked = $lBlockedReason || ($lHttpStatus && $lHttpStatus >= 400);
                                    $lIsTimeout = $lTimedOut;
                                ?>
                                <div class="mt-1.5 <?php echo e($lIsBlocked ? 'bg-red-50 border-red-300' : ($lIsTimeout ? 'bg-orange-50 border-orange-300' : 'bg-red-50 border-red-200')); ?> border rounded px-2 py-1.5 text-xs <?php echo e($lIsBlocked ? 'text-red-800' : ($lIsTimeout ? 'text-orange-700' : 'text-red-700')); ?> flex items-center justify-between gap-2">
                                    <div class="flex-1">
                                        <?php if(str_starts_with($lBlockedReason ?? '', 'headless_service_')): ?>
                                            <span class="font-semibold">Portal fetch engine offline</span> — start the headless service and retry
                                        <?php elseif($lIsBlocked): ?>
                                            <span class="font-semibold">Blocked</span> — <?php echo e($lBlockedReason ?? $lErrorMsg); ?>

                                            <?php if($lHttpStatus): ?>
                                                <span class="text-red-500">(HTTP <?php echo e($lHttpStatus); ?>)</span>
                                            <?php endif; ?>
                                        <?php elseif($lIsTimeout): ?>
                                            <span class="font-semibold">Timed out</span> — connection to site failed
                                        <?php else: ?>
                                            No data extracted — <?php echo e($lErrorMsg); ?>

                                        <?php endif; ?>
                                    </div>
                                    <form method="POST"
                                          action="<?php echo e(route('presentations.links.re-extract', [$presentation, $link])); ?>"
                                          class="shrink-0">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit"
                                                class="px-2 py-0.5 bg-red-600 text-white text-xs rounded hover:bg-red-700 font-medium">
                                            Retry
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>

                        
                        <?php if($link->isOverridden()): ?>
                            <p class="mt-1 text-xs text-orange-500">
                                Overridden <?php echo e($link->override_at ? $link->override_at->format('Y-m-d H:i') : ''); ?>

                                <?php if($link->override_by_user_id): ?>
                                    by user #<?php echo e($link->override_by_user_id); ?>

                                <?php endif; ?>
                            </p>
                        <?php endif; ?>

                        
                        <?php if(config('features.presentation_link_details_v1') && isset($linkViews[$link->id])): ?>
                            <?php $lView = $linkViews[$link->id]; ?>
                            <details class="mt-1.5">
                                <summary class="text-xs text-indigo-500 cursor-pointer hover:underline">
                                    <?php if(($lView['capture_page_type'] ?? null) === 'search'): ?>
                                        View search summary
                                    <?php else: ?>
                                        <?php echo e($link->isOverridden() ? 'Edit override' : 'View details / Override'); ?>

                                    <?php endif; ?>
                                </summary>
                                <div class="mt-2 space-y-3">

                                    <?php if(($lView['capture_page_type'] ?? null) === 'search'): ?>
                                        
                                        <div class="bg-purple-50 border border-purple-200 rounded p-3">
                                            <p class="text-xs font-semibold text-purple-700 mb-2 uppercase tracking-wide">Search Capture Summary</p>
                                            <?php if(!empty($lView['search_summary'])): ?>
                                                <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs">
                                                    <?php if(!empty($lView['search_summary']['listings_found'])): ?>
                                                        <dt class="text-purple-400 whitespace-nowrap">Listings found</dt>
                                                        <dd class="text-purple-800 font-medium"><?php echo e($lView['search_summary']['listings_found']); ?></dd>
                                                    <?php endif; ?>
                                                    <?php if(!empty($lView['search_summary']['total_results'])): ?>
                                                        <dt class="text-purple-400 whitespace-nowrap">Total results</dt>
                                                        <dd class="text-purple-800 font-medium"><?php echo e($lView['search_summary']['total_results']); ?></dd>
                                                    <?php endif; ?>
                                                    <?php if(!empty($lView['search_summary']['price_change_count'])): ?>
                                                        <dt class="text-purple-400 whitespace-nowrap">Price changes</dt>
                                                        <dd class="text-amber-700 font-semibold"><?php echo e($lView['search_summary']['price_change_count']); ?></dd>
                                                    <?php endif; ?>
                                                    <?php if(!empty($lView['search_summary']['capture_time'])): ?>
                                                        <dt class="text-purple-400 whitespace-nowrap">Captured</dt>
                                                        <dd class="text-purple-800"><?php echo e($lView['search_summary']['capture_time']); ?></dd>
                                                    <?php endif; ?>
                                                    <?php if(!empty($lView['search_summary']['html_bytes'])): ?>
                                                        <dt class="text-purple-400 whitespace-nowrap">Page size</dt>
                                                        <dd class="text-purple-800"><?php echo e(number_format($lView['search_summary']['html_bytes'])); ?> bytes</dd>
                                                    <?php endif; ?>
                                                    <?php if(!empty($lView['search_summary']['parse_status'])): ?>
                                                        <dt class="text-purple-400 whitespace-nowrap">Status</dt>
                                                        <dd class="text-purple-800"><?php echo e($lView['search_summary']['parse_status']); ?></dd>
                                                    <?php endif; ?>
                                                </dl>
                                            <?php endif; ?>
                                            <p class="mt-2 text-xs text-purple-500 italic">
                                                Search captures monitor competitor changes. To see listing details, open the listing page and capture it.
                                            </p>
                                        </div>
                                        
                                    <?php else: ?>
                                        

                                        
                                        <?php if(!empty($lView['imported'])): ?>
                                            <div class="bg-gray-50 rounded p-2">
                                                <p class="text-xs font-medium text-gray-500 mb-1.5 uppercase tracking-wide">Imported data</p>
                                                <dl class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-xs">
                                                    <?php $__currentLoopData = $lView['imported']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fieldLabel => $fieldVal): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                        <dt class="text-gray-400 whitespace-nowrap"><?php echo e($fieldLabel); ?></dt>
                                                        <dd class="text-gray-700 font-medium"><?php echo e($fieldVal); ?></dd>
                                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                </dl>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-xs text-gray-400 italic">No imported data available.</p>
                                        <?php endif; ?>

                                        
                                        <?php if(!empty($lView['meta'])): ?>
                                            <div class="flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-gray-400">
                                                <?php $__currentLoopData = $lView['meta']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $mLabel => $mVal): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <span><?php echo e($mLabel); ?>: <span class="text-gray-600"><?php echo e($mVal); ?></span></span>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                            </div>
                                        <?php endif; ?>

                                        
                                        <?php if(!empty($lView['override_fields'])): ?>
                                            <form method="POST"
                                                  action="<?php echo e(route('presentations.links.override', [$presentation, $link])); ?>"
                                                  class="border border-orange-200 rounded p-2 bg-orange-50">
                                                <?php echo csrf_field(); ?>
                                                <?php echo method_field('PATCH'); ?>
                                                <p class="text-xs font-medium text-orange-700 mb-1.5">Override values</p>
                                                <table class="w-full text-xs border-collapse">
                                                    <thead>
                                                        <tr class="text-left text-gray-400 border-b">
                                                            <th class="py-1 pr-2 font-medium">Field</th>
                                                            <th class="py-1 pr-2 font-medium">Current</th>
                                                            <th class="py-1 pr-2 font-medium">Imported</th>
                                                            <th class="py-1 font-medium">Override</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php $__currentLoopData = $lView['override_fields']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $oField): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                            <tr class="border-b border-gray-100">
                                                                <td class="py-1.5 pr-2 text-gray-500 whitespace-nowrap"><?php echo e($oField['label']); ?></td>
                                                                <td class="py-1.5 pr-2 text-gray-700"><?php echo e($oField['current'] ?? '—'); ?></td>
                                                                <td class="py-1.5 pr-2 <?php echo e($oField['imported'] ? 'text-blue-600' : 'text-gray-300'); ?>">
                                                                    <?php echo e($oField['imported'] ?? ($oField['imported_missing_label'] ?? 'No imported value yet')); ?>

                                                                </td>
                                                                <td class="py-1.5">
                                                                    <input type="text" name="override_data[<?php echo e($oField['key']); ?>]"
                                                                           placeholder="<?php echo e($oField['label']); ?>"
                                                                           value="<?php echo e($oField['current_raw'] ?? ''); ?>"
                                                                           class="w-full border border-gray-200 rounded px-1.5 py-0.5 text-xs">
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                                    </tbody>
                                                </table>
                                                <?php if(!empty($lView['meta']['Captured'])): ?>
                                                    <p class="text-xs text-gray-400 mt-1">Last captured: <?php echo e($lView['meta']['Captured']); ?>

                                                        <?php if(!empty($lView['meta']['Source'])): ?>
                                                            (<?php echo e($lView['meta']['Source']); ?>)
                                                        <?php endif; ?>
                                                    </p>
                                                <?php endif; ?>
                                                <div class="flex gap-2 mt-1.5">
                                                    <button type="submit"
                                                            class="px-2 py-1 bg-orange-600 text-white text-xs rounded hover:bg-orange-700">
                                                        Save Override
                                                    </button>
                                                </div>
                                            </form>
                                        <?php else: ?>
                                            
                                            <?php $lOverride = $link->override_json ?? $link->extracted_json ?? []; ?>
                                            <form method="POST"
                                                  action="<?php echo e(route('presentations.links.override', [$presentation, $link])); ?>"
                                                  class="border border-orange-200 rounded p-2 bg-orange-50">
                                                <?php echo csrf_field(); ?>
                                                <?php echo method_field('PATCH'); ?>
                                                <p class="text-xs font-medium text-orange-700 mb-1.5">Override values</p>
                                                <div class="grid grid-cols-2 gap-1.5">
                                                    <?php if($link->type === 'market_article'): ?>
                                                        <input type="text" name="override_data[headline]" placeholder="Headline"
                                                               value="<?php echo e($lOverride['headline'] ?? ''); ?>"
                                                               class="col-span-2 border border-gray-200 rounded px-2 py-1 text-xs">
                                                    <?php else: ?>
                                                        <input type="text" name="override_data[notes]" placeholder="Notes"
                                                               value="<?php echo e($lOverride['notes'] ?? ''); ?>"
                                                               class="col-span-2 border border-gray-200 rounded px-2 py-1 text-xs">
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex gap-2 mt-1.5">
                                                    <button type="submit"
                                                            class="px-2 py-1 bg-orange-600 text-white text-xs rounded hover:bg-orange-700">
                                                        Save Override
                                                    </button>
                                                </div>
                                            </form>
                                        <?php endif; ?>

                                        <?php if($link->isOverridden()): ?>
                                            <form method="POST"
                                                  action="<?php echo e(route('presentations.links.override.clear', [$presentation, $link])); ?>"
                                                  class="mt-1">
                                                <?php echo csrf_field(); ?>
                                                <?php echo method_field('DELETE'); ?>
                                                <button type="submit"
                                                        class="px-2 py-1 text-xs text-gray-500 hover:text-red-600"
                                                        onclick="return confirm('Clear this override?')">
                                                    Clear Override
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    
                                    <?php if($isAdmin && $link->extracted_json): ?>
                                        <details class="mt-1">
                                            <summary class="text-xs text-gray-400 cursor-pointer hover:text-gray-600">Diagnostics (raw)</summary>
                                            <div class="mt-1 bg-gray-50 rounded p-2 text-xs font-mono text-gray-600 overflow-x-auto max-h-40 overflow-y-auto">
                                                <pre><?php echo e(json_encode($link->extracted_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
                                            </div>
                                            <?php if($link->portal_capture_id && $link->portalCapture && $link->portalCapture->extracted_fields_json): ?>
                                                <p class="text-xs text-gray-400 mt-1">Portal capture fields:</p>
                                                <div class="mt-0.5 bg-gray-50 rounded p-2 text-xs font-mono text-gray-600 overflow-x-auto max-h-40 overflow-y-auto">
                                                    <pre><?php echo e(json_encode($link->portalCapture->extracted_fields_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
                                                </div>
                                            <?php endif; ?>
                                        </details>
                                    <?php endif; ?>
                                </div>
                            </details>
                        <?php else: ?>
                            
                            <details class="mt-1.5">
                                <summary class="text-xs text-indigo-500 cursor-pointer hover:underline">
                                    <?php echo e($link->isOverridden() ? 'Edit override' : 'View details / Override'); ?>

                                </summary>
                                <div class="mt-2 space-y-2">
                                    <?php if($link->extracted_json): ?>
                                        <div class="bg-gray-50 rounded p-2 text-xs font-mono text-gray-600 overflow-x-auto max-h-40 overflow-y-auto">
                                            <pre><?php echo e(json_encode($link->extracted_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
                                        </div>
                                    <?php endif; ?>
                                    <?php $lOverride = $link->override_json ?? $link->extracted_json ?? []; ?>
                                    <form method="POST"
                                          action="<?php echo e(route('presentations.links.override', [$presentation, $link])); ?>"
                                          class="border border-orange-200 rounded p-2 bg-orange-50">
                                        <?php echo csrf_field(); ?>
                                        <?php echo method_field('PATCH'); ?>
                                        <p class="text-xs font-medium text-orange-700 mb-1.5">Override values</p>
                                        <div class="grid grid-cols-2 gap-1.5">
                                            <?php if(in_array($link->type, ['property24', 'active_listing', 'competitor_listing'])): ?>
                                                <input type="number" name="override_data[asking_price]" placeholder="Asking price (R)"
                                                       value="<?php echo e($lOverride['asking_price'] ?? ''); ?>"
                                                       class="border border-gray-200 rounded px-2 py-1 text-xs">
                                                <input type="text" name="override_data[suburb]" placeholder="Suburb"
                                                       value="<?php echo e($lOverride['suburb'] ?? ''); ?>"
                                                       class="border border-gray-200 rounded px-2 py-1 text-xs">
                                                <input type="number" name="override_data[beds]" placeholder="Beds"
                                                       value="<?php echo e($lOverride['beds'] ?? ''); ?>"
                                                       class="border border-gray-200 rounded px-2 py-1 text-xs">
                                                <input type="number" name="override_data[baths]" placeholder="Baths"
                                                       value="<?php echo e($lOverride['baths'] ?? ''); ?>"
                                                       class="border border-gray-200 rounded px-2 py-1 text-xs">
                                                <input type="number" name="override_data[floor_area_m2]" placeholder="Floor m²"
                                                       value="<?php echo e($lOverride['floor_area_m2'] ?? ''); ?>"
                                                       class="border border-gray-200 rounded px-2 py-1 text-xs">
                                                <input type="number" name="override_data[erf_m2]" placeholder="Erf m²"
                                                       value="<?php echo e($lOverride['erf_m2'] ?? ''); ?>"
                                                       class="border border-gray-200 rounded px-2 py-1 text-xs">
                                            <?php elseif($link->type === 'market_article'): ?>
                                                <input type="text" name="override_data[headline]" placeholder="Headline"
                                                       value="<?php echo e($lOverride['headline'] ?? ''); ?>"
                                                       class="col-span-2 border border-gray-200 rounded px-2 py-1 text-xs">
                                            <?php else: ?>
                                                <input type="text" name="override_data[notes]" placeholder="Notes"
                                                       value="<?php echo e($lOverride['notes'] ?? ''); ?>"
                                                       class="col-span-2 border border-gray-200 rounded px-2 py-1 text-xs">
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex gap-2 mt-1.5">
                                            <button type="submit"
                                                    class="px-2 py-1 bg-orange-600 text-white text-xs rounded hover:bg-orange-700">
                                                Save Override
                                            </button>
                                        </div>
                                    </form>
                                    <?php if($link->isOverridden()): ?>
                                        <form method="POST"
                                              action="<?php echo e(route('presentations.links.override.clear', [$presentation, $link])); ?>"
                                              class="mt-1">
                                            <?php echo csrf_field(); ?>
                                            <?php echo method_field('DELETE'); ?>
                                            <button type="submit"
                                                    class="px-2 py-1 text-xs text-gray-500 hover:text-red-600"
                                                    onclick="return confirm('Clear this override?')">
                                                Clear Override
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </details>
                        <?php endif; ?>
                    </li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ul>
        <?php endif; ?>

        <form method="POST" action="<?php echo e(route('presentations.links.store', $presentation)); ?>" id="add-link-form" class="space-y-2">
            <?php echo csrf_field(); ?>
            <div class="flex gap-2">
                <select name="type" id="link-type" class="border border-gray-300 rounded px-2 py-1.5 text-xs">
                    <option value="property24">Property24</option>
                    <option value="lightstone">Lightstone</option>
                    <option value="active_listing">Active Listing</option>
                    <option value="competitor_listing">Competitor Listing</option>
                    <option value="market_article">Market Article</option>
                    <option value="other">Other</option>
                </select>
                <input type="url" name="url" id="link-url" placeholder="https://..." required
                       class="flex-1 border border-gray-300 rounded px-2 py-1.5 text-xs min-w-0">
                <a href="#" id="open-link-btn" target="_blank" rel="noopener noreferrer"
                   class="px-2 py-1.5 border border-gray-300 text-xs rounded text-gray-500 hover:bg-gray-50 shrink-0"
                   title="Open link in new tab">↗</a>
            </div>
            <div class="flex gap-2">
                <input type="text" name="notes" placeholder="Notes (optional)"
                       class="flex-1 border border-gray-300 rounded px-2 py-1.5 text-xs">
                <button type="submit" id="add-link-btn"
                        class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded hover:bg-indigo-700 shrink-0">
                    Add Link
                </button>
            </div>
            <p id="add-link-error" class="text-xs text-red-600 hidden"></p>
            <p id="add-link-success" class="text-xs text-green-600 hidden"></p>

            <?php $__errorArgs = ['url'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                <p class="text-xs text-red-600"><?php echo e($message); ?></p>
            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </form>
        <script>
        (function () {
            var typeEl = document.getElementById('link-type');
            var urlEl  = document.getElementById('link-url');
            var openBtn = document.getElementById('open-link-btn');

            urlEl.addEventListener('input', function () {
                openBtn.href = urlEl.value || '#';
            });

            // ── AJAX Add Link ──────────────────────────────────────────────
            var form      = document.getElementById('add-link-form');
            var btn       = document.getElementById('add-link-btn');
            var errEl     = document.getElementById('add-link-error');
            var successEl = document.getElementById('add-link-success');
            var linksList = document.getElementById('links-list');
            var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            var linkTypeLabels = {
                'property24': 'Property24', 'lightstone': 'Lightstone',
                'active_listing': 'Active Listing', 'competitor_listing': 'Competitor',
                'market_article': 'Article', 'other': 'Other'
            };

            function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                errEl.classList.add('hidden');
                successEl.classList.add('hidden');

                btn.disabled = true;
                btn.textContent = 'Adding...';

                var formData = new FormData(form);
                var body = {};
                formData.forEach(function (v, k) { if (k !== '_token' && v !== '') body[k] = v; });

                fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(body)
                })
                .then(function (r) {
                    if (r.status === 422) {
                        return r.json().then(function (d) {
                            var msgs = [];
                            if (d.errors) {
                                Object.keys(d.errors).forEach(function (k) {
                                    msgs = msgs.concat(d.errors[k]);
                                });
                            }
                            errEl.textContent = msgs.join('; ') || 'Validation error';
                            errEl.classList.remove('hidden');
                            throw new Error('validation');
                        });
                    }
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(function (data) {
                    if (!data.success) {
                        errEl.textContent = 'Server error adding link';
                        errEl.classList.remove('hidden');
                        return;
                    }

                    // Build new link row and insert into DOM
                    var link = data.link;
                    var typeColor = ['active_listing', 'competitor_listing'].indexOf(link.type) >= 0
                        ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500';
                    var extBadge = link.portal_capture_id
                        ? 'bg-blue-100 text-blue-700'
                        : (link.extraction_status === 'ok' ? 'bg-green-100 text-green-700' : (link.extraction_status === 'failed' ? 'bg-red-100 text-red-600' : 'bg-yellow-100 text-yellow-700'));
                    var extLabel = link.portal_capture_id
                        ? 'Captured'
                        : (link.extraction_status === 'ok' ? 'Extracted' : (link.extraction_status === 'failed' ? 'Failed' : 'Pending'));
                    var shortUrl = link.url.length > 50 ? link.url.substring(0, 50) + '...' : link.url;

                    var li = document.createElement('li');
                    li.className = 'border border-gray-100 rounded-lg p-2 text-xs';
                    li.setAttribute('data-link-id', link.id);
                    li.style.backgroundColor = '#eef2ff';
                    li.innerHTML = '<div class="flex items-start justify-between gap-2">'
                        + '<div class="min-w-0 flex items-center gap-1 flex-wrap">'
                        + '<span class="inline-block px-1.5 py-0.5 rounded text-xs font-medium ' + typeColor + '">' + esc(linkTypeLabels[link.type] || link.type) + '</span>'
                        + '<span class="inline-block px-1.5 py-0.5 rounded text-xs font-medium ' + extBadge + '" data-link-badge="' + link.id + '">' + extLabel + '</span>'
                        + '<a href="' + esc(link.url) + '" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline break-all">' + esc(shortUrl) + '</a>'
                        + (link.notes ? '<span class="text-gray-400"> — ' + esc(link.notes) + '</span>' : '')
                        + '</div></div>';

                    if (linksList) {
                        linksList.appendChild(li);
                    } else {
                        // First link — create the list
                        var noLinks = form.parentElement.querySelector('p.italic');
                        if (noLinks) noLinks.remove();
                        var ul = document.createElement('ul');
                        ul.className = 'space-y-3 mb-4';
                        ul.id = 'links-list';
                        form.parentElement.insertBefore(ul, form);
                        ul.appendChild(li);
                        linksList = ul;
                    }

                    // Fade highlight
                    setTimeout(function () {
                        li.style.transition = 'background-color 2s';
                        li.style.backgroundColor = '';
                    }, 50);

                    // Clear form inputs, keep focus on URL input
                    urlEl.value = '';
                    openBtn.href = '#';
                    form.querySelector('[name="notes"]').value = '';

                    successEl.textContent = 'Link added.';
                    successEl.classList.remove('hidden');
                    setTimeout(function () { successEl.classList.add('hidden'); }, 3000);

                    urlEl.focus();
                })
                .catch(function (err) {
                    if (err.message !== 'validation') {
                        errEl.textContent = 'Failed to add link: ' + err.message;
                        errEl.classList.remove('hidden');
                    }
                })
                .finally(function () {
                    btn.disabled = false;
                    btn.textContent = 'Add Link';
                });
            });
        })();
        </script>
    </div>

    
    <?php if($isAdmin && config('features.portal_extension_capture_v1')): ?>
    <div class="mt-6">
    <details class="bg-white rounded-xl shadow">
        <summary class="px-5 py-3 text-sm font-semibold text-gray-500 cursor-pointer hover:text-gray-700 select-none">
            Portal captures (admin)
        </summary>
        <div class="px-5 pb-5">
        <div class="flex items-center justify-end mb-3">
                <button type="button" id="refresh-captures-btn"
                        class="px-2 py-1 bg-gray-100 text-gray-700 text-xs font-medium rounded hover:bg-gray-200 border border-gray-300">
                    Refresh
                </button>
        </div>

        <div id="captures-container">
            <p class="text-xs text-gray-400 italic">Loading captures...</p>
        </div>

        <script>
        (function () {
            var container = document.getElementById('captures-container');
            var refreshBtn = document.getElementById('refresh-captures-btn');
            var presentationId = <?php echo e($presentation->id); ?>;
            var listUrl = '<?php echo e(route("presentations.portal-captures.index", $presentation)); ?>';

            function loadCaptures() {
                container.innerHTML = '<p class="text-xs text-gray-400 italic">Loading...</p>';
                fetch(listUrl, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin'
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    var html = '';

                    if (data.attached && data.attached.length > 0) {
                        html += '<p class="text-xs font-semibold text-gray-500 mb-1">Attached</p>';
                        html += buildTable(data.attached, false);
                    }

                    if (data.unattached && data.unattached.length > 0) {
                        html += '<p class="text-xs font-semibold text-gray-500 mt-3 mb-1">Unattached (your recent captures)</p>';
                        html += buildTable(data.unattached, true);
                    }

                    if (!html) {
                        html = '<p class="text-xs text-gray-400 italic">No captures yet. Open a portal site and use the capture extension.</p>';
                    }

                    container.innerHTML = html;
                })
                .catch(function () {
                    container.innerHTML = '<p class="text-xs text-red-500">Failed to load captures.</p>';
                });
            }

            function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

            function buildTable(items, showAttach) {
                var t = '<table class="w-full text-xs border-collapse">';
                t += '<thead><tr class="text-left text-gray-400 border-b">';
                t += '<th class="py-1 pr-2">Site</th><th class="py-1 pr-2">Type</th><th class="py-1 pr-2">URL</th><th class="py-1 pr-2">Status</th><th class="py-1 pr-2">Captured</th>';
                t += '<th class="py-1">Action</th>';
                t += '</tr></thead><tbody>';

                items.forEach(function (c) {
                    var shortUrl = (c.source_url || '').length > 45 ? c.source_url.substring(0, 45) + '...' : c.source_url;
                    var capturedAt = c.captured_at ? c.captured_at.substring(0, 16).replace('T', ' ') : '';
                    var statusBadge = c.parse_status === 'parsed'
                        ? '<span class="px-1 py-0.5 rounded bg-green-50 text-green-700" data-capture-status>parsed</span>'
                        : '<span class="px-1 py-0.5 rounded bg-yellow-50 text-yellow-700" data-capture-status>' + esc(c.parse_status || 'unknown') + '</span>';
                    t += '<tr class="border-b border-gray-50" data-capture-id="' + c.id + '">';
                    t += '<td class="py-1.5 pr-2 text-gray-600">' + esc(c.source_site || '') + '</td>';
                    t += '<td class="py-1.5 pr-2"><span class="px-1 py-0.5 rounded bg-blue-50 text-blue-700">' + esc(c.page_type) + '</span></td>';
                    t += '<td class="py-1.5 pr-2"><a href="' + esc(c.source_url) + '" target="_blank" class="text-indigo-600 hover:underline">' + esc(shortUrl) + '</a></td>';
                    t += '<td class="py-1.5 pr-2">' + statusBadge + '</td>';
                    t += '<td class="py-1.5 pr-2 text-gray-500">' + capturedAt + '</td>';
                    if (showAttach) {
                        t += '<td class="py-1.5"><button class="px-2 py-0.5 bg-green-600 text-white rounded hover:bg-green-700 text-xs" onclick="attachCapture(' + c.id + ')">Attach</button></td>';
                    } else {
                        t += '<td class="py-1.5 text-gray-500">' + (c.html_bytes ? Number(c.html_bytes).toLocaleString() + 'b' : '-') + '</td>';
                    }
                    t += '</tr>';
                });

                t += '</tbody></table>';
                return t;
            }

            window.attachCapture = function (captureId) {
                var attachUrl = '/presentations/' + presentationId + '/portal-captures/' + captureId + '/attach';
                fetch(attachUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) loadCaptures();
                    else alert('Failed to attach capture');
                })
                .catch(function () { alert('Error attaching capture'); });
            };

            refreshBtn.addEventListener('click', loadCaptures);
            loadCaptures();
        })();
        </script>
        </div>
    </details>
    </div>
    <?php endif; ?>

    
    <div class="mt-6 bg-white rounded-xl shadow p-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Documents</h2>

        <?php
            $docTypeLabels = [
                'suburb_stats'   => 'Suburb Stats',
                'vicinity_sales' => 'Vicinity Sales',
                'cma'            => 'CMA',
                'market_article' => 'Market Article',
                'other'          => 'Other',
            ];
        ?>

        <?php if($presentation->uploads->isEmpty()): ?>
            <p class="text-xs text-gray-400 italic mb-3">No documents uploaded yet.</p>
        <?php else: ?>
            <ul class="space-y-3 mb-4 text-xs text-gray-600">
                <?php $__currentLoopData = $presentation->uploads; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $upload): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <li class="border border-gray-100 rounded-lg p-2">
                        
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-center gap-2 min-w-0 flex-wrap">
                                <span class="text-gray-400 shrink-0">📄</span>
                                <span class="truncate"><?php echo e($upload->original_filename ?? basename($upload->file_path)); ?></span>
                                <span class="inline-block px-1.5 py-0.5 rounded text-xs font-medium
                                    <?php echo e(isset($docTypeLabels[$upload->type]) && $upload->type !== 'other' ? 'bg-indigo-50 text-indigo-600' : 'bg-gray-100 text-gray-500'); ?>">
                                    <?php echo e($docTypeLabels[$upload->type] ?? $upload->type); ?>

                                </span>

                                
                                <?php
                                    $uExtStatus = $upload->extraction_status ?? 'pending';
                                    $uExtBadge = match($uExtStatus) {
                                        'ok'     => 'bg-green-100 text-green-700',
                                        'failed' => 'bg-red-100 text-red-600',
                                        default  => 'bg-yellow-100 text-yellow-700',
                                    };
                                    $uExtLabel = match($uExtStatus) {
                                        'ok'     => 'Extracted',
                                        'failed' => 'Failed',
                                        default  => 'Pending',
                                    };
                                ?>
                                <span class="inline-block px-1.5 py-0.5 rounded text-xs font-medium <?php echo e($uExtBadge); ?>">
                                    <?php echo e($uExtLabel); ?>

                                </span>

                                <form method="POST"
                                      action="<?php echo e(route('presentations.uploads.re-extract', [$presentation, $upload])); ?>"
                                      class="inline">
                                    <?php echo csrf_field(); ?>
                                    <button type="submit"
                                            class="inline-block px-1 py-0.5 text-xs text-indigo-500 hover:text-indigo-700"
                                            title="Re-run extraction">&#x27F3;</button>
                                </form>

                                <?php if($upload->isOverridden()): ?>
                                    <span class="inline-block px-1.5 py-0.5 rounded text-xs font-medium bg-orange-100 text-orange-700">
                                        Override
                                    </span>
                                <?php endif; ?>
                            </div>
                            <form method="POST"
                                  action="<?php echo e(route('presentations.uploads.update-type', [$presentation, $upload])); ?>"
                                  class="flex items-center gap-1 shrink-0">
                                <?php echo csrf_field(); ?>
                                <?php echo method_field('PATCH'); ?>
                                <select name="type" class="border border-gray-200 rounded px-1 py-0.5 text-xs">
                                    <?php $__currentLoopData = $docTypeLabels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($val); ?>" <?php echo e($upload->type === $val ? 'selected' : ''); ?>><?php echo e($label); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                                <button type="submit"
                                        class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">Save</button>
                            </form>
                        </div>

                        
                        <?php
                            $uVerified  = $upload->getVerifiedData();
                            $uAgg       = $uVerified['aggregates'] ?? [];
                            $uCounts    = $uVerified['parsed_counts'] ?? [];
                            $uFields    = $uVerified['fields'] ?? [];
                            $hasDocExtract = !empty($uFields) && ($uVerified['extracted_version'] ?? '') === 'doc_extract_v1';
                        ?>

                        <?php if($hasDocExtract && $upload->type === 'cma'): ?>
                            
                            <div class="mt-2 bg-blue-50 rounded-lg px-3 py-2 text-xs text-gray-700 space-y-1">
                                <div class="font-semibold text-indigo-700">CMA Valuation Summary</div>
                                <?php if(isset($uFields['cma.lower_range']) || isset($uFields['cma.middle_range']) || isset($uFields['cma.upper_range'])): ?>
                                    <div>
                                        <span class="text-gray-500">Price Range:</span>
                                        <?php if(isset($uFields['cma.lower_range'])): ?> R<?php echo e(number_format((int)$uFields['cma.lower_range'])); ?> <?php endif; ?>
                                        <?php if(isset($uFields['cma.middle_range'])): ?> &ndash; <span class="font-medium">R<?php echo e(number_format((int)$uFields['cma.middle_range'])); ?></span> <?php endif; ?>
                                        <?php if(isset($uFields['cma.upper_range'])): ?> &ndash; R<?php echo e(number_format((int)$uFields['cma.upper_range'])); ?> <?php endif; ?>
                                    </div>
                                    <div class="text-[10px] text-gray-400 -mt-0.5">Lower &ndash; Middle &ndash; Upper</div>
                                <?php endif; ?>
                                <?php if(isset($uFields['municipal.total_value'])): ?>
                                    <div>
                                        <span class="text-gray-500">Municipal:</span>
                                        R<?php echo e(number_format((int)$uFields['municipal.total_value'])); ?>

                                        <?php if(isset($uFields['municipal.valuation_year'])): ?>
                                            <span class="text-gray-400">(<?php echo e($uFields['municipal.valuation_year']); ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if(isset($uFields['subject.address'])): ?>
                                    <div><?php echo e($uFields['subject.address']); ?><?php if(isset($uFields['subject.suburb'])): ?>, <?php echo e($uFields['subject.suburb']); ?><?php endif; ?></div>
                                <?php endif; ?>
                                <?php
                                    $subjectParts = [];
                                    if (isset($uFields['subject.erf'])) $subjectParts[] = 'Erf ' . $uFields['subject.erf'];
                                    if (isset($uFields['subject.extent_m2'])) $subjectParts[] = number_format((int)$uFields['subject.extent_m2']) . ' m²';
                                ?>
                                <?php if(!empty($subjectParts)): ?>
                                    <div class="text-gray-500"><?php echo e(implode(' | ', $subjectParts)); ?></div>
                                <?php endif; ?>
                                <?php if(isset($uFields['subject.purchase_price'])): ?>
                                    <div class="text-gray-500">
                                        Purchased<?php echo e(isset($uFields['subject.purchase_date']) ? ': ' . $uFields['subject.purchase_date'] : ''); ?>

                                        for R<?php echo e(number_format((int)$uFields['subject.purchase_price'])); ?>

                                        <?php if(isset($uFields['subject.indexed_value'])): ?>
                                            | Indexed: R<?php echo e(number_format((int)$uFields['subject.indexed_value'])); ?>

                                        <?php endif; ?>
                                        <?php if(isset($uFields['subject.cagr'])): ?>
                                            | CAGR: <?php echo e($uFields['subject.cagr']); ?>%
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                        <?php elseif($hasDocExtract && $upload->type === 'suburb_stats'): ?>
                            
                            <div class="mt-2 bg-blue-50 rounded-lg px-3 py-2 text-xs text-gray-700 space-y-1">
                                <div class="font-semibold text-indigo-700">
                                    Suburb Sales Summary
                                    <?php if(isset($uFields['suburb.latest_year'])): ?>
                                        <span class="font-normal text-gray-400">(<?php echo e($uFields['suburb.latest_year']); ?>)</span>
                                    <?php endif; ?>
                                </div>
                                <?php if(isset($uFields['suburb.latest_median_price'])): ?>
                                    <div>
                                        <span class="text-gray-500">Median:</span>
                                        <span class="font-medium">R<?php echo e(number_format((int)$uFields['suburb.latest_median_price'])); ?></span>
                                        <?php if(isset($uFields['suburb.latest_sales_count'])): ?>
                                            | <span class="text-gray-500">Sales:</span> <?php echo e($uFields['suburb.latest_sales_count']); ?>

                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if(isset($uFields['suburb.latest_low']) && isset($uFields['suburb.latest_high'])): ?>
                                    <div>
                                        <span class="text-gray-500">Range:</span>
                                        R<?php echo e(number_format((int)$uFields['suburb.latest_low'])); ?>

                                        &ndash; R<?php echo e(number_format((int)$uFields['suburb.latest_high'])); ?>

                                    </div>
                                <?php endif; ?>
                            </div>

                        <?php elseif($hasDocExtract && $upload->type === 'vicinity_sales'): ?>
                            
                            <div class="mt-2 bg-blue-50 rounded-lg px-3 py-2 text-xs text-gray-700 space-y-1">
                                <div class="font-semibold text-indigo-700">Vicinity Sales Summary</div>
                                <?php if(isset($uFields['vicinity.lower_range']) || isset($uFields['vicinity.middle_range']) || isset($uFields['vicinity.upper_range'])): ?>
                                    <div>
                                        <span class="text-gray-500">Price Range:</span>
                                        <?php if(isset($uFields['vicinity.lower_range'])): ?> R<?php echo e(number_format((int)$uFields['vicinity.lower_range'])); ?> <?php endif; ?>
                                        <?php if(isset($uFields['vicinity.middle_range'])): ?> &ndash; <span class="font-medium">R<?php echo e(number_format((int)$uFields['vicinity.middle_range'])); ?></span> <?php endif; ?>
                                        <?php if(isset($uFields['vicinity.upper_range'])): ?> &ndash; R<?php echo e(number_format((int)$uFields['vicinity.upper_range'])); ?> <?php endif; ?>
                                    </div>
                                    <div class="text-[10px] text-gray-400 -mt-0.5">Lower &ndash; Middle &ndash; Upper</div>
                                <?php endif; ?>
                                <?php
                                    $vicParts = [];
                                    if (isset($uFields['vicinity.average_price'])) $vicParts[] = 'Avg: R' . number_format((int)$uFields['vicinity.average_price']);
                                    if (isset($uFields['vicinity.avg_price_per_m2'])) $vicParts[] = 'Avg R/m²: R' . number_format((int)$uFields['vicinity.avg_price_per_m2']);
                                    if (isset($uFields['vicinity.comps_count'])) $vicParts[] = 'Comps: ' . $uFields['vicinity.comps_count'];
                                ?>
                                <?php if(!empty($vicParts)): ?>
                                    <div><?php echo e(implode(' | ', $vicParts)); ?></div>
                                <?php endif; ?>
                            </div>

                        <?php elseif($uVerified && ($upload->type === 'suburb_stats') && !empty($uAgg)): ?>
                            
                            <?php
                                $uParts = [];
                                if (!empty($uAgg['active_listings_count'])) $uParts[] = 'Active: ' . $uAgg['active_listings_count'];
                                if (!empty($uAgg['median_price'])) $uParts[] = 'Median: R' . number_format($uAgg['median_price'], 0);
                                if (!empty($uAgg['average_price'])) $uParts[] = 'Avg: R' . number_format($uAgg['average_price'], 0);
                                if (!empty($uAgg['dom_p50'])) $uParts[] = 'DOM: ' . $uAgg['dom_p50'];
                                if (!empty($uAgg['months_of_inventory'])) $uParts[] = 'MOI: ' . $uAgg['months_of_inventory'];
                                if (!empty($uCounts['active_listings'])) $uParts[] = 'Rows: ' . $uCounts['active_listings'];
                            ?>
                            <div class="mt-1.5 text-xs text-gray-600 bg-blue-50 rounded px-2 py-1">
                                <?php echo e(implode(' | ', $uParts)); ?>

                            </div>
                        <?php elseif($uVerified && ($upload->type === 'vicinity_sales') && !empty($uAgg)): ?>
                            
                            <?php
                                $uParts = [];
                                if (!empty($uAgg['sold_count'])) $uParts[] = 'Sold: ' . $uAgg['sold_count'];
                                if (!empty($uAgg['median_price'])) $uParts[] = 'Median: R' . number_format($uAgg['median_price'], 0);
                                if (!empty($uAgg['average_price'])) $uParts[] = 'Avg: R' . number_format($uAgg['average_price'], 0);
                                if (!empty($uAgg['dom_p50'])) $uParts[] = 'DOM: ' . $uAgg['dom_p50'];
                                if (!empty($uAgg['price_range_low']) && !empty($uAgg['price_range_high'])) {
                                    $uParts[] = 'Range: R' . number_format($uAgg['price_range_low'], 0) . '–R' . number_format($uAgg['price_range_high'], 0);
                                }
                                if (!empty($uCounts['sold_comps'])) $uParts[] = 'Rows: ' . $uCounts['sold_comps'];
                            ?>
                            <div class="mt-1.5 text-xs text-gray-600 bg-blue-50 rounded px-2 py-1">
                                <?php echo e(implode(' | ', $uParts)); ?>

                            </div>
                        <?php elseif($uVerified && ($upload->type === 'cma') && !empty($uVerified['suggested_band'])): ?>
                            
                            <?php
                                $band = $uVerified['suggested_band'];
                            ?>
                            <div class="mt-1.5 text-xs text-gray-600 bg-blue-50 rounded px-2 py-1">
                                Band: R<?php echo e(number_format($band['low'], 0)); ?> – R<?php echo e(number_format($band['high'], 0)); ?>

                                <?php if(!empty($uVerified['notes'])): ?>
                                    <?php $__currentLoopData = $uVerified['notes']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $note): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        | <?php echo e(str_replace('suggested_value:', 'Suggested: R', $note)); ?>

                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                <?php endif; ?>
                            </div>
                        <?php elseif($uVerified && !empty($uCounts)): ?>
                            
                            <div class="mt-1.5 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-gray-500">
                                <?php $__currentLoopData = $uCounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pcKey => $pcVal): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <span>
                                        <span class="text-gray-400"><?php echo e(str_replace('_', ' ', $pcKey)); ?>:</span>
                                        <?php echo e($pcVal); ?>

                                    </span>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </div>
                        <?php endif; ?>
                        <?php if($uExtStatus === 'failed'): ?>
                            <div class="mt-1.5 bg-red-50 border border-red-200 rounded px-2 py-1.5 text-xs text-red-700">
                                No data extracted — <?php echo e($upload->extraction_error ?? 'check PDF format'); ?>

                            </div>
                        <?php endif; ?>

                        
                        <?php if($upload->isOverridden()): ?>
                            <p class="mt-1 text-xs text-orange-500">
                                Overridden <?php echo e($upload->override_at ? $upload->override_at->format('Y-m-d H:i') : ''); ?>

                                <?php if($upload->override_by_user_id): ?>
                                    by user #<?php echo e($upload->override_by_user_id); ?>

                                <?php endif; ?>
                            </p>
                        <?php endif; ?>

                        
                            <details class="mt-1.5">
                                <summary class="text-xs text-indigo-500 cursor-pointer hover:underline">
                                    <?php echo e($upload->isOverridden() ? 'Edit override' : 'Details'); ?>

                                </summary>
                                <div class="mt-2 space-y-2">

                                    
                                    <?php if($hasDocExtract): ?>
                                        <div class="bg-white border border-gray-100 rounded p-2">
                                            <p class="text-xs font-medium text-gray-500 mb-1">Extracted Fields <span class="text-gray-300">(<?php echo e($uVerified['extracted_version'] ?? ''); ?>)</span></p>
                                            <div class="grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5 text-xs">
                                                <?php $__currentLoopData = $uFields; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fk => $fv): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <span class="text-gray-400"><?php echo e($fk); ?></span>
                                                    <span class="text-gray-700">
                                                        <?php if(is_numeric($fv) && (int)$fv >= 10000): ?>
                                                            R<?php echo e(number_format((int)$fv)); ?>

                                                        <?php else: ?>
                                                            <?php echo e($fv); ?>

                                                        <?php endif; ?>
                                                    </span>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    
                                    <details class="text-xs">
                                        <summary class="text-gray-400 cursor-pointer hover:underline">Diagnostics</summary>
                                        <div class="mt-1 space-y-1">
                                            <?php if($upload->extraction_json): ?>
                                                <div class="bg-gray-50 rounded p-2 font-mono text-gray-600 overflow-x-auto max-h-40 overflow-y-auto">
                                                    <pre><?php echo e(json_encode($upload->extraction_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)); ?></pre>
                                                </div>
                                            <?php endif; ?>
                                            <?php if($upload->text_extracted): ?>
                                                <div class="bg-gray-50 rounded p-2 font-mono text-gray-500 overflow-x-auto max-h-24 overflow-y-auto">
                                                    <pre><?php echo e(Illuminate\Support\Str::limit($upload->text_extracted, 500)); ?></pre>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </details>

                                    
                                    <form method="POST"
                                          action="<?php echo e(route('presentations.uploads.override', [$presentation, $upload])); ?>"
                                          class="border border-orange-200 rounded p-2 bg-orange-50">
                                        <?php echo csrf_field(); ?>
                                        <?php echo method_field('PATCH'); ?>
                                        <p class="text-xs font-medium text-orange-700 mb-1.5">Override values</p>
                                        <?php
                                            $uOverrideSource = $upload->override_json ?? [];
                                            $uAggPrefill = $uVerified['aggregates'] ?? [];
                                            $uOverride = !empty($uOverrideSource) ? $uOverrideSource : $uAggPrefill;
                                            $uFieldDefs = match($upload->type) {
                                                'suburb_stats' => [
                                                    'active_listings_count' => 'Active listings',
                                                    'median_price' => 'Median price',
                                                    'average_price' => 'Average price',
                                                    'dom_p50' => 'DOM p50',
                                                    'months_of_inventory' => 'Months of inventory',
                                                ],
                                                'vicinity_sales' => [
                                                    'sold_count' => 'Sold count',
                                                    'median_price' => 'Median price',
                                                    'average_price' => 'Average price',
                                                    'dom_p50' => 'DOM p50',
                                                ],
                                                'cma' => [
                                                    'suggested_price_low' => 'Price low',
                                                    'suggested_price_high' => 'Price high',
                                                    'comps_count' => 'Comps count',
                                                ],
                                                default => [
                                                    'notes' => 'Notes',
                                                ],
                                            };
                                        ?>
                                        <div class="grid grid-cols-2 gap-1.5">
                                            <?php $__currentLoopData = $uFieldDefs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $fKey => $fLabel): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                <div>
                                                    <label class="block text-xs text-gray-400"><?php echo e($fLabel); ?></label>
                                                    <input type="text" name="override_data[<?php echo e($fKey); ?>]"
                                                           placeholder="<?php echo e($fLabel); ?>"
                                                           value="<?php echo e($uOverride[$fKey] ?? ''); ?>"
                                                           class="w-full border border-gray-200 rounded px-2 py-1 text-xs">
                                                </div>
                                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                        </div>
                                        <div class="flex gap-2 mt-1.5">
                                            <button type="submit"
                                                    class="px-2 py-1 bg-orange-600 text-white text-xs rounded hover:bg-orange-700">
                                                Save Override
                                            </button>
                                        </div>
                                    </form>
                                    <?php if($upload->isOverridden()): ?>
                                        <form method="POST"
                                              action="<?php echo e(route('presentations.uploads.override.clear', [$presentation, $upload])); ?>"
                                              class="mt-1">
                                            <?php echo csrf_field(); ?>
                                            <?php echo method_field('DELETE'); ?>
                                            <button type="submit"
                                                    class="px-2 py-1 text-xs text-gray-500 hover:text-red-600"
                                                    onclick="return confirm('Clear this override?')">
                                                Clear Override
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </details>
                    </li>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </ul>
        <?php endif; ?>

        <form method="POST" action="<?php echo e(route('presentations.upload', $presentation)); ?>"
              enctype="multipart/form-data" class="space-y-2">
            <?php echo csrf_field(); ?>
            <div class="flex gap-2 items-center">
                <select name="doc_type" class="border border-gray-300 rounded px-2 py-1.5 text-xs" required>
                    <option value="" disabled selected>Document type...</option>
                    <?php $__currentLoopData = $docTypeLabels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($val); ?>"><?php echo e($label); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
                <input type="file" name="documents[]" multiple
                       class="flex-1 text-xs text-gray-600 border border-gray-300 rounded px-2 py-1.5" required>
                <button type="submit"
                        class="px-3 py-1.5 bg-gray-600 text-white text-xs font-medium rounded hover:bg-gray-700 shrink-0">
                    Upload
                </button>
            </div>
            <?php $__errorArgs = ['doc_type'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                <p class="mt-1 text-xs text-red-600"><?php echo e($message); ?></p>
            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            <?php $__errorArgs = ['documents'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                <p class="mt-1 text-xs text-red-600"><?php echo e($message); ?></p>
            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            <?php $__errorArgs = ['documents.*'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                <p class="mt-1 text-xs text-red-600"><?php echo e($message); ?></p>
            <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        </form>

        
        <?php if(config('features.document_library_v1')): ?>
            <div class="mt-3 pt-3 border-t border-gray-100">
                <a href="<?php echo e(route('documents.library.index', ['presentation_id' => $presentation->id, 'return' => url()->current() . '#documents'])); ?>"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-indigo-50 text-indigo-700 text-xs font-medium rounded hover:bg-indigo-100 border border-indigo-200">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    Document Library
                </a>
            </div>

            
            <?php
                $libraryDocs = $presentation->documentLibraryItems()->with('uploader')->get();
            ?>
            <?php if($libraryDocs->isNotEmpty()): ?>
                <div class="mt-4 pt-3 border-t border-gray-100">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Attached from Library</h3>
                    <ul class="space-y-2 text-xs text-gray-600">
                        <?php $__currentLoopData = $libraryDocs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $libDoc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li class="flex items-center justify-between border border-gray-100 rounded-lg px-2 py-1.5">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="text-gray-400 shrink-0">&#128206;</span>
                                    <span class="truncate"><?php echo e($libDoc->title ?? $libDoc->original_name); ?></span>
                                    <span class="inline-block px-1.5 py-0.5 rounded text-xs font-medium bg-indigo-50 text-indigo-600">
                                        <?php echo e($libDoc->doc_type); ?>

                                    </span>
                                    <span class="text-gray-400"><?php echo e($libDoc->uploader->name ?? ''); ?></span>
                                    <span class="text-gray-400"><?php echo e($libDoc->pivot->created_at ? \Carbon\Carbon::parse($libDoc->pivot->created_at)->format('d M Y') : ''); ?></span>
                                </div>
                                <a href="<?php echo e(route('documents.library.download', $libDoc)); ?>"
                                   class="text-indigo-600 hover:text-indigo-800 font-medium shrink-0 ml-2">
                                    Download
                                </a>
                            </li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>


<div class="mt-6">
    <div class="bg-white rounded-xl shadow p-5">
        <h2 class="text-sm font-semibold text-gray-700 mb-3">Holding Cost Inputs (monthly, ZAR)</h2>

        <form method="POST" action="<?php echo e(route('presentations.holding-cost.update', $presentation)); ?>" class="space-y-3">
            <?php echo csrf_field(); ?>
            <?php echo method_field('PATCH'); ?>

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Bond payment</label>
                    <input type="number" name="monthly_bond" min="0" step="0.01"
                           value="<?php echo e($presentation->monthly_bond ?? ''); ?>"
                           placeholder="0"
                           class="w-full border border-gray-300 rounded px-2 py-1.5 text-xs">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Rates</label>
                    <input type="number" name="monthly_rates" min="0" step="0.01"
                           value="<?php echo e($presentation->monthly_rates ?? ''); ?>"
                           placeholder="0"
                           class="w-full border border-gray-300 rounded px-2 py-1.5 text-xs">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Levies</label>
                    <input type="number" name="monthly_levies" min="0" step="0.01"
                           value="<?php echo e($presentation->monthly_levies ?? ''); ?>"
                           placeholder="0"
                           class="w-full border border-gray-300 rounded px-2 py-1.5 text-xs">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Insurance</label>
                    <input type="number" name="monthly_insurance" min="0" step="0.01"
                           value="<?php echo e($presentation->monthly_insurance ?? ''); ?>"
                           placeholder="0"
                           class="w-full border border-gray-300 rounded px-2 py-1.5 text-xs">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Utilities</label>
                    <input type="number" name="monthly_utilities" min="0" step="0.01"
                           value="<?php echo e($presentation->monthly_utilities ?? ''); ?>"
                           placeholder="0"
                           class="w-full border border-gray-300 rounded px-2 py-1.5 text-xs">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Opportunity cost</label>
                    <input type="number" name="monthly_opportunity_cost" min="0" step="0.01"
                           value="<?php echo e($presentation->monthly_opportunity_cost ?? ''); ?>"
                           placeholder="0"
                           class="w-full border border-gray-300 rounded px-2 py-1.5 text-xs">
                </div>
            </div>

            <div class="flex items-center gap-3 pt-1">
                <button type="submit"
                        class="px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded hover:bg-indigo-700">
                    Save Holding Cost
                </button>
                <?php
                    $hcTotal = collect([
                        $presentation->monthly_bond,
                        $presentation->monthly_rates,
                        $presentation->monthly_levies,
                        $presentation->monthly_insurance,
                        $presentation->monthly_utilities,
                        $presentation->monthly_opportunity_cost,
                    ])->sum();
                ?>
                <?php if($hcTotal > 0): ?>
                    <span class="text-xs text-gray-500">
                        Monthly total: R<?php echo e(number_format($hcTotal, 0)); ?>

                    </span>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>


<?php if(config('features.presentation_live_updates_v1') && config('features.portal_extension_capture_v1')): ?>

<div id="live-new-captures-banner" class="hidden fixed bottom-4 right-4 z-50 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg shadow-lg cursor-pointer hover:bg-indigo-700 transition-colors"
     onclick="window.__liveUpdates && window.__liveUpdates.scrollToCaptures()">
    <span id="live-banner-text">0 new captures</span>
</div>


<div id="live-debug-indicator" class="hidden fixed top-2 right-2 z-50 bg-gray-900 text-green-400 text-xs font-mono rounded-lg shadow-lg px-3 py-2 max-w-xs opacity-90">
    <div>Live: <span id="ldi-status">OFF</span></div>
    <div>Last poll: <span id="ldi-poll-time">-</span></div>
    <div>HTTP: <span id="ldi-http-status">-</span></div>
    <div>New: <span id="ldi-new-captures">0</span> | Upd: <span id="ldi-updated-captures">0</span> | Links: <span id="ldi-updated-links">0</span></div>
    <div id="ldi-error" class="text-red-400 hidden"></div>
</div>

<script>
(function () {
    'use strict';

    // ── Config ──────────────────────────────────────────────────────────
    var POLL_ACTIVE_MS   = 2000;   // 2s when tab visible
    var POLL_HIDDEN_MS   = 10000;  // 10s when tab hidden
    var POLL_URL         = '<?php echo e(route("presentations.live-snapshot", $presentation)); ?>';
    var CSRF_TOKEN       = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    // ── State ───────────────────────────────────────────────────────────
    var lastCaptureId        = <?php echo e($maxCaptureId); ?>;
    var lastLinkUpdatedAt    = null;  // null → first polls omit cursor for wide catch-up
    var lastCaptureUpdatedAt = null;
    var pollCycleCount       = 0;     // tracks poll cycles; first 2 are "wide catch-up"
    var pollTimer            = null;
    var pendingNewCaptures   = 0;
    var isCapturesSectionVisible = false;

    // ── DOM refs ────────────────────────────────────────────────────────
    var capturesContainer = document.getElementById('captures-container');
    var banner            = document.getElementById('live-new-captures-banner');
    var bannerText        = document.getElementById('live-banner-text');

    // ── Helpers ─────────────────────────────────────────────────────────
    function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    function isCapturesInView() {
        if (!capturesContainer) return false;
        var rect = capturesContainer.getBoundingClientRect();
        return rect.top < window.innerHeight && rect.bottom > 0;
    }

    function showBanner(count) {
        pendingNewCaptures = count;
        if (count > 0 && !isCapturesInView()) {
            bannerText.textContent = count + ' new capture' + (count > 1 ? 's' : '');
            banner.classList.remove('hidden');
        } else {
            banner.classList.add('hidden');
            pendingNewCaptures = 0;
        }
    }

    function scrollToCaptures() {
        if (capturesContainer) {
            capturesContainer.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        banner.classList.add('hidden');
        pendingNewCaptures = 0;
    }

    // ── In-place link badge update ──────────────────────────────────────
    function updateLinkBadge(linkData) {
        var badgeEl = document.querySelector('[data-link-badge="' + linkData.id + '"]');
        if (!badgeEl) return;

        if (linkData.portal_capture_id) {
            badgeEl.className = 'inline-block px-1.5 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-700';
            badgeEl.textContent = 'Captured';
        } else {
            var statusMap = {
                'ok':      { cls: 'bg-green-100 text-green-700', label: 'Extracted' },
                'failed':  { cls: 'bg-red-100 text-red-600',     label: 'Failed' },
                'pending': { cls: 'bg-yellow-100 text-yellow-700', label: 'Pending' },
            };
            var st = statusMap[linkData.extraction_status] || statusMap['pending'];
            badgeEl.className = 'inline-block px-1.5 py-0.5 rounded text-xs font-medium ' + st.cls;
            badgeEl.textContent = st.label;
        }

        // Price change indicator
        if (linkData.price_change_indicator) {
            var priceEl = document.querySelector('[data-price-change="' + linkData.id + '"]');
            if (priceEl) {
                priceEl.classList.remove('hidden');
            }
        }
    }

    // ── In-place capture status update ─────────────────────────────────
    function updateCaptureRow(c) {
        var row = capturesContainer ? capturesContainer.querySelector('[data-capture-id="' + c.id + '"]') : null;
        if (!row) return;

        var statusEl = row.querySelector('[data-capture-status]');
        if (statusEl) {
            if (c.parse_status === 'parsed') {
                statusEl.className = 'px-1 py-0.5 rounded bg-green-50 text-green-700';
                statusEl.textContent = 'parsed';
            } else {
                statusEl.className = 'px-1 py-0.5 rounded bg-yellow-50 text-yellow-700';
                statusEl.textContent = c.parse_status || 'unknown';
            }
        }

        // Flash highlight
        row.style.backgroundColor = '#fef9c3';
        setTimeout(function () {
            row.style.transition = 'background-color 2s';
            row.style.backgroundColor = '';
        }, 50);
    }

    // ── Capture card builder ────────────────────────────────────────────
    function buildCaptureRow(c) {
        var shortUrl = (c.source_url || '').length > 45
            ? c.source_url.substring(0, 45) + '...'
            : c.source_url;
        var capturedAt = c.captured_at ? c.captured_at.substring(0, 16).replace('T', ' ') : '';
        var statusBadge = c.parse_status === 'parsed'
            ? '<span class="px-1 py-0.5 rounded bg-green-50 text-green-700" data-capture-status>parsed</span>'
            : '<span class="px-1 py-0.5 rounded bg-yellow-50 text-yellow-700" data-capture-status>' + esc(c.parse_status || 'unknown') + '</span>';

        var row = '<tr class="border-b border-gray-50 live-capture-new" data-capture-id="' + c.id + '">';
        row += '<td class="py-1.5 pr-2 text-gray-600">' + esc(c.source_site || '') + '</td>';
        row += '<td class="py-1.5 pr-2"><span class="px-1 py-0.5 rounded bg-blue-50 text-blue-700">' + esc(c.page_type) + '</span></td>';
        row += '<td class="py-1.5 pr-2"><a href="' + esc(c.source_url) + '" target="_blank" class="text-indigo-600 hover:underline">' + esc(shortUrl) + '</a></td>';
        row += '<td class="py-1.5 pr-2">' + statusBadge + '</td>';
        row += '<td class="py-1.5 pr-2 text-gray-500">' + capturedAt + '</td>';
        row += '<td class="py-1.5 text-gray-500">' + (c.html_bytes ? Number(c.html_bytes).toLocaleString() + 'b' : '-') + '</td>';

        // Price change indicator
        if (c.price_change_count > 0) {
            row += '</tr><tr class="border-b border-gray-50"><td colspan="6"><div class="bg-amber-50 border border-amber-300 rounded px-2 py-1 text-xs text-amber-800 font-medium">';
            row += 'Price Change Detected — ' + c.price_change_count + ' listing' + (c.price_change_count > 1 ? 's' : '') + ' changed';
            row += '</div></td></tr>';
        } else {
            row += '</tr>';
        }

        return row;
    }

    // ── Inject new captures into existing table ─────────────────────────
    function injectCaptures(captures) {
        if (!captures || captures.length === 0) return;

        // Find the "Attached" table body
        var tbody = capturesContainer.querySelector('table tbody');
        if (!tbody) {
            // Captures section might not have loaded yet or is empty — trigger a full reload
            if (typeof window.loadCaptures === 'function') window.loadCaptures();
            return;
        }

        // Prepend rows (newest first, so reverse the array which came oldest-first)
        var reversed = captures.slice().reverse();
        for (var i = 0; i < reversed.length; i++) {
            var c = reversed[i];
            // Skip if already in DOM
            if (tbody.querySelector('[data-capture-id="' + c.id + '"]')) continue;

            var temp = document.createElement('template');
            temp.innerHTML = buildCaptureRow(c);
            var newRow = temp.content.firstChild;

            // Flash animation
            newRow.style.backgroundColor = '#eef2ff';
            tbody.insertBefore(newRow, tbody.firstChild);

            // Also insert price-change row if present
            if (temp.content.firstChild) {
                tbody.insertBefore(temp.content.firstChild, newRow.nextSibling);
            }

            // Fade out highlight
            setTimeout(function (el) {
                el.style.transition = 'background-color 2s';
                el.style.backgroundColor = '';
            }.bind(null, newRow), 50);
        }
    }

    // ── Debug indicator refs ────────────────────────────────────────────
    var debugPanel     = document.getElementById('live-debug-indicator');
    var ldiStatus      = document.getElementById('ldi-status');
    var ldiPollTime    = document.getElementById('ldi-poll-time');
    var ldiHttpStatus  = document.getElementById('ldi-http-status');
    var ldiNewCap      = document.getElementById('ldi-new-captures');
    var ldiUpdCap      = document.getElementById('ldi-updated-captures');
    var ldiUpdLinks    = document.getElementById('ldi-updated-links');
    var ldiError       = document.getElementById('ldi-error');
    var isFirstPoll    = true;

    function updateDebugPanel(httpStatus, data, error) {
        if (!window.PRESENTATIONS_LIVE_DEBUG) {
            if (debugPanel) debugPanel.classList.add('hidden');
            return;
        }
        if (debugPanel) debugPanel.classList.remove('hidden');
        ldiStatus.textContent = 'ON';
        ldiPollTime.textContent = new Date().toLocaleTimeString();
        ldiHttpStatus.textContent = httpStatus || '-';
        if (data) {
            ldiNewCap.textContent = (data.counts || {}).new_captures || 0;
            ldiUpdCap.textContent = (data.counts || {}).updated_captures || 0;
            ldiUpdLinks.textContent = (data.counts || {}).updated_links || 0;
        }
        if (error) {
            ldiError.textContent = error;
            ldiError.classList.remove('hidden');
        } else {
            ldiError.classList.add('hidden');
        }
    }

    // ── Poll ────────────────────────────────────────────────────────────
    function poll() {
        pollCycleCount++;

        // Build poll URL — omit cursor params during first 2 cycles (wide catch-up)
        var url = POLL_URL + '?after_capture_id=' + lastCaptureId;
        if (pollCycleCount > 2 && lastLinkUpdatedAt) {
            url += '&after_link_updated_at=' + encodeURIComponent(lastLinkUpdatedAt);
        }
        if (pollCycleCount > 2 && lastCaptureUpdatedAt) {
            url += '&after_capture_updated_at=' + encodeURIComponent(lastCaptureUpdatedAt);
        }

        // Include debug=1 on first poll if debug mode is on
        if (window.PRESENTATIONS_LIVE_DEBUG && isFirstPoll) {
            url += '&debug=1';
        }
        isFirstPoll = false;

        if (window.PRESENTATIONS_LIVE_DEBUG) {
            console.log('[LiveUpdates] poll #' + pollCycleCount, {
                url: url,
                cursors: {
                    lastCaptureId: lastCaptureId,
                    lastLinkUpdatedAt: lastLinkUpdatedAt,
                    lastCaptureUpdatedAt: lastCaptureUpdatedAt,
                },
                wideCatchUp: pollCycleCount <= 2,
            });
        }

        fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            credentials: 'same-origin'
        })
        .then(function (r) {
            var status = r.status;
            if (!r.ok) {
                console.error('[LiveUpdates] HTTP error', status);
                updateDebugPanel(status, null, 'HTTP ' + status);
                throw new Error('HTTP ' + status);
            }
            return r.json().then(function (d) { return { status: status, data: d }; });
        })
        .then(function (result) {
            var data = result.data;
            if (data.enabled === false) return;

            updateDebugPanel(result.status, data, null);

            // Update cursors from server response only
            if (data.latest_capture_id)          lastCaptureId        = data.latest_capture_id;
            if (data.latest_link_updated_at)     lastLinkUpdatedAt    = data.latest_link_updated_at;
            if (data.latest_capture_updated_at)  lastCaptureUpdatedAt = data.latest_capture_updated_at;

            // Debug logging
            if (window.PRESENTATIONS_LIVE_DEBUG) {
                console.log('[LiveUpdates] response', {
                    new_captures: (data.new_captures || []).length,
                    updated_captures: (data.updated_captures || []).length,
                    updated_links: (data.updated_links || []).length,
                    upd_link_ids: (data.updated_links || []).map(function(l) { return l.id; }),
                    latest_link_updated_at: data.latest_link_updated_at,
                    latest_capture_updated_at: data.latest_capture_updated_at,
                    debug: data.debug || null,
                });
            }

            // Inject new captures
            if (data.new_captures && data.new_captures.length > 0) {
                injectCaptures(data.new_captures);
                showBanner(pendingNewCaptures + data.new_captures.length);
            }

            // Update existing capture rows in-place
            if (data.updated_captures && data.updated_captures.length > 0) {
                data.updated_captures.forEach(updateCaptureRow);
            }

            // Update link badges in-place
            if (data.updated_links && data.updated_links.length > 0) {
                data.updated_links.forEach(updateLinkBadge);
            }

            schedulePoll();
        })
        .catch(function (err) {
            console.error('[LiveUpdates] Poll failed:', err.message);
            updateDebugPanel(null, null, err.message);
            // On error, back off and retry
            schedulePoll();
        });
    }

    function schedulePoll() {
        clearTimeout(pollTimer);
        var interval = document.hidden ? POLL_HIDDEN_MS : POLL_ACTIVE_MS;
        pollTimer = setTimeout(poll, interval);
    }

    // ── Visibility change ───────────────────────────────────────────────
    document.addEventListener('visibilitychange', function () {
        clearTimeout(pollTimer);
        if (!document.hidden) {
            // Returning to tab — poll immediately to catch up
            poll();
        } else {
            schedulePoll();
        }
    });

    // Scroll listener to auto-dismiss banner when captures section is visible
    window.addEventListener('scroll', function () {
        if (pendingNewCaptures > 0 && isCapturesInView()) {
            showBanner(0);
        }
    }, { passive: true });

    // ── Start ───────────────────────────────────────────────────────────
    schedulePoll();

    // Public API for banner click
    window.__liveUpdates = { scrollToCaptures: scrollToCaptures };

})();
</script>


<script>
(function () {
    'use strict';
    var STORAGE_KEY = 'pres_show_scroll_<?php echo e($presentation->id); ?>';

    // On page load: restore scroll + focus
    try {
        var saved = sessionStorage.getItem(STORAGE_KEY);
        if (saved) {
            sessionStorage.removeItem(STORAGE_KEY);
            var state = JSON.parse(saved);
            if (state.scrollY) {
                window.scrollTo(0, state.scrollY);
            }
            if (state.focusId) {
                var el = document.getElementById(state.focusId);
                if (el) el.focus();
            } else if (state.focusName) {
                var el2 = document.querySelector('[name="' + state.focusName + '"]');
                if (el2) el2.focus();
            }
        }
    } catch (e) { /* ignore */ }

    // Before form submit: save scroll + focus
    document.addEventListener('submit', function (e) {
        if (!e.target || e.target.tagName !== 'FORM') return;
        // Skip AJAX forms (those with fetch-based handlers)
        if (e.defaultPrevented) return;

        try {
            var focused = document.activeElement;
            var state = { scrollY: window.scrollY };
            if (focused && focused.id) {
                state.focusId = focused.id;
            } else if (focused && focused.name) {
                state.focusName = focused.name;
            }
            sessionStorage.setItem(STORAGE_KEY, JSON.stringify(state));
        } catch (ex) { /* ignore */ }
    });
})();
</script>
<?php endif; ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.nexus', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\johan\OneDrive\Documents\GitHub\hfc-dash\resources\views/presentations/show.blade.php ENDPATH**/ ?>