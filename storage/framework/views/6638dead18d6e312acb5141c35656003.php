<?php $__env->startSection('content'); ?>
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    <div style="background:#0b2a4a;" class="rounded-2xl px-6 py-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold text-white leading-tight">Daily Activity (v2)</h2>
                <div class="text-sm text-white/60">
                    Date: <span class="font-medium text-white"><?php echo e($selectedDate); ?></span>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="<?php echo e(route('agent.daily.print', ['date' => $selectedDate])); ?>" target="_blank"
                   class="nexus-btn-outline text-sm">
                   Print Sheet
                </a>
                <form method="GET" action="<?php echo e(route('agent.daily')); ?>" class="flex items-center gap-2">
                    <input
                        type="date"
                        name="date"
                        value="<?php echo e($selectedDate); ?>"
                        class="rounded-lg border-0 bg-white/10 text-white text-sm px-3 py-1.5 placeholder:text-white/40"
                        onchange="this.form.submit()"
                    />
                </form>
            </div>
        </div>
    </div>


    <?php if(isset($agentDailyWeek) && isset($agentDailyWeek['days'])): ?>
        <div class="ds-status-card p-3">
            <div class="flex flex-wrap gap-2">
                <?php $__currentLoopData = $agentDailyWeek['days']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $d): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                    <a href="<?php echo e(route('agent.daily', ['date' => $d['date']])); ?>"
                       class="px-3 py-2 rounded-lg border text-sm
                       <?php echo e($d['is_selected'] ? 'bg-[#0b2a4a] text-white border-[#0b2a4a]' : 'bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800'); ?>">
                        <div class="font-medium"><?php echo e($d['label']); ?></div>
                        <?php if($d['is_today']): ?>
                            <div class="text-xs opacity-80">today</div>
                        <?php endif; ?>
                    </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
        </div>
    <?php endif; ?>

    
    <div class="ds-status-card" style="border-left-color: var(--ds-cyan);">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="ds-label">Month</div>
                <div class="ds-value text-lg"><?php echo e($period); ?></div>
            </div>

            <div class="grid grid-cols-3 gap-4 text-right">
                <div>
                    <div class="ds-label">Monthly target</div>
                    <div class="ds-value-lg"><?php echo e((int)($monthlyTarget ?? 0)); ?></div>
                </div>
                <div>
                    <div class="ds-label">Points MTD</div>
                    <div class="ds-value-lg"><?php echo e((int)($mtdPoints ?? 0)); ?></div>
                </div>
                <div>
                    <div class="ds-label">Remaining</div>
                    <div class="ds-value-lg"><?php echo e((int)($remainingPoints ?? 0)); ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="ds-status-card">
        <form method="POST" action="<?php echo e(route('agent.daily')); ?>">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="activity_date" value="<?php echo e($selectedDate); ?>"/>

            <div class="flex items-center justify-between gap-4">
                <div>
                    <h3 class="ds-section-header">Capture activity</h3>
                    <div class="text-sm text-slate-500 dark:text-slate-400">
                        Date: <span class="font-medium text-slate-900 dark:text-slate-100"><?php echo e($selectedDate); ?></span>
                    </div>
                </div>

                <button class="nexus-btn-primary">
                    Save
                </button>
            </div>

            <div class="mt-5 overflow-x-auto">
                <table class="min-w-full text-sm ds-table">
                    <thead class="bg-slate-50 dark:bg-slate-900/40 text-slate-600 dark:text-slate-300">
                        <tr>
                            <th class="text-left py-2 pr-4 px-3">Activity</th>
                            <th class="text-left py-2 pr-4 px-3 w-32">Weight</th>
                            <th class="text-left py-2 pr-4 px-3 w-40">Done / Qty</th>
                            <th class="text-left py-2 pr-0 px-3 w-40">Points</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        <?php $__empty_1 = true; $__currentLoopData = $definitions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $def): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <?php
                                $val = (int)($values[$def->id] ?? 0);
                                $pts = $val * (int)$def->weight;
                            ?>
                            <tr>
                                <td class="py-3 pr-4 px-3">
                                    <div class="font-medium text-slate-900 dark:text-slate-100"><?php echo e($def->name); ?></div>
                                </td>
                                <td class="py-3 pr-4 px-3 text-slate-700 dark:text-slate-200"><?php echo e((int)$def->weight); ?></td>
                                <td class="py-3 pr-4 px-3">
                                    <?php ($mode = (string)($def->scoring_mode ?? 'count')); ?>
                                    <?php if($mode === 'once'): ?>
                                        <div class="flex items-center gap-3">
                                            <input type="hidden" name="values[<?php echo e($def->id); ?>]" value="0">
                                            <label class="inline-flex items-center gap-2">
                                                <input
                                                    type="checkbox"
                                                    name="values[<?php echo e($def->id); ?>]"
                                                    value="1"
                                                    <?php if($val > 0): echo 'checked'; endif; ?>
                                                    class="h-5 w-5 rounded border-slate-300 dark:border-slate-600"
                                                >
                                                <span class="text-sm text-slate-700 dark:text-slate-200">Done</span>
                                            </label>
                                        </div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">Tick to score once today.</div>
                                    <?php else: ?>
                                        <input
                                            type="number"
                                            min="0"
                                            step="1"
                                            name="values[<?php echo e($def->id); ?>]"
                                            value="<?php echo e($val); ?>"
                                            class="w-28 border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 rounded-lg px-3 py-2"
                                        />
                                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">Enter quantity to score per action.</div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 pr-0 px-3 text-slate-900 dark:text-slate-100">
                                    <?php echo e($pts); ?>

                                </td>
                            </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <tr>
                                <td colspan="4" class="py-6 text-center text-slate-500 dark:text-slate-400">
                                    No enabled activity definitions found for your branch.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 text-sm text-slate-700 dark:text-slate-200">
                <span class="font-medium">Total points today:</span> <?php echo e($totalPoints); ?>

            </div>
        </form>
    </div>

    <div class="text-xs text-slate-500 dark:text-slate-400">
        v2 uses activity_definitions + daily_activity_entries (no legacy dynamic columns).
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.nexus', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\johan\OneDrive\Documents\GitHub\hfc-dash\resources\views/agent/daily-v2.blade.php ENDPATH**/ ?>