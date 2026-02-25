<?php $__env->startSection('content'); ?>
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    <div style="background:#0b2a4a;" class="rounded-2xl px-6 py-4 flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-white leading-tight">Clause Library</h2>
            <div class="text-sm text-white/60">Reusable conditional clauses for document templates.</div>
        </div>
        <?php if($canEdit): ?>
        <button type="button" onclick="document.getElementById('addClauseSection').classList.toggle('hidden')" class="nexus-btn-primary text-sm" style="background:rgba(255,255,255,0.15);">
            + New Clause
        </button>
        <?php endif; ?>
    </div>

    <?php if(session('status')): ?>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 text-emerald-900 px-4 py-3 text-sm">
            <?php echo e(session('status')); ?>

        </div>
    <?php endif; ?>

    <?php if($errors->any()): ?>
        <div class="rounded-2xl border border-red-200 bg-red-50 text-red-900 px-4 py-3 text-sm">
            <?php echo e($errors->first()); ?>

        </div>
    <?php endif; ?>

    
    <?php if($canEdit): ?>
    <div id="addClauseSection" class="ds-status-card p-4 hidden">
        <h3 class="ds-section-header mb-3">Add Clause</h3>
        <form method="POST" action="<?php echo e(route('docuperfect.clauses.store')); ?>" class="space-y-3">
            <?php echo csrf_field(); ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-slate-600 mb-1">Name</label>
                    <input name="name" required class="w-full rounded-lg border border-slate-300 bg-white text-slate-900 px-3 py-2 text-sm" placeholder="e.g. Subject to Viewing">
                </div>
                <div class="flex items-center gap-4 mt-5">
                    <label class="flex items-center gap-2 text-sm text-slate-700">
                        <input type="hidden" name="is_global" value="0">
                        <input type="checkbox" name="is_global" value="1" class="rounded border-slate-300"> Global (all branches)
                    </label>
                </div>
            </div>
            <div>
                <label class="block text-xs text-slate-600 mb-1">Clause Text</label>
                <textarea name="text" required rows="4" class="w-full rounded-lg border border-slate-300 bg-white text-slate-900 px-3 py-2 text-sm" placeholder="Enter the full clause wording..."></textarea>
            </div>
            <div>
                <label class="block text-xs text-slate-600 mb-1">Branch Access (if not global)</label>
                <div class="flex flex-wrap gap-3">
                    <?php $__currentLoopData = $branches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $branch): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <label class="flex items-center gap-1 text-sm text-slate-700">
                        <input type="checkbox" name="branch_ids[]" value="<?php echo e($branch->id); ?>" class="rounded border-slate-300">
                        <?php echo e($branch->name); ?>

                    </label>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            </div>
            <button class="nexus-btn-primary text-sm">Add Clause</button>
        </form>
    </div>
    <?php endif; ?>

    
    <?php if($clauses->isEmpty()): ?>
        <div class="ds-status-card p-6 text-center">
            <div class="text-sm text-slate-500">No clauses yet.</div>
        </div>
    <?php else: ?>
        <div class="space-y-3">
            <?php $__currentLoopData = $clauses; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $clause): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="ds-status-card p-4" x-data="{ editing: false }">
                <div x-show="!editing">
                    <div class="flex items-start justify-between">
                        <div>
                            <div class="font-semibold text-slate-900 text-sm"><?php echo e($clause->name); ?></div>
                            <div class="text-xs text-slate-500 mt-0.5">
                                <?php if($clause->is_global): ?>
                                    <span class="ds-badge ds-badge-success text-[10px]">Global</span>
                                <?php else: ?>
                                    <?php echo e($clause->branches->pluck('name')->join(', ') ?: 'No branches assigned'); ?>

                                <?php endif; ?>
                                <?php if($clause->owner): ?>
                                    &middot; by <?php echo e($clause->owner->name); ?>

                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if($canEdit): ?>
                        <div class="flex items-center gap-2">
                            <button @click="editing = true" class="ds-link text-xs">Edit</button>
                            <form method="POST" action="<?php echo e(route('docuperfect.clauses.copy', $clause->id)); ?>" class="inline">
                                <?php echo csrf_field(); ?>
                                <button class="ds-link text-xs">Copy</button>
                            </form>
                            <form method="POST" action="<?php echo e(route('docuperfect.clauses.destroy', $clause->id)); ?>" class="inline" onsubmit="return confirm('Delete this clause?');">
                                <?php echo csrf_field(); ?>
                                <?php echo method_field('DELETE'); ?>
                                <button class="text-xs text-slate-400 hover:text-red-600">Delete</button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="mt-2 text-sm text-slate-700 whitespace-pre-line"><?php echo e(Str::limit($clause->text, 300)); ?></div>
                </div>

                <?php if($canEdit): ?>
                <div x-show="editing" x-cloak>
                    <form method="POST" action="<?php echo e(route('docuperfect.clauses.update', $clause->id)); ?>" class="space-y-3">
                        <?php echo csrf_field(); ?>
                        <?php echo method_field('PUT'); ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-slate-600 mb-1">Name</label>
                                <input name="name" value="<?php echo e($clause->name); ?>" required class="w-full rounded-lg border border-slate-300 bg-white text-slate-900 px-3 py-2 text-sm">
                            </div>
                            <div class="flex items-center gap-4 mt-5">
                                <label class="flex items-center gap-2 text-sm text-slate-700">
                                    <input type="hidden" name="is_global" value="0">
                                    <input type="checkbox" name="is_global" value="1" <?php echo e($clause->is_global ? 'checked' : ''); ?> class="rounded border-slate-300"> Global
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600 mb-1">Clause Text</label>
                            <textarea name="text" required rows="4" class="w-full rounded-lg border border-slate-300 bg-white text-slate-900 px-3 py-2 text-sm"><?php echo e($clause->text); ?></textarea>
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600 mb-1">Branch Access</label>
                            <div class="flex flex-wrap gap-3">
                                <?php $__currentLoopData = $branches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $branch): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <label class="flex items-center gap-1 text-sm text-slate-700">
                                    <input type="checkbox" name="branch_ids[]" value="<?php echo e($branch->id); ?>" <?php echo e($clause->branches->contains('id', $branch->id) ? 'checked' : ''); ?> class="rounded border-slate-300">
                                    <?php echo e($branch->name); ?>

                                </label>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button class="nexus-btn-primary text-sm">Save</button>
                            <button type="button" @click="editing = false" class="text-sm text-slate-500 hover:text-slate-700">Cancel</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    <?php endif; ?>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.nexus', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\johan\OneDrive\Documents\GitHub\hfc-dash\resources\views/docuperfect/clauses/index.blade.php ENDPATH**/ ?>