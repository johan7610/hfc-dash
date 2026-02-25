<?php $__env->startSection('nexus-content'); ?>
<link rel="stylesheet" href="<?php echo e(asset('css/docuperfect-editor.css')); ?>">

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">

    <div style="background:#0b2a4a;" class="rounded-2xl px-6 py-4 flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-white leading-tight">Edit Template &mdash; <?php echo e($template->name); ?></h2>
            <div class="text-sm text-white/60"><?php echo e($template->page_count); ?> page<?php echo e($template->page_count !== 1 ? 's' : ''); ?> &middot; <?php echo e($template->template_type); ?></div>
        </div>
        <div class="flex items-center gap-3">
            <button type="button" id="dpSaveBtn" class="nexus-btn-primary text-sm" style="background:rgba(255,255,255,0.15);">Save</button>
            <a href="<?php echo e(route('docuperfect.templates.index')); ?>" class="text-sm text-white/70 hover:text-white">Back</a>
        </div>
    </div>

    <?php if(session('status')): ?>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 text-emerald-900 px-4 py-3 text-sm">
            <?php echo e(session('status')); ?>

        </div>
    <?php endif; ?>

    
    <div class="ds-status-card p-4 space-y-3">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="ds-label block mb-1">Template Name</label>
                <input type="text" id="dpTemplateName" value="<?php echo e($template->name); ?>" class="w-full rounded-lg border border-slate-300 bg-white text-slate-900 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="ds-label block mb-1">Type</label>
                <select id="dpTemplateType" class="w-full rounded-lg border border-slate-300 bg-white text-slate-900 px-3 py-2 text-sm">
                    <option value="sales" <?php echo e($template->template_type === 'sales' ? 'selected' : ''); ?>>Sales</option>
                    <option value="rentals" <?php echo e($template->template_type === 'rentals' ? 'selected' : ''); ?>>Rentals</option>
                    <option value="compliance" <?php echo e($template->template_type === 'compliance' ? 'selected' : ''); ?>>Compliance</option>
                </select>
            </div>
            <div>
                <label class="ds-label block mb-1">Document Type</label>
                <select id="dpDocumentType" class="w-full rounded-lg border border-slate-300 bg-white text-slate-900 px-3 py-2 text-sm">
                    <option value="">— None —</option>
                    <?php $__currentLoopData = $documentTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <option value="<?php echo e($dt->id); ?>" <?php echo e($template->document_type_id == $dt->id ? 'selected' : ''); ?>><?php echo e($dt->name); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
            </div>
            <div>
                <label class="ds-label block mb-1">Visibility</label>
                <div class="flex items-center gap-2 mt-1">
                    <input type="checkbox" id="dpGlobal" <?php echo e($template->is_global ? 'checked' : ''); ?> class="rounded border-slate-300">
                    <span class="text-sm text-slate-700">Global (all branches)</span>
                </div>
            </div>
        </div>
        <div>
            <label class="ds-label block mb-1">Branch Access</label>
            <div class="flex flex-wrap gap-2">
                <?php $__currentLoopData = $branches; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $branch): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <label class="flex items-center gap-1 text-sm text-slate-700">
                    <input type="checkbox" class="dp-branch-cb rounded border-slate-300" value="<?php echo e($branch->id); ?>" <?php echo e($template->branches->contains('id', $branch->id) ? 'checked' : ''); ?>>
                    <?php echo e($branch->name); ?>

                </label>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>
    </div>

    
    <div class="ds-status-card p-4">
        <div id="docuperfect-editor"></div>
    </div>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<?php
    $pageImageUrls = [];
    for ($n = 0; $n < $template->page_count; $n++) {
        $pageImageUrls[] = route('docuperfect.page.image', ['id' => $template->id, 'page' => $n]);
    }
?>
<script>
    window.DocuperfectConfig = {
        mode: 'template',
        templateId: <?php echo json_encode($template->id, 15, 512) ?>,
        pageImages: <?php echo json_encode($pageImageUrls, 15, 512) ?>,
        fields: <?php echo json_encode($template->fields_json ?? [], 15, 512) ?>,
        isGlobal: <?php echo json_encode($template->is_global, 15, 512) ?>,
        allowedBranches: <?php echo json_encode($template->branches->pluck('id'), 15, 512) ?>,
        saveUrl: <?php echo json_encode(route('docuperfect.templates.saveFields', $template->id), 512) ?>,
        uploadPagesUrl: <?php echo json_encode(route('docuperfect.templates.uploadPages', $template->id), 512) ?>,
        clauseApiUrl: <?php echo json_encode(route('docuperfect.clauses.json'), 15, 512) ?>,
        csrfToken: <?php echo json_encode(csrf_token(), 15, 512) ?>,
        templateName: <?php echo json_encode($template->name, 15, 512) ?>,
        templateType: <?php echo json_encode($template->template_type, 15, 512) ?>,
        documentTypeId: <?php echo json_encode($template->document_type_id, 15, 512) ?>,
        namedFields: <?php echo json_encode($namedFields, 15, 512) ?>
    };
</script>
<script src="<?php echo e(asset('js/docuperfect-editor.js')); ?>"></script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.nexus', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\johan\OneDrive\Documents\GitHub\hfc-dash\resources\views/docuperfect/templates/edit.blade.php ENDPATH**/ ?>