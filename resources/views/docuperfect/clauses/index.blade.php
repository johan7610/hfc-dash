@extends('layouts.corex')

@section('corex-content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">

    <x-list-header
        title="Clause Library"
        :form-action="route('docuperfect.clauses.index')"
        :paginator="$clauses"
        search-placeholder="Search clauses..."
    >
        <x-slot:filters>
            <select name="visibility" onchange="this.form.submit()" class="list-header-filter">
                <option value="">All visibility</option>
                <option value="global" {{ request('visibility') === 'global' ? 'selected' : '' }}>Global</option>
                <option value="branch" {{ request('visibility') === 'branch' ? 'selected' : '' }}>Branch-specific</option>
            </select>
        </x-slot:filters>
        @if($canEdit)
        <x-slot:actions>
            <button type="button" onclick="document.getElementById('addClauseSection').classList.toggle('hidden')" class="corex-btn-primary text-sm">+ New Clause</button>
        </x-slot:actions>
        @endif
    </x-list-header>

    @if(session('status'))
        <div class="rounded-md px-4 py-3 text-sm" style="border: 1px solid var(--ds-green, #10b981); background: rgba(16,185,129,0.1); color: var(--text-primary);">
            {{ session('status') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-md px-4 py-3 text-sm" style="border: 1px solid #ef4444; background: rgba(239,68,68,0.1); color: var(--text-primary);">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Add Clause --}}
    @if($canEdit)
    <div id="addClauseSection" class="hidden rounded-md p-5" style="background: var(--surface); border: 1px solid var(--border);">
        <h3 class="text-base font-semibold mb-4" style="color: var(--text-primary);">Add Clause</h3>
        <form method="POST" action="{{ route('docuperfect.clauses.store') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Name</label>
                    <input name="name" required
                           class="w-full rounded-md px-3 py-2 text-sm focus:outline-none transition-all duration-300"
                           style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-primary);"
                           onfocus="this.style.borderColor='var(--brand-button)'" onblur="this.style.borderColor='var(--border)'"
                           placeholder="e.g. Subject to Viewing">
                </div>
                <div class="flex items-center gap-4 mt-5">
                    <label class="flex items-center gap-2 text-sm" style="color: var(--text-secondary);">
                        <input type="hidden" name="is_global" value="0">
                        <input type="checkbox" name="is_global" value="1" class="rounded-md" style="border-color: var(--border);"> Global (all branches)
                    </label>
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Clause Text</label>
                <textarea name="text" required rows="4"
                          class="w-full rounded-md px-3 py-2 text-sm focus:outline-none transition-all duration-300"
                          style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-primary);"
                          onfocus="this.style.borderColor='var(--brand-button)'" onblur="this.style.borderColor='var(--border)'"
                          placeholder="Enter the full clause wording..."></textarea>
            </div>
            <div>
                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Branch Access (if not global)</label>
                <div class="flex flex-wrap gap-3">
                    @foreach($branches as $branch)
                    <label class="flex items-center gap-1 text-sm" style="color: var(--text-secondary);">
                        <input type="checkbox" name="branch_ids[]" value="{{ $branch->id }}" class="rounded-md" style="border-color: var(--border);">
                        {{ $branch->name }}
                    </label>
                    @endforeach
                </div>
            </div>
            <button class="corex-btn-primary text-sm">Add Clause</button>
        </form>
    </div>
    @endif

    {{-- Clause List --}}
    @if($clauses->isEmpty())
        <div class="rounded-md p-6 text-center" style="background: var(--surface); border: 1px solid var(--border);">
            <div class="text-sm" style="color: var(--text-muted);">
                @if(request('search') || request('visibility'))
                    No clauses match your search.
                @else
                    No clauses yet.
                @endif
            </div>
        </div>
    @else
        <div class="space-y-3">
            @foreach($clauses as $clause)
            <div class="rounded-md p-4 transition-all duration-300" style="background: var(--surface); border: 1px solid var(--border);" x-data="{ editing: false }">
                <div x-show="!editing">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="font-semibold text-sm" style="color: var(--text-primary);">{{ $clause->name }}</div>
                            <div class="text-xs mt-1" style="color: var(--text-muted);">
                                @if($clause->is_global)
                                    <span class="ds-badge ds-badge-success text-[10px]">Global</span>
                                @else
                                    {{ $clause->branches->pluck('name')->join(', ') ?: 'No branches assigned' }}
                                @endif
                                @if($clause->owner)
                                    &middot; by {{ $clause->owner->name }}
                                @endif
                            </div>
                        </div>
                        @if($canEdit)
                        <div class="flex items-center gap-3 flex-shrink-0">
                            <button @click="editing = true" class="text-xs font-medium transition-all duration-300" style="color: var(--brand-icon);">Edit</button>
                            <form method="POST" action="{{ route('docuperfect.clauses.copy', $clause->id) }}" class="inline">
                                @csrf
                                <button class="text-xs font-medium transition-all duration-300" style="color: var(--brand-icon);">Copy</button>
                            </form>
                            <form method="POST" action="{{ route('docuperfect.clauses.destroy', $clause->id) }}" class="inline" onsubmit="return confirm('Delete this clause?');">
                                @csrf
                                @method('DELETE')
                                <button class="text-xs transition-all duration-300" style="color: var(--text-muted);" onmouseover="this.style.color='#ef4444'" onmouseout="this.style.color='var(--text-muted)'">Delete</button>
                            </form>
                        </div>
                        @endif
                    </div>
                    <div class="mt-2 text-sm whitespace-pre-line" style="color: var(--text-secondary);">{{ Str::limit($clause->text, 300) }}</div>
                </div>

                @if($canEdit)
                <div x-show="editing" x-cloak>
                    <form method="POST" action="{{ route('docuperfect.clauses.update', $clause->id) }}" class="space-y-4">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Name</label>
                                <input name="name" value="{{ $clause->name }}" required
                                       class="w-full rounded-md px-3 py-2 text-sm focus:outline-none transition-all duration-300"
                                       style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-primary);"
                                       onfocus="this.style.borderColor='var(--brand-button)'" onblur="this.style.borderColor='var(--border)'">
                            </div>
                            <div class="flex items-center gap-4 mt-5">
                                <label class="flex items-center gap-2 text-sm" style="color: var(--text-secondary);">
                                    <input type="hidden" name="is_global" value="0">
                                    <input type="checkbox" name="is_global" value="1" {{ $clause->is_global ? 'checked' : '' }} class="rounded-md" style="border-color: var(--border);"> Global
                                </label>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Clause Text</label>
                            <textarea name="text" required rows="4"
                                      class="w-full rounded-md px-3 py-2 text-sm focus:outline-none transition-all duration-300"
                                      style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-primary);"
                                      onfocus="this.style.borderColor='var(--brand-button)'" onblur="this.style.borderColor='var(--border)'">{{ $clause->text }}</textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Branch Access</label>
                            <div class="flex flex-wrap gap-3">
                                @foreach($branches as $branch)
                                <label class="flex items-center gap-1 text-sm" style="color: var(--text-secondary);">
                                    <input type="checkbox" name="branch_ids[]" value="{{ $branch->id }}" {{ $clause->branches->contains('id', $branch->id) ? 'checked' : '' }} class="rounded-md" style="border-color: var(--border);">
                                    {{ $branch->name }}
                                </label>
                                @endforeach
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <button class="corex-btn-primary text-sm">Save</button>
                            <button type="button" @click="editing = false" class="text-sm transition-all duration-300" style="color: var(--text-muted);" onmouseover="this.style.color='var(--text-primary)'" onmouseout="this.style.color='var(--text-muted)'">Cancel</button>
                        </div>
                    </form>
                </div>
                @endif
            </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $clauses->links() }}
        </div>
    @endif

</div>
@endsection
