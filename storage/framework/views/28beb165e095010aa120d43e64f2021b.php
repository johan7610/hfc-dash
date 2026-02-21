<?php $__env->startSection('nexus-content'); ?>

<?php
    $docTypeLabels = [
        'suburb_stats'   => 'Suburb Stats',
        'suburb_sales'   => 'Suburb Sales',
        'vicinity_sales' => 'Vicinity Sales',
        'cma'            => 'CMA',
        'market_article' => 'Market Article',
        'market_report'  => 'Market Report',
        'other'          => 'Other',
    ];
?>

<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Document Library</h1>
            <p class="text-sm text-gray-500 mt-1">Upload, browse and manage shared documents.</p>
        </div>
        <?php if($presentation): ?>
            <a href="<?php echo e($returnUrl ?? route('presentations.show', $presentation) . '#documents'); ?>"
               class="px-3 py-1.5 bg-gray-200 text-gray-700 text-xs font-medium rounded hover:bg-gray-300">
                Back to Presentation #<?php echo e($presentation->id); ?>

            </a>
        <?php endif; ?>
    </div>
</div>


<?php if($presentation): ?>
<div class="mb-4 bg-indigo-50 border border-indigo-200 rounded-xl p-4">
    <p class="text-sm font-semibold text-indigo-800 mb-1">
        Attaching to: <?php echo e($presentation->title); ?> (#<?php echo e($presentation->id); ?>)
    </p>
    <p class="text-xs text-indigo-600">Select documents below and click "Attach Selected" to link them to this presentation.</p>
</div>
<?php endif; ?>


<?php if(session('success')): ?>
    <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
        <?php echo e(session('success')); ?>

    </div>
<?php endif; ?>

<?php if(session('error')): ?>
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
        <?php echo e(session('error')); ?>

    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

    
    <div class="lg:col-span-1 space-y-4">

        
        <div class="bg-white rounded-xl shadow p-4">
            <h2 class="text-sm font-semibold text-gray-700 mb-3">Upload to Library</h2>
            <form method="POST" action="<?php echo e(route('documents.library.upload')); ?>" enctype="multipart/form-data" class="space-y-2">
                <?php echo csrf_field(); ?>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Document Type</label>
                    <select name="doc_type" class="w-full border border-gray-300 rounded px-2 py-1.5 text-xs" required>
                        <option value="" disabled selected>Select type...</option>
                        <?php $__currentLoopData = $docTypeLabels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($val); ?>"><?php echo e($label); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                    <?php $__errorArgs = ['doc_type'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <p class="text-xs text-red-600 mt-1"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Title (optional)</label>
                    <input type="text" name="title" value="<?php echo e(old('title')); ?>"
                           class="w-full border border-gray-300 rounded px-2 py-1.5 text-xs" placeholder="Descriptive title...">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">File</label>
                    <input type="file" name="file" class="w-full text-xs text-gray-600 border border-gray-300 rounded px-2 py-1.5" required>
                    <?php $__errorArgs = ['file'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <p class="text-xs text-red-600 mt-1"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>
                <button type="submit"
                        class="w-full px-3 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded hover:bg-indigo-700">
                    Upload
                </button>
            </form>
        </div>

        
        <div class="bg-white rounded-xl shadow p-4">
            <h2 class="text-sm font-semibold text-gray-700 mb-3">Filter</h2>
            <form method="GET" action="<?php echo e(route('documents.library.index')); ?>" class="space-y-2">
                <?php if($presentationId): ?>
                    <input type="hidden" name="presentation_id" value="<?php echo e($presentationId); ?>">
                <?php endif; ?>
                <?php if($returnUrl): ?>
                    <input type="hidden" name="return" value="<?php echo e($returnUrl); ?>">
                <?php endif; ?>

                <div>
                    <label class="block text-xs text-gray-500 mb-1">Search</label>
                    <input type="text" name="q" value="<?php echo e(request('q')); ?>"
                           class="w-full border border-gray-300 rounded px-2 py-1.5 text-xs" placeholder="Name or title...">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Doc Type</label>
                    <select name="doc_type" class="w-full border border-gray-300 rounded px-2 py-1.5 text-xs">
                        <option value="">All types</option>
                        <?php $__currentLoopData = $docTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($dt); ?>" <?php echo e(request('doc_type') === $dt ? 'selected' : ''); ?>>
                                <?php echo e($docTypeLabels[$dt] ?? $dt); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Uploaded By</label>
                    <select name="user_id" class="w-full border border-gray-300 rounded px-2 py-1.5 text-xs">
                        <option value="">All users</option>
                        <?php $__currentLoopData = $uploaders; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $u): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($u->id); ?>" <?php echo e(request('user_id') == $u->id ? 'selected' : ''); ?>>
                                <?php echo e($u->name); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                </div>
                <button type="submit"
                        class="w-full px-3 py-1.5 bg-gray-600 text-white text-xs font-medium rounded hover:bg-gray-700">
                    Apply Filters
                </button>
                <a href="<?php echo e(route('documents.library.index', array_filter(['presentation_id' => $presentationId, 'return' => $returnUrl]))); ?>"
                   class="block text-center text-xs text-gray-500 hover:text-gray-700">Clear Filters</a>
            </form>
        </div>
    </div>

    
    <div class="lg:col-span-3">
        <?php if($presentation): ?>
        <form method="POST" action="<?php echo e(route('documents.library.attach')); ?>" id="attachForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="presentation_id" value="<?php echo e($presentationId); ?>">
            <input type="hidden" name="return" value="<?php echo e($returnUrl ?? route('presentations.show', $presentation) . '#documents'); ?>">

            <div class="mb-3 flex items-center justify-between">
                <p class="text-xs text-gray-500"><?php echo e($items->total()); ?> document(s) in library</p>
                <button type="submit"
                        class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50"
                        id="attachBtn" disabled>
                    Attach Selected
                </button>
            </div>
        <?php else: ?>
            <div class="mb-3">
                <p class="text-xs text-gray-500"><?php echo e($items->total()); ?> document(s) in library</p>
            </div>
        <?php endif; ?>

        <?php if($items->isEmpty()): ?>
            <div class="bg-white rounded-xl shadow p-6 text-center">
                <p class="text-sm text-gray-400 italic">No documents in the library yet. Upload one to get started.</p>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow overflow-hidden">
                <table class="w-full text-xs text-left">
                    <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider">
                        <tr>
                            <?php if($presentation): ?>
                                <th class="px-3 py-2 w-8">
                                    <input type="checkbox" id="selectAll" class="rounded">
                                </th>
                            <?php endif; ?>
                            <th class="px-3 py-2">Name</th>
                            <th class="px-3 py-2">Type</th>
                            <th class="px-3 py-2">Uploaded By</th>
                            <th class="px-3 py-2">Date</th>
                            <th class="px-3 py-2">Size</th>
                            <th class="px-3 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php $__currentLoopData = $items; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php
                                $isAttached = in_array($item->id, $attachedIds);
                            ?>
                            <tr class="<?php echo e($isAttached ? 'bg-green-50' : ''); ?>">
                                <?php if($presentation): ?>
                                    <td class="px-3 py-2">
                                        <?php if($isAttached): ?>
                                            <span class="text-green-600 text-sm" title="Already attached">&#10003;</span>
                                        <?php else: ?>
                                            <input type="checkbox" name="item_ids[]" value="<?php echo e($item->id); ?>"
                                                   class="item-checkbox rounded" form="attachForm">
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
                                <td class="px-3 py-2 font-medium text-gray-800 max-w-[200px] truncate" title="<?php echo e($item->original_name); ?>">
                                    <?php echo e($item->title ?? $item->original_name); ?>

                                </td>
                                <td class="px-3 py-2">
                                    <span class="inline-block px-1.5 py-0.5 rounded text-xs font-medium bg-indigo-50 text-indigo-600">
                                        <?php echo e($docTypeLabels[$item->doc_type] ?? $item->doc_type); ?>

                                    </span>
                                </td>
                                <td class="px-3 py-2 text-gray-600"><?php echo e($item->uploader->name ?? 'Unknown'); ?></td>
                                <td class="px-3 py-2 text-gray-500"><?php echo e($item->created_at->format('d M Y')); ?></td>
                                <td class="px-3 py-2 text-gray-500">
                                    <?php if($item->bytes > 0): ?>
                                        <?php echo e(number_format($item->bytes / 1024, 0)); ?> KB
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <a href="<?php echo e(route('documents.library.download', $item)); ?>"
                                       class="text-indigo-600 hover:text-indigo-800 font-medium">
                                        Download
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                <?php echo e($items->links()); ?>

            </div>
        <?php endif; ?>

        <?php if($presentation): ?>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php if($presentation): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    const attachBtn = document.getElementById('attachBtn');
    const selectAll = document.getElementById('selectAll');

    function updateBtn() {
        const checked = document.querySelectorAll('.item-checkbox:checked').length;
        attachBtn.disabled = checked === 0;
        attachBtn.textContent = checked > 0 ? 'Attach Selected (' + checked + ')' : 'Attach Selected';
    }

    checkboxes.forEach(function(cb) { cb.addEventListener('change', updateBtn); });

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(function(cb) { cb.checked = selectAll.checked; });
            updateBtn();
        });
    }
});
</script>
<?php endif; ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.nexus', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\johan\OneDrive\Documents\GitHub\hfc-dash\resources\views/documents/library/index.blade.php ENDPATH**/ ?>