<?php $__env->startSection('content'); ?>
<?php
    $u = auth()->user();
    $bmBranchId = (int)($u?->effectiveBranchId() ?? ($u->branch_id ?? 0));
    $bmBranchName = '';
    if (!empty($branches) && $bmBranchId) {
        $found = $branches->firstWhere('id', $bmBranchId);
        $bmBranchName = $found?->name ?? '';
    }
?>

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    <div style="background:#0b2a4a;" class="rounded-2xl px-6 py-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
            <div>
                <h2 class="text-xl font-bold text-white leading-tight">TV Messages (Branch)</h2>
                <div class="text-sm text-white/60">
                    Add messages for your branch TV. Admin can add global messages visible on all TVs.
                </div>
            </div>
            <?php if($bmBranchId): ?>
                <div class="inline-flex items-center gap-2 text-xs px-3 py-1 rounded-full bg-white/10 text-white/80">
                    <span class="opacity-70">Branch:</span>
                    <span class="font-semibold"><?php echo e($bmBranchName ?: ('#'.$bmBranchId)); ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if(session('status')): ?>
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 text-emerald-900 px-4 py-3">
            <?php echo e(session('status')); ?>

        </div>
    <?php endif; ?>

    <?php if($errors->any()): ?>
        <div class="rounded-2xl border border-red-200 bg-red-50 text-red-900 px-4 py-3">
            <?php echo e($errors->first()); ?>

        </div>
    <?php endif; ?>

    
    <div class="ds-status-card p-4">
        <h3 class="ds-section-header mb-3">Add TV message</h3>

        <form method="POST" action="<?php echo e(route('bm.tv-messages.store')); ?>"
              class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
            <?php echo csrf_field(); ?>

            <input type="hidden" name="branch_id" value="<?php echo e($bmBranchId); ?>">

            <div class="md:col-span-10">
                <label class="block text-xs text-slate-600 dark:text-slate-300 mb-1">
                    Message
                </label>
                <input name="message" required
                       class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm text-slate-900 dark:text-slate-100"
                       placeholder="Motivational message, announcement, etc.">

<div class="mt-2">
    <div class="text-[11px] uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Insert values</div>
    <div class="flex flex-wrap gap-2">
        <?php
            $__tvPh = [
                '{{branch_name}}','{{period}}',
                '{{deals_target}}','{{deals_actual}}','{{deals_remaining}}',
                '{{value_target}}','{{value_actual}}','{{value_remaining}}',
                '{{points_target}}','{{points_actual}}','{{points_status}}',
                '{{listings_active}}','{{listings_avg_dom}}','{{listings_stale}}','{{listings_expiring}}','{{listings_expired}}',
            ];
        ?>
        <?php $__currentLoopData = $__tvPh; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ph): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <button type="button"
                class="px-2 py-1 rounded-full border border-slate-200 dark:border-slate-800 bg-white/70 dark:bg-slate-900 text-slate-700 dark:text-slate-200 text-xs hover:bg-white dark:hover:bg-slate-800"
                data-ph="<?php echo e($ph); ?>" onclick="window.__tvInsertPh(this)" >
                <?php echo e($ph); ?>

            </button>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>
</div>
            </div>

            <div class="md:col-span-2">
                <label class="block text-xs text-slate-600 dark:text-slate-300 mb-1">Show on</label>
                <select name="display_area" class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm">
                    <option value="both" selected>Hero + Ticker</option>
                    <option value="hero">Hero only</option>
                    <option value="ticker">Ticker only</option>
                </select>
            </div>


            <div class="md:col-span-1 flex items-center gap-2">
                <input type="hidden" name="is_enabled" value="0">
                <input type="checkbox" name="is_enabled" value="1" checked class="rounded border-slate-300 dark:border-slate-700">
                <span class="text-sm text-slate-700 dark:text-slate-200">On</span>
            </div>

            <div class="md:col-span-1">
                <button class="w-full nexus-btn-primary text-sm">
                    Add
                </button>
            </div>

        </form>
    </div>

    
    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 overflow-hidden">

        <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-800 flex justify-between">
            <div class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                Your branch messages
            </div>
            <div class="text-xs text-slate-500 dark:text-slate-400">
                <?php echo e(count($messages)); ?> branch
            </div>
        </div>

        <div class="divide-y divide-slate-200 dark:divide-slate-800">

            <?php $__empty_1 = true; $__currentLoopData = $messages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>

                <div class="p-4 space-y-2">

                    <form method="POST"
                          action="<?php echo e(route('bm.tv-messages.update', $m->id)); ?>"
                          class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                        <?php echo csrf_field(); ?>

                        
                        <input type="hidden" name="branch_id" value="<?php echo e($bmBranchId); ?>">

                        <div class="md:col-span-9">
                            <label class="block text-xs text-slate-600 dark:text-slate-300 mb-1">Message</label>
                            <input name="message"
                                   value="<?php echo e($m->message); ?>"
                                   class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 px-3 py-2 text-sm text-slate-900 dark:text-slate-100">
                        </div>

                        <div class="md:col-span-2">
                            <label class="text-xs">Show on</label>
                            <select name="display_area" class="w-full rounded-lg border px-3 py-2 text-sm">
                                <option value="both" <?php echo e((($m->display_area ?? 'both') === 'both') ? 'selected' : ''); ?>>Hero + Ticker</option>
                                <option value="hero" <?php echo e((($m->display_area ?? 'both') === 'hero') ? 'selected' : ''); ?>>Hero only</option>
                                <option value="ticker" <?php echo e((($m->display_area ?? 'both') === 'ticker') ? 'selected' : ''); ?>>Ticker only</option>
                            </select>
                        </div>


                        <div class="md:col-span-2 flex items-center gap-2">
                            <input type="hidden" name="is_enabled" value="0">
                            <input type="checkbox"
                                   name="is_enabled"
                                   value="1"
                                   <?php echo e($m->is_enabled ? 'checked' : ''); ?>

                                   class="rounded border-slate-300 dark:border-slate-700">
                            <span class="text-sm text-slate-700 dark:text-slate-200">On</span>
                        </div>

                        <div class="md:col-span-1">
                            <button class="w-full px-3 py-2 rounded-lg bg-slate-900 text-white hover:bg-slate-800 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100 text-sm font-semibold">
                                Save
                            </button>
                        </div>

                    </form>

                    <div class="text-xs text-slate-500 dark:text-slate-400 flex flex-wrap items-center gap-2">
                        <span>
                            Created by: <span class="font-semibold"><?php echo e($m->creator->name ?? 'System'); ?></span>
                            <span class="opacity-70">(<?php echo e($m->creator->email ?? '-'); ?>)</span>
                        </span>

                        <?php if(is_null($m->branch_id)): ?>
                            <span class="inline-flex items-center text-[11px] px-2 py-0.5 rounded-full bg-sky-50 text-[#0b2a4a] dark:bg-sky-500/10 dark:text-sky-200">
                                Global
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center text-[11px] px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-200">
                                Branch
                            </span>
                        <?php endif; ?>
                    </div>

                    <form method="POST"
                          action="<?php echo e(route('bm.tv-messages.delete', $m->id)); ?>"
                          onsubmit="return confirm('Delete message?');">
                        <?php echo csrf_field(); ?>
                        <button class="text-xs font-semibold text-red-600 hover:text-red-700">
                            Delete
                        </button>
                    </form>

                </div>

            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>

                <div class="p-6 text-sm text-slate-500 dark:text-slate-400">
                    No TV messages yet.
                </div>

            <?php endif; ?>

        </div>


    
    <div class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 overflow-hidden">
        <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-800 flex justify-between">
            <div class="text-sm font-semibold text-slate-900 dark:text-slate-100">
                Global messages (Admin)
            </div>
            <div class="text-xs text-slate-500 dark:text-slate-400">
                <?php echo e(count($globalMessages ?? [])); ?> global
            </div>
        </div>

        <div class="divide-y divide-slate-200 dark:divide-slate-800">

            <?php $__empty_1 = true; $__currentLoopData = ($globalMessages ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gm): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>

                <div class="p-4 space-y-2">
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-start">
                        <div class="md:col-span-9">
                            <div class="text-xs text-slate-500 dark:text-slate-400 mb-1">Message</div>
                            <div class="rounded-lg border border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-900 px-3 py-2 text-sm text-slate-800 dark:text-slate-100">
                                <?php echo e($gm->message); ?>

                            </div>

                            <?php if(!empty($gm->title)): ?>
                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    Title: <span class="font-semibold"><?php echo e($gm->title); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="md:col-span-2">
                            <div class="text-xs text-slate-500 dark:text-slate-400 mb-1">Show on</div>
                            <div class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                <?php ($da = $gm->display_area ?? 'both'); ?>
                                <?php if($da === 'hero'): ?>
                                    Hero only
                                <?php elseif($da === 'ticker'): ?>
                                    Ticker only
                                <?php else: ?>
                                    Hero + Ticker
                                <?php endif; ?>
                            </div>

                            <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                <?php ($on = (bool)($gm->is_enabled ?? false)); ?>
                                Status:
                                <span class="font-semibold <?php echo e($on ? 'text-emerald-700 dark:text-emerald-300' : 'text-slate-500 dark:text-slate-400'); ?>">
                                    <?php echo e($on ? 'On' : 'Off'); ?>

                                </span>
                            </div>

                            <?php if(!empty($gm->starts_at) || !empty($gm->ends_at)): ?>
                                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    Window:
                                    <span class="font-semibold">
                                        <?php echo e($gm->starts_at ? \Illuminate\Support\Carbon::parse($gm->starts_at)->format('Y-m-d') : '—'); ?>

                                        →
                                        <?php echo e($gm->ends_at ? \Illuminate\Support\Carbon::parse($gm->ends_at)->format('Y-m-d') : '—'); ?>

                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="md:col-span-1">
                            <span class="inline-flex items-center text-[11px] px-2 py-0.5 rounded-full bg-sky-50 text-[#0b2a4a] dark:bg-sky-500/10 dark:text-sky-200">
                                Global
                            </span>
                        </div>
                    </div>

                    <div class="text-xs text-slate-500 dark:text-slate-400 flex flex-wrap items-center gap-2">
                        <span>
                            Created by: <span class="font-semibold"><?php echo e($gm->creator->name ?? 'System'); ?></span>
                            <span class="opacity-70">(<?php echo e($gm->creator->email ?? '-'); ?>)</span>
                        </span>
                    </div>

                    <div class="text-xs text-slate-500 dark:text-slate-400">
                        Read-only (managed by Admin).
                    </div>
                </div>

            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>

                <div class="p-6 text-sm text-slate-500 dark:text-slate-400">
                    No global messages.
                </div>

            <?php endif; ?>

        </div>
    </div>

    </div>

</div>


<script>
(function(){
  function insertAtCursor(el, text) {
    if (!el) return;
    el.focus();

    // Most inputs support selectionStart/selectionEnd
    if (typeof el.selectionStart === 'number' && typeof el.selectionEnd === 'number') {
      const start = el.selectionStart;
      const end = el.selectionEnd;
      const v = el.value || '';
      el.value = v.slice(0, start) + text + v.slice(end);

      const pos = start + text.length;
      el.selectionStart = el.selectionEnd = pos;
      return;
    }

    // Fallback: append
    el.value = (el.value || '') + text;
  }

  window.__tvInsertPh = function(btn){
    try {
      const token = btn && btn.getAttribute('data-ph') ? btn.getAttribute('data-ph') : '';
      if (!token) return;

      // Prefer the input in the same form as the clicked chip
      const form = btn.closest('form');
      let input = form ? form.querySelector('input[name="message"]') : null;

      // Fallback: first message input on page
      if (!input) input = document.querySelector('input[name="message"]');

      insertAtCursor(input, token);
    } catch (e) {
      // swallow errors (TV screens / admin pages should never crash from this)
      console && console.warn && console.warn('tv placeholder insert failed', e);
    }
  };
})();
</script>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.nexus', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH C:\Users\johan\OneDrive\Documents\GitHub\hfc-dash\resources\views/bm/tv-messages/index.blade.php ENDPATH**/ ?>