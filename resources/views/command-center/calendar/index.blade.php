@extends('layouts.corex')

@section('corex-content')
@php
    $today = \Carbon\Carbon::today();

    // Month grid vars (only when month/agenda view)
    if (in_array($currentView, ['month', 'agenda'])) {
        $carbon = \Carbon\Carbon::create($year, $month, 1);
        $monthLabel = $carbon->format('F Y');
        $daysInMonth = $carbon->daysInMonth;
        $firstDayOfWeek = $carbon->dayOfWeekIso;
    }

    // RAG colour classes (dark theme)
    $ragChip = [
        'red'   => 'background:rgba(239,68,68,0.15); color:#fca5a5; border-left:2px solid #ef4444;',
        'amber' => 'background:rgba(245,158,11,0.15); color:#fcd34d; border-left:2px solid #f59e0b;',
        'green' => 'background:rgba(20,184,166,0.15); color:#5eead4; border-left:2px solid #14b8a6;',
    ];
    $ragDot = [
        'red'   => '#ef4444',
        'amber' => '#f59e0b',
        'green' => '#14b8a6',
    ];
    $defaultChip = 'background:rgba(100,116,139,0.15); color:#94a3b8; border-left:2px solid #64748b;';
    $defaultDot  = '#64748b';
@endphp

<div class="space-y-6" x-data="calendarPage()">

    {{-- ══════ PAGE HEADER (Pattern A — branded) ══════ --}}
    <div class="rounded-md px-6 py-5" style="background: var(--brand-default, #0b2a4a);">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-white leading-tight">Calendar</h1>
                <p class="text-sm text-white/60">
                    @if($currentView === 'week' && isset($weekStart))
                        Week of {{ $weekStart->format('j M Y') }}
                    @elseif($currentView === 'day' && isset($anchorDate))
                        {{ $anchorDate->format('l, j F Y') }}
                    @elseif(isset($monthLabel))
                        {{ $monthLabel }}
                    @endif
                    — deals, leases, compliance and personal events.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <button type="button" @click="openBlank()" class="corex-btn-primary">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    Add Event
                </button>
            </div>
        </div>
    </div>

    {{-- ══════ TOOLBAR (nav + view switcher) ══════ --}}
    @php
        // View-aware navigation URLs
        if (in_array($currentView, ['week', 'day'])) {
            $prevUrl = route('command-center.calendar', array_merge(request()->only(['scope','types','categories']), ['view' => $currentView, 'date' => $prevAnchor ?? null]));
            $nextUrl = route('command-center.calendar', array_merge(request()->only(['scope','types','categories']), ['view' => $currentView, 'date' => $nextAnchor ?? null]));
            $todayUrl = route('command-center.calendar', ['view' => $currentView]);
            $navLabel = $currentView === 'week'
                ? 'Week of ' . ($weekStart ?? now())->format('j M Y')
                : ($anchorDate ?? now())->format('l, j F Y');
            $showToday = ($currentView === 'day' && isset($anchorDate) && !$anchorDate->isToday())
                      || ($currentView === 'week' && isset($weekStart) && !now()->between($weekStart, $weekEnd ?? now()));
        } else {
            $prevUrl = route('command-center.calendar', ['year' => ($prevMonth ?? now()->subMonth())->year, 'month' => ($prevMonth ?? now()->subMonth())->month, 'view' => $currentView]);
            $nextUrl = route('command-center.calendar', ['year' => ($nextMonth ?? now()->addMonth())->year, 'month' => ($nextMonth ?? now()->addMonth())->month, 'view' => $currentView]);
            $todayUrl = route('command-center.calendar', ['view' => $currentView]);
            $navLabel = $monthLabel ?? now()->format('F Y');
            $showToday = ($year ?? now()->year) !== now()->year || ($month ?? now()->month) !== now()->month;
        }
    @endphp
    <div class="rounded-md px-4 py-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
         style="background: var(--surface); border: 1px solid var(--border);">
        <div class="flex items-center gap-2">
            <a href="{{ $prevUrl }}" class="corex-btn-outline" aria-label="Previous">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
            </a>
            <span class="px-3 py-1.5 text-sm font-semibold" style="color: var(--text-primary);">{{ $navLabel }}</span>
            <a href="{{ $nextUrl }}" class="corex-btn-outline" aria-label="Next">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
            </a>
            @if($showToday)
                <a href="{{ $todayUrl }}" class="corex-btn-outline">Today</a>
            @endif
        </div>

        <div class="inline-flex rounded-md overflow-hidden" style="background: var(--surface-2); border: 1px solid var(--border);">
            @foreach(['month' => 'Month', 'week' => 'Week', 'day' => 'Day', 'agenda' => 'Agenda'] as $vKey => $vLabel)
                <a href="{{ route('command-center.calendar', array_merge(request()->only(['scope','types','categories']), ['view' => $vKey])) }}"
                   class="px-3 py-1.5 text-xs font-semibold transition-colors"
                   style="{{ $currentView === $vKey ? 'background: var(--brand-button); color: #fff;' : 'color: var(--text-secondary);' }}">
                    {{ $vLabel }}
                </a>
            @endforeach
        </div>
    </div>

    {{-- ══════ LEGEND ══════ --}}
    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs" style="color: var(--text-muted);">
        <span>Status:</span>
        <span class="inline-flex items-center gap-1.5 whitespace-nowrap">
            <span class="w-2.5 h-2.5 rounded-full" style="background: #ef4444;"></span>
            <span style="color: var(--text-secondary);">Urgent / overdue</span>
        </span>
        <span class="inline-flex items-center gap-1.5 whitespace-nowrap">
            <span class="w-2.5 h-2.5 rounded-full" style="background: #f59e0b;"></span>
            <span style="color: var(--text-secondary);">Approaching</span>
        </span>
        <span class="inline-flex items-center gap-1.5 whitespace-nowrap">
            <span class="w-2.5 h-2.5 rounded-full" style="background: #14b8a6;"></span>
            <span style="color: var(--text-secondary);">Upcoming</span>
        </span>
    </div>

    {{-- ══════ FILTER BAR ══════ --}}
    <form method="GET" action="{{ route('command-center.calendar') }}" id="calendar-filters"
          class="flex flex-wrap items-center gap-3 rounded-md px-4 py-3"
          style="background: var(--surface); border: 1px solid var(--border);">
        <input type="hidden" name="view" value="{{ $currentView }}">
        <input type="hidden" name="month" value="{{ $month ?? now()->month }}">
        <input type="hidden" name="year" value="{{ $year ?? now()->year }}">
        @if(isset($anchorDate))
            <input type="hidden" name="date" value="{{ $anchorDate->toDateString() }}">
        @endif
        @if($currentView === 'agenda' && isset($agendaRange))
            <input type="hidden" name="range" value="{{ $agendaRange }}">
        @endif

        {{-- Scope pills --}}
        <div class="inline-flex rounded-md overflow-hidden" style="background: var(--surface-2); border: 1px solid var(--border);">
            @foreach(['all' => 'All', 'branch' => 'Branch', 'own' => 'Mine'] as $sKey => $sLabel)
                <label class="cursor-pointer">
                    <input type="radio" name="scope" value="{{ $sKey }}"
                           {{ ($scope ?? 'all') === $sKey ? 'checked' : '' }}
                           onchange="this.form.submit()" class="sr-only peer">
                    <span class="block px-3 py-1.5 text-xs font-semibold transition-colors peer-checked:text-white"
                          style="{{ ($scope ?? 'all') === $sKey ? 'background: var(--brand-button); color: #fff;' : 'color: var(--text-secondary);' }}">
                        {{ $sLabel }}
                    </span>
                </label>
            @endforeach
        </div>

        {{-- Event type dropdown --}}
        <div x-data="{ open: false }" class="relative">
            <button type="button" @click="open = !open"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium"
                    style="background: var(--surface-2); color: var(--text-primary); border: 1px solid var(--border);">
                Types
                @if(!empty($typeFilter))
                    <span class="px-1 py-0.5 rounded text-[10px] font-bold" style="background: var(--brand-button); color: #fff;">{{ count($typeFilter) }}</span>
                @endif
            </button>
            <div x-show="open" x-cloak @click.outside="open = false" x-transition
                 class="absolute z-30 left-0 mt-1 w-52 rounded-md p-2 shadow-lg"
                 style="background: var(--surface); border: 1px solid var(--border);">
                @foreach($availableTypes as $type)
                    <label class="flex items-center gap-2 px-2 py-1 rounded text-xs cursor-pointer" style="color: var(--text-primary);"
                           onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background='transparent'">
                        <input type="checkbox" name="types[]" value="{{ $type }}"
                               {{ in_array($type, $typeFilter ?? []) ? 'checked' : '' }}
                               onchange="document.getElementById('calendar-filters').submit()" class="rounded">
                        {{ ucfirst($type) }}
                    </label>
                @endforeach
            </div>
        </div>

        {{-- Category dropdown --}}
        <div x-data="{ open: false }" class="relative">
            <button type="button" @click="open = !open"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-medium"
                    style="background: var(--surface-2); color: var(--text-primary); border: 1px solid var(--border);">
                Classes
                @if(!empty($categoryFilter))
                    <span class="px-1 py-0.5 rounded text-[10px] font-bold" style="background: var(--brand-button); color: #fff;">{{ count($categoryFilter) }}</span>
                @endif
            </button>
            <div x-show="open" x-cloak @click.outside="open = false" x-transition
                 class="absolute z-30 left-0 mt-1 w-64 rounded-md p-2 shadow-lg max-h-80 overflow-y-auto"
                 style="background: var(--surface); border: 1px solid var(--border);">
                @foreach($availableCategories as $cat)
                    <label class="flex items-center gap-2 px-2 py-1 rounded text-xs cursor-pointer" style="color: var(--text-primary);"
                           onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background='transparent'">
                        <input type="checkbox" name="categories[]" value="{{ $cat->event_class }}"
                               {{ in_array($cat->event_class, $categoryFilter ?? []) ? 'checked' : '' }}
                               onchange="document.getElementById('calendar-filters').submit()" class="rounded">
                        {{ $cat->label }}
                    </label>
                @endforeach
            </div>
        </div>

        @if(!empty($typeFilter) || !empty($categoryFilter) || ($scope ?? 'all') !== 'all')
            <a href="{{ route('command-center.calendar', array_merge(['view' => $currentView], isset($month) ? ['month' => $month, 'year' => $year] : [])) }}"
               class="text-xs font-medium hover:underline" style="color: var(--brand-icon);">Clear filters</a>
        @endif
    </form>

    @if($currentView === 'month')
        {{-- ══════ MONTH VIEW ══════ --}}
        <div class="rounded-md overflow-hidden" style="background: var(--surface); border: 1px solid var(--border);">
            {{-- Day headers --}}
            <div class="grid grid-cols-7" style="background: var(--surface-2); border-bottom: 1px solid var(--border);">
                @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $dayName)
                    <div class="px-2 py-2.5 text-xs font-semibold text-center uppercase tracking-wider"
                         style="color: var(--text-muted); {{ !$loop->last ? 'border-right: 1px solid var(--border);' : '' }}">
                        {{ $dayName }}
                    </div>
                @endforeach
            </div>

            {{-- Calendar grid --}}
            <div class="grid grid-cols-7">
                {{-- Empty cells before first of month --}}
                @for($i = 1; $i < $firstDayOfWeek; $i++)
                    <div class="min-h-[6rem] p-1"
                         style="background: var(--surface-2); border-bottom: 1px solid var(--border); {{ $i < 7 ? 'border-right: 1px solid var(--border);' : '' }} opacity: 0.5;"></div>
                @endfor

                @for($d = 1; $d <= $daysInMonth; $d++)
                    @php
                        $dateStr = $carbon->copy()->day($d)->toDateString();
                        $dayEvents = $byDate[$dateStr] ?? [];
                        $isToday = $carbon->copy()->day($d)->isSameDay($today);
                        $isWeekend = in_array($carbon->copy()->day($d)->dayOfWeekIso, [6, 7]);
                        $cellCol = ($firstDayOfWeek - 1 + $d - 1) % 7; // 0=Mon
                        $cellBg = $isToday
                            ? 'color-mix(in srgb, var(--brand-button) 8%, transparent)'
                            : ($isWeekend ? 'var(--surface-2)' : 'transparent');
                    @endphp
                    <div role="button" tabindex="0"
                         @click="openForDate('{{ $dateStr }}')"
                         @keydown.enter.prevent="openForDate('{{ $dateStr }}')"
                         @keydown.space.prevent="openForDate('{{ $dateStr }}')"
                         class="min-h-[6rem] p-1 transition-colors cursor-pointer focus:outline-none"
                         style="background: {{ $cellBg }}; border-bottom: 1px solid var(--border); {{ $cellCol < 6 ? 'border-right: 1px solid var(--border);' : '' }}"
                         onmouseover="this.style.background='color-mix(in srgb, var(--brand-button) 6%, transparent)'"
                         onmouseout="this.style.background='{{ $cellBg }}'"
                         title="Click to add an event on {{ $carbon->copy()->day($d)->format('D, d M Y') }}">
                        <div class="flex items-center justify-between mb-1 pointer-events-none">
                            <span class="text-xs font-semibold {{ $isToday ? 'px-1.5 py-0.5 rounded-md text-white' : '' }}"
                                  style="{{ $isToday ? 'background: var(--brand-button);' : 'color: var(--text-secondary);' }}">
                                {{ $d }}
                            </span>
                            @if(count($dayEvents) > 3)
                                <span class="text-[10px] px-1.5 py-0.5 rounded font-medium whitespace-nowrap"
                                      style="background: var(--surface-2); color: var(--text-muted); border: 1px solid var(--border);">
                                    +{{ count($dayEvents) - 3 }}
                                </span>
                            @endif
                        </div>
                        <div class="space-y-0.5">
                            @foreach(array_slice($dayEvents, 0, 3) as $evt)
                                @php $chipStyle = $ragChip[$evt->resolved_colour] ?? $defaultChip; @endphp
                                <button type="button"
                                        @click.stop="openEventPanel({{ $evt->id }})"
                                        class="block w-full text-left text-[11px] leading-tight px-1.5 py-0.5 rounded truncate hover:opacity-80 transition-opacity"
                                        style="{{ $chipStyle }}"
                                        title="{{ $evt->title }}">
                                    {{ $evt->all_day ? '' : $evt->event_date->format('H:i') . ' ' }}{{ \Illuminate\Support\Str::limit($evt->title, 20) }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endfor

                {{-- Fill remaining cells --}}
                @php $remaining = 7 - (($firstDayOfWeek - 1 + $daysInMonth) % 7); @endphp
                @if($remaining < 7)
                    @for($i = 0; $i < $remaining; $i++)
                        <div class="min-h-[6rem] p-1"
                             style="background: var(--surface-2); {{ $i < $remaining - 1 ? 'border-right: 1px solid var(--border);' : '' }} opacity: 0.5;"></div>
                    @endfor
                @endif
            </div>
        </div>
    @elseif($currentView === 'week')
        {{-- ══════ WEEK VIEW ══════ --}}
        <div class="grid grid-cols-7 gap-2">
            @foreach($weekDays as $day)
                <div class="rounded-md p-3 min-h-[20rem]"
                     style="background: var(--surface); border: 1px solid {{ $day['is_today'] ? 'var(--brand-button)' : 'var(--border)' }};">
                    <div class="text-[0.6875rem] uppercase tracking-wider mb-1" style="color: var(--text-muted);">{{ $day['date']->format('D') }}</div>
                    <div class="text-lg font-semibold mb-3" style="color: {{ $day['is_today'] ? 'var(--brand-button)' : 'var(--text-primary)' }};">{{ $day['date']->format('j M') }}</div>
                    <div class="space-y-1.5">
                        @forelse($day['events'] as $evt)
                            @php $chipStyle = $ragChip[$evt->resolved_colour] ?? $defaultChip; @endphp
                            <button type="button" @click="openEventPanel({{ $evt->id }})"
                                    class="block w-full text-left p-2 rounded text-xs transition hover:opacity-80"
                                    style="{{ $chipStyle }}">
                                <div class="truncate font-medium">{{ $evt->title }}</div>
                                <div class="text-[10px] opacity-70 truncate mt-0.5">{{ $evt->category }}</div>
                            </button>
                        @empty
                            <div class="text-xs italic" style="color: var(--text-muted);">&mdash;</div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>

    @elseif($currentView === 'day')
        {{-- ══════ DAY VIEW ══════ --}}
        <div class="max-w-3xl mx-auto">
            <div class="mb-4 text-center">
                <div class="text-xs uppercase tracking-wider" style="color: var(--text-muted);">{{ $anchorDate->format('l') }}</div>
                <div class="text-2xl font-semibold" style="color: {{ $anchorDate->isSameDay(now()) ? 'var(--brand-button)' : 'var(--text-primary)' }};">{{ $anchorDate->format('j F Y') }}</div>
            </div>
            <div class="space-y-3">
                @forelse($dayEvents as $evt)
                    @php $chipStyle = $ragChip[$evt->resolved_colour] ?? $defaultChip; @endphp
                    <button type="button" @click="openEventPanel({{ $evt->id }})"
                            class="block w-full text-left p-4 rounded-lg transition hover:opacity-90"
                            style="background: var(--surface); border: 1px solid var(--border);">
                        <div class="flex items-start gap-3">
                            <span class="w-2 h-2 rounded-full mt-1.5 flex-shrink-0" style="background: {{ $ragDot[$evt->resolved_colour] ?? $defaultDot }};"></span>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-base font-semibold" style="color: var(--text-primary);">{{ $evt->title }}</span>
                                    <span class="px-2 py-0.5 text-xs rounded" style="{{ $chipStyle }} border-left:none;">{{ ucfirst($evt->resolved_colour) }}</span>
                                </div>
                                <div class="text-xs mt-1" style="color: var(--text-muted);">
                                    {{ $evt->category }}
                                    @if(!$evt->all_day && $evt->event_date->format('Hi') !== '0000')
                                        &middot; {{ $evt->event_date->format('H:i') }}
                                    @endif
                                </div>
                                @if($evt->description)
                                    <p class="text-sm mt-2" style="color: var(--text-secondary);">{{ \Illuminate\Support\Str::limit($evt->description, 200) }}</p>
                                @endif
                            </div>
                        </div>
                    </button>
                @empty
                    <div class="text-center py-12" style="color: var(--text-muted);">
                        <p>No events on this day.</p>
                        <button type="button" @click="openBlank()" class="corex-btn-primary mt-3">Add Event</button>
                    </div>
                @endforelse
            </div>
        </div>

    @elseif($currentView === 'agenda')
        {{-- ══════ AGENDA VIEW ══════ --}}
        <div class="rounded-md" style="background: var(--surface); border: 1px solid var(--border);">
            {{-- Range filter bar --}}
            <form method="GET" action="{{ route('command-center.calendar') }}"
                  class="flex flex-col gap-3 px-4 py-3"
                  style="border-bottom: 1px solid var(--border);">
                <input type="hidden" name="year" value="{{ $year }}">
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="view" value="agenda">

                <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-3">
                    <div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-end gap-3">
                        {{-- Preset --}}
                        <div class="flex flex-col gap-1">
                            <label for="agenda-range" class="text-[0.6875rem] font-semibold uppercase tracking-wider" style="color: var(--text-muted);">Range</label>
                            <select id="agenda-range" name="range" onchange="this.form.submit()" class="list-header-filter">
                                @foreach($rangeGroups as $groupLabel => $opts)
                                    <optgroup label="{{ $groupLabel }}">
                                        @foreach($opts as $rKey => $rLabel)
                                            <option value="{{ $rKey }}" @selected($agendaRange === $rKey)>{{ $rLabel }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>

                        {{-- Custom from/to — editing a date forces range=custom --}}
                        <div class="flex flex-col gap-1">
                            <label for="agenda-from" class="text-[0.6875rem] font-semibold uppercase tracking-wider" style="color: var(--text-muted);">From</label>
                            <input id="agenda-from" type="date" name="from" value="{{ $agendaFrom }}"
                                   onchange="this.form.querySelector('[name=range]').value='custom'; this.form.submit();"
                                   class="list-header-filter">
                        </div>
                        <div class="flex flex-col gap-1">
                            <label for="agenda-to" class="text-[0.6875rem] font-semibold uppercase tracking-wider" style="color: var(--text-muted);">To</label>
                            <input id="agenda-to" type="date" name="to" value="{{ $agendaTo }}"
                                   onchange="this.form.querySelector('[name=range]').value='custom'; this.form.submit();"
                                   class="list-header-filter">
                        </div>

                        @if($agendaRange !== 'month' || request('from') || request('to'))
                            <a href="{{ route('command-center.calendar', ['year' => $year, 'month' => $month, 'view' => 'agenda']) }}"
                               class="text-xs font-semibold self-end pb-2 hover:underline"
                               style="color: var(--brand-icon);">
                                Clear
                            </a>
                        @endif
                    </div>

                    <div class="flex items-center gap-3 self-end pb-1">
                        <span class="text-xs" style="color: var(--text-muted);">
                            {{ \Carbon\Carbon::parse($agendaFrom)->format('d M Y') }} — {{ \Carbon\Carbon::parse($agendaTo)->format('d M Y') }}
                        </span>
                        <span class="text-xs font-semibold" style="color: var(--text-primary);">
                            {{ $agendaEvents->count() }} {{ Str::plural('event', $agendaEvents->count()) }}
                        </span>
                    </div>
                </div>
            </form>

            @if($agendaEvents->isEmpty())
                <div class="py-12 px-6 text-center">
                    <div class="w-12 h-12 rounded-full mx-auto mb-4 flex items-center justify-center"
                         style="background: color-mix(in srgb, var(--brand-icon) 12%, transparent); color: var(--brand-icon);">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" /></svg>
                    </div>
                    <h3 class="text-base font-semibold mb-1" style="color: var(--text-primary);">No events in {{ strtolower($agendaRangeLabel) }}</h3>
                    <p class="text-sm mb-4" style="color: var(--text-muted);">Expand the range or add an event to plan deals, viewings, compliance reminders and more.</p>
                    <button type="button" @click="openBlank()" class="corex-btn-primary">Add Event</button>
                </div>
            @else
                @php $groupedByDate = $agendaEvents->groupBy(fn ($e) => $e->event_date->toDateString()); @endphp
                <div class="px-4 py-2">
                    @foreach($groupedByDate as $dateKey => $dayEvents)
                        @php $dateObj = \Carbon\Carbon::parse($dateKey); @endphp
                        <div class="py-3" style="{{ !$loop->first ? 'border-top: 1px solid var(--border);' : '' }}">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="text-sm font-semibold"
                                      style="color: {{ $dateObj->isToday() ? 'var(--brand-icon)' : 'var(--text-primary)' }};">
                                    {{ $dateObj->format($dateObj->year === now()->year ? 'D, d M' : 'D, d M Y') }}
                                </span>
                                @if($dateObj->isToday())
                                    <span class="ds-badge ds-badge-info">Today</span>
                                @endif
                            </div>
                            <div class="space-y-1">
                                @foreach($dayEvents as $evt)
                                    @php $dotColour = $ragDot[$evt->resolved_colour] ?? $defaultDot; @endphp
                                    <div class="flex items-center gap-3 py-1.5 px-2 rounded-md transition-colors group cursor-pointer"
                                         style="background: transparent;"
                                         onmouseover="this.style.background='var(--surface-2)'"
                                         onmouseout="this.style.background='transparent'"
                                         @click="openEventPanel({{ $evt->id }})">
                                        <div class="w-1.5 h-6 rounded flex-shrink-0" style="background: {{ $dotColour }};"></div>
                                        <span class="text-xs font-mono flex-shrink-0 whitespace-nowrap" style="color: var(--text-muted); min-width: 3rem;">
                                            {{ $evt->all_day ? 'All day' : $evt->event_date->format('H:i') }}
                                        </span>
                                        @if($evt->property_id)
                                            <a href="{{ route('corex.properties.show', $evt->property_id) }}" class="text-sm flex-1 truncate hover:underline" style="color: var(--text-primary);">
                                                {{ $evt->title }}
                                            </a>
                                        @else
                                            <span class="text-sm flex-1 truncate" style="color: var(--text-primary);">{{ $evt->title }}</span>
                                        @endif
                                        @if($evt->property_id)
                                            <a href="{{ route('corex.properties.show', $evt->property_id) }}"
                                               class="text-[10px] font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded whitespace-nowrap hover:opacity-80 transition-opacity inline-flex items-center gap-1"
                                               style="background: color-mix(in srgb, var(--ds-green) 12%, transparent); color: var(--ds-green);"
                                               title="View Property">
                                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21m-3.75 3H21" /></svg>
                                                Property
                                            </a>
                                        @endif
                                        <span class="text-[10px] font-semibold uppercase tracking-wider px-1.5 py-0.5 rounded whitespace-nowrap"
                                              style="{{ $ragChip[$evt->resolved_colour] ?? $defaultChip }} border-left:none;">
                                            {{ $evt->category ?? ucfirst($evt->event_type) }}
                                        </span>
                                        <div class="flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                                            <form method="POST" action="{{ route('command-center.calendar.complete', $evt) }}">
                                                @csrf
                                                <button type="submit" class="p-1 rounded transition-colors"
                                                        style="color: var(--ds-green);"
                                                        onmouseover="this.style.background='color-mix(in srgb, var(--ds-green) 12%, transparent)'"
                                                        onmouseout="this.style.background='transparent'"
                                                        title="Mark complete">
                                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
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
    @endif

    {{-- ══════ CREATE EVENT MODAL ══════ --}}
    <div x-show="showCreateEvent" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50"
         @keydown.escape.window="showCreateEvent = false; panelOpen = false">
        <div class="w-full max-w-lg rounded-md"
             style="background: var(--surface); border: 1px solid var(--border); box-shadow: 0 10px 30px rgba(0,0,0,0.18);"
             @click.outside="showCreateEvent = false">
            <form method="POST" action="{{ route('command-center.calendar.store') }}">
                @csrf
                <div class="px-6 py-4" style="border-bottom: 1px solid var(--border);">
                    <h3 class="text-lg font-semibold" style="color: var(--text-primary);">Add Calendar Event</h3>
                </div>
                <div class="px-6 py-4 space-y-4">
                    <div>
                        <label for="event-title" class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                            Title <span class="text-red-500">*</span>
                        </label>
                        <input id="event-title" type="text" name="title" required
                               class="w-full rounded-md px-3 py-2 text-sm"
                               style="background: var(--surface); border: 1px solid var(--border); color: var(--text-primary);">
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="event-start" class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                                Start <span class="text-red-500">*</span>
                            </label>
                            <input id="event-start" type="datetime-local" name="event_date" required
                                   x-model="presetStart"
                                   class="w-full rounded-md px-3 py-2 text-sm"
                                   style="background: var(--surface); border: 1px solid var(--border); color: var(--text-primary);">
                        </div>
                        <div>
                            <label for="event-end" class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">End (optional)</label>
                            <input id="event-end" type="datetime-local" name="end_date"
                                   x-model="presetEnd"
                                   class="w-full rounded-md px-3 py-2 text-sm"
                                   style="background: var(--surface); border: 1px solid var(--border); color: var(--text-primary);">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="event-type" class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Type</label>
                            <select id="event-type" name="event_type"
                                    class="w-full rounded-md px-3 py-2 text-sm"
                                    style="background: var(--surface); border: 1px solid var(--border); color: var(--text-primary);">
                                <option value="manual">Personal</option>
                                <option value="deal">Deal</option>
                                <option value="lease">Lease / Rental</option>
                                <option value="compliance">Compliance</option>
                                <option value="prospecting">Prospecting</option>
                            </select>
                        </div>
                        <div>
                            <label for="event-priority" class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Priority</label>
                            <select id="event-priority" name="priority"
                                    class="w-full rounded-md px-3 py-2 text-sm"
                                    style="background: var(--surface); border: 1px solid var(--border); color: var(--text-primary);">
                                <option value="normal">Normal</option>
                                <option value="low">Low</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label for="event-description" class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Description</label>
                        <textarea id="event-description" name="description" rows="4"
                                  class="w-full rounded-md px-3 py-2 text-sm"
                                  style="background: var(--surface); border: 1px solid var(--border); color: var(--text-primary);"></textarea>
                    </div>
                    <div>
                        <label class="inline-flex items-center gap-2 text-sm" style="color: var(--text-secondary);">
                            <input type="hidden" name="send_reminder" value="0">
                            <input type="checkbox" name="send_reminder" value="1" checked class="rounded">
                            Send me a reminder before this event
                        </label>
                    </div>
                </div>
                <div class="px-6 py-4 flex justify-end gap-2" style="border-top: 1px solid var(--border);">
                    <button type="button" @click="showCreateEvent = false" class="corex-btn-outline">Cancel</button>
                    <button type="submit" class="corex-btn-primary">Add Event</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ══════ EVENT DETAIL SIDE PANEL ══════ --}}
    <div x-show="panelOpen" x-cloak class="fixed inset-0 z-40">
        <div class="absolute inset-0 bg-black/40" @click="panelOpen = false"></div>
        <aside class="absolute top-0 right-0 h-full w-full max-w-md shadow-2xl overflow-y-auto"
               style="background: var(--surface); border-left: 1px solid var(--border);"
               x-transition:enter="transition ease-out duration-200 transform"
               x-transition:enter-start="translate-x-full"
               x-transition:enter-end="translate-x-0"
               x-transition:leave="transition ease-in duration-150 transform"
               x-transition:leave-start="translate-x-0"
               x-transition:leave-end="translate-x-full">

            <div class="p-5" style="border-bottom: 1px solid var(--border);">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-2" x-show="panelData.colour">
                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-xs"
                                  :style="panelColourStyle(panelData.colour)">
                                <span class="w-1.5 h-1.5 rounded-full" :style="'background:' + panelDotHex(panelData.colour)"></span>
                                <span x-text="panelColourLabel(panelData.colour)"></span>
                            </span>
                            <span class="text-xs truncate" style="color: var(--text-muted);" x-text="panelData.class_label"></span>
                        </div>
                        <h2 class="text-lg font-semibold leading-tight" style="color: var(--text-primary);" x-text="panelData.title"></h2>
                        <p class="text-sm mt-1" style="color: var(--text-secondary);" x-text="panelData.event_date_h"></p>
                        <p class="text-xs mt-0.5" style="color: var(--text-muted);" x-text="panelDaysDiffLabel(panelData.days_diff)"></p>
                    </div>
                    <button @click="panelOpen = false" class="p-1 rounded" style="color: var(--text-muted);"
                            onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background='transparent'">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <template x-if="panelData.description">
                <div class="p-5" style="border-bottom: 1px solid var(--border);">
                    <h3 class="text-[0.6875rem] font-semibold uppercase tracking-wider mb-2" style="color: var(--text-muted);">Description</h3>
                    <p class="text-sm" style="color: var(--text-primary);" x-text="panelData.description"></p>
                </div>
            </template>

            <template x-if="panelData.source_link">
                <div class="p-5" style="border-bottom: 1px solid var(--border);">
                    <a :href="panelData.source_link.url" class="text-sm font-medium hover:underline" style="color: var(--brand-button);">
                        <span x-text="panelData.source_link.label"></span> &rarr;
                    </a>
                </div>
            </template>

            <div class="p-5 flex items-center gap-2">
                <form :action="'/corex/command-center/calendar/' + panelData.id + '/complete'" method="POST" class="flex-1">
                    @csrf
                    <button type="submit" class="w-full px-4 py-2 text-sm font-medium rounded-md" style="background: var(--brand-button); color: #fff;">
                        Mark complete
                    </button>
                </form>
                <form :action="'/corex/command-center/calendar/' + panelData.id + '/dismiss'" method="POST">
                    @csrf
                    <button type="submit" class="px-4 py-2 text-sm rounded-md"
                            style="background: var(--surface-2); color: var(--text-secondary); border: 1px solid var(--border);">
                        Dismiss
                    </button>
                </form>
            </div>
        </aside>
    </div>

</div>

<script>
function calendarPage() {
    return {
        showCreateEvent: false,
        presetStart: '',
        presetEnd: '',
        panelOpen: false,
        panelData: {},

        openForDate(dateStr) {
            this.presetStart = dateStr + 'T09:00';
            this.presetEnd = '';
            this.showCreateEvent = true;
        },
        openBlank() {
            this.presetStart = '';
            this.presetEnd = '';
            this.showCreateEvent = true;
        },

        openEventPanel(eventId) {
            this.panelOpen = true;
            this.panelData = { title: 'Loading\u2026', colour: null, days_diff: 0 };

            fetch('/corex/command-center/calendar/' + eventId, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
            })
            .then(r => r.ok ? r.json() : Promise.reject(r.status))
            .then(data => { this.panelData = data; })
            .catch(err => {
                this.panelData = { title: 'Could not load event', colour: null, days_diff: 0 };
                console.warn('Calendar event load failed:', err);
            });
        },

        panelColourStyle(colour) {
            const m = {
                red:   'background:rgba(239,68,68,0.15); color:#fca5a5; border:1px solid rgba(239,68,68,0.4);',
                amber: 'background:rgba(245,158,11,0.15); color:#fde68a; border:1px solid rgba(245,158,11,0.4);',
                green: 'background:rgba(20,184,166,0.15); color:#99f6e4; border:1px solid rgba(20,184,166,0.4);',
            };
            return m[colour] || '';
        },
        panelDotHex(colour) {
            return { red: '#ef4444', amber: '#f59e0b', green: '#14b8a6' }[colour] || '#64748b';
        },
        panelColourLabel(colour) {
            return { red: 'Urgent', amber: 'Approaching', green: 'Upcoming' }[colour] || '';
        },
        panelDaysDiffLabel(days) {
            if (days == null) return '';
            if (days === 0) return 'Today';
            if (days === 1) return 'Tomorrow';
            if (days === -1) return 'Yesterday';
            if (days > 0) return 'In ' + days + ' days';
            return Math.abs(days) + ' days ago';
        },
    };
}
</script>
@endsection
