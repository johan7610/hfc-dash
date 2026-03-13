@extends('layouts.corex')

@section('corex-content')

@php
    $docTypeLabels = $documentTypes->pluck('label', 'key')->toArray();
@endphp

{{-- Page Header --}}
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold" style="color: var(--text-primary);">Document Library</h1>
            <p class="text-sm mt-1" style="color: var(--text-muted);">Upload, browse and manage shared documents.</p>
        </div>
        @if($presentation)
            <a href="{{ $returnUrl ?? route('presentations.show', $presentation) . '#documents' }}"
               class="corex-btn-outline text-xs px-3 py-1.5">
                Back to Presentation #{{ $presentation->id }}
            </a>
        @endif
    </div>
</div>

{{-- Attach toolbar --}}
@if($presentation)
<div class="rounded-md p-4 mb-4" style="background: color-mix(in srgb, var(--brand-icon, #0ea5e9) 8%, var(--surface)); border: 1px solid color-mix(in srgb, var(--brand-icon, #0ea5e9) 20%, var(--border));">
    <p class="text-sm font-semibold mb-1" style="color: var(--text-primary);">
        Attaching to: {{ $presentation->title }} (#{{ $presentation->id }})
    </p>
    <p class="text-xs" style="color: var(--brand-icon, #0ea5e9);">Select documents below and click "Attach Selected" to link them to this presentation.</p>
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

    {{-- LEFT: Upload + Filters --}}
    <div class="lg:col-span-1 space-y-4">

        {{-- Upload --}}
        <div class="rounded-md p-4" style="background: var(--surface); border: 1px solid var(--border);">
            <h2 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">Upload to Library</h2>
            <form method="POST" action="{{ route('documents.library.upload') }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs mb-1" style="color: var(--text-muted);">Document Type</label>
                    <select name="doc_type" class="w-full rounded-md px-2.5 py-1.5 text-xs transition-all duration-300 focus:outline-none" style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-primary);" required>
                        <option value="" disabled selected>Select type...</option>
                        @foreach($docTypeLabels as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('doc_type')
                        <p class="text-xs mt-1" style="color: #ef4444;">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-xs mb-1" style="color: var(--text-muted);">Title (optional)</label>
                    <input type="text" name="title" value="{{ old('title') }}"
                           class="w-full rounded-md px-2.5 py-1.5 text-xs transition-all duration-300 focus:outline-none"
                           style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-primary);"
                           placeholder="Descriptive title...">
                </div>
                <div>
                    <label class="block text-xs mb-1" style="color: var(--text-muted);">File</label>
                    <input type="file" name="file"
                           class="w-full text-xs rounded-md px-2.5 py-1.5 transition-all duration-300 focus:outline-none"
                           style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-secondary);"
                           required>
                    @error('file')
                        <p class="text-xs mt-1" style="color: #ef4444;">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="corex-btn-primary w-full text-xs px-3 py-1.5">
                    Upload
                </button>
            </form>
        </div>

        {{-- Filters --}}
        <div class="rounded-md p-4" style="background: var(--surface); border: 1px solid var(--border);">
            <h2 class="text-sm font-semibold mb-3" style="color: var(--text-primary);">Filter</h2>
            <form method="GET" action="{{ route('documents.library.index') }}" class="space-y-3">
                @if($presentationId)
                    <input type="hidden" name="presentation_id" value="{{ $presentationId }}">
                @endif
                @if($returnUrl)
                    <input type="hidden" name="return" value="{{ $returnUrl }}">
                @endif

                <div>
                    <label class="block text-xs mb-1" style="color: var(--text-muted);">Search</label>
                    <input type="text" name="q" value="{{ request('q') }}"
                           class="w-full rounded-md px-2.5 py-1.5 text-xs transition-all duration-300 focus:outline-none"
                           style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-primary);"
                           placeholder="Name or title...">
                </div>
                <div>
                    <label class="block text-xs mb-1" style="color: var(--text-muted);">Doc Type</label>
                    <select name="doc_type" class="w-full rounded-md px-2.5 py-1.5 text-xs transition-all duration-300 focus:outline-none" style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-primary);">
                        <option value="">All types</option>
                        @foreach($docTypes as $dt)
                            <option value="{{ $dt }}" {{ request('doc_type') === $dt ? 'selected' : '' }}>
                                {{ $docTypeLabels[$dt] ?? $dt }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs mb-1" style="color: var(--text-muted);">Uploaded By</label>
                    <select name="user_id" class="w-full rounded-md px-2.5 py-1.5 text-xs transition-all duration-300 focus:outline-none" style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-primary);">
                        <option value="">All users</option>
                        @foreach($uploaders as $u)
                            <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>
                                {{ $u->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="corex-btn-outline w-full text-xs px-3 py-1.5">
                    Apply Filters
                </button>
                <a href="{{ route('documents.library.index', array_filter(['presentation_id' => $presentationId, 'return' => $returnUrl])) }}"
                   class="block text-center text-xs transition-all duration-300 hover:underline" style="color: var(--text-muted);">Clear Filters</a>
            </form>
        </div>

        {{-- Manage Document Types (collapsible) --}}
        <div class="rounded-md" style="background: var(--surface); border: 1px solid var(--border);" x-data="{ open: false }">
            <button @click="open = !open" type="button"
                    class="w-full flex items-center justify-between p-4 text-left transition-all duration-300">
                <h2 class="text-sm font-semibold" style="color: var(--text-primary);">Manage Document Types</h2>
                <svg class="w-4 h-4 transition-transform duration-300" :class="{ 'rotate-180': open }"
                     style="color: var(--text-muted);"
                     xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div x-show="open" x-cloak class="px-4 pb-4">
                <div class="space-y-2 mb-3">
                    @foreach($documentTypes as $dt)
                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('documents.library.types.update', $dt) }}" class="flex-1 flex items-center gap-2">
                                @csrf
                                @method('PUT')
                                <input type="text" name="label" value="{{ $dt->label }}"
                                       class="flex-1 rounded-md px-2 py-1 text-xs transition-all duration-300 focus:outline-none"
                                       style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-primary);">
                                <button type="submit" class="text-xs font-medium transition-all duration-300" style="color: var(--brand-icon, #0ea5e9);">Save</button>
                            </form>
                            <form method="POST" action="{{ route('documents.library.types.destroy', $dt) }}"
                                  onsubmit="return confirm('Delete type \'{{ $dt->label }}\'?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-medium transition-all duration-300 hover:opacity-80" style="color: #ef4444;" title="Delete">&times;</button>
                            </form>
                        </div>
                    @endforeach
                </div>

                <form method="POST" action="{{ route('documents.library.types.store') }}" class="flex items-center gap-2">
                    @csrf
                    <input type="text" name="label" placeholder="New type name..." required
                           class="flex-1 rounded-md px-2 py-1 text-xs transition-all duration-300 focus:outline-none"
                           style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-primary);">
                    <button type="submit" class="corex-btn-primary text-xs px-2.5 py-1">
                        Add
                    </button>
                </form>
                @error('label')
                    <p class="text-xs mt-1" style="color: #ef4444;">{{ $message }}</p>
                @enderror
            </div>
        </div>
    </div>

    {{-- RIGHT: Document list --}}
    <div class="lg:col-span-3">
        @if($presentation)
        <form method="POST" action="{{ route('documents.library.attach') }}" id="attachForm">
            @csrf
            <input type="hidden" name="presentation_id" value="{{ $presentationId }}">
            <input type="hidden" name="return" value="{{ $returnUrl ?? route('presentations.show', $presentation) . '#documents' }}">

            <div class="mb-3 flex items-center justify-between">
                <p class="text-xs" style="color: var(--text-muted);">{{ $items->total() }} document(s) in library</p>
                <button type="submit"
                        class="corex-btn-primary text-sm px-4 py-2 disabled:opacity-50"
                        id="attachBtn" disabled>
                    Attach Selected
                </button>
            </div>
        @else
            <div class="mb-3">
                <p class="text-xs" style="color: var(--text-muted);">{{ $items->total() }} document(s) in library</p>
            </div>
        @endif

        @if($items->isEmpty())
            <div class="rounded-md p-6 text-center" style="background: var(--surface); border: 1px solid var(--border);">
                <p class="text-sm italic" style="color: var(--text-muted);">No documents in the library yet. Upload one to get started.</p>
            </div>
        @else
            <div class="rounded-md overflow-hidden" style="background: var(--surface); border: 1px solid var(--border);">
                <table class="w-full text-xs text-left ds-table">
                    <thead>
                        <tr>
                            @if($presentation)
                                <th class="px-3 py-2.5 w-8">
                                    <input type="checkbox" id="selectAll" class="rounded-md">
                                </th>
                            @endif
                            <th class="px-3 py-2.5">Name</th>
                            <th class="px-3 py-2.5">Type</th>
                            <th class="px-3 py-2.5">Uploaded By</th>
                            <th class="px-3 py-2.5">Date</th>
                            <th class="px-3 py-2.5">Size</th>
                            <th class="px-3 py-2.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                            @php
                                $isAttached = in_array($item->id, $attachedIds);
                            @endphp
                            <tr class="transition-all duration-300" @if($isAttached) style="background: color-mix(in srgb, var(--brand-icon, #0ea5e9) 6%, transparent);" @endif>
                                @if($presentation)
                                    <td class="px-3 py-2.5">
                                        @if($isAttached)
                                            <span class="text-sm" style="color: var(--brand-icon, #0ea5e9);" title="Already attached">&#10003;</span>
                                        @else
                                            <input type="checkbox" name="item_ids[]" value="{{ $item->id }}"
                                                   class="item-checkbox rounded-md" form="attachForm">
                                        @endif
                                    </td>
                                @endif
                                <td class="px-3 py-2.5 font-medium max-w-[200px] truncate" style="color: var(--text-primary);" title="{{ $item->original_name }}">
                                    {{ $item->title ?? $item->original_name }}
                                </td>
                                <td class="px-3 py-2.5">
                                    <span class="ds-badge ds-badge-info text-[10px]">
                                        {{ $docTypeLabels[$item->doc_type] ?? $item->doc_type }}
                                    </span>
                                </td>
                                <td class="px-3 py-2.5" style="color: var(--text-secondary);">{{ $item->uploader->name ?? 'Unknown' }}</td>
                                <td class="px-3 py-2.5" style="color: var(--text-muted);">{{ $item->created_at->format('d M Y') }}</td>
                                <td class="px-3 py-2.5" style="color: var(--text-muted);">
                                    @if($item->bytes > 0)
                                        {{ number_format($item->bytes / 1024, 0) }} KB
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-right">
                                    <a href="{{ route('documents.library.download', $item) }}"
                                       class="font-medium transition-all duration-300 hover:underline"
                                       style="color: var(--brand-icon, #0ea5e9);">
                                        Download
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $items->links() }}
            </div>
        @endif

        @if($presentation)
        </form>
        @endif
    </div>
</div>

@if($presentation)
<script>
document.addEventListener('DOMContentLoaded', function() {
    const checkboxes = document.querySelectorAll('.item-checkbox');
    const attachBtn = document.getElementById('attachBtn');
    const selectAll = document.getElementById('selectAll');

    function updateBtn() {
        const checked = document.querySelectorAll('.item-checkbox:checked').length;
        attachBtn.disabled = checked === 0;
        attachBtn.textContent = checked > 0 ? 'Attach Selected (' + checked + ')' : 'Attach Selected';
    }

    checkboxes.forEach(function(cb) { cb.addEventListener('change', updateBtn); });

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(function(cb) { cb.checked = selectAll.checked; });
            updateBtn();
        });
    }
});
</script>
@endif

@endsection
