<?php $__env->startSection('nexus-content'); ?>


<div class="mb-6 flex items-start justify-between">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Market Analysis</h1>
        <p class="text-sm text-gray-500 mt-1">
            <?php echo e($presentation->title); ?>

            <?php if($presentation->property_address): ?>
                &nbsp;·&nbsp; <?php echo e($presentation->property_address); ?>

            <?php endif; ?>
        </p>
        <?php if(isset($latestSnapshot) && $latestSnapshot && $latestSnapshot->generated_at): ?>
            <p class="text-xs text-emerald-600 mt-1 font-medium">
                Last analysed: <?php echo e($latestSnapshot->generated_at->format('d M Y, H:i')); ?>

            </p>
        <?php endif; ?>
    </div>
    <a href="<?php echo e(route('presentations.show', $presentation)); ?>"
       class="text-xs text-indigo-600 hover:underline mt-1">← Overview</a>
</div>


<?php
    $linkCount   = $presentation->links->count();
    $uploadCount = $presentation->uploads->count();
    $lastUpload  = $presentation->uploads->sortByDesc('created_at')->first();
?>
<?php if($linkCount > 0 || $uploadCount > 0): ?>
<div class="mb-4 flex flex-wrap gap-4 text-xs text-gray-500">
    <?php if($linkCount > 0): ?>
        <span>
            <span class="font-medium text-gray-700"><?php echo e($linkCount); ?></span>
            <?php echo e($linkCount === 1 ? 'link' : 'links'); ?> attached
            <a href="<?php echo e(route('presentations.show', $presentation)); ?>#links"
               class="ml-1 text-indigo-500 hover:underline">manage</a>
        </span>
    <?php endif; ?>
    <?php if($uploadCount > 0): ?>
        <span>
            <span class="font-medium text-gray-700"><?php echo e($uploadCount); ?></span>
            <?php echo e($uploadCount === 1 ? 'document' : 'documents'); ?> uploaded
            <?php if($lastUpload): ?>
                <span class="text-gray-400">· last <?php echo e($lastUpload->created_at->format('d M')); ?></span>
            <?php endif; ?>
        </span>
    <?php endif; ?>
</div>
<?php endif; ?>


<div class="bg-white rounded-xl shadow p-6 mb-6">
    <div class="flex items-center justify-between mb-3">
        <h2 class="text-base font-semibold text-gray-700">Run Analysis</h2>
        <?php if(isset($latestSnapshot) && $latestSnapshot && $latestSnapshot->generated_at): ?>
            <span class="text-xs text-emerald-600 font-medium">
                Snapshot saved <?php echo e($latestSnapshot->generated_at->diffForHumans()); ?>

            </span>
        <?php endif; ?>
    </div>
    <form method="POST" action="<?php echo e(route('presentations.analysis.run', $presentation)); ?>">
        <?php echo csrf_field(); ?>
        <div class="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-4">
            <div>
                <label class="block text-xs text-gray-600 mb-1">Asking Price (R)</label>
                <input type="number" name="asking_price_inc"
                       value="<?php echo e($presentation->asking_price_inc ?? ''); ?>"
                       step="1" min="0"
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm"
                       placeholder="e.g. 2500000">
                <p class="mt-0.5 text-xs text-gray-400">Saves to presentation and freezes analysis snapshot.</p>
                <?php $__errorArgs = ['asking_price_inc'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-1 text-xs text-red-600"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
            <div class="flex items-end">
                <button type="submit"
                        class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded hover:bg-indigo-700">
                    <?php if(isset($latestSnapshot) && $latestSnapshot): ?> Re-run Analysis <?php else: ?> Run Analysis <?php endif; ?>
                </button>
            </div>
        </div>

        
        <div class="mt-4 pt-3 border-t grid grid-cols-2 gap-x-8 gap-y-1 text-xs text-gray-500 md:grid-cols-4">
            <div>Suburb: <span class="font-medium text-gray-700"><?php echo e($presentation->suburb ?? '—'); ?></span></div>
            <div>Type: <span class="font-medium text-gray-700"><?php echo e(ucfirst($presentation->property_type ?? '—')); ?></span></div>
            <div>Bedrooms: <span class="font-medium text-gray-700"><?php echo e($presentation->bedrooms ?? '—'); ?></span></div>
            <div>Floor area: <span class="font-medium text-gray-700"><?php echo e($presentation->floor_area_m2 ? $presentation->floor_area_m2 . ' m²' : '—'); ?></span></div>
        </div>
    </form>
</div>


<?php
    $ckSuburb    = !empty($presentation->suburb);
    $ckType      = !empty($presentation->property_type);
    $ckPrice     = !empty($presentation->asking_price_inc);
    $ckFloorArea = !empty($presentation->floor_area_m2);
    $ckSold      = $presentation->soldComps()->count() > 0;
    $ckActive    = $presentation->activeListings()->count() > 0;
?>

<div class="bg-white rounded-xl shadow p-5 mb-6">
    <h2 class="text-sm font-semibold text-gray-700 mb-3">Analysis readiness</h2>
    <div class="grid grid-cols-2 gap-x-8 gap-y-2 text-xs sm:grid-cols-3">
        <?php
        $items = [
            ['label' => 'Suburb', 'ok' => $ckSuburb, 'fix' => 'Set suburb on overview page'],
            ['label' => 'Property type', 'ok' => $ckType, 'fix' => 'Set property type on overview page'],
            ['label' => 'Asking price', 'ok' => $ckPrice, 'fix' => 'Enter asking price above'],
            ['label' => 'Floor area', 'ok' => $ckFloorArea, 'fix' => 'Add floor area on overview page'],
            ['label' => 'Sold comparables', 'ok' => $ckSold, 'fix' => 'Upload CMA/vicinity sales PDF'],
            ['label' => 'Active listings', 'ok' => $ckActive, 'fix' => 'Import active listings via extension'],
        ];
        ?>
        <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="flex items-center gap-2">
                <?php if($item['ok']): ?>
                    <span class="text-emerald-500 font-bold">✓</span>
                    <span class="text-gray-700"><?php echo e($item['label']); ?></span>
                <?php else: ?>
                    <span class="text-gray-300 font-bold">○</span>
                    <span class="text-gray-400"><?php echo e($item['label']); ?>

                        <span class="text-indigo-500"> — <?php echo e($item['fix']); ?></span>
                    </span>
                <?php endif; ?>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
</div>


<?php echo $__env->make('presentations.partials.analysis-data-review', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.nexus', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\johan\OneDrive\Documents\GitHub\hfc-dash\resources\views/presentations/analysis.blade.php ENDPATH**/ ?>