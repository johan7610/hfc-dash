@extends('layouts.corex')

@section('corex-content')
@php
    $carbon = \Carbon\Carbon::create($year, $month, 1);
    $monthLabel = $carbon->format('F Y');
    $prevMonth = $carbon->copy()->subMonth();
    $nextMonth = $carbon->copy()->addMonth();
    $daysInMonth = $carbon->daysInMonth;
    $firstDayOfWeek = $carbon->dayOfWeekIso; // 1=Mon
    $today = \Carbon\Carbon::today();

    $typeColours = \App\Models\CommandCenter\CalendarEvent::TYPE_COLOURS;
@endphp

<div class="space-y-4" x-data="calendarPage()">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex items-center gap-3">
            <a href="{{ route('command-center.calendar', ['year' => $prevMonth->year, 'month' => $prevMonth->month]) }}"
               class="p-2 rounded-md transition-colors" style="color:var(--text-secondary); background:var(--surface-2);">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
            </a>
            <h1 class="text-xl font-bold" style="color:var(--text-primary);">{{ $monthLabel }}</h1>
            <a href="{{ route('command-center.calendar', ['year' => $nextMonth->year, 'month' => $nextMonth->month]) }}"
               class="p-2 rounded-md transition-colors" style="color:var(--text-secondary); background:var(--surface-2);">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
            </a>
            @if($year !== now()->year || $month !== now()->month)
                <a href="{{ route('command-center.calendar') }}" class="text-xs px-2 py-1 rounded-md" style="background:var(--surface-2); color:var(--text-muted);">Today</a>
            @endif
        </div>
        <div class="flex items-center gap-2">
            {{-- View switcher --}}
            @foreach(['month' => 'Month', 'agenda' => 'Agenda'] as $vKey => $vLabel)
                <a href="{{ route('command-center.calendar', ['year' => $year, 'month' => $month, 'view' => $vKey]) }}"
                   class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors"
                   style="{{ $currentView === $vKey ? 'background:var(--brand-button); color:#fff;' : 'background:var(--surface-2); color:var(--text-secondary);' }}">
                    {{ $vLabel }}
                </a>
            @endforeach
            <button @click="showCreateEvent = true"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold text-white transition-colors"
                    style="background:var(--brand-button);">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                Add Event
            </button>
        </div>
    </div>

    {{-- Legend --}}
    <div class="flex flex-wrap items-center gap-3 text-xs" style="color:var(--text-muted);">
        @foreach($typeColours as $type => $colour)
            <span class="flex items-center gap-1">
                <span class="w-2.5 h-2.5 rounded-sm" style="background:{{ $colour }};"></span>
                {{ ucfirst($type) }}
            </span>
        @endforeach
    </div>

    @if($currentView === 'month')
        {{-- ══════ MONTH VIEW ══════ --}}
        <div class="corex-panel overflow-hidden">
            {{-- Day headers --}}
            <div class="grid grid-cols-7 border-b" style="border-color:var(--border-default);">
                @foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $dayName)
                    <div class="px-2 py-2 text-xs font-medium text-center" style="color:var(--text-muted); {{ !$loop->last ? 'border-right:1px solid var(--border-default);' : '' }}">
                        {{ $dayName }}
                    </div>
                @endforeach
            </div>

            {{-- Calendar grid --}}
            <div class="grid grid-cols-7">
                {{-- Empty cells --}}
                @for($i = 1; $i < $firstDayOfWeek; $i++)
                    <div class="min-h-[6rem] p-1 border-b border-r" style="border-color:var(--border-default); background:var(--surface-2); opacity:0.5;"></div>
                @endfor

                @for($d = 1; $d <= $daysInMonth; $d++)
                    @php
                        $dateStr = $carbon->copy()->day($d)->toDateString();
                        $dayEvents = $byDate[$dateStr] ?? [];
                        $isToday = $carbon->copy()->day($d)->isSameDay($today);
                        $isWeekend = in_array($carbon->copy()->day($d)->dayOfWeekIso, [6, 7]);
                        $cellCol = ($firstDayOfWeek - 1 + $d - 1) % 7; // 0=Mon
                    @endphp
                    <div class="min-h-[6rem] p-1 border-b {{ $cellCol < 6 ? 'border-r' : '' }} transition-colors group"
                         style="border-color:var(--border-default); {{ $isToday ? 'background:rgba(14,165,233,0.05);' : ($isWeekend ? 'background:var(--surface-2); opacity:0.7;' : '') }}">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-medium {{ $isToday ? 'px-1.5 py-0.5 rounded-md text-white' : '' }}"
                                  style="{{ $isToday ? 'background:var(--brand-button);' : 'color:var(--text-secondary);' }}">
                                {{ $d }}
                            </span>
                            @if(count($dayEvents) > 3)
                                <span class="text-[10px] px-1 rounded" style="background:var(--surface-2); color:var(--text-muted);">+{{ count($dayEvents) - 3 }}</span>
                            @endif
                        </div>
                        <div class="space-y-0.5">
                            @foreach(array_slice($dayEvents, 0, 3) as $evt)
                                @if($evt->property_id)
                                    <a href="{{ route('corex.properties.show', $evt->property_id) }}"
                                       class="block text-[11px] leading-tight px-1 py-0.5 rounded truncate hover:opacity-80 transition-opacity"
                                       style="background:{{ $evt->colour }}22; color:{{ $evt->colour }}; border-left:2px solid {{ $evt->colour }};"
                                       title="{{ $evt->title }} — Click to view property">
                                        {{ $evt->all_day ? '' : $evt->event_date->format('H:i') . ' ' }}{{ \Illuminate\Support\Str::limit($evt->title, 20) }}
                                    </a>
                                @else
                                    <div class="text-[11px] leading-tight px-1 py-0.5 rounded truncate"
                                         style="background:{{ $evt->colour }}22; color:{{ $evt->colour }}; border-left:2px solid {{ $evt->colour }};"
                                         title="{{ $evt->title }}">
                                        {{ $evt->all_day ? '' : $evt->event_date->format('H:i') . ' ' }}{{ \Illuminate\Support\Str::limit($evt->title, 20) }}
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endfor

                {{-- Fill remaining cells --}}
                @php $remaining = 7 - (($firstDayOfWeek - 1 + $daysInMonth) % 7); @endphp
                @if($remaining < 7)
                    @for($i = 0; $i < $remaining; $i++)
                        <div class="min-h-[6rem] p-1 border-b {{ $i < $remaining - 1 ? 'border-r' : '' }}" style="border-color:var(--border-default); background:var(--surface-2); opacity:0.5;"></div>
                    @endfor
                @endif
            </div>
        </div>
    @else
        {{-- ══════ AGENDA VIEW ══════ --}}
        <div class="corex-panel">
            <div class="corex-panel-body">
                @if($events->isEmpty())
                    <div class="py-10 text-center">
                        <svg class="w-12 h-12 mx-auto mb-3" style="color:var(--text-muted);" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                        <p class="text-sm" style="color:var(--text-muted);">No events this month</p>
                    </div>
                @else
                    @php $groupedByDate = $events->groupBy(fn ($e) => $e->event_date->toDateString()); @endphp
                    <div class="divide-y" style="border-color:var(--border-default);">
                        @foreach($groupedByDate as $dateKey => $dayEvents)
                            @php $dateObj = \Carbon\Carbon::parse($dateKey); @endphp
                            <div class="py-3">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="text-sm font-semibold {{ $dateObj->isToday() ? '' : '' }}"
                                          style="color:{{ $dateObj->isToday() ? 'var(--brand-icon)' : 'var(--text-primary)' }};">
                                        {{ $dateObj->format('D, d M') }}
                                    </span>
                                    @if($dateObj->isToday())
                                        <span class="text-[10px] px-1.5 py-0.5 rounded-full font-medium text-white" style="background:var(--brand-button);">Today</span>
                                    @endif
                                </div>
                                <div class="space-y-1 ml-2">
                                    @foreach($dayEvents as $evt)
                                        <div class="flex items-center gap-3 py-1.5 px-2 rounded-md group hover:bg-white/5 transition-colors">
                                            <div class="w-1.5 h-6 rounded-full flex-shrink-0" style="background:{{ $evt->colour }};"></div>
                                            <span class="text-xs font-mono flex-shrink-0" style="color:var(--text-muted); min-width:3rem;">
                                                {{ $evt->all_day ? 'All day' : $evt->event_date->format('H:i') }}
                                            </span>
                                            @if($evt->property_id)
                                                <a href="{{ route('corex.properties.show', $evt->property_id) }}" class="text-sm flex-1 truncate hover:underline" style="color:var(--text-primary);">
                                                    {{ $evt->title }}
                                                </a>
                                            @else
                                                <span class="text-sm flex-1 truncate" style="color:var(--text-primary);">{{ $evt->title }}</span>
                                            @endif
                                            @if($evt->property_id)
                                                <a href="{{ route('corex.properties.show', $evt->property_id) }}" class="text-[10px] px-1.5 py-0.5 rounded hover:opacity-80 transition-opacity" style="background:rgba(16,185,129,0.1); color:#10b981;" title="View Property">
                                                    <svg class="w-3 h-3 inline -mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21m-3.75 3H21" /></svg>
                                                    Property
                                                </a>
                                            @endif
                                            <span class="text-[10px] px-1.5 py-0.5 rounded" style="background:{{ $evt->colour }}22; color:{{ $evt->colour }};">{{ ucfirst($evt->event_type) }}</span>
                                            <div class="flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                                                <form method="POST" action="{{ route('command-center.calendar.complete', $evt) }}">
                                                    @csrf
                                                    <button type="submit" class="p-1 rounded hover:bg-green-500/10" title="Complete">
                                                        <svg class="w-3.5 h-3.5 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- CREATE EVENT MODAL (same as dashboard) --}}
    <div x-show="showCreateEvent" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="background:rgba(0,0,0,0.5);"
         @keydown.escape.window="showCreateEvent = false">
        <div class="w-full max-w-lg rounded-lg shadow-xl" style="background:var(--surface);"
             @click.outside="showCreateEvent = false">
            <form method="POST" action="{{ route('command-center.calendar.store') }}">
                @csrf
                <div class="px-6 py-4 border-b" style="border-color:var(--border-default);">
                    <h3 class="text-lg font-semibold" style="color:var(--text-primary);">Add Calendar Event</h3>
                </div>
                <div class="px-6 py-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color:var(--text-secondary);">Title</label>
                        <input type="text" name="title" required class="w-full px-3 py-2 rounded-md text-sm border" style="background:var(--surface-2); border-color:var(--border-default); color:var(--text-primary);">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color:var(--text-secondary);">Start</label>
                            <input type="datetime-local" name="event_date" required class="w-full px-3 py-2 rounded-md text-sm border" style="background:var(--surface-2); border-color:var(--border-default); color:var(--text-primary);">
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color:var(--text-secondary);">End (optional)</label>
                            <input type="datetime-local" name="end_date" class="w-full px-3 py-2 rounded-md text-sm border" style="background:var(--surface-2); border-color:var(--border-default); color:var(--text-primary);">
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color:var(--text-secondary);">Type</label>
                            <select name="event_type" class="w-full px-3 py-2 rounded-md text-sm border" style="background:var(--surface-2); border-color:var(--border-default); color:var(--text-primary);">
                                <option value="manual">Personal</option>
                                <option value="deal">Deal</option>
                                <option value="lease">Lease / Rental</option>
                                <option value="compliance">Compliance</option>
                                <option value="prospecting">Prospecting</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color:var(--text-secondary);">Priority</label>
                            <select name="priority" class="w-full px-3 py-2 rounded-md text-sm border" style="background:var(--surface-2); border-color:var(--border-default); color:var(--text-primary);">
                                <option value="normal">Normal</option>
                                <option value="low">Low</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color:var(--text-secondary);">Description</label>
                        <textarea name="description" rows="2" class="w-full px-3 py-2 rounded-md text-sm border" style="background:var(--surface-2); border-color:var(--border-default); color:var(--text-primary);"></textarea>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 text-sm" style="color:var(--text-secondary);">
                            <input type="hidden" name="send_reminder" value="0">
                            <input type="checkbox" name="send_reminder" value="1" checked class="rounded">
                            Send me a reminder before this event
                        </label>
                    </div>
                </div>
                <div class="px-6 py-4 border-t flex justify-end gap-2" style="border-color:var(--border-default);">
                    <button type="button" @click="showCreateEvent = false" class="px-4 py-2 rounded-md text-sm font-medium" style="background:var(--surface-2); color:var(--text-secondary);">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-md text-sm font-semibold text-white" style="background:var(--brand-button);">Add Event</button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
function calendarPage() {
    return {
        showCreateEvent: false,
    };
}
</script>
@endsection
