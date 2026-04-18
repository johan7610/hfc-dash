@extends('layouts.corex')

@section('corex-content')
<div class="space-y-4" x-data="taskBoard()">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-xl font-bold" style="color:var(--text-primary);">Tasks</h1>
            <div class="flex items-center gap-3 mt-1 text-xs" style="color:var(--text-muted);">
                <span>{{ $summary['open'] }} open</span>
                @if($summary['overdue'] > 0)
                    <span style="color:#ef4444;">{{ $summary['overdue'] }} overdue</span>
                @endif
                <span>{{ $summary['today'] }} due today</span>
                <span>{{ $summary['thisWeek'] }} this week</span>
            </div>
        </div>
        <div class="flex items-center gap-2">
            @foreach(['kanban' => 'Board', 'list' => 'List'] as $vKey => $vLabel)
                <a href="{{ route('command-center.tasks', ['view' => $vKey]) }}"
                   class="px-3 py-1.5 rounded-md text-xs font-medium transition-colors"
                   style="{{ $currentView === $vKey ? 'background:var(--brand-button); color:#fff;' : 'background:var(--surface-2); color:var(--text-secondary);' }}">
                    {{ $vLabel }}
                </a>
            @endforeach
            <button @click="showCreateTask = true"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold text-white"
                    style="background:var(--brand-button);">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                New Task
            </button>
        </div>
    </div>

    @if($currentView === 'kanban')
        {{-- ══════ KANBAN BOARD ══════ --}}
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
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
                <div class="flex flex-col">
                    {{-- Column header --}}
                    <div class="flex items-center justify-between px-3 py-2 rounded-t-md" style="background:{{ $headerColors[$statusKey] }}15;">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full" style="background:{{ $headerColors[$statusKey] }};"></span>
                            <span class="text-sm font-semibold" style="color:var(--text-primary);">{{ $statusLabel }}</span>
                        </div>
                        <span class="text-xs px-1.5 py-0.5 rounded-full" style="background:var(--surface-2); color:var(--text-muted);">{{ $colTasks->count() }}</span>
                    </div>

                    {{-- Task cards --}}
                    <div class="flex-1 space-y-2 p-2 rounded-b-md min-h-[12rem]" style="background:var(--surface-2); border:1px solid var(--border-default); border-top:none;">
                        @forelse($colTasks as $task)
                            @php
                                $taskLink = $task->property ? route('corex.properties.show', $task->property)
                                          : ($task->contact  ? route('corex.contacts.show',  $task->contact)
                                          : ($task->deal_id  ? route('deals-v2.show',        $task->deal_id) : null));
                            @endphp
                            <div class="corex-panel transition-shadow hover:shadow-md" style="margin:0;">
                                <div class="p-3 space-y-2">
                                    {{-- Title --}}
                                    @if($taskLink)
                                        <a href="{{ $taskLink }}" class="block text-sm font-medium leading-snug hover:underline" style="color:var(--text-primary);">{{ $task->title }}</a>
                                    @else
                                        <p class="text-sm font-medium leading-snug" style="color:var(--text-primary);">{{ $task->title }}</p>
                                    @endif

                                    {{-- Property / Contact link --}}
                                    @if($task->property)
                                        <a href="{{ route('corex.properties.show', $task->property) }}"
                                           class="block text-xs truncate hover:underline" style="color:var(--text-muted);">
                                            {{ $task->property->buildDisplayAddress() ?: ($task->property->title ?: '') }}
                                        </a>
                                    @elseif($task->contact)
                                        <a href="{{ route('corex.contacts.show', $task->contact) }}"
                                           class="block text-xs truncate hover:underline" style="color:var(--text-muted);">
                                            {{ $task->contact->first_name }} {{ $task->contact->last_name }}
                                        </a>
                                    @endif

                                    {{-- Footer: priority, due date, actions --}}
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-1.5">
                                            @if($task->priority === 'critical')
                                                <span class="text-[10px] px-1.5 py-0.5 rounded font-medium" style="background:rgba(239,68,68,0.1); color:#ef4444;">Critical</span>
                                            @elseif($task->priority === 'high')
                                                <span class="text-[10px] px-1.5 py-0.5 rounded font-medium" style="background:rgba(245,158,11,0.1); color:#f59e0b;">High</span>
                                            @endif
                                            @if($task->due_date)
                                                <span class="text-[10px]" style="color:{{ $task->isOverdue() ? '#ef4444' : 'var(--text-muted)' }};">
                                                    {{ $task->due_date->isToday() ? 'Today' : ($task->due_date->isTomorrow() ? 'Tomorrow' : $task->due_date->format('d M')) }}
                                                </span>
                                            @endif
                                        </div>

                                        {{-- Status change buttons --}}
                                        <div class="flex items-center gap-1">
                                            @if($statusKey !== 'done')
                                                @php
                                                    $nextStatus = match($statusKey) {
                                                        'todo' => 'in_progress',
                                                        'in_progress' => 'awaiting',
                                                        'awaiting' => 'done',
                                                        default => null,
                                                    };
                                                @endphp
                                                @if($nextStatus)
                                                    <form method="POST" action="{{ route('command-center.tasks.update-status', $task) }}">
                                                        @csrf @method('PATCH')
                                                        <input type="hidden" name="status" value="{{ $nextStatus }}">
                                                        <button type="submit" class="p-1 rounded transition-colors hover:bg-white/10" title="Move to {{ str_replace('_', ' ', $nextStatus) }}">
                                                            <svg class="w-3.5 h-3.5" style="color:var(--text-muted);" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" /></svg>
                                                        </button>
                                                    </form>
                                                @endif
                                                <form method="POST" action="{{ route('command-center.tasks.complete', $task) }}">
                                                    @csrf
                                                    <button type="submit" class="p-1 rounded transition-colors hover:bg-green-500/10" title="Complete">
                                                        <svg class="w-3.5 h-3.5 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="py-6 text-center text-xs" style="color:var(--text-muted);">
                                No tasks
                            </div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @else
        {{-- ══════ LIST VIEW ══════ --}}
        <div class="corex-panel">
            <div class="corex-panel-body overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b" style="border-color:var(--border-default);">
                            <th class="text-left py-2 px-3 font-medium" style="color:var(--text-muted);">Task</th>
                            <th class="text-left py-2 px-3 font-medium" style="color:var(--text-muted);">Status</th>
                            <th class="text-left py-2 px-3 font-medium" style="color:var(--text-muted);">Priority</th>
                            <th class="text-left py-2 px-3 font-medium" style="color:var(--text-muted);">Due</th>
                            <th class="text-left py-2 px-3 font-medium" style="color:var(--text-muted);">Property</th>
                            <th class="py-2 px-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @php $allTasks = collect($columns)->flatten(1)->sortBy('due_date'); @endphp
                        @forelse($allTasks as $task)
                            @php
                                $statusColors = [
                                    'todo' => '#6b7280', 'in_progress' => '#3b82f6',
                                    'awaiting' => '#f59e0b', 'done' => '#10b981', 'dismissed' => '#9ca3af',
                                ];
                                $taskLink = $task->property ? route('corex.properties.show', $task->property)
                                          : ($task->contact  ? route('corex.contacts.show',  $task->contact)
                                          : ($task->deal_id  ? route('deals-v2.show',        $task->deal_id) : null));
                            @endphp
                            <tr class="border-b hover:bg-white/5 transition-colors" style="border-color:var(--border-default);">
                                <td class="py-2.5 px-3" style="color:var(--text-primary);">
                                    @if($taskLink)
                                        <a href="{{ $taskLink }}" class="hover:underline {{ $task->status === 'done' ? 'line-through opacity-60' : '' }}">{{ $task->title }}</a>
                                    @else
                                        <span class="{{ $task->status === 'done' ? 'line-through opacity-60' : '' }}">{{ $task->title }}</span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3">
                                    <span class="text-xs px-2 py-0.5 rounded-full font-medium" style="background:{{ $statusColors[$task->status] ?? '#6b7280' }}20; color:{{ $statusColors[$task->status] ?? '#6b7280' }};">
                                        {{ str_replace('_', ' ', ucfirst($task->status)) }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3">
                                    @if($task->priority === 'critical')
                                        <span class="text-xs font-medium" style="color:#ef4444;">Critical</span>
                                    @elseif($task->priority === 'high')
                                        <span class="text-xs font-medium" style="color:#f59e0b;">High</span>
                                    @else
                                        <span class="text-xs" style="color:var(--text-muted);">{{ ucfirst($task->priority) }}</span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 text-xs" style="color:{{ $task->isOverdue() ? '#ef4444' : 'var(--text-muted)' }};">
                                    {{ $task->due_date ? $task->due_date->format('d M Y') : '—' }}
                                </td>
                                <td class="py-2.5 px-3 text-xs truncate max-w-[12rem]" style="color:var(--text-muted);">
                                    @if($task->property)
                                        <a href="{{ route('corex.properties.show', $task->property) }}" class="hover:underline">{{ $task->property->buildDisplayAddress() }}</a>
                                    @elseif($task->contact)
                                        <a href="{{ route('corex.contacts.show', $task->contact) }}" class="hover:underline">{{ $task->contact->first_name }} {{ $task->contact->last_name }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="py-2.5 px-3">
                                    @if($task->status !== 'done')
                                        <form method="POST" action="{{ route('command-center.tasks.complete', $task) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="text-xs px-2 py-1 rounded-md font-medium" style="background:var(--surface-2); color:var(--text-secondary);">Done</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="py-8 text-center text-sm" style="color:var(--text-muted);">No tasks</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
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
    return {
        showCreateTask: false,
    };
}
</script>
@endsection
