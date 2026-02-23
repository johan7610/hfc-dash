<?php $__env->startSection('nexus-content'); ?>


<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">Presentations</h1>
        <p class="text-sm text-gray-500 mt-1">Seller presentations with market analysis.</p>
    </div>
    <a href="<?php echo e(route('presentations.create')); ?>"
       class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded hover:bg-indigo-700">
        + New Presentation
    </a>
</div>

<?php if(session('success')): ?>
    <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded text-sm">
        <?php echo e(session('success')); ?>

    </div>
<?php endif; ?>


<div class="bg-white rounded-xl shadow overflow-hidden">
    <?php if($presentations->isEmpty()): ?>
        <div class="px-6 py-12 text-center">
            <p class="text-gray-400 text-sm mb-4">No presentations yet.</p>
            <a href="<?php echo e(route('presentations.create')); ?>"
               class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded hover:bg-indigo-700">
                Create your first presentation
            </a>
        </div>
    <?php else: ?>
        <table class="w-full text-sm text-left text-gray-700">
            <thead class="bg-gray-50 border-b">
                <tr>
                    <th class="px-4 py-3 font-medium text-gray-500">Title</th>
                    <th class="px-4 py-3 font-medium text-gray-500">Address</th>
                    <th class="px-4 py-3 font-medium text-gray-500">Property</th>
                    <th class="px-4 py-3 font-medium text-gray-500">Seller</th>
                    <th class="px-4 py-3 font-medium text-gray-500">Status</th>
                    <th class="px-4 py-3 font-medium text-gray-500">Last Updated</th>
                    <th class="px-4 py-3 font-medium text-gray-500"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php $__currentLoopData = $presentations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pres): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-800">
                            <?php echo e($pres->title); ?>

                        </td>
                        <td class="px-4 py-3 text-gray-600 text-xs">
                            <?php echo e($pres->property_address ?? '—'); ?>

                        </td>
                        <td class="px-4 py-3 text-xs">
                            <?php if($pres->suburb || $pres->property_type): ?>
                                <span class="text-gray-700"><?php echo e($pres->suburb ?? '—'); ?></span>
                                <?php if($pres->property_type): ?>
                                    <span class="text-gray-400"> · <?php echo e(ucfirst($pres->property_type)); ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-gray-300">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-gray-600 text-xs">
                            <?php echo e($pres->seller_name ?? '—'); ?>

                        </td>
                        <td class="px-4 py-3">
                            <?php
                                $statusClasses = match($pres->status) {
                                    'presented' => 'bg-blue-100 text-blue-700',
                                    'locked'    => 'bg-green-100 text-green-700',
                                    default     => 'bg-gray-100 text-gray-600',
                                };
                            ?>
                            <span class="px-2 py-0.5 rounded text-xs font-medium <?php echo e($statusClasses); ?>">
                                <?php echo e(ucfirst($pres->status)); ?>

                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-400 text-xs">
                            <?php echo e($pres->updated_at->format('Y-m-d H:i')); ?>

                        </td>
                        <td class="px-4 py-3">
                            <a href="<?php echo e(route('presentations.show', $pres)); ?>"
                               class="text-indigo-600 hover:underline text-xs font-medium">
                                Open →
                            </a>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </tbody>
        </table>

        <?php if($presentations->hasPages()): ?>
            <div class="px-4 py-3 border-t">
                <?php echo e($presentations->links()); ?>

            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.nexus', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\johan\OneDrive\Documents\GitHub\hfc-dash\resources\views/presentations/index.blade.php ENDPATH**/ ?>