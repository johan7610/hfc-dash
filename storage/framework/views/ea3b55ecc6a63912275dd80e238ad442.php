<?php $__env->startSection('nexus-content'); ?>
<link rel="stylesheet" href="<?php echo e(asset('css/docuperfect-editor.css')); ?>">

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">

    <div style="background:#0b2a4a;" class="rounded-2xl px-6 py-4 flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-white leading-tight">Document &mdash; <?php echo e($document->name); ?></h2>
            <div class="text-sm text-white/60">Template: <?php echo e($template->name); ?> &middot; <?php echo e($template->page_count); ?> page<?php echo e($template->page_count !== 1 ? 's' : ''); ?></div>
        </div>
        <div class="flex items-center gap-3">
            <button type="button" id="dpSaveBtn" class="nexus-btn-primary text-sm" style="background:rgba(255,255,255,0.15);">Save</button>
            <button type="button" id="dpDownloadBtn" class="nexus-btn-primary text-sm" style="background:rgba(255,255,255,0.15);">Download PDF</button>
            <a href="<?php echo e(route('docuperfect.documents.index')); ?>" class="text-sm text-white/70 hover:text-white">Back</a>
        </div>
    </div>

    <?php if(session('status')): ?>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 text-emerald-900 px-4 py-3 text-sm">
            <?php echo e(session('status')); ?>

        </div>
    <?php endif; ?>

    
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
        mode: 'document',
        templateId: <?php echo json_encode($template->id, 15, 512) ?>,
        documentId: <?php echo json_encode($document->id, 15, 512) ?>,
        pageImages: <?php echo json_encode($pageImageUrls, 15, 512) ?>,
        fields: <?php echo json_encode($document->fields_json ?? [], 15, 512) ?>,
        saveUrl: <?php echo json_encode(route('docuperfect.documents.saveFields', $document->id), 512) ?>,
        clauseApiUrl: <?php echo json_encode(route('docuperfect.clauses.json'), 15, 512) ?>,
        csrfToken: <?php echo json_encode(csrf_token(), 15, 512) ?>,
        templateName: <?php echo json_encode($template->name, 15, 512) ?>,
        documentName: <?php echo json_encode($document->name, 15, 512) ?>,
        namedFields: <?php echo json_encode($namedFields, 15, 512) ?>,
        packInstanceId: <?php echo json_encode($document->pack_instance_id, 15, 512) ?>,
        packInstanceValuesUrl: <?php echo json_encode($document->pack_instance_id ? route('docuperfect.api.packInstanceValues', ['instanceId' => $document->pack_instance_id]) : null, 512) ?>,
        packInstanceSaveUrl: <?php echo json_encode(route('docuperfect.api.packInstanceValuesSave'), 15, 512) ?>
    };
</script>
<script src="<?php echo e(asset('js/docuperfect-editor.js')); ?>"></script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.nexus', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\johan\OneDrive\Documents\GitHub\hfc-dash\resources\views/docuperfect/documents/edit.blade.php ENDPATH**/ ?>