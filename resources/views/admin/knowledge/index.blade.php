<x-app-layout>

<x-slot name="header">
    <div style="background:#0b2a4a;" class="rounded-2xl px-6 py-4">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
            <div>
                <h2 class="text-xl font-bold text-white leading-tight">Knowledge Base</h2>
                <div class="text-sm text-white/60">Ellie's training documents &amp; agent resources</div>
            </div>
        </div>
    </div>
</x-slot>

<div class="max-w-7xl mx-auto px-4 py-6 space-y-6">

    {{-- Flash messages --}}
    @if (session('status'))
        <div class="mb-4 p-3 rounded-lg bg-green-100 text-green-800 font-medium text-sm">
            {{ session('status') }}
        </div>
    @endif

    {{-- Upload Section --}}
    <div class="ds-status-card">
        <h3 class="ds-section-header" style="margin-bottom:0.75rem;">Upload Document</h3>
        <form action="{{ route('admin.knowledge.upload') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="ds-label">Title <span class="text-red-500">*</span></label>
                    <input type="text" name="title" required class="w-full rounded border-gray-300 text-sm px-3 py-2 focus:ring-cyan-500 focus:border-cyan-500" placeholder="Document title" value="{{ old('title') }}">
                    @error('title') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="ds-label">Category <span class="text-red-500">*</span></label>
                    <select name="category_id" required class="w-full rounded border-gray-300 text-sm px-3 py-2 focus:ring-cyan-500 focus:border-cyan-500">
                        <option value="">Select category...</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                    @error('category_id') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="ds-label">File <span class="text-red-500">*</span></label>
                    <input type="file" name="file" required accept=".pdf,.docx,.doc,.txt,.md" class="w-full text-sm">
                    @error('file') <div class="text-red-500 text-xs mt-1">{{ $message }}</div> @enderror
                </div>
                <div>
                    <label class="ds-label">Description</label>
                    <textarea name="description" rows="2" class="w-full rounded border-gray-300 text-sm px-3 py-2 focus:ring-cyan-500 focus:border-cyan-500" placeholder="Optional description">{{ old('description') }}</textarea>
                </div>
                <div>
                    <label class="ds-label">Version</label>
                    <input type="text" name="version" class="w-full rounded border-gray-300 text-sm px-3 py-2 focus:ring-cyan-500 focus:border-cyan-500" placeholder="e.g. v2.1" value="{{ old('version') }}">
                </div>
                <div class="flex items-end">
                    <button type="submit" class="nexus-btn-primary px-4 py-2 rounded text-sm font-medium" style="background:var(--nexus-cyan,#00b4d8);color:#fff;">Upload Document</button>
                </div>
            </div>
            <div class="text-xs text-gray-500 mt-2">Accepted: PDF, DOCX, DOC, TXT, MD &mdash; Max 20MB</div>
        </form>
    </div>

    {{-- Stats Row --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="ds-status-card">
            <div class="ds-label">Total Documents</div>
            <div class="ds-value text-2xl">{{ $stats['total_documents'] }}</div>
            <div class="text-xs text-gray-500 mt-1">
                <span class="text-green-600">{{ $stats['by_status']['ready'] }} ready</span>
                @if($stats['by_status']['processing'] > 0)
                    &middot; <span class="text-amber-600">{{ $stats['by_status']['processing'] }} processing</span>
                @endif
                @if($stats['by_status']['error'] > 0)
                    &middot; <span class="text-red-600">{{ $stats['by_status']['error'] }} error</span>
                @endif
            </div>
        </div>
        <div class="ds-status-card">
            <div class="ds-label">Total Chunks</div>
            <div class="ds-value text-2xl">{{ number_format($stats['total_chunks']) }}</div>
            <div class="text-xs text-gray-500 mt-1">Searchable text segments</div>
        </div>
        <div class="ds-status-card">
            <div class="ds-label">Ellie-Enabled</div>
            <div class="ds-value text-2xl">{{ $stats['ellie_enabled'] }}</div>
            <div class="text-xs text-gray-500 mt-1">Documents Ellie can search</div>
        </div>
    </div>

    {{-- Categories Section --}}
    <div>
        <h3 class="ds-section-header">Categories</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($categories as $cat)
                <a href="{{ route('admin.knowledge.category', $cat->id) }}" class="ds-status-card hover:shadow-md transition-shadow block" style="text-decoration:none;color:inherit;">
                    <div class="flex items-center gap-3 mb-2">
                        @if($cat->icon)
                            <i class="fas {{ $cat->icon }} text-lg" style="color:var(--nexus-cyan,#00b4d8);"></i>
                        @endif
                        <div class="font-semibold text-sm">{{ $cat->name }}</div>
                    </div>
                    <div class="text-xs text-gray-500">{{ $cat->documents_count }} {{ Str::plural('document', $cat->documents_count) }}</div>
                    <div class="text-xs text-cyan-600 mt-1 font-medium">View &rarr;</div>
                </a>
            @endforeach
        </div>
    </div>

    {{-- Recent Documents Section --}}
    <div>
        <h3 class="ds-section-header">Recent Documents</h3>
        @if($recentDocuments->isEmpty())
            <div class="ds-status-card text-center text-gray-500 text-sm py-8">
                No documents uploaded yet. Use the form above to upload your first document.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="ds-table w-full">
                    <thead>
                        <tr>
                            <th class="text-left text-xs font-semibold text-gray-600 uppercase px-3 py-2">Title</th>
                            <th class="text-left text-xs font-semibold text-gray-600 uppercase px-3 py-2">Category</th>
                            <th class="text-center text-xs font-semibold text-gray-600 uppercase px-3 py-2">Status</th>
                            <th class="text-center text-xs font-semibold text-gray-600 uppercase px-3 py-2">Chunks</th>
                            <th class="text-center text-xs font-semibold text-gray-600 uppercase px-3 py-2">Ellie</th>
                            <th class="text-center text-xs font-semibold text-gray-600 uppercase px-3 py-2">Active</th>
                            <th class="text-left text-xs font-semibold text-gray-600 uppercase px-3 py-2">Uploaded</th>
                            <th class="text-right text-xs font-semibold text-gray-600 uppercase px-3 py-2">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($recentDocuments as $doc)
                            <tr class="border-t border-gray-100">
                                <td class="px-3 py-2 text-sm font-medium">{{ Str::limit($doc->title, 40) }}</td>
                                <td class="px-3 py-2 text-xs text-gray-600">{{ $doc->category->name ?? '-' }}</td>
                                <td class="px-3 py-2 text-center">{!! $doc->status_badge !!}</td>
                                <td class="px-3 py-2 text-center text-sm">{{ $doc->chunk_count }}</td>
                                <td class="px-3 py-2 text-center">
                                    <form action="{{ route('admin.knowledge.toggleEllie', $doc->id) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs px-2 py-0.5 rounded {{ $doc->is_ellie_enabled ? 'bg-cyan-100 text-cyan-800' : 'bg-gray-100 text-gray-500' }}" title="{{ $doc->is_ellie_enabled ? 'Disable Ellie' : 'Enable Ellie' }}">
                                            {{ $doc->is_ellie_enabled ? 'ON' : 'OFF' }}
                                        </button>
                                    </form>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <form action="{{ route('admin.knowledge.toggleActive', $doc->id) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs px-2 py-0.5 rounded {{ $doc->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-500' }}" title="{{ $doc->is_active ? 'Deactivate' : 'Activate' }}">
                                            {{ $doc->is_active ? 'ON' : 'OFF' }}
                                        </button>
                                    </form>
                                </td>
                                <td class="px-3 py-2 text-xs text-gray-500">{{ $doc->created_at->format('d M Y') }}</td>
                                <td class="px-3 py-2 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('admin.knowledge.preview', $doc->id) }}" class="text-xs text-cyan-600 hover:underline">Preview</a>
                                        <form action="{{ route('admin.knowledge.reprocess', $doc->id) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="text-xs text-amber-600 hover:underline ml-2">Reprocess</button>
                                        </form>
                                        <form action="{{ route('admin.knowledge.destroy', $doc->id) }}" method="POST" class="inline" x-data x-on:submit.prevent="if(confirm('Delete this document and all its chunks?')) $el.submit()">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs text-red-600 hover:underline ml-2">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</div>

</x-app-layout>
