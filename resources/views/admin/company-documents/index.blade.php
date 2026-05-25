@extends('layouts.corex-app')

@section('corex-content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 py-6 space-y-5">

    <div class="rounded-md px-6 py-5" style="background: var(--brand-default, #0b2a4a);">
        <h1 class="text-xl font-bold text-white leading-tight">Company Documents</h1>
        <p class="text-sm text-white/60 mt-1">Privacy policy, T&amp;Cs, complaints procedure, AML statement, code of conduct. Each document gets a public link (<code style="background:rgba(255,255,255,0.1);padding:1px 4px;border-radius:3px;">/legal/&lt;token&gt;</code>) usable on emails, mandates, and the public website.</p>
    </div>

    @if(session('success'))
    <div class="rounded-md px-4 py-3 text-sm font-medium" style="background: color-mix(in srgb, var(--ds-green) 10%, transparent); border:1px solid color-mix(in srgb, var(--ds-green) 30%, transparent); color: var(--text-primary);">
        {{ session('success') }}
    </div>
    @endif
    @if(session('info'))
    <div class="rounded-md px-4 py-3 text-sm font-medium" style="background: color-mix(in srgb, var(--ds-amber) 10%, transparent); border:1px solid color-mix(in srgb, var(--ds-amber) 30%, transparent); color: var(--text-primary);">
        {{ session('info') }}
    </div>
    @endif

    {{-- Documents list --}}
    <div class="rounded-md" style="background:var(--surface); border:1px solid var(--border);">
        <div class="px-4 py-3 flex items-center justify-between" style="border-bottom:1px solid var(--border); background:var(--surface-2);">
            <div class="text-sm font-semibold" style="color:var(--text-primary);">Documents ({{ $documents->count() }})</div>
        </div>

        @forelse($documents as $doc)
        <div class="px-4 py-3 flex items-center gap-3" style="border-bottom:1px solid var(--border);">
            <div class="flex-1 min-w-0">
                <div class="text-sm font-semibold" style="color:var(--text-primary);">{{ $doc->title }}</div>
                <div class="text-xs mt-0.5" style="color:var(--text-muted);">
                    {{ $doc->typeLabel() }}
                    @if($doc->is_published)
                        · <span style="color: var(--ds-green);">Published {{ $doc->published_at?->diffForHumans() }}</span>
                    @else
                        · <span style="color: var(--ds-amber);">Draft</span>
                    @endif
                    · Last updated {{ $doc->updated_at->diffForHumans() }}{{ $doc->lastUpdatedBy?->name ? ' by '.$doc->lastUpdatedBy->name : '' }}
                </div>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.company-documents.edit', $doc->id) }}" class="corex-btn-outline text-xs">Edit</a>
                @if($doc->is_published)
                    <a href="{{ $doc->publicUrl() }}" target="_blank" rel="noopener" class="corex-btn-outline text-xs">View public</a>
                @endif
                <form method="POST" action="{{ route('admin.company-documents.toggle-published', $doc->id) }}" class="inline">
                    @csrf
                    <button type="submit" class="corex-btn-outline text-xs">{{ $doc->is_published ? 'Unpublish' : 'Publish' }}</button>
                </form>
            </div>
        </div>
        @empty
        <div class="px-4 py-6 text-sm text-center" style="color:var(--text-muted);">
            No company documents yet. Create one below.
        </div>
        @endforelse
    </div>

    {{-- Create new --}}
    <div class="rounded-md p-4" style="background:var(--surface); border:1px solid var(--border);">
        <div class="text-sm font-semibold mb-3" style="color:var(--text-primary);">Add a document</div>
        <form method="POST" action="{{ route('admin.company-documents.create') }}" class="flex items-end gap-3">
            @csrf
            <div class="flex-1">
                <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary);">Document type</label>
                <select name="document_type" required class="w-full px-3 py-2 text-sm rounded" style="background:var(--surface-2); border:1px solid var(--border); color:var(--text-primary);">
                    <option value="">— pick a type —</option>
                    @foreach($documentTypes as $slug => $label)
                        @php
                            $alreadyExists = $documents->firstWhere('document_type', $slug);
                        @endphp
                        <option value="{{ $slug }}" @if($alreadyExists) disabled @endif>
                            {{ $label }}@if($alreadyExists) (exists){{ '' }}@endif
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="corex-btn-primary text-sm">Create</button>
        </form>
    </div>

</div>
@endsection
