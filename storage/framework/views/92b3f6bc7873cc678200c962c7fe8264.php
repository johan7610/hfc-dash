<?php $__env->startSection('nexus-content'); ?>

<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-gray-800">New Presentation</h1>
        <p class="text-sm text-gray-500 mt-1">Enter the property details — you'll run the analysis on the next screen.</p>
    </div>
    <a href="<?php echo e(route('presentations.index')); ?>"
       class="text-xs text-indigo-600 hover:underline">← Back to Presentations</a>
</div>

<div class="bg-white rounded-xl shadow p-6 max-w-2xl">
    <form method="POST" action="<?php echo e(route('presentations.store')); ?>">
        <?php echo csrf_field(); ?>

        <div class="space-y-5">

            
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">
                    Presentation Title <span class="text-red-500">*</span>
                </label>
                <input type="text" name="title" value="<?php echo e(old('title')); ?>" required
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm"
                       placeholder="e.g. 12 Ocean Drive — Market Analysis">
                <?php $__errorArgs = ['title'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <p class="mt-1 text-xs text-red-600"><?php echo e($message); ?></p>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>

            
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">
                    Property Address <span class="text-red-500">*</span>
                </label>
                <input type="text" name="property_address" value="<?php echo e(old('property_address')); ?>" required
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm"
                       placeholder="e.g. 12 Ocean Drive, Ballito, 4399">
                <?php $__errorArgs = ['property_address'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <p class="mt-1 text-xs text-red-600"><?php echo e($message); ?></p>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>

            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Suburb <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="suburb" value="<?php echo e(old('suburb')); ?>" required
                           class="w-full border border-gray-300 rounded px-3 py-2 text-sm"
                           placeholder="e.g. Ballito">
                    <?php $__errorArgs = ['suburb'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <p class="mt-1 text-xs text-red-600"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Property Type <span class="text-red-500">*</span>
                    </label>
                    <select name="property_type" required class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                        <option value="">— Select type —</option>
                        <?php $__currentLoopData = ['house' => 'House', 'unit' => 'Unit / Apartment', 'land' => 'Vacant Land', 'other' => 'Other']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $val => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($val); ?>" <?php echo e(old('property_type') === $val ? 'selected' : ''); ?>>
                                <?php echo e($label); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                    <?php $__errorArgs = ['property_type'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <p class="mt-1 text-xs text-red-600"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>
            </div>

            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Bedrooms
                        <span class="text-gray-400 font-normal">(optional)</span>
                    </label>
                    <input type="number" name="bedrooms" value="<?php echo e(old('bedrooms')); ?>"
                           min="0" max="20"
                           class="w-full border border-gray-300 rounded px-3 py-2 text-sm"
                           placeholder="e.g. 3">
                    <?php $__errorArgs = ['bedrooms'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <p class="mt-1 text-xs text-red-600"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">
                        Floor Area (m²)
                        <span class="text-gray-400 font-normal">(optional — unlocks price/m² signal)</span>
                    </label>
                    <input type="number" name="floor_area_m2" value="<?php echo e(old('floor_area_m2')); ?>"
                           min="0"
                           class="w-full border border-gray-300 rounded px-3 py-2 text-sm"
                           placeholder="e.g. 180">
                    <?php $__errorArgs = ['floor_area_m2'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <p class="mt-1 text-xs text-red-600"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>
            </div>

            
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Seller Name
                    <span class="text-gray-400 font-normal">(optional)</span>
                </label>
                <input type="text" name="seller_name" value="<?php echo e(old('seller_name')); ?>"
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm"
                       placeholder="e.g. John Smith">
                <?php $__errorArgs = ['seller_name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <p class="mt-1 text-xs text-red-600"><?php echo e($message); ?></p>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>

            
            <?php if($isAdmin): ?>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">
                    Branch <span class="text-red-500">*</span>
                </label>
                <?php if($branches->isEmpty()): ?>
                    <p class="text-xs text-red-600">No branches configured. Contact an admin.</p>
                <?php else: ?>
                    <select name="branch_id" required class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                        <option value="">— Select branch —</option>
                        <?php $__currentLoopData = $branches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $branch): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($branch->id); ?>" <?php echo e(old('branch_id') == $branch->id ? 'selected' : ''); ?>>
                                <?php echo e($branch->name); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                <?php endif; ?>
                <?php $__errorArgs = ['branch_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                    <p class="mt-1 text-xs text-red-600"><?php echo e($message); ?></p>
                <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
            <?php endif; ?>

        </div>

        <div class="mt-6 flex items-center gap-3">
            <button type="submit"
                    class="px-5 py-2 bg-indigo-600 text-white text-sm font-medium rounded hover:bg-indigo-700">
                Create &amp; Run Analysis →
            </button>
            <a href="<?php echo e(route('presentations.index')); ?>"
               class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800">
                Cancel
            </a>
        </div>
    </form>
</div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.nexus', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\johan\OneDrive\Documents\GitHub\hfc-dash\resources\views/presentations/create.blade.php ENDPATH**/ ?>