<?php $__env->startSection('content'); ?>
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    <div style="background:#0b2a4a;" class="rounded-2xl px-6 py-4">
        <h2 class="text-xl font-bold text-white leading-tight">Create Document</h2>
        <div class="text-sm text-white/60">Choose a template or launch a document pack.</div>
    </div>

    <?php if(session('status')): ?>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 text-emerald-900 px-4 py-3 text-sm">
            <?php echo e(session('status')); ?>

        </div>
    <?php endif; ?>

    <div x-data="{ tab: 'templates', viewMode: localStorage.getItem('docuperfect_view_mode') || 'grid', typeFilter: '', search: '' }">

        
        <div class="flex items-center gap-1 mb-5 border border-slate-300 rounded-lg overflow-hidden w-fit">
            <button @click="tab = 'templates'"
                    :class="tab === 'templates' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 hover:text-slate-800'"
                    class="px-4 py-2 text-sm font-medium transition-colors">
                Templates
            </button>
            <button @click="tab = 'packs'"
                    :class="tab === 'packs' ? 'bg-slate-900 text-white' : 'bg-white text-slate-600 hover:text-slate-800'"
                    class="px-4 py-2 text-sm font-medium transition-colors">
                Document Packs
            </button>
        </div>

        
        <div x-show="tab === 'templates'">
            
            <div class="flex items-center justify-between mb-4">
                <div class="flex items-center gap-2">
                    <select x-model="typeFilter" class="rounded-lg border border-slate-300 bg-white text-slate-700 px-2 py-1.5 text-xs">
                        <option value="">All Types</option>
                        <?php $__currentLoopData = $documentTypes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dt): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($dt->id); ?>"><?php echo e($dt->name); ?></option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        <option value="none">Uncategorized</option>
                    </select>
                    <input type="text" x-model="search" placeholder="Search templates…"
                           class="rounded-lg border border-slate-300 bg-white text-slate-700 px-3 py-1.5 text-xs w-48" />
                </div>
                <div class="flex items-center border border-slate-300 rounded-lg overflow-hidden">
                    <button @click="viewMode = 'grid'; localStorage.setItem('docuperfect_view_mode', 'grid')"
                            :class="viewMode === 'grid' ? 'bg-slate-900 text-white' : 'bg-white text-slate-500 hover:text-slate-700'"
                            class="px-2 py-1.5 transition-colors" title="Grid view">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path d="M4 5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5ZM14 5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1V5ZM4 15a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-4ZM14 15a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1v-4Z"/>
                        </svg>
                    </button>
                    <button @click="viewMode = 'list'; localStorage.setItem('docuperfect_view_mode', 'list')"
                            :class="viewMode === 'list' ? 'bg-slate-900 text-white' : 'bg-white text-slate-500 hover:text-slate-700'"
                            class="px-2 py-1.5 transition-colors" title="List view">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                        </svg>
                    </button>
                </div>
            </div>

            <?php if($templates->isEmpty()): ?>
                <div class="ds-status-card p-6 text-center">
                    <div class="text-sm text-slate-500">No templates available yet.</div>
                </div>
            <?php else: ?>
                
                <div x-show="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <?php $__currentLoopData = $templates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tpl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="ds-status-card p-4 flex flex-col"
                         x-show="(typeFilter === '' || typeFilter === '<?php echo e($tpl->document_type_id ?? 'none'); ?>') && (search === '' || '<?php echo e(strtolower(addslashes($tpl->name))); ?>'.includes(search.toLowerCase()))" x-cloak>
                        <div class="flex items-start justify-between mb-1">
                            <div class="font-semibold text-slate-900 text-sm leading-tight"><?php echo e($tpl->name); ?></div>
                            <?php if($tpl->documentType): ?>
                            <span class="ds-badge ds-badge-info text-[10px] ml-2 flex-shrink-0"><?php echo e($tpl->documentType->name); ?></span>
                            <?php elseif($tpl->template_type): ?>
                            <span class="ds-badge ds-badge-info text-[10px] ml-2 flex-shrink-0"><?php echo e($tpl->template_type); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="text-xs text-slate-500 mb-3">
                            <?php if($tpl->is_global): ?>
                                Global
                            <?php else: ?>
                                <?php echo e($tpl->branches->pluck('name')->join(', ') ?: 'No branches'); ?>

                            <?php endif; ?>
                        </div>
                        <?php if($tpl->page_count > 0): ?>
                        <div class="flex-1 flex items-center justify-center mb-3">
                            <img src="<?php echo e(route('docuperfect.page.image', ['id' => $tpl->id, 'page' => 0])); ?>"
                                 alt="<?php echo e($tpl->name); ?>"
                                 style="max-height: 200px; width: auto;"
                                 loading="lazy"
                                 class="rounded shadow-sm mx-auto block" />
                        </div>
                        <?php endif; ?>
                        <div class="mt-auto pt-3 border-t border-slate-100">
                            <a href="<?php echo e(route('docuperfect.documents.create', $tpl->id)); ?>" class="nexus-btn-primary text-xs px-3 py-1.5">Create Document</a>
                        </div>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>

                
                <div x-show="viewMode === 'list'" x-cloak>
                    <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden">
                        <table class="w-full text-sm ds-table">
                            <thead>
                                <tr>
                                    <th class="text-left px-4 py-3 w-12"></th>
                                    <th class="text-left px-4 py-3">Name</th>
                                    <th class="text-left px-4 py-3">Type</th>
                                    <th class="text-left px-4 py-3">Branches</th>
                                    <th class="text-center px-4 py-3">Pages</th>
                                    <th class="text-right px-4 py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $__currentLoopData = $templates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tpl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <tr x-show="(typeFilter === '' || typeFilter === '<?php echo e($tpl->document_type_id ?? 'none'); ?>') && (search === '' || '<?php echo e(strtolower(addslashes($tpl->name))); ?>'.includes(search.toLowerCase()))" x-cloak>
                                    <td class="px-4 py-2">
                                        <?php if($tpl->page_count > 0): ?>
                                        <img src="<?php echo e(route('docuperfect.page.image', ['id' => $tpl->id, 'page' => 0])); ?>"
                                             alt="<?php echo e($tpl->name); ?>"
                                             class="w-10 h-14 object-cover rounded shadow-sm"
                                             loading="lazy" />
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2 font-medium text-slate-900"><?php echo e($tpl->name); ?></td>
                                    <td class="px-4 py-2">
                                        <?php if($tpl->documentType): ?>
                                        <span class="ds-badge ds-badge-info text-[10px]"><?php echo e($tpl->documentType->name); ?></span>
                                        <?php elseif($tpl->template_type): ?>
                                        <span class="text-xs text-slate-500"><?php echo e($tpl->template_type); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2 text-xs text-slate-500">
                                        <?php if($tpl->is_global): ?>
                                            <span class="ds-badge ds-badge-success text-[10px]">Global</span>
                                        <?php else: ?>
                                            <?php echo e($tpl->branches->pluck('name')->join(', ') ?: '—'); ?>

                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2 text-center text-slate-500"><?php echo e($tpl->page_count); ?></td>
                                    <td class="px-4 py-2 text-right">
                                        <a href="<?php echo e(route('docuperfect.documents.create', $tpl->id)); ?>" class="nexus-btn-primary text-xs px-3 py-1.5">Create</a>
                                    </td>
                                </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        
        <div x-show="tab === 'packs'" x-cloak>
            <?php if($packs->isEmpty()): ?>
                <div class="ds-status-card p-6 text-center">
                    <div class="text-sm text-slate-500">No document packs available yet.</div>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php $__currentLoopData = $packs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pack): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <div class="ds-status-card p-4 flex flex-col">
                        <div class="font-semibold text-slate-900 text-sm leading-tight mb-1"><?php echo e($pack->name); ?></div>
                        <?php if($pack->description): ?>
                        <div class="text-xs text-slate-500 mb-2"><?php echo e($pack->description); ?></div>
                        <?php endif; ?>
                        <div class="text-xs text-slate-400 mb-3">
                            <?php echo e($pack->templates->count()); ?> template<?php echo e($pack->templates->count() !== 1 ? 's' : ''); ?>

                            &middot;
                            <?php if($pack->is_global): ?>
                                <span class="ds-badge ds-badge-success text-[10px]">Global</span>
                            <?php else: ?>
                                <?php echo e($pack->branches->pluck('name')->join(', ') ?: 'No branches'); ?>

                            <?php endif; ?>
                        </div>

                        <?php if($pack->templates->isNotEmpty()): ?>
                        <div class="flex-1 mb-3">
                            <div class="text-[11px] text-slate-400 uppercase tracking-wider mb-1">Templates included</div>
                            <ul class="text-xs text-slate-600 space-y-0.5">
                                <?php $__currentLoopData = $pack->templates; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tpl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <li class="flex items-center gap-1">
                                    <span class="w-1.5 h-1.5 rounded-full bg-cyan-500 flex-shrink-0"></span>
                                    <?php echo e($tpl->name); ?>

                                </li>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </ul>
                        </div>
                        <?php endif; ?>

                        <div class="mt-auto pt-3 border-t border-slate-100">
                            <form method="POST" action="<?php echo e(route('docuperfect.packs.launch', $pack->id)); ?>" class="inline" onsubmit="return confirm('Launch this pack? This will create <?php echo e($pack->templates->count()); ?> document<?php echo e($pack->templates->count() !== 1 ? 's' : ''); ?>.');">
                                <?php echo csrf_field(); ?>
                                <button class="nexus-btn-primary text-xs px-3 py-1.5">Launch Pack</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endif; ?>
        </div>

    </div>

</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.nexus', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\johan\OneDrive\Documents\GitHub\hfc-dash\resources\views/docuperfect/create.blade.php ENDPATH**/ ?>