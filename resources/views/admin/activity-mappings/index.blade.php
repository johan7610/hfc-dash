@extends('layouts.corex-app')

@section('corex-content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 py-6 space-y-5">

    <div class="rounded-md px-6 py-5" style="background: var(--brand-default, #0b2a4a);">
        <h1 class="text-xl font-bold text-white leading-tight">Activity Points → Calendar Mappings</h1>
        <p class="text-sm text-white/60 mt-1">Map each actionable calendar event class to the activity definition it should auto-credit. M6.3's observer reads this table when an event is created and credits a provisional point to the event's agent.</p>
    </div>

    @if(session('success'))
    <div class="rounded-md px-4 py-3 text-sm font-medium" style="background: color-mix(in srgb, var(--ds-green) 10%, transparent); border:1px solid color-mix(in srgb, var(--ds-green) 30%, transparent); color: var(--text-primary);">{{ session('success') }}</div>
    @endif
    @if($errors->any())
    <div class="rounded-md px-4 py-3 text-sm font-medium" style="background: color-mix(in srgb, var(--ds-crimson) 10%, transparent); border:1px solid color-mix(in srgb, var(--ds-crimson) 30%, transparent); color: var(--text-primary);">
        @foreach($errors->all() as $err) <div>{{ $err }}</div> @endforeach
    </div>
    @endif

    {{-- Existing mappings grouped by event class --}}
    <div class="rounded-md" style="background:var(--surface); border:1px solid var(--border);">
        <div class="px-4 py-3" style="border-bottom:1px solid var(--border); background:var(--surface-2);">
            <div class="text-sm font-semibold" style="color:var(--text-primary);">Current mappings ({{ $mappings->flatten()->count() }})</div>
        </div>

        @forelse($mappings as $eventClass => $rows)
        <div class="px-4 py-3" style="border-bottom:1px solid var(--border);">
            <div class="text-xs font-semibold uppercase tracking-wider mb-2" style="color:var(--text-muted);">{{ $eventClass }}</div>
            @foreach($rows as $m)
            <div class="flex items-center gap-3 py-2" style="border-top:1px solid var(--border);">
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-semibold" style="color:var(--text-primary);">
                        {{ $m->activityDefinition?->name ?? '(definition deleted)' }}
                    </div>
                    <div class="text-xs mt-0.5" style="color:var(--text-muted);">
                        {{ $m->value_per_event }} per event ·
                        {{ $m->requires_feedback ? 'requires feedback' : 'instant confirm' }} ·
                        @if($m->daily_cap) cap {{ $m->daily_cap }}/day · @endif
                        revoke after {{ $m->auto_revoke_after_hours ?? '—' }}h ·
                        back-date limit {{ $m->back_date_limit_hours }}h
                    </div>
                </div>
                <span class="inline-flex items-center px-2 py-0.5 text-xs font-semibold rounded"
                      style="background: {{ $m->is_active ? 'color-mix(in srgb, var(--ds-green) 15%, transparent)' : 'color-mix(in srgb, var(--ds-amber) 15%, transparent)' }}; color: {{ $m->is_active ? 'var(--ds-green)' : 'var(--ds-amber)' }};">
                    {{ $m->is_active ? 'Active' : 'Inactive' }}
                </span>
                <form method="POST" action="{{ route('admin.activity-mappings.toggle-active', $m->id) }}" class="inline">
                    @csrf
                    <button type="submit" class="corex-btn-outline text-xs">{{ $m->is_active ? 'Deactivate' : 'Activate' }}</button>
                </form>
                <form method="POST" action="{{ route('admin.activity-mappings.destroy', $m->id) }}" class="inline" onsubmit="return confirm('Archive this mapping?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="corex-btn-outline text-xs" style="color: var(--ds-crimson);">×</button>
                </form>
            </div>
            @endforeach
        </div>
        @empty
        <div class="px-4 py-6 text-sm text-center" style="color:var(--text-muted);">
            No mappings — calendar events won't auto-credit points until you map classes to activities.
        </div>
        @endforelse
    </div>

    {{-- Create new mapping --}}
    <div class="rounded-md p-4" style="background:var(--surface); border:1px solid var(--border);">
        <div class="text-sm font-semibold mb-3" style="color:var(--text-primary);">Add mapping</div>
        <form method="POST" action="{{ route('admin.activity-mappings.store') }}" class="grid grid-cols-1 md:grid-cols-2 gap-3">
            @csrf
            <div>
                <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary);">Calendar event class *</label>
                <select name="event_class" required class="w-full px-3 py-2 text-sm rounded" style="background:var(--surface-2); border:1px solid var(--border); color:var(--text-primary);">
                    <option value="">— pick —</option>
                    @foreach($eventClasses as $ec)
                        <option value="{{ $ec->event_class }}">{{ $ec->label ?: $ec->event_class }} ({{ $ec->event_class }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary);">Activity definition *</label>
                <select name="activity_definition_id" required class="w-full px-3 py-2 text-sm rounded" style="background:var(--surface-2); border:1px solid var(--border); color:var(--text-primary);">
                    <option value="">— pick —</option>
                    @foreach($activityDefinitions as $ad)
                        <option value="{{ $ad->id }}">{{ $ad->name }} ({{ $ad->weight }} pts)</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary);">Value per event *</label>
                <input type="number" name="value_per_event" value="1" min="1" max="1000" required class="w-full px-3 py-2 text-sm rounded" style="background:var(--surface-2); border:1px solid var(--border); color:var(--text-primary);">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary);">Back-date limit (hours) *</label>
                <input type="number" name="back_date_limit_hours" value="48" min="0" max="8760" required class="w-full px-3 py-2 text-sm rounded" style="background:var(--surface-2); border:1px solid var(--border); color:var(--text-primary);">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary);">Auto-revoke after (hours)</label>
                <input type="number" name="auto_revoke_after_hours" value="24" min="1" max="8760" class="w-full px-3 py-2 text-sm rounded" style="background:var(--surface-2); border:1px solid var(--border); color:var(--text-primary);" placeholder="blank = never">
            </div>
            <div>
                <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary);">Daily cap per agent</label>
                <input type="number" name="daily_cap" min="1" max="1000" class="w-full px-3 py-2 text-sm rounded" style="background:var(--surface-2); border:1px solid var(--border); color:var(--text-primary);" placeholder="blank = no cap">
            </div>
            <div class="flex items-center gap-4 md:col-span-2">
                <label class="flex items-center gap-2 text-sm cursor-pointer" style="color:var(--text-primary);">
                    <input type="hidden" name="requires_feedback" value="0">
                    <input type="checkbox" name="requires_feedback" value="1" checked> Requires feedback to confirm
                </label>
                <label class="flex items-center gap-2 text-sm cursor-pointer" style="color:var(--text-primary);">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" checked> Active
                </label>
                <div class="ml-auto"><button type="submit" class="corex-btn-primary text-sm">Add mapping</button></div>
            </div>
        </form>
    </div>

</div>
@endsection
