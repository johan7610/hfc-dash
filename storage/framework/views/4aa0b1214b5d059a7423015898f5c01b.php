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

    <?php if(isset($paidNotSettledDeals) && $paidNotSettledDeals->count() > 0 && auth()->user()?->isEffectiveAdmin()): ?>
        <div x-data="{ openPaidExceptions: false }" class="mb-4">
            <div class="rounded-xl border border-red-500 bg-red-50 px-4 py-3 text-sm text-red-900 flex items-center justify-between gap-3">
                <div class="font-semibold">
                    <?php echo e($paidNotSettledDeals->count()); ?> deal<?php echo e($paidNotSettledDeals->count() === 1 ? '' : 's'); ?> marked Paid but Settlement not marked Paid
                </div>
                <button type="button"
                        @click="openPaidExceptions = true"
                        class="rounded-lg bg-red-200/70 px-3 py-1.5 text-xs font-semibold hover:bg-red-200">
                    View exceptions
                </button>
            </div>

            <div x-show="openPaidExceptions" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50" @click="openPaidExceptions = false"></div>

                <div class="relative w-full max-w-3xl rounded-2xl bg-white p-5 shadow-xl">
                    <div class="flex items-center justify-between mb-3">
                        <div class="text-lg font-extrabold text-gray-900">Paid but not settled</div>
                        <button type="button" @click="openPaidExceptions = false" class="text-gray-500 hover:text-gray-800">&times;</button>
                    </div>

                    <div class="text-sm text-gray-600 mb-4">
                        These deals are marked <b>Paid</b> on the Deal Register, but settlement has not been marked paid yet.
                        Open each settlement and complete the agent payout workflow.
                    </div>

                    <div class="max-h-[60vh] overflow-auto rounded-xl border border-gray-200">
                        <table class="min-w-full text-sm ds-table">
                            <thead>
                                <tr>
                                    <th class="px-3 py-2 text-left">Deal No</th>
                                    <th class="px-3 py-2 text-left">Property</th>
                                    <th class="px-3 py-2 text-left">Period</th>
                                    <th class="px-3 py-2 text-left">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php $__currentLoopData = $paidNotSettledDeals; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-3 py-2 font-semibold" style="color:#0b2a4a"><?php echo e($d->deal_no ?? ('#'.$d->id)); ?></td>
                                        <td class="px-3 py-2 text-gray-700"><?php echo e($d->property_address ?? '—'); ?></td>
                                        <td class="px-3 py-2 text-gray-700"><?php echo e($d->period ?? '—'); ?></td>
                                        <td class="px-3 py-2">
                                            <a href="<?php echo e(route('admin.deals.settle', $d)); ?>" class="nexus-btn-primary text-xs px-3 py-1.5">
                                                Open settlement
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 flex justify-end">
                        <button type="button" @click="openPaidExceptions = false" class="nexus-btn-outline text-sm">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

     <?php $__env->slot('header', null, []); ?> 
        <div style="background:#0b2a4a;" class="rounded-2xl px-6 py-4">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                <div>
                    <h2 class="text-xl font-bold text-white leading-tight">Deal Register</h2>
                    <div class="text-sm text-white/60">Operational view for tracking deal status, settlement, and audit log.</div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="inline-flex items-center rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-white ring-1 ring-white/20">
                        <?php echo e($deals->count()); ?> deals
                    </span>
                    <a href="<?php echo e(route('admin.deals.create')); ?>"
                       class="inline-flex items-center gap-2 rounded-xl bg-white/20 px-4 py-2 text-sm font-semibold text-white hover:bg-white/30">
                        <span class="text-base leading-none">+</span>
                        <span>Add Deal</span>
                    </a>
                </div>
            </div>
        </div>
     <?php $__env->endSlot(); ?>

    <div class="space-y-6">

        <?php if($errors->any()): ?>
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <?php echo e($errors->first()); ?>

            </div>
        <?php endif; ?>

        
        <div>
            <h2 class="ds-section-header">Deals Overview</h2>
            <div class="ds-section-sub mb-4">All deals sorted by deal number (newest first).</div>

            <div class="ds-status-card overflow-hidden" style="padding:0">
                <div class="table-scroll">
                    <table class="ds-table min-w-full text-sm table-sticky">
                        <thead>
                            <tr>
                                <th class="text-left px-4 py-3">Deal</th>
                                <th class="text-left px-4 py-3">Property</th>
                                <th class="text-left px-4 py-3">Branch / Period</th>
                                <th class="text-right px-4 py-3">Selling Price</th>
                                <th class="text-right px-4 py-3">Our Total (Ex VAT)</th>
                                <th class="text-center px-4 py-3">Status</th>
                                <th class="text-center px-4 py-3">Commission</th>
                                <th class="text-center px-4 py-3">Quick Update</th>
                                <th class="text-right px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $__currentLoopData = $deals->sortByDesc('deal_no'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $deal): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php
                                    $b = $branches->firstWhere('id', $deal->branch_id);
                                    $acceptedMap = ['P'=>'Pending','D'=>'Declined','G'=>'Granted','R'=>'Registered'];
                                    $asVal = (string)($deal->accepted_status ?? '');
                                    $csVal = (string)($deal->commission_status ?? '');
                                    $acceptedLabel = $acceptedMap[$asVal] ?? ($asVal ?: '—');

                                    $statusBadge = 'ds-badge-default';
                                    if ($asVal === 'G') $statusBadge = 'ds-badge-success';
                                    elseif ($asVal === 'R') $statusBadge = 'ds-badge-info';
                                    elseif ($asVal === 'D') $statusBadge = 'ds-badge-danger';
                                    elseif ($asVal === 'P') $statusBadge = 'ds-badge-warning';

                                    $commBadge = 'ds-badge-default';
                                    if ($csVal === 'Paid') $commBadge = 'ds-badge-paid';
                                    elseif ($csVal === 'Not Paid') $commBadge = 'ds-badge-notpaid';
                                    elseif ($csVal === 'Loss') $commBadge = 'ds-badge-loss';
                                ?>

                                <tr>
                                    <td class="px-4 py-3">
                                        <a href="<?php echo e(route('admin.deals.edit', $deal)); ?>" class="ds-agent-link font-bold"><?php echo e($deal->deal_no); ?></a>
                                        <div class="text-xs text-gray-500 mt-0.5"><?php echo e($deal->deal_date ? \Carbon\Carbon::parse($deal->deal_date)->format('d M Y') : '—'); ?></div>
                                    </td>

                                    <td class="px-4 py-3">
                                        <div class="text-sm text-gray-900 font-medium"><?php echo e(\Illuminate\Support\Str::limit($deal->property_address, 40) ?: '—'); ?></div>
                                        <div class="text-xs text-gray-500 mt-0.5"><?php echo e($deal->seller_name ?: '—'); ?> &rarr; <?php echo e($deal->buyer_name ?: '—'); ?></div>
                                    </td>

                                    <td class="px-4 py-3">
                                        <div class="font-medium" style="color:#0b2a4a"><?php echo e($b?->name ?? '—'); ?></div>
                                        <div class="text-xs text-gray-500 mt-0.5"><?php echo e($deal->period ?: '—'); ?></div>
                                    </td>

                                    <td class="px-4 py-3 text-right">
                                        <div class="font-semibold ds-value">R <?php echo e(number_format((float)$deal->property_value, 0)); ?></div>
                                    </td>

                                    <td class="px-4 py-3 text-right">
                                        <div class="font-bold ds-value">R <?php echo e(number_format((float)$deal->totalOurCommission(), 0)); ?></div>
                                    </td>

                                    <td class="px-4 py-3 text-center">
                                        <span class="ds-badge <?php echo e($statusBadge); ?>"><?php echo e($acceptedLabel); ?></span>
                                    </td>

                                    <td class="px-4 py-3 text-center">
                                        <span class="ds-badge <?php echo e($commBadge); ?>"><?php echo e($csVal ?: '—'); ?></span>
                                    </td>

                                    <td class="px-4 py-3">
                                        <form method="POST" action="<?php echo e(route('admin.deals.quickUpdate', $deal)); ?>" class="flex items-center gap-1.5">
                                            <?php echo csrf_field(); ?>
                                            <select name="accepted_status" class="h-7 rounded-lg border-gray-200 text-xs px-1.5 py-0">
                                                <option value="">—</option>
                                                <option value="P" <?php echo e($asVal === 'P' ? 'selected' : ''); ?>>Pend</option>
                                                <option value="G" <?php echo e($asVal === 'G' ? 'selected' : ''); ?>>Grant</option>
                                                <option value="R" <?php echo e($asVal === 'R' ? 'selected' : ''); ?>>Reg</option>
                                                <option value="D" <?php echo e($asVal === 'D' ? 'selected' : ''); ?>>Decl</option>
                                            </select>
                                            <select name="commission_status" class="h-7 rounded-lg border-gray-200 text-xs px-1.5 py-0">
                                                <option value="">—</option>
                                                <option value="Not Paid" <?php echo e($csVal === 'Not Paid' ? 'selected' : ''); ?>>Not Paid</option>
                                                <option value="Paid" <?php echo e($csVal === 'Paid' ? 'selected' : ''); ?>>Paid</option>
                                                <option value="Loss" <?php echo e($csVal === 'Loss' ? 'selected' : ''); ?>>Loss</option>
                                            </select>
                                            <button type="submit" class="nexus-btn-primary text-xs px-2 py-1" style="font-size:0.6875rem">Save</button>
                                        </form>
                                    </td>

                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-1.5">
                                            <a href="<?php echo e(route('admin.deals.log', $deal)); ?>" class="nexus-btn-outline text-xs px-2 py-1">Log</a>
                                            <a href="<?php echo e(route('admin.deals.edit', $deal)); ?>" class="nexus-btn-outline text-xs px-2 py-1">Edit</a>
                                            <?php if(auth()->user()->isEffectiveAdmin()): ?>
                                                <a href="<?php echo e(route('admin.deals.settle', $deal)); ?>" class="nexus-btn-primary text-xs px-2 py-1">Pay</a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>

                            <?php if($deals->isEmpty()): ?>
                                <tr>
                                    <td colspan="9" class="px-5 py-10 text-center text-sm text-gray-500">No deals found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
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
<?php /**PATH C:\Users\johan\OneDrive\Documents\GitHub\hfc-dash\resources\views/admin/deals/index.blade.php ENDPATH**/ ?>