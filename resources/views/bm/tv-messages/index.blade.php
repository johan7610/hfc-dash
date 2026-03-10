@extends('layouts.corex')

@section('content')
@php
    $u = auth()->user();
    $bmBranchId = (int)($u?->effectiveBranchId() ?? ($u->branch_id ?? 0));
    $bmBranchName = '';
    if (!empty($branches) && $bmBranchId) {
        $found = $branches->firstWhere('id', $bmBranchId);
        $bmBranchName = $found?->name ?? '';
    }
@endphp

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    {{-- Page Header --}}
    <div style="background: var(--brand-default, #0b2a4a);" class="rounded-md px-6 py-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold text-white leading-tight tracking-tight">TV Messages (Branch)</h2>
                <div class="text-sm text-white/60">
                    Add messages for your branch TV. Admin can add global messages visible on all TVs.
                </div>
            </div>
            @if($bmBranchId)
                <div class="inline-flex items-center gap-2 text-xs px-3 py-1 rounded-md bg-white/10 text-white/80">
                    <span class="opacity-70">Branch:</span>
                    <span class="font-semibold">{{ $bmBranchName ?: ('#'.$bmBranchId) }}</span>
                </div>
            @endif
        </div>
    </div>

    {{-- Flash Messages --}}
    @if (session('status'))
        <div class="rounded-md px-4 py-3 text-sm" style="border: 1px solid color-mix(in srgb, #10b981 30%, transparent); background: color-mix(in srgb, #10b981 10%, var(--surface)); color: #10b981;">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="rounded-md px-4 py-3 text-sm" style="border: 1px solid color-mix(in srgb, #ef4444 30%, transparent); background: color-mix(in srgb, #ef4444 10%, var(--surface)); color: #ef4444;">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Add Message Form --}}
    <div class="rounded-md p-5" style="background: var(--surface); border: 1px solid var(--border);">
        <h3 class="text-sm font-semibold mb-4" style="color: var(--text-primary);">Add TV Message</h3>

        <form method="POST" action="{{ route('bm.tv-messages.store') }}"
              class="space-y-4">
            @csrf
            <input type="hidden" name="branch_id" value="{{ $bmBranchId }}">

            <div>
                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Message</label>
                <input name="message" required
                       class="w-full rounded-md text-sm px-3 py-2 transition-all duration-300 placeholder:opacity-50"
                       style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-primary);"
                       onfocus="this.style.borderColor='var(--brand-button, #0ea5e9)';this.style.boxShadow='0 0 0 2px color-mix(in srgb, var(--brand-button, #0ea5e9) 20%, transparent)'"
                       onblur="this.style.borderColor='var(--border)';this.style.boxShadow='none'"
                       placeholder="Motivational message, announcement, etc.">

                <div class="mt-3">
                    <div class="text-[11px] uppercase tracking-wider mb-2" style="color: var(--text-muted);">Insert values</div>
                    <div class="flex flex-wrap gap-1.5">
                        @php
                            $__tvPh = [
                                '{{branch_name}}','{{period}}',
                                '{{deals_target}}','{{deals_actual}}','{{deals_remaining}}',
                                '{{value_target}}','{{value_actual}}','{{value_remaining}}',
                                '{{points_target}}','{{points_actual}}','{{points_status}}',
                                '{{listings_active}}','{{listings_avg_dom}}','{{listings_stale}}','{{listings_expiring}}','{{listings_expired}}',
                            ];
                        @endphp
                        @foreach($__tvPh as $ph)
                            <button type="button"
                                class="px-2 py-1 rounded-md text-xs transition-all duration-300"
                                style="border: 1px solid var(--border); background: var(--surface-2); color: var(--text-secondary);"
                                onmouseover="this.style.background='var(--surface)';this.style.color='var(--text-primary)'"
                                onmouseout="this.style.background='var(--surface-2)';this.style.color='var(--text-secondary)'"
                                data-ph="{{ $ph }}" onclick="window.__tvInsertPh(this)">
                                {{ $ph }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-end">
                <div class="md:col-span-3">
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Show on</label>
                    <select name="display_area"
                            class="w-full rounded-md text-sm px-3 py-2 transition-all duration-300"
                            style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-primary);">
                        <option value="both" selected>Hero + Ticker</option>
                        <option value="hero">Hero only</option>
                        <option value="ticker">Ticker only</option>
                    </select>
                </div>

                <div class="md:col-span-2 flex items-center gap-2">
                    <input type="hidden" name="is_enabled" value="0">
                    <input type="checkbox" name="is_enabled" value="1" checked
                           class="rounded-md" style="border-color: var(--border); accent-color: var(--brand-button, #0ea5e9);">
                    <span class="text-sm" style="color: var(--text-secondary);">Enabled</span>
                </div>

                <div class="md:col-span-2">
                    <button class="w-full corex-btn-primary text-sm">Add Message</button>
                </div>
            </div>
        </form>
    </div>

    {{-- Branch Messages List --}}
    <div class="rounded-md overflow-hidden" style="border: 1px solid var(--border); background: var(--surface);">
        <div class="px-5 py-3 flex items-center justify-between" style="border-bottom: 1px solid var(--border);">
            <div class="text-sm font-semibold" style="color: var(--text-primary);">Your Branch Messages</div>
            <div class="text-xs" style="color: var(--text-muted);">{{ count($messages) }} message{{ count($messages) !== 1 ? 's' : '' }}</div>
        </div>

        <div>
            @forelse($messages as $m)
                <div class="px-5 py-4 space-y-3" style="border-bottom: 1px solid var(--border);">
                    <form method="POST"
                          action="{{ route('bm.tv-messages.update', $m->id) }}"
                          class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                        @csrf
                        <input type="hidden" name="branch_id" value="{{ $bmBranchId }}">

                        <div class="md:col-span-7">
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Message</label>
                            <input name="message"
                                   value="{{ $m->message }}"
                                   class="w-full rounded-md text-sm px-3 py-2 transition-all duration-300"
                                   style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-primary);"
                                   onfocus="this.style.borderColor='var(--brand-button, #0ea5e9)';this.style.boxShadow='0 0 0 2px color-mix(in srgb, var(--brand-button, #0ea5e9) 20%, transparent)'"
                                   onblur="this.style.borderColor='var(--border)';this.style.boxShadow='none'">
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Show on</label>
                            <select name="display_area"
                                    class="w-full rounded-md text-sm px-3 py-2 transition-all duration-300"
                                    style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-primary);">
                                <option value="both" {{ (($m->display_area ?? 'both') === 'both') ? 'selected' : '' }}>Hero + Ticker</option>
                                <option value="hero" {{ (($m->display_area ?? 'both') === 'hero') ? 'selected' : '' }}>Hero only</option>
                                <option value="ticker" {{ (($m->display_area ?? 'both') === 'ticker') ? 'selected' : '' }}>Ticker only</option>
                            </select>
                        </div>

                        <div class="md:col-span-1 flex items-center gap-2">
                            <input type="hidden" name="is_enabled" value="0">
                            <input type="checkbox"
                                   name="is_enabled"
                                   value="1"
                                   {{ $m->is_enabled ? 'checked' : '' }}
                                   class="rounded-md" style="border-color: var(--border); accent-color: var(--brand-button, #0ea5e9);">
                            <span class="text-sm" style="color: var(--text-secondary);">On</span>
                        </div>

                        <div class="md:col-span-2 flex items-center gap-2">
                            <button class="flex-1 corex-btn-primary text-sm">Save</button>
                        </div>
                    </form>

                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="text-xs flex flex-wrap items-center gap-2" style="color: var(--text-muted);">
                            <span>
                                Created by: <span class="font-semibold" style="color: var(--text-secondary);">{{ $m->creator->name ?? 'System' }}</span>
                                <span class="opacity-70">({{ $m->creator->email ?? '-' }})</span>
                            </span>

                            @if(is_null($m->branch_id))
                                <span class="inline-flex items-center text-[11px] px-2 py-0.5 rounded-md" style="background: color-mix(in srgb, var(--brand-icon, #0ea5e9) 10%, transparent); color: var(--brand-icon, #0ea5e9);">
                                    Global
                                </span>
                            @else
                                <span class="inline-flex items-center text-[11px] px-2 py-0.5 rounded-md" style="background: color-mix(in srgb, #10b981 10%, transparent); color: #10b981;">
                                    Branch
                                </span>
                            @endif
                        </div>

                        <form method="POST"
                              action="{{ route('bm.tv-messages.delete', $m->id) }}"
                              onsubmit="return confirm('Delete message?');">
                            @csrf
                            <button class="text-xs font-semibold transition-all duration-300" style="color: #ef4444;" onmouseover="this.style.opacity='0.7'" onmouseout="this.style.opacity='1'">
                                Delete
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="px-5 py-8 text-center text-sm" style="color: var(--text-muted);">
                    No TV messages yet.
                </div>
            @endforelse
        </div>
    </div>

    {{-- Global Messages (read-only) --}}
    <div class="rounded-md overflow-hidden" style="border: 1px solid var(--border); background: var(--surface);">
        <div class="px-5 py-3 flex items-center justify-between" style="border-bottom: 1px solid var(--border);">
            <div class="text-sm font-semibold" style="color: var(--text-primary);">Global Messages (Admin)</div>
            <div class="text-xs" style="color: var(--text-muted);">{{ count($globalMessages ?? []) }} global</div>
        </div>

        <div>
            @forelse(($globalMessages ?? []) as $gm)
                <div class="px-5 py-4 space-y-3" style="border-bottom: 1px solid var(--border);">
                    <div class="grid grid-cols-1 md:grid-cols-12 gap-4 items-start">
                        <div class="md:col-span-7">
                            <div class="text-xs font-medium mb-1" style="color: var(--text-secondary);">Message</div>
                            <div class="rounded-md px-3 py-2 text-sm" style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-primary);">
                                {{ $gm->message }}
                            </div>

                            @if(!empty($gm->title))
                                <div class="mt-1.5 text-xs" style="color: var(--text-muted);">
                                    Title: <span class="font-semibold" style="color: var(--text-secondary);">{{ $gm->title }}</span>
                                </div>
                            @endif
                        </div>

                        <div class="md:col-span-3">
                            <div class="text-xs font-medium mb-1" style="color: var(--text-secondary);">Show on</div>
                            <div class="text-sm font-semibold" style="color: var(--text-primary);">
                                @php($da = $gm->display_area ?? 'both')
                                @if($da === 'hero')
                                    Hero only
                                @elseif($da === 'ticker')
                                    Ticker only
                                @else
                                    Hero + Ticker
                                @endif
                            </div>

                            <div class="mt-2 text-xs" style="color: var(--text-muted);">
                                @php($on = (bool)($gm->is_enabled ?? false))
                                Status:
                                <span class="font-semibold" style="color: {{ $on ? '#10b981' : 'var(--text-muted)' }};">
                                    {{ $on ? 'On' : 'Off' }}
                                </span>
                            </div>

                            @if(!empty($gm->starts_at) || !empty($gm->ends_at))
                                <div class="mt-1.5 text-xs" style="color: var(--text-muted);">
                                    Window:
                                    <span class="font-semibold" style="color: var(--text-secondary);">
                                        {{ $gm->starts_at ? \Illuminate\Support\Carbon::parse($gm->starts_at)->format('Y-m-d') : '—' }}
                                        →
                                        {{ $gm->ends_at ? \Illuminate\Support\Carbon::parse($gm->ends_at)->format('Y-m-d') : '—' }}
                                    </span>
                                </div>
                            @endif
                        </div>

                        <div class="md:col-span-2">
                            <span class="inline-flex items-center text-[11px] px-2 py-0.5 rounded-md" style="background: color-mix(in srgb, var(--brand-icon, #0ea5e9) 10%, transparent); color: var(--brand-icon, #0ea5e9);">
                                Global
                            </span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="text-xs flex flex-wrap items-center gap-2" style="color: var(--text-muted);">
                            <span>
                                Created by: <span class="font-semibold" style="color: var(--text-secondary);">{{ $gm->creator->name ?? 'System' }}</span>
                                <span class="opacity-70">({{ $gm->creator->email ?? '-' }})</span>
                            </span>
                        </div>
                        <div class="text-xs" style="color: var(--text-muted);">Read-only (managed by Admin)</div>
                    </div>
                </div>
            @empty
                <div class="px-5 py-8 text-center text-sm" style="color: var(--text-muted);">
                    No global messages.
                </div>
            @endforelse
        </div>
    </div>

</div>

{{-- TV_INSERT_PH_JS_2026 --}}
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

@endsection
