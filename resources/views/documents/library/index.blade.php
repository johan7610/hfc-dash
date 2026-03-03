@extends('layouts.corex')

@section('corex-content')

@php
    $docTypeLabels = $documentTypes->pluck('label', 'key')->toArray();
@endphp

<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Document Library</h1>
            <p class="text-sm text-gray-500 mt-1">Upload, browse and manage shared documents.</p>
        </div>
        @if($presentation)
            <a href="{{ $returnUrl ?? route('presentations.show', $presentation) . '#documents' }}"
               class="px-3 py-1.5 bg-gray-200 text-gray-700 text-xs font-medium rounded hover:bg-gray-300">
                Back to Presentation #{{ $presentation->id }}
            </a>
        @endif
    </div>
</div>

{{-- Attach toolbar --}}
@if($presentation)
<div class="mb-4 bg-sky-50 border border-sky-200 rounded-xl p-4">
    <p class="text-sm font-semibold text-[#0b2a4a] mb-1">
        Attaching to: {{ $presentation->title }} (#{{ $presentation->id }})
    </p>
    <p class="text-xs text-[#00b4d8]">Select documents below and click "Attach Selected" to link them to this presentation.</p>
</div>
@endif

{{-- Flash messages handled by global toast system --}}

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

    {{-- LEFT: Upload + Filters --}}
    <div class="lg:col-span-1 space-y-4">

        {{-- Upload --}}
        <div class="bg-white rounded-xl shadow p-4">
            <h2 class="text-sm font-semibold text-gray-700 mb-3">Upload to Library</h2>
            <form method="POST" action="{{ route('documents.library.upload') }}" enctype="multipart/form-data" class="space-y-2">
                @csrf
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Document Type</label>
                    <select name="doc_type" class="w-full border border-gray-300 rounded px-2 py-1.5 text-xs" required>
                        <option value="" disabled selected>Select type...</option>
                        @foreach($docTypeLabels as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('doc_type')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Title (optional)</label>
                    <input type="text" name="title" value="{{ old('title') }}"
                           class="w-full border border-gray-300 rounded px-2 py-1.5 text-xs" placeholder="Descriptive title...">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">File</label>
                    <input type="file" name="file" class="w-full text-xs text-gray-600 border border-gray-300 rounded px-2 py-1.5" required>
                    @error('file')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit"
                        class="w-full px-3 py-1.5 bg-[#0b2a4a] text-white text-xs font-medium rounded hover:bg-[#081f36]">
                    Upload
                </button>
            </form>
        </div>

        {{-- Filters --}}
        <div class="bg-white rounded-xl shadow p-4">
            <h2 class="text-sm font-semibold text-gray-700 mb-3">Filter</h2>
            <form method="GET" action="{{ route('documents.library.index') }}" class="space-y-2">
                @if($presentationId)
                    <input type="hidden" name="presentation_id" value="{{ $presentationId }}">
                @endif
                @if($returnUrl)
                    <input type="hidden" name="return" value="{{ $returnUrl }}">
                @endif

                <div>
                    <label class="block text-xs text-gray-500 mb-1">Search</label>
                    <input type="text" name="q" value="{{ request('q') }}"
                           class="w-full border border-gray-300 rounded px-2 py-1.5 text-xs" placeholder="Name or title...">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Doc Type</label>
                    <select name="doc_type" class="w-full border border-gray-300 rounded px-2 py-1.5 text-xs">
                        <option value="">All types</option>
                        @foreach($docTypes as $dt)
                            <option value="{{ $dt }}" {{ request('doc_type') === $dt ? 'selected' : '' }}>
                                {{ $docTypeLabels[$dt] ?? $dt }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Uploaded By</label>
                    <select name="user_id" class="w-full border border-gray-300 rounded px-2 py-1.5 text-xs">
                        <option value="">All users</option>
                        @foreach($uploaders as $u)
                            <option value="{{ $u->id }}" {{ request('user_id') == $u->id ? 'selected' : '' }}>
                                {{ $u->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <button type="submit"
                        class="w-full px-3 py-1.5 bg-gray-600 text-white text-xs font-medium rounded hover:bg-gray-700">
                    Apply Filters
                </button>
                <a href="{{ route('documents.library.index', array_filter(['presentation_id' => $presentationId, 'return' => $returnUrl])) }}"
                   class="block text-center text-xs text-gray-500 hover:text-gray-700">Clear Filters</a>
            </form>
        </div>

        {{-- Manage Document Types (collapsible) --}}
        <div class="bg-white rounded-xl shadow" x-data="{ open: false }">
            <button @click="open = !open" type="button"
                    class="w-full flex items-center justify-between p-4 text-left">
                <h2 class="text-sm font-semibold text-gray-700">Manage Document Types</h2>
                <svg class="w-4 h-4 text-gray-400 transition-transform" :class="{ 'rotate-180': open }"
                     xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div x-show="open" x-cloak class="px-4 pb-4">
            <div class="space-y-1.5 mb-3">
                @foreach($documentTypes as $dt)
                    <div class="flex items-center gap-1.5">
                        <form method="POST" action="{{ route('documents.library.types.update', $dt) }}" class="flex-1 flex items-center gap-1.5">
                            @csrf
                            @method('PUT')
                            <input type="text" name="label" value="{{ $dt->label }}"
                                   class="flex-1 border border-gray-300 rounded px-2 py-1 text-xs">
                            <button type="submit" class="text-[#00b4d8] hover:text-[#0b2a4a] text-xs font-medium" title="Save">Save</button>
                        </form>
                        <form method="POST" action="{{ route('documents.library.types.destroy', $dt) }}"
                              onsubmit="return confirm('Delete type \'{{ $dt->label }}\'?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-400 hover:text-red-600 text-xs font-medium" title="Delete">&times;</button>
                        </form>
                    </div>
                @endforeach
            </div>

            <form method="POST" action="{{ route('documents.library.types.store') }}" class="flex items-center gap-1.5">
                @csrf
                <input type="text" name="label" placeholder="New type name..." required
                       class="flex-1 border border-gray-300 rounded px-2 py-1 text-xs">
                <button type="submit"
                        class="px-2 py-1 bg-[#0b2a4a] text-white text-xs font-medium rounded hover:bg-[#081f36]">
                    Add
                </button>
            </form>
            @error('label')
                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
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
                <p class="text-xs text-gray-500">{{ $items->total() }} document(s) in library</p>
                <button type="submit"
                        class="px-4 py-2 bg-[#0b2a4a] text-white text-sm font-medium rounded-lg hover:bg-[#081f36] disabled:opacity-50"
                        id="attachBtn" disabled>
                    Attach Selected
                </button>
            </div>
        @else
            <div class="mb-3">
                <p class="text-xs text-gray-500">{{ $items->total() }} document(s) in library</p>
            </div>
        @endif

        @if($items->isEmpty())
            <div class="bg-white rounded-xl shadow p-6 text-center">
                <p class="text-sm text-gray-400 italic">No documents in the library yet. Upload one to get started.</p>
            </div>
        @else
            <div class="bg-white rounded-xl shadow overflow-hidden">
                <table class="w-full text-xs text-left">
                    <thead class="bg-gray-50 text-gray-500 uppercase tracking-wider">
                        <tr>
                            @if($presentation)
                                <th class="px-3 py-2 w-8">
                                    <input type="checkbox" id="selectAll" class="rounded">
                                </th>
                            @endif
                            <th class="px-3 py-2">Name</th>
                            <th class="px-3 py-2">Type</th>
                            <th class="px-3 py-2">Uploaded By</th>
                            <th class="px-3 py-2">Date</th>
                            <th class="px-3 py-2">Size</th>
                            <th class="px-3 py-2 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($items as $item)
                            @php
                                $isAttached = in_array($item->id, $attachedIds);
                            @endphp
                            <tr class="{{ $isAttached ? 'bg-green-50' : '' }}">
                                @if($presentation)
                                    <td class="px-3 py-2">
                                        @if($isAttached)
                                            <span class="text-green-600 text-sm" title="Already attached">&#10003;</span>
                                        @else
                                            <input type="checkbox" name="item_ids[]" value="{{ $item->id }}"
                                                   class="item-checkbox rounded" form="attachForm">
                                        @endif
                                    </td>
                                @endif
                                <td class="px-3 py-2 font-medium text-gray-800 max-w-[200px] truncate" title="{{ $item->original_name }}">
                                    {{ $item->title ?? $item->original_name }}
                                </td>
                                <td class="px-3 py-2">
                                    <span class="inline-block px-1.5 py-0.5 rounded text-xs font-medium bg-sky-50 text-[#00b4d8]">
                                        {{ $docTypeLabels[$item->doc_type] ?? $item->doc_type }}
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-gray-600">{{ $item->uploader->name ?? 'Unknown' }}</td>
                                <td class="px-3 py-2 text-gray-500">{{ $item->created_at->format('d M Y') }}</td>
                                <td class="px-3 py-2 text-gray-500">
                                    @if($item->bytes > 0)
                                        {{ number_format($item->bytes / 1024, 0) }} KB
                                    @else
                                        -
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <a href="{{ route('documents.library.download', $item) }}"
                                       class="text-[#00b4d8] hover:text-[#0b2a4a] font-medium">
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
