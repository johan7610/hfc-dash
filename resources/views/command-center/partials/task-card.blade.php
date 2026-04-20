@php
    /** @var \App\Models\CommandCenter\CommandTask $task */
    /** @var bool $showPillar */
    /** @var array $pillarStyle */
    /** @var string $statusKey */
    $tag = $task->pillarTag();
    $taskLink = $task->property ? route('corex.properties.show', $task->property)
              : ($task->contact  ? route('corex.contacts.show',  $task->contact)
              : ($task->deal_id  ? route('deals-v2.show',        $task->deal_id) : null));
    $nextStatus = match($statusKey) {
        'todo'        => 'in_progress',
        'in_progress' => 'awaiting',
        'awaiting'    => 'done',
        default       => null,
    };

    // Due bucket — drives filter chips + list-view grouping
    $bucket = 'none';
    if ($task->due_date) {
        if ($task->isOverdue())                                  $bucket = 'overdue';
        elseif ($task->due_date->isToday())                      $bucket = 'today';
        elseif ($task->due_date->isTomorrow())                   $bucket = 'tomorrow';
        elseif ($task->due_date->between(now()->startOfWeek(), now()->endOfWeek()))
                                                                 $bucket = 'week';
        else                                                     $bucket = 'later';
    }

    // Lowercase haystack for client-side search
    $searchHay = strtolower(trim(
        ($task->title ?? '') . ' ' .
        ($task->property?->buildDisplayAddress() ?? '') . ' ' .
        ($task->property?->title ?? '') . ' ' .
        ($task->contact ? ($task->contact->first_name . ' ' . $task->contact->last_name) : '')
    ));
@endphp
<div data-task-card
     data-task-id="{{ $task->id }}"
     data-status="{{ $task->status }}"
     data-priority="{{ $task->priority }}"
     data-pillar="{{ $tag ?? '' }}"
     data-bucket="{{ $bucket }}"
     data-title="{{ $searchHay }}"
     draggable="true"
     class="task-card rounded-md transition-shadow hover:shadow-sm cursor-grab active:cursor-grabbing"
     :class="$root.density === 'compact' ? 'p-1.5' : 'p-2'"
     style="background:var(--surface); border:1px solid var(--border-default);">
    {{-- Row 1: tag + priority --}}
    <div class="flex items-center gap-1.5 flex-wrap" x-show="$root.density !== 'compact' || true">
        @if(($showPillar ?? true) && $tag && isset($pillarStyle[$tag]))
            <span class="text-[9px] font-bold uppercase px-1 py-px rounded"
                  style="background:{{ $pillarStyle[$tag]['bg'] }}; color:{{ $pillarStyle[$tag]['fg'] }}; letter-spacing:0.5px;">
                {{ $pillarStyle[$tag]['label'] }}
            </span>
        @endif
        @if($task->priority === 'critical')
            <span class="text-[9px] font-bold uppercase px-1 py-px rounded" style="background:rgba(239,68,68,0.15); color:#ef4444; letter-spacing:0.5px;">Crit</span>
        @elseif($task->priority === 'high')
            <span class="text-[9px] font-bold uppercase px-1 py-px rounded" style="background:rgba(245,158,11,0.15); color:#f59e0b; letter-spacing:0.5px;">High</span>
        @endif
        @if($bucket === 'overdue')
            <span class="text-[9px] font-bold uppercase px-1 py-px rounded" style="background:rgba(239,68,68,0.15); color:#ef4444; letter-spacing:0.5px;">Overdue</span>
        @endif
    </div>

    {{-- Row 2: title --}}
    @if($taskLink)
        <a href="{{ $taskLink }}"
           class="block font-medium leading-snug mt-1 hover:underline {{ $task->status === 'done' ? 'line-through opacity-70' : '' }}"
           :class="$root.density === 'compact' ? 'text-[12px]' : 'text-[13px]'"
           style="color:var(--text-primary);">
            {{ $task->title }}
        </a>
    @else
        <p class="font-medium leading-snug mt-1 {{ $task->status === 'done' ? 'line-through opacity-70' : '' }}"
           :class="$root.density === 'compact' ? 'text-[12px]' : 'text-[13px]'"
           style="color:var(--text-primary);">{{ $task->title }}</p>
    @endif

    {{-- Row 3: linked entity (hidden in compact density) --}}
    <div x-show="$root.density !== 'compact'">
        @if($task->property)
            <a href="{{ route('corex.properties.show', $task->property) }}" class="block text-[11px] truncate mt-0.5 hover:underline" style="color:var(--text-muted);">
                {{ $task->property->buildDisplayAddress() ?: ($task->property->title ?: '') }}
            </a>
        @elseif($task->contact)
            <a href="{{ route('corex.contacts.show', $task->contact) }}" class="block text-[11px] truncate mt-0.5 hover:underline" style="color:var(--text-muted);">
                {{ $task->contact->first_name }} {{ $task->contact->last_name }}
            </a>
        @endif
    </div>

    {{-- Row 4: due + action buttons --}}
    <div class="flex items-center justify-between mt-1.5">
        @if($task->due_date)
            <span class="text-[10px]" style="color:{{ $task->isOverdue() ? '#ef4444' : 'var(--text-muted)' }};">
                {{ $task->due_date->isToday() ? 'Today' : ($task->due_date->isTomorrow() ? 'Tomorrow' : $task->due_date->format('d M')) }}
            </span>
        @else
            <span></span>
        @endif

        <div class="flex items-center gap-0.5">
            @if($statusKey !== 'done' && $nextStatus)
                <button type="button" data-quick-move="{{ $nextStatus }}"
                        class="p-1 rounded hover:bg-white/10" title="Move to {{ str_replace('_',' ',$nextStatus) }}">
                    <svg class="w-3 h-3" style="color:var(--text-muted);" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                </button>
                <button type="button" data-quick-move="done"
                        class="p-1 rounded hover:bg-green-500/10" title="Complete">
                    <svg class="w-3 h-3 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                </button>
            @elseif($statusKey === 'done')
                <form method="POST" action="{{ route('command-center.tasks.destroy', $task) }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="p-1 rounded hover:bg-white/10" title="Archive">
                        <svg class="w-3 h-3" style="color:var(--text-muted);" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" /></svg>
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>
