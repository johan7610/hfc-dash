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
                <h2 class="text-xl font-bold text-white leading-tight">Knowledge Base</h2>
                <div class="text-sm text-white/60">Ellie's training documents &amp; agent resources</div>
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

    
    <div class="ds-status-card">
        <h3 class="ds-section-header" style="margin-bottom:0.75rem;">Upload Document</h3>
        <form action="<?php echo e(route('admin.knowledge.upload')); ?>" method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="ds-label">Title <span class="text-red-500">*</span></label>
                    <input type="text" name="title" required class="w-full rounded border-gray-300 text-sm px-3 py-2 focus:ring-cyan-500 focus:border-cyan-500" placeholder="Document title" value="<?php echo e(old('title')); ?>">
                    <?php $__errorArgs = ['title'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-red-500 text-xs mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>
                <div>
                    <label class="ds-label">Category <span class="text-red-500">*</span></label>
                    <select name="category_id" required class="w-full rounded border-gray-300 text-sm px-3 py-2 focus:ring-cyan-500 focus:border-cyan-500">
                        <option value="">Select category...</option>
                        <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($cat->id); ?>" <?php echo e(old('category_id') == $cat->id ? 'selected' : ''); ?>><?php echo e($cat->name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                    <?php $__errorArgs = ['category_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-red-500 text-xs mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>
                <div>
                    <label class="ds-label">File <span class="text-red-500">*</span></label>
                    <input type="file" name="file" required accept=".pdf,.docx,.doc,.txt,.md" class="w-full text-sm">
                    <?php $__errorArgs = ['file'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <div class="text-red-500 text-xs mt-1"><?php echo e($message); ?></div> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                </div>
                <div>
                    <label class="ds-label">Description</label>
                    <textarea name="description" rows="2" class="w-full rounded border-gray-300 text-sm px-3 py-2 focus:ring-cyan-500 focus:border-cyan-500" placeholder="Optional description"><?php echo e(old('description')); ?></textarea>
                </div>
                <div>
                    <label class="ds-label">Version</label>
                    <input type="text" name="version" class="w-full rounded border-gray-300 text-sm px-3 py-2 focus:ring-cyan-500 focus:border-cyan-500" placeholder="e.g. v2.1" value="<?php echo e(old('version')); ?>">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="nexus-btn-primary px-4 py-2 rounded text-sm font-medium" style="background:var(--nexus-cyan,#00b4d8);color:#fff;">Upload Document</button>
                </div>
            </div>
            <div class="text-xs text-gray-500 mt-2">Accepted: PDF, DOCX, DOC, TXT, MD &mdash; Max 20MB</div>
        </form>
    </div>

    
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="ds-status-card">
            <div class="ds-label">Total Documents</div>
            <div class="ds-value text-2xl"><?php echo e($stats['total_documents']); ?></div>
            <div class="text-xs text-gray-500 mt-1">
                <span class="text-green-600"><?php echo e($stats['by_status']['ready']); ?> ready</span>
                <?php if($stats['by_status']['processing'] > 0): ?>
                    &middot; <span class="text-amber-600"><?php echo e($stats['by_status']['processing']); ?> processing</span>
                <?php endif; ?>
                <?php if($stats['by_status']['error'] > 0): ?>
                    &middot; <span class="text-red-600"><?php echo e($stats['by_status']['error']); ?> error</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="ds-status-card">
            <div class="ds-label">Total Chunks</div>
            <div class="ds-value text-2xl"><?php echo e(number_format($stats['total_chunks'])); ?></div>
            <div class="text-xs text-gray-500 mt-1">Searchable text segments</div>
        </div>
        <div class="ds-status-card">
            <div class="ds-label">Ellie-Enabled</div>
            <div class="ds-value text-2xl"><?php echo e($stats['ellie_enabled']); ?></div>
            <div class="text-xs text-gray-500 mt-1">Documents Ellie can search</div>
        </div>
    </div>

    
    <div>
        <h3 class="ds-section-header">Categories</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cat): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <a href="<?php echo e(route('admin.knowledge.category', $cat->id)); ?>" class="ds-status-card hover:shadow-md transition-shadow block" style="text-decoration:none;color:inherit;">
                    <div class="flex items-center gap-3 mb-2">
                        <?php if($cat->icon): ?>
                            <i class="fas <?php echo e($cat->icon); ?> text-lg" style="color:var(--nexus-cyan,#00b4d8);"></i>
                        <?php endif; ?>
                        <div class="font-semibold text-sm"><?php echo e($cat->name); ?></div>
                    </div>
                    <div class="text-xs text-gray-500"><?php echo e($cat->documents_count); ?> <?php echo e(Str::plural('document', $cat->documents_count)); ?></div>
                    <div class="text-xs text-cyan-600 mt-1 font-medium">View &rarr;</div>
                </a>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>

    
    <div>
        <h3 class="ds-section-header">Recent Documents</h3>
        <?php if($recentDocuments->isEmpty()): ?>
            <div class="ds-status-card text-center text-gray-500 text-sm py-8">
                No documents uploaded yet. Use the form above to upload your first document.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="ds-table w-full">
                    <thead>
                        <tr>
                            <th class="text-left text-xs font-semibold text-gray-600 uppercase px-3 py-2">Title</th>
                            <th class="text-left text-xs font-semibold text-gray-600 uppercase px-3 py-2">Category</th>
                            <th class="text-center text-xs font-semibold text-gray-600 uppercase px-3 py-2">Status</th>
                            <th class="text-center text-xs font-semibold text-gray-600 uppercase px-3 py-2">Chunks</th>
                            <th class="text-center text-xs font-semibold text-gray-600 uppercase px-3 py-2">Ellie</th>
                            <th class="text-center text-xs font-semibold text-gray-600 uppercase px-3 py-2">Active</th>
                            <th class="text-left text-xs font-semibold text-gray-600 uppercase px-3 py-2">Uploaded</th>
                            <th class="text-right text-xs font-semibold text-gray-600 uppercase px-3 py-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $recentDocuments; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $doc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <tr class="border-t border-gray-100">
                                <td class="px-3 py-2 text-sm font-medium"><?php echo e(Str::limit($doc->title, 40)); ?></td>
                                <td class="px-3 py-2 text-xs text-gray-600"><?php echo e($doc->category->name ?? '-'); ?></td>
                                <td class="px-3 py-2 text-center"><?php echo $doc->status_badge; ?></td>
                                <td class="px-3 py-2 text-center text-sm"><?php echo e($doc->chunk_count); ?></td>
                                <td class="px-3 py-2 text-center">
                                    <form action="<?php echo e(route('admin.knowledge.toggleEllie', $doc->id)); ?>" method="POST" class="inline">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit" class="text-xs px-2 py-0.5 rounded <?php echo e($doc->is_ellie_enabled ? 'bg-cyan-100 text-cyan-800' : 'bg-gray-100 text-gray-500'); ?>" title="<?php echo e($doc->is_ellie_enabled ? 'Disable Ellie' : 'Enable Ellie'); ?>">
                                            <?php echo e($doc->is_ellie_enabled ? 'ON' : 'OFF'); ?>

                                        </button>
                                    </form>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <form action="<?php echo e(route('admin.knowledge.toggleActive', $doc->id)); ?>" method="POST" class="inline">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit" class="text-xs px-2 py-0.5 rounded <?php echo e($doc->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500'); ?>" title="<?php echo e($doc->is_active ? 'Deactivate' : 'Activate'); ?>">
                                            <?php echo e($doc->is_active ? 'ON' : 'OFF'); ?>

                                        </button>
                                    </form>
                                </td>
                                <td class="px-3 py-2 text-xs text-gray-500"><?php echo e($doc->created_at->format('d M Y')); ?></td>
                                <td class="px-3 py-2 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="<?php echo e(route('admin.knowledge.preview', $doc->id)); ?>" class="text-xs text-cyan-600 hover:underline">Preview</a>
                                        <form action="<?php echo e(route('admin.knowledge.reprocess', $doc->id)); ?>" method="POST" class="inline">
                                            <?php echo csrf_field(); ?>
                                            <button type="submit" class="text-xs text-amber-600 hover:underline ml-2">Reprocess</button>
                                        </form>
                                        <form action="<?php echo e(route('admin.knowledge.destroy', $doc->id)); ?>" method="POST" class="inline" x-data x-on:submit.prevent="if(confirm('Delete this document and all its chunks?')) $el.submit()">
                                            <?php echo csrf_field(); ?>
                                            <?php echo method_field('DELETE'); ?>
                                            <button type="submit" class="text-xs text-red-600 hover:underline ml-2">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
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
<?php /**PATH C:\Users\johan\OneDrive\Documents\GitHub\hfc-dash\resources\views/admin/knowledge/index.blade.php ENDPATH**/ ?>