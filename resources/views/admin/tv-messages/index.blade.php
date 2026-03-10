@extends('layouts.corex')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    {{-- Page Header --}}
    <div style="background: var(--brand-default, #0b2a4a);" class="rounded-md px-6 py-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <h2 class="text-xl font-bold text-white leading-tight tracking-tight">TV Messages (Admin)</h2>
                <div class="text-sm text-white/60">Create global messages (all TVs) or branch-specific messages.</div>
            </div>
            <form method="GET" action="{{ route('admin.tv-messages') }}">
                <select name="status" onchange="this.form.submit()"
                        class="px-3 py-2 rounded-md text-sm transition-all duration-300"
                        style="border: 1px solid rgba(255,255,255,0.3); background: transparent; color: white;">
                    <option value="active" style="background: var(--surface); color: var(--text-primary);" {{ request('status', 'active') === 'active' ? 'selected' : '' }}>Active</option>
                    <option value="archived" style="background: var(--surface); color: var(--text-primary);" {{ request('status') === 'archived' ? 'selected' : '' }}>Archived</option>
                </select>
            </form>
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
    @if(!$showArchived)
    <div class="rounded-md p-5" style="background: var(--surface); border: 1px solid var(--border);">
        <h3 class="text-sm font-semibold mb-4" style="color: var(--text-primary);">Add TV Message</h3>

        <form method="POST" action="{{ route('admin.tv-messages.store') }}"
              class="space-y-4">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <div class="md:col-span-3">
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Branch (blank = global)</label>
                    <select name="branch_id"
                            class="w-full rounded-md text-sm px-3 py-2 transition-all duration-300"
                            style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-primary);">
                        <option value="">Global</option>
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}">{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="md:col-span-9">
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
    @endif

    {{-- Messages List --}}
    <div class="rounded-md overflow-hidden" style="border: 1px solid var(--border); background: var(--surface);">
        <div class="px-5 py-3 flex items-center justify-between" style="border-bottom: 1px solid var(--border);">
            <div class="text-sm font-semibold" style="color: var(--text-primary);">{{ $showArchived ? 'Archived Messages' : 'Existing Messages' }}</div>
            <div class="text-xs" style="color: var(--text-muted);">{{ count($messages) }} total</div>
        </div>

        <div>
            @forelse($messages as $m)
                <div class="px-5 py-4 space-y-3" style="border-bottom: 1px solid var(--border);">

                    @if($showArchived)
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <div class="text-sm" style="color: var(--text-primary);">{{ $m->message }}</div>
                                <div class="text-xs mt-1.5 flex flex-wrap items-center gap-2" style="color: var(--text-muted);">
                                    <span class="inline-flex items-center text-[11px] px-2 py-0.5 rounded-md" style="background: color-mix(in srgb, #f59e0b 10%, transparent); color: #f59e0b;">
                                        Archived
                                    </span>
                                    <span>{{ $m->branch?->name ?? 'Global' }}</span>
                                    <span>&middot;</span>
                                    <span>Created by: <span class="font-semibold" style="color: var(--text-secondary);">{{ $m->creator->name ?? 'System' }}</span></span>
                                </div>
                            </div>
                            <form method="POST" action="{{ route('admin.tv-messages.restore', $m->id) }}" class="inline shrink-0">
                                @csrf
                                <button type="submit" class="text-xs font-semibold transition-all duration-300" style="color: #10b981;" onmouseover="this.style.opacity='0.7'" onmouseout="this.style.opacity='1'">
                                    Restore
                                </button>
                            </form>
                        </div>
                    @else
                        <form method="POST"
                              action="{{ route('admin.tv-messages.update', $m->id) }}"
                              class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                            @csrf

                            <div class="md:col-span-3">
                                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Branch</label>
                                <select name="branch_id"
                                        class="w-full rounded-md text-sm px-3 py-2 transition-all duration-300"
                                        style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-primary);">
                                    <option value="">Global</option>
                                    @foreach($branches as $b)
                                        <option value="{{ $b->id }}"
                                            {{ $m->branch_id == $b->id ? 'selected' : '' }}>
                                            {{ $b->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="md:col-span-5">
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

                            <div class="md:col-span-1">
                                <button class="w-full corex-btn-primary text-sm">Save</button>
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
                                        {{ $m->branch?->name ?? 'Branch' }}
                                    </span>
                                @endif
                            </div>

                            <form method="POST"
                                  action="{{ route('admin.tv-messages.delete', $m->id) }}"
                                  onsubmit="return confirm('Delete message?');">
                                @csrf
                                <button class="text-xs font-semibold transition-all duration-300" style="color: #ef4444;" onmouseover="this.style.opacity='0.7'" onmouseout="this.style.opacity='1'">
                                    Delete
                                </button>
                            </form>
                        </div>
                    @endif

                </div>

            @empty
                <div class="px-5 py-8 text-center text-sm" style="color: var(--text-muted);">
                    {{ $showArchived ? 'No archived TV messages.' : 'No TV messages yet.' }}
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

    if (typeof el.selectionStart === 'number' && typeof el.selectionEnd === 'number') {
      const start = el.selectionStart;
      const end = el.selectionEnd;
      const v = el.value || '';
      el.value = v.slice(0, start) + text + v.slice(end);

      const pos = start + text.length;
      el.selectionStart = el.selectionEnd = pos;
      return;
    }

    el.value = (el.value || '') + text;
  }

  window.__tvInsertPh = function(btn){
    try {
      const token = btn && btn.getAttribute('data-ph') ? btn.getAttribute('data-ph') : '';
      if (!token) return;

      const form = btn.closest('form');
      let input = form ? form.querySelector('input[name="message"]') : null;

      if (!input) input = document.querySelector('input[name="message"]');

      insertAtCursor(input, token);
    } catch (e) {
      console && console.warn && console.warn('tv placeholder insert failed', e);
    }
  };
})();
</script>

@endsection
