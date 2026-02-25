<x-app-layout>

<x-slot name="header">
    <div style="background:#0b2a4a;" class="rounded-2xl px-6 py-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
            <div>
                <div class="flex items-center gap-3">
                    <a href="{{ url()->previous() }}" class="text-white/60 hover:text-white text-sm">&larr; Back</a>
                    <h2 class="text-xl font-bold text-white leading-tight">Document Preview</h2>
                </div>
                <div class="text-sm text-white/60">{{ $document->title }}</div>
            </div>
        </div>
    </div>
</x-slot>

<div class="max-w-7xl mx-auto px-4 py-6 space-y-6">

    @if (session('status'))
        <div class="mb-4 p-3 rounded-lg bg-green-100 text-green-800 font-medium text-sm">
            {{ session('status') }}
        </div>
    @endif

    {{-- Document Info Card --}}
    <div class="ds-status-card">
        <h3 class="ds-section-header" style="margin-bottom:0.75rem;">Document Information</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
                <div class="ds-label">File</div>
                <div class="text-sm font-medium">{{ $document->file_name }}</div>
            </div>
            <div>
                <div class="ds-label">Type</div>
                <div class="text-sm font-medium uppercase">{{ $document->file_type }}</div>
            </div>
            <div>
                <div class="ds-label">Size</div>
                <div class="text-sm font-medium">{{ $document->file_size_formatted }}</div>
            </div>
            <div>
                <div class="ds-label">Category</div>
                <div class="text-sm font-medium">{{ $document->category->name ?? '-' }}</div>
            </div>
            <div>
                <div class="ds-label">Status</div>
                <div>{!! $document->status_badge !!}</div>
            </div>
            <div>
                <div class="ds-label">Chunks</div>
                <div class="text-sm font-medium">{{ $document->chunk_count }}</div>
            </div>
            <div>
                <div class="ds-label">Uploaded By</div>
                <div class="text-sm font-medium">{{ $document->uploader->name ?? '-' }}</div>
            </div>
            <div>
                <div class="ds-label">Date</div>
                <div class="text-sm font-medium">{{ $document->created_at->format('d M Y H:i') }}</div>
            </div>
        </div>

        @if($document->description)
            <div class="mt-3 pt-3 border-t border-gray-100">
                <div class="ds-label">Description</div>
                <div class="text-sm text-gray-600">{{ $document->description }}</div>
            </div>
        @endif

        @if($document->status === 'error' && $document->error_message)
            <div class="mt-3 pt-3 border-t border-gray-100">
                <div class="text-sm text-red-600 font-medium">Error: {{ $document->error_message }}</div>
            </div>
        @endif
    </div>

    {{-- Chunks List --}}
    <div>
        <h3 class="ds-section-header">Document Chunks ({{ $document->chunks->count() }})</h3>

        @if($document->chunks->isEmpty())
            <div class="ds-status-card text-center text-gray-500 text-sm py-8">
                No chunks extracted from this document.
            </div>
        @else
            <div class="space-y-4">
                @foreach($document->chunks as $chunk)
                    <div class="ds-status-card">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold">Chunk {{ $chunk->chunk_index }}</span>
                                @if($chunk->section_title)
                                    <span class="text-xs px-2 py-0.5 rounded bg-cyan-50 text-cyan-700">{{ Str::limit($chunk->section_title, 60) }}</span>
                                @endif
                                @if($chunk->page_number)
                                    <span class="text-xs text-gray-500">Page {{ $chunk->page_number }}</span>
                                @endif
                            </div>
                            <div class="text-xs text-gray-500">
                                {{ $chunk->word_count }} words &middot; {{ number_format($chunk->char_count) }} chars
                            </div>
                        </div>
                        <div class="bg-gray-50 rounded p-3 text-sm text-gray-700 font-mono whitespace-pre-wrap max-h-48 overflow-y-auto" style="font-size:0.75rem;line-height:1.4;">{{ $chunk->content }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

</div>

</x-app-layout>
