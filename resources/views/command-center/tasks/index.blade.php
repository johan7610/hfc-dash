@extends('layouts.corex')

@section('corex-content')
@php
    // Shared chip colour lookup for pillar tags
    $pillarStyle = [
        'property' => ['bg' => 'rgba(249,115,22,0.15)',  'fg' => '#f97316', 'label' => 'Property'],
        'deal'     => ['bg' => 'rgba(59,130,246,0.15)',  'fg' => '#3b82f6', 'label' => 'Deal'],
        'contact'  => ['bg' => 'rgba(139,92,246,0.15)',  'fg' => '#8b5cf6', 'label' => 'Contact'],
    ];

    // Flatten all tasks once and bucket by due date for list view + at-risk strip
    $allTasks = collect($columns)->flatten(1);

    $bucketOf = function ($task) {
        if (!$task->due_date) return 'none';
        if ($task->isOverdue())             return 'overdue';
        if ($task->due_date->isToday())     return 'today';
        if ($task->due_date->isTomorrow())  return 'tomorrow';
        if ($task->due_date->between(now()->startOfWeek(), now()->endOfWeek()))
                                            return 'week';
        return 'later';
    };

    $buckets = [
        'overdue'  => ['label' => 'Overdue',     'color' => '#ef4444', 'tasks' => collect(), 'defaultCollapsed' => false],
        'today'    => ['label' => 'Today',       'color' => '#f59e0b', 'tasks' => collect(), 'defaultCollapsed' => false],
        'tomorrow' => ['label' => 'Tomorrow',    'color' => '#3b82f6', 'tasks' => collect(), 'defaultCollapsed' => false],
        'week'     => ['label' => 'This Week',   'color' => '#8b5cf6', 'tasks' => collect(), 'defaultCollapsed' => false],
        'later'    => ['label' => 'Later',       'color' => '#6b7280', 'tasks' => collect(), 'defaultCollapsed' => true],
        'none'     => ['label' => 'No Due Date', 'color' => '#6b7280', 'tasks' => collect(), 'defaultCollapsed' => true],
    ];

    foreach ($allTasks as $t) {
        $b = $bucketOf($t);
        $buckets[$b]['tasks']->push($t);
    }
    foreach ($buckets as $k => &$bk) {
        $bk['tasks'] = $bk['tasks']->sortBy(fn($t) => $t->due_date?->timestamp ?? PHP_INT_MAX);
    }
    unset($bk);

    // At-risk strip: overdue + today, open only, sorted by due
    $atRisk = $buckets['overdue']['tasks']->merge($buckets['today']['tasks'])
        ->filter(fn($t) => !in_array($t->status, ['done','dismissed']))
        ->sortBy(fn($t) => $t->due_date?->timestamp ?? PHP_INT_MAX)
        ->values();
@endphp

<div class="space-y-3" x-data="taskBoard()" x-init="init()">

    {{-- ══════ HEADER ══════ --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div>
            <h1 class="text-xl font-bold" style="color:var(--text-primary);">Tasks</h1>
            <div class="flex items-center gap-3 mt-0.5 text-xs" style="color:var(--text-muted);">
                <span>{{ $summary['open'] }} open</span>
                @if($summary['overdue'] > 0)
                    <span style="color:#ef4444;">{{ $summary['overdue'] }} overdue</span>
                @endif
                <span>{{ $summary['today'] }} today</span>
                <span>{{ $summary['thisWeek'] }} this week</span>
            </div>
        </div>

        <div class="flex items-center gap-1.5 flex-wrap">
            {{-- View mode: Board / List --}}
            <div class="inline-flex rounded-md overflow-hidden" style="background:var(--surface-2);">
                @foreach(['kanban' => 'Board', 'list' => 'List'] as $vKey => $vLabel)
                    <a href="{{ route('command-center.tasks', array_merge(request()->query(), ['view' => $vKey])) }}"
                       class="px-2.5 py-1 text-xs font-medium"
                       style="{{ $currentView === $vKey ? 'background:var(--brand-button); color:#fff;' : 'color:var(--text-secondary);' }}">
                        {{ $vLabel }}
                    </a>
                @endforeach
            </div>

            {{-- Density toggle --}}
            <div class="inline-flex rounded-md overflow-hidden" style="background:var(--surface-2);">
                <button type="button" @click="density = 'comfortable'"
                        class="px-2 py-1 text-xs font-medium"
                        :style="density === 'comfortable' ? 'background:var(--brand-button); color:#fff;' : 'color:var(--text-secondary);'"
                        title="Comfortable">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" /></svg>
                </button>
                <button type="button" @click="density = 'compact'"
                        class="px-2 py-1 text-xs font-medium"
                        :style="density === 'compact' ? 'background:var(--brand-button); color:#fff;' : 'color:var(--text-secondary);'"
                        title="Compact">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 5.25h16.5m-16.5 4.5h16.5m-16.5 4.5h16.5m-16.5 4.5h16.5" /></svg>
                </button>
            </div>

            <a href="{{ route('command-center.tasks.archived') }}"
               class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium"
               style="background:var(--surface-2); color:var(--text-secondary);">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" /></svg>
                Archived
            </a>

            <form method="POST" action="{{ route('command-center.tasks.archive-done') }}"
                  onsubmit="return confirm('Archive all Done tasks? They can be restored from the Archived view.');"
                  class="inline">
                @csrf
                <button type="submit"
                        class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium"
                        style="background:var(--surface-2); color:var(--text-secondary);">
                    Clear Done
                </button>
            </form>

            <button @click="showCreateTask = true"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-semibold text-white"
                    style="background:var(--brand-button);">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                New Task
            </button>
        </div>
    </div>

    {{-- ══════ AT-RISK STRIP (Overdue + Today, pinned) ══════ --}}
    @if($atRisk->isNotEmpty())
        <div class="rounded-md border" style="background:rgba(239,68,68,0.04); border-color:rgba(239,68,68,0.25);"
             x-show="!atRiskCollapsed" x-transition>
            <div class="flex items-center justify-between px-2.5 py-1.5">
                <div class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" style="color:#ef4444;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" /></svg>
                    <span class="text-xs font-semibold" style="color:#ef4444;">At Risk</span>
                    <span class="text-[10px] px-1.5 py-0.5 rounded-full" style="background:rgba(239,68,68,0.15); color:#ef4444;">{{ $atRisk->count() }}</span>
                </div>
                <button @click="atRiskCollapsed = true" class="text-[10px]" style="color:var(--text-muted);" title="Hide">Hide</button>
            </div>
            <div class="px-2.5 pb-2 flex flex-wrap gap-1.5">
                @foreach($atRisk as $task)
                    @php
                        $tag = $task->pillarTag();
                        $taskLink = $task->property ? route('corex.properties.show', $task->property)
                                  : ($task->contact  ? route('corex.contacts.show',  $task->contact)
                                  : ($task->deal_id  ? route('deals-v2.show',        $task->deal_id) : null));
                    @endphp
                    <a @if($taskLink) href="{{ $taskLink }}" @endif
                       class="inline-flex items-center gap-1.5 px-2 py-1 rounded text-[11px] @if($taskLink) hover:bg-white/10 @endif"
                       style="background:var(--surface); border:1px solid var(--border-default);">
                        @if($task->isOverdue())
                            <span class="w-1.5 h-1.5 rounded-full" style="background:#ef4444;"></span>
                        @else
                            <span class="w-1.5 h-1.5 rounded-full" style="background:#f59e0b;"></span>
                        @endif
                        @if($tag && isset($pillarStyle[$tag]))
                            <span class="text-[9px] font-bold uppercase" style="color:{{ $pillarStyle[$tag]['fg'] }};">{{ $pillarStyle[$tag]['label'] }}</span>
                        @endif
                        <span style="color:var(--text-primary);">{{ \Illuminate\Support\Str::limit($task->title, 40) }}</span>
                        @if($task->due_date)
                            <span style="color:{{ $task->isOverdue() ? '#ef4444' : 'var(--text-muted)' }};">
                                · {{ $task->isOverdue() ? $task->due_date->diffForHumans(['short' => true]) : 'Today' }}
                            </span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
        <div x-show="atRiskCollapsed" x-transition>
            <button @click="atRiskCollapsed = false"
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium"
                    style="background:rgba(239,68,68,0.1); color:#ef4444;">
                Show At Risk ({{ $atRisk->count() }})
            </button>
        </div>
    @endif

    {{-- ══════ FILTER BAR ══════ --}}
    <div class="flex flex-wrap items-center gap-1.5">
        {{-- Search --}}
        <div class="relative">
            <input type="text" x-model="search" placeholder="Search tasks…"
                   class="pl-7 pr-2 py-1 rounded-md text-xs border w-48"
                   style="background:var(--surface-2); border-color:var(--border-default); color:var(--text-primary);">
            <svg class="w-3.5 h-3.5 absolute left-2 top-1/2 -translate-y-1/2" style="color:var(--text-muted);" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" /></svg>
        </div>

        {{-- Due chips --}}
        <template x-for="f in [
            { key: 'overdue',  label: 'Overdue',  color: '#ef4444' },
            { key: 'today',    label: 'Today',    color: '#f59e0b' },
            { key: 'week',     label: 'This Week',color: '#8b5cf6' },
        ]" :key="f.key">
            <button type="button" @click="toggleFilter(f.key)"
                    class="px-2 py-1 rounded-md text-[11px] font-medium border transition-colors"
                    :style="filters[f.key]
                        ? `background:${f.color}20; border-color:${f.color}60; color:${f.color};`
                        : 'background:var(--surface-2); border-color:var(--border-default); color:var(--text-secondary);'">
                <span x-text="f.label"></span>
            </button>
        </template>

        <span class="w-px h-4" style="background:var(--border-default);"></span>

        {{-- Priority chips --}}
        <button type="button" @click="toggleFilter('critical')"
                class="px-2 py-1 rounded-md text-[11px] font-medium border"
                :style="filters.critical
                    ? 'background:rgba(239,68,68,0.15); border-color:rgba(239,68,68,0.4); color:#ef4444;'
                    : 'background:var(--surface-2); border-color:var(--border-default); color:var(--text-secondary);'">
            Critical
        </button>
        <button type="button" @click="toggleFilter('high')"
                class="px-2 py-1 rounded-md text-[11px] font-medium border"
                :style="filters.high
                    ? 'background:rgba(245,158,11,0.15); border-color:rgba(245,158,11,0.4); color:#f59e0b;'
                    : 'background:var(--surface-2); border-color:var(--border-default); color:var(--text-secondary);'">
            High+
        </button>

        <span class="w-px h-4" style="background:var(--border-default);"></span>

        {{-- Pillar chips --}}
        @foreach($pillarStyle as $pk => $ps)
            <button type="button" @click="togglePillar('{{ $pk }}')"
                    class="px-2 py-1 rounded-md text-[11px] font-medium border"
                    :style="pillars.includes('{{ $pk }}')
                        ? 'background:{{ $ps['bg'] }}; border-color:{{ $ps['fg'] }}60; color:{{ $ps['fg'] }};'
                        : 'background:var(--surface-2); border-color:var(--border-default); color:var(--text-secondary);'">
                {{ $ps['label'] }}
            </button>
        @endforeach

        {{-- Reset --}}
        <button type="button" @click="resetFilters()" x-show="activeFilterCount > 0"
                class="px-2 py-1 rounded-md text-[11px] font-medium"
                style="color:var(--text-muted);">
            Clear filters
        </button>
    </div>

    @if($currentView === 'kanban')
        {{-- ══════ KANBAN BOARD ══════ --}}
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
            @foreach(['todo' => 'To Do', 'in_progress' => 'In Progress', 'awaiting' => 'Awaiting', 'done' => 'Done'] as $statusKey => $statusLabel)
                @php
                    $colTasks = $columns[$statusKey] ?? collect();
                    $headerColors = [
                        'todo' => '#6b7280',
                        'in_progress' => '#3b82f6',
                        'awaiting' => '#f59e0b',
                        'done' => '#10b981',
                    ];
                @endphp
                <div class="flex flex-col" data-kanban-column="{{ $statusKey }}"
                     x-show="!emptyColumnHidden('{{ $statusKey }}')">

                    {{-- Header (click to toggle collapse) --}}
                    <button type="button"
                            @click="toggleColumn('{{ $statusKey }}')"
                            class="flex items-center justify-between w-full px-2.5 py-1.5 rounded-t-md text-left"
                            :class="colCollapsed['{{ $statusKey }}'] ? 'rounded-b-md' : ''"
                            style="background:{{ $headerColors[$statusKey] }}15;">
                        <div class="flex items-center gap-1.5">
                            <svg class="w-3 h-3 transition-transform"
                                 :class="colCollapsed['{{ $statusKey }}'] ? '-rotate-90' : ''"
                                 style="color:{{ $headerColors[$statusKey] }};"
                                 fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                            </svg>
                            <span class="w-2 h-2 rounded-full" style="background:{{ $headerColors[$statusKey] }};"></span>
                            <span class="text-xs font-semibold" style="color:var(--text-primary);">{{ $statusLabel }}</span>
                        </div>
                        <span class="text-[10px] px-1.5 py-0.5 rounded-full" style="background:var(--surface-2); color:var(--text-muted);"
                              data-col-count>{{ $colTasks->count() }}</span>
                    </button>

                    {{-- Column body (scrollable, capped height) --}}
                    <div class="flex-1 space-y-1.5 p-1.5 rounded-b-md overflow-y-auto"
                         data-drop-zone="{{ $statusKey }}"
                         x-show="!colCollapsed['{{ $statusKey }}']" x-transition
                         style="background:var(--surface-2); border:1px solid var(--border-default); border-top:none; max-height: calc(100vh - 280px); min-height: 8rem;">
                        @forelse($colTasks as $task)
                            @include('command-center.partials.task-card', ['task' => $task, 'compact' => true, 'showPillar' => true, 'pillarStyle' => $pillarStyle, 'statusKey' => $statusKey])
                        @empty
                            <div class="py-4 text-center text-[11px]" data-empty-placeholder style="color:var(--text-muted);">No tasks</div>
                        @endforelse

                        {{-- Show more / show less --}}
                        <button type="button" data-col-more
                                @click="showMoreColumn('{{ $statusKey }}')"
                                x-show="false"
                                class="w-full text-[11px] py-1.5 rounded-md font-medium"
                                style="background:var(--surface); color:var(--text-secondary); border:1px dashed var(--border-default);">
                            Show more
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        {{-- ══════ LIST VIEW — grouped by smart date buckets ══════ --}}
        <div class="space-y-2">
            @foreach($buckets as $bKey => $bucket)
                @if($bucket['tasks']->isNotEmpty())
                    <div class="rounded-md border" data-list-group="{{ $bKey }}"
                         style="background:var(--surface); border-color:var(--border-default);">
                        {{-- Group header --}}
                        <button type="button"
                                @click="toggleBucket('{{ $bKey }}')"
                                class="flex items-center justify-between w-full px-3 py-2 text-left"
                                style="background:{{ $bucket['color'] }}10;">
                            <div class="flex items-center gap-2">
                                <svg class="w-3 h-3 transition-transform"
                                     :class="bucketCollapsed['{{ $bKey }}'] ? '-rotate-90' : ''"
                                     style="color:{{ $bucket['color'] }};"
                                     fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                                <span class="w-1.5 h-1.5 rounded-full" style="background:{{ $bucket['color'] }};"></span>
                                <span class="text-xs font-semibold" style="color:var(--text-primary);">{{ $bucket['label'] }}</span>
                                <span class="text-[10px] px-1.5 py-0.5 rounded-full" data-group-count
                                      style="background:{{ $bucket['color'] }}20; color:{{ $bucket['color'] }};">{{ $bucket['tasks']->count() }}</span>
                            </div>
                        </button>

                        {{-- Group body --}}
                        <div x-show="!bucketCollapsed['{{ $bKey }}']" x-transition>
                            <table class="w-full text-sm">
                                <tbody>
                                    @foreach($bucket['tasks'] as $task)
                                        @php
                                            $statusColors = [
                                                'todo' => '#6b7280', 'in_progress' => '#3b82f6',
                                                'awaiting' => '#f59e0b', 'done' => '#10b981', 'dismissed' => '#9ca3af',
                                            ];
                                            $taskLink = $task->property ? route('corex.properties.show', $task->property)
                                                      : ($task->contact  ? route('corex.contacts.show',  $task->contact)
                                                      : ($task->deal_id  ? route('deals-v2.show',        $task->deal_id) : null));
                                            $tag = $task->pillarTag();
                                            $searchHay = strtolower(trim(
                                                ($task->title ?? '') . ' ' .
                                                ($task->property?->buildDisplayAddress() ?? '') . ' ' .
                                                ($task->contact ? ($task->contact->first_name . ' ' . $task->contact->last_name) : '')
                                            ));
                                        @endphp
                                        <tr class="border-t hover:bg-white/5 transition-colors"
                                            data-task-row
                                            data-status="{{ $task->status }}"
                                            data-priority="{{ $task->priority }}"
                                            data-pillar="{{ $tag ?? '' }}"
                                            data-bucket="{{ $bKey }}"
                                            data-title="{{ $searchHay }}"
                                            style="border-color:var(--border-default);">
                                            <td class="py-2 px-3" style="color:var(--text-primary);">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    @if($tag && isset($pillarStyle[$tag]))
                                                        <span class="text-[9px] font-bold uppercase px-1 py-px rounded flex-shrink-0" style="background:{{ $pillarStyle[$tag]['bg'] }}; color:{{ $pillarStyle[$tag]['fg'] }};">{{ $pillarStyle[$tag]['label'] }}</span>
                                                    @endif
                                                    @if($task->priority === 'critical')
                                                        <span class="text-[9px] font-bold uppercase px-1 py-px rounded flex-shrink-0" style="background:rgba(239,68,68,0.15); color:#ef4444;">Crit</span>
                                                    @elseif($task->priority === 'high')
                                                        <span class="text-[9px] font-bold uppercase px-1 py-px rounded flex-shrink-0" style="background:rgba(245,158,11,0.15); color:#f59e0b;">High</span>
                                                    @endif
                                                    @if($taskLink)
                                                        <a href="{{ $taskLink }}" class="text-sm hover:underline {{ $task->status === 'done' ? 'line-through opacity-60' : '' }}">{{ $task->title }}</a>
                                                    @else
                                                        <span class="text-sm {{ $task->status === 'done' ? 'line-through opacity-60' : '' }}">{{ $task->title }}</span>
                                                    @endif
                                                </div>
                                                @if($task->property || $task->contact)
                                                    <div class="text-[11px] mt-0.5 truncate" style="color:var(--text-muted);">
                                                        @if($task->property){{ $task->property->buildDisplayAddress() }}
                                                        @elseif($task->contact){{ $task->contact->first_name }} {{ $task->contact->last_name }}
                                                        @endif
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="py-2 px-3 w-28">
                                                <span class="text-[11px] px-1.5 py-0.5 rounded-full font-medium" style="background:{{ $statusColors[$task->status] ?? '#6b7280' }}20; color:{{ $statusColors[$task->status] ?? '#6b7280' }};">
                                                    {{ str_replace('_', ' ', ucfirst($task->status)) }}
                                                </span>
                                            </td>
                                            <td class="py-2 px-3 text-xs w-24" style="color:{{ $task->isOverdue() ? '#ef4444' : 'var(--text-muted)' }};">
                                                {{ $task->due_date ? $task->due_date->format('d M Y') : '—' }}
                                            </td>
                                            <td class="py-2 px-3 w-20 text-right">
                                                @if($task->status !== 'done')
                                                    <form method="POST" action="{{ route('command-center.tasks.complete', $task) }}" class="inline">
                                                        @csrf
                                                        <button type="submit" class="text-xs px-2 py-1 rounded-md font-medium" style="background:var(--surface-2); color:var(--text-secondary);">Done</button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            @endforeach

            @if($allTasks->isEmpty())
                <div class="corex-panel">
                    <div class="corex-panel-body py-8 text-center text-sm" style="color:var(--text-muted);">No tasks</div>
                </div>
            @endif
        </div>
    @endif

    {{-- CREATE TASK MODAL --}}
    <div x-show="showCreateTask" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="background:rgba(0,0,0,0.5);"
         @keydown.escape.window="showCreateTask = false">
        <div class="w-full max-w-lg rounded-lg shadow-xl" style="background:var(--surface);"
             @click.outside="showCreateTask = false">
            <form method="POST" action="{{ route('command-center.tasks.store') }}">
                @csrf
                <div class="px-6 py-4 border-b" style="border-color:var(--border-default);">
                    <h3 class="text-lg font-semibold" style="color:var(--text-primary);">Create Task</h3>
                </div>
                <div class="px-6 py-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color:var(--text-secondary);">Title</label>
                        <input type="text" name="title" required class="w-full px-3 py-2 rounded-md text-sm border" style="background:var(--surface-2); border-color:var(--border-default); color:var(--text-primary);">
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color:var(--text-secondary);">Priority</label>
                            <select name="priority" class="w-full px-3 py-2 rounded-md text-sm border" style="background:var(--surface-2); border-color:var(--border-default); color:var(--text-primary);">
                                <option value="normal">Normal</option>
                                <option value="low">Low</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium mb-1" style="color:var(--text-secondary);">Due Date</label>
                            <input type="date" name="due_date" class="w-full px-3 py-2 rounded-md text-sm border" style="background:var(--surface-2); border-color:var(--border-default); color:var(--text-primary);">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color:var(--text-secondary);">Type</label>
                        <select name="task_type" class="w-full px-3 py-2 rounded-md text-sm border" style="background:var(--surface-2); border-color:var(--border-default); color:var(--text-primary);">
                            <option value="custom">General</option>
                            <option value="document_upload">Document Upload</option>
                            <option value="follow_up">Follow-up</option>
                            <option value="compliance">Compliance</option>
                            <option value="review">Review</option>
                            <option value="deal_action">Deal Action</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1" style="color:var(--text-secondary);">Description</label>
                        <textarea name="description" rows="3" class="w-full px-3 py-2 rounded-md text-sm border" style="background:var(--surface-2); border-color:var(--border-default); color:var(--text-primary);"></textarea>
                    </div>
                    <div>
                        <label class="flex items-center gap-2 text-sm" style="color:var(--text-secondary);">
                            <input type="hidden" name="send_reminder" value="0">
                            <input type="checkbox" name="send_reminder" value="1" checked class="rounded">
                            Send me a reminder before this task is due
                        </label>
                    </div>
                </div>
                <div class="px-6 py-4 border-t flex justify-end gap-2" style="border-color:var(--border-default);">
                    <button type="button" @click="showCreateTask = false" class="px-4 py-2 rounded-md text-sm font-medium" style="background:var(--surface-2); color:var(--text-secondary);">Cancel</button>
                    <button type="submit" class="px-4 py-2 rounded-md text-sm font-semibold text-white" style="background:var(--brand-button);">Create Task</button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
function taskBoard() {
    const LS = {
        density: 'hfc_tasks_density',
        col:     'hfc_tasks_col_collapsed',
        bucket:  'hfc_tasks_bucket_collapsed',
        atRisk:  'hfc_tasks_atrisk_collapsed',
    };
    const readJSON = (k, fb) => {
        try { return JSON.parse(localStorage.getItem(k)) ?? fb; } catch (e) { return fb; }
    };

    return {
        showCreateTask: false,
        search: '',
        filters: { overdue: false, today: false, week: false, critical: false, high: false },
        pillars: [],
        density: localStorage.getItem(LS.density) || 'comfortable',
        colCollapsed: readJSON(LS.col, { todo: false, in_progress: false, awaiting: false, done: true }),
        bucketCollapsed: readJSON(LS.bucket, { overdue: false, today: false, tomorrow: false, week: false, later: true, none: true }),
        atRiskCollapsed: localStorage.getItem(LS.atRisk) === '1',
        colShowAll: {},
        limitPerColumn: 10,

        get activeFilterCount() {
            return Object.values(this.filters).filter(Boolean).length + this.pillars.length + (this.search ? 1 : 0);
        },

        init() {
            this.$watch('density',         v => localStorage.setItem(LS.density, v));
            this.$watch('colCollapsed',    v => localStorage.setItem(LS.col, JSON.stringify(v)), { deep: true });
            this.$watch('bucketCollapsed', v => localStorage.setItem(LS.bucket, JSON.stringify(v)), { deep: true });
            this.$watch('atRiskCollapsed', v => localStorage.setItem(LS.atRisk, v ? '1' : '0'));

            ['search', 'filters', 'pillars', 'colShowAll'].forEach(k => {
                this.$watch(k, () => this.applyFilters(), { deep: true });
            });
            this.$nextTick(() => {
                this.applyFilters();
                this.initDragAndDrop();
            });
        },

        async moveTask(card, newStatus) {
            if (!card) return;
            const taskId    = card.dataset.taskId;
            const oldStatus = card.dataset.status;
            if (newStatus === oldStatus) return;

            const targetZone = document.querySelector(`[data-drop-zone="${newStatus}"]`);
            if (targetZone) {
                const placeholder = targetZone.querySelector('[data-empty-placeholder]');
                targetZone.insertBefore(card, placeholder);
            }
            card.dataset.status = newStatus;

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            try {
                const res = await fetch(`/corex/command-center/tasks/${taskId}/status`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ status: newStatus }),
                });
                if (!res.ok) throw new Error('Status update failed');
                this.applyFilters();
            } catch (err) {
                window.location.reload();
            }
        },

        initDragAndDrop() {
            let draggingCard = null;

            document.querySelectorAll('[data-task-card]').forEach(card => {
                card.addEventListener('dragstart', (e) => {
                    draggingCard = card;
                    card.classList.add('opacity-50');
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', card.dataset.taskId);
                });
                card.addEventListener('dragend', () => {
                    card.classList.remove('opacity-50');
                    draggingCard = null;
                    document.querySelectorAll('[data-drop-zone]').forEach(z => z.classList.remove('ring-2', 'ring-blue-400'));
                });
            });

            document.querySelectorAll('[data-drop-zone]').forEach(zone => {
                zone.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    zone.classList.add('ring-2', 'ring-blue-400');
                });
                zone.addEventListener('dragleave', (e) => {
                    if (!zone.contains(e.relatedTarget)) {
                        zone.classList.remove('ring-2', 'ring-blue-400');
                    }
                });
                zone.addEventListener('drop', (e) => {
                    e.preventDefault();
                    zone.classList.remove('ring-2', 'ring-blue-400');
                    this.moveTask(draggingCard, zone.dataset.dropZone);
                });
            });

            document.querySelectorAll('[data-quick-move]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const card = btn.closest('[data-task-card]');
                    this.moveTask(card, btn.dataset.quickMove);
                });
            });
        },

        toggleFilter(k) { this.filters[k] = !this.filters[k]; },
        togglePillar(p) {
            const i = this.pillars.indexOf(p);
            if (i === -1) this.pillars.push(p); else this.pillars.splice(i, 1);
        },
        toggleColumn(s) { this.colCollapsed = { ...this.colCollapsed, [s]: !this.colCollapsed[s] }; },
        toggleBucket(b) { this.bucketCollapsed = { ...this.bucketCollapsed, [b]: !this.bucketCollapsed[b] }; },
        showMoreColumn(s) { this.colShowAll = { ...this.colShowAll, [s]: !this.colShowAll[s] }; },

        resetFilters() {
            this.search = '';
            this.filters = { overdue: false, today: false, week: false, critical: false, high: false };
            this.pillars = [];
            this.colShowAll = {};
        },

        emptyColumnHidden(status) {
            // hide empty columns on narrow viewports to reduce noise
            if (window.innerWidth >= 768) return false;
            const col = document.querySelector(`[data-kanban-column="${status}"]`);
            if (!col) return false;
            return (col.dataset.visibleCount || '0') === '0';
        },

        matches(el) {
            const title    = el.dataset.title || '';
            const pillar   = el.dataset.pillar || '';
            const priority = el.dataset.priority || '';
            const bucket   = el.dataset.bucket || '';

            if (this.search && !title.includes(this.search.toLowerCase().trim())) return false;
            if (this.filters.overdue  && bucket !== 'overdue') return false;
            if (this.filters.today    && bucket !== 'today')   return false;
            if (this.filters.week     && !['overdue','today','tomorrow','week'].includes(bucket)) return false;
            if (this.filters.critical && priority !== 'critical') return false;
            if (this.filters.high     && !['critical','high'].includes(priority)) return false;
            if (this.pillars.length   && !this.pillars.includes(pillar)) return false;
            return true;
        },

        applyFilters() {
            // Kanban columns
            document.querySelectorAll('[data-kanban-column]').forEach(col => {
                const status = col.dataset.kanbanColumn;
                const cards  = Array.from(col.querySelectorAll('[data-task-card]'));
                const matching = cards.filter(c => this.matches(c));
                cards.forEach(c => { c.style.display = 'none'; });

                const limit = this.colShowAll[status] ? matching.length : this.limitPerColumn;
                matching.forEach((c, i) => { c.style.display = i < limit ? '' : 'none'; });

                // header count badge
                const badge = col.querySelector('[data-col-count]');
                if (badge) badge.textContent = matching.length;

                // show-more button
                const moreBtn = col.querySelector('[data-col-more]');
                if (moreBtn) {
                    const hidden = matching.length - limit;
                    if (hidden > 0 && !this.colShowAll[status]) {
                        moreBtn.textContent = `Show ${hidden} more`;
                        moreBtn.style.display = '';
                    } else if (this.colShowAll[status] && matching.length > this.limitPerColumn) {
                        moreBtn.textContent = 'Show less';
                        moreBtn.style.display = '';
                    } else {
                        moreBtn.style.display = 'none';
                    }
                }

                // empty placeholder
                const empty = col.querySelector('[data-empty-placeholder]');
                if (empty) empty.style.display = matching.length === 0 ? '' : 'none';

                col.dataset.visibleCount = matching.length;
            });

            // List view groups
            document.querySelectorAll('[data-list-group]').forEach(grp => {
                const rows = Array.from(grp.querySelectorAll('[data-task-row]'));
                let visible = 0;
                rows.forEach(r => {
                    const ok = this.matches(r);
                    r.style.display = ok ? '' : 'none';
                    if (ok) visible++;
                });
                const badge = grp.querySelector('[data-group-count]');
                if (badge) badge.textContent = visible;
                grp.style.display = visible > 0 ? '' : 'none';
            });
        },
    };
}
</script>
@endsection
