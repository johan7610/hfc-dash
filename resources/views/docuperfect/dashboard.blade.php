@extends('layouts.nexus')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    <div style="background:#0b2a4a;" class="rounded-2xl px-6 py-4 flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-white leading-tight">Documents</h2>
            <div class="text-sm text-white/60">Create, fill, and manage your documents.</div>
        </div>
        @if($user->isAdmin() || $user->isBranchManager())
        <form method="POST" action="{{ route('docuperfect.templates.upload') }}" enctype="multipart/form-data" class="flex items-center gap-2" id="uploadForm">
            @csrf
            <label class="nexus-btn-primary cursor-pointer text-sm" style="background:rgba(255,255,255,0.15);">
                + Upload Template
                <input type="file" name="pdf" accept=".pdf" class="hidden" onchange="document.getElementById('uploadForm').submit();">
            </label>
        </form>
        @endif
    </div>

    @if(session('status'))
        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 text-emerald-900 px-4 py-3 text-sm">
            {{ session('status') }}
        </div>
    @endif

    {{-- My Documents --}}
    <div>
        <h3 class="ds-section-header mb-3">My Documents</h3>

        @if($documents->isEmpty())
            <div class="ds-status-card p-6 text-center">
                <div class="text-sm text-slate-500">No documents yet — create one from a template below.</div>
            </div>
        @else
            <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden">
                <table class="w-full text-sm ds-table">
                    <thead>
                        <tr>
                            <th class="text-left px-4 py-3">Name</th>
                            <th class="text-left px-4 py-3">Template</th>
                            <th class="text-left px-4 py-3">Last Edited</th>
                            @if($user->isAdmin() || $user->isBranchManager())
                            <th class="text-left px-4 py-3">Agent</th>
                            @endif
                            <th class="text-right px-4 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($documents as $doc)
                        <tr>
                            <td class="px-4 py-3 font-medium text-slate-900">{{ $doc->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $doc->template->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $doc->updated_at->format('d M Y H:i') }}</td>
                            @if($user->isAdmin() || $user->isBranchManager())
                            <td class="px-4 py-3 text-slate-600">{{ $doc->owner->name ?? '—' }}</td>
                            @endif
                            <td class="px-4 py-3 text-right space-x-2">
                                <a href="{{ route('docuperfect.documents.edit', $doc->id) }}" class="ds-link text-sm">Edit</a>
                                <form method="POST" action="{{ route('docuperfect.documents.archive', $doc->id) }}" class="inline" onsubmit="return confirm('Archive this document?');">
                                    @csrf
                                    <button class="text-sm text-slate-400 hover:text-red-600">Archive</button>
                                </form>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Available Templates --}}
    <div x-data="{ viewMode: localStorage.getItem('docuperfect_view_mode') || 'grid', typeFilter: '' }">
        <div class="flex items-center justify-between mb-3">
            <h3 class="ds-section-header">Available Templates</h3>
            <div class="flex items-center gap-2">
                {{-- Document type filter --}}
                <select x-model="typeFilter" class="rounded-lg border border-slate-300 bg-white text-slate-700 px-2 py-1.5 text-xs">
                    <option value="">All Types</option>
                    @foreach($documentTypes as $dt)
                    <option value="{{ $dt->id }}">{{ $dt->name }}</option>
                    @endforeach
                    <option value="none">Uncategorized</option>
                </select>

                {{-- View toggle --}}
                <div class="flex items-center border border-slate-300 rounded-lg overflow-hidden">
                    <button @click="viewMode = 'grid'; localStorage.setItem('docuperfect_view_mode', 'grid')"
                            :class="viewMode === 'grid' ? 'bg-slate-900 text-white' : 'bg-white text-slate-500 hover:text-slate-700'"
                            class="px-2 py-1.5 transition-colors" title="Grid view">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path d="M4 5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5ZM14 5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1V5ZM4 15a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-4ZM14 15a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1h-4a1 1 0 0 1-1-1v-4Z"/>
                        </svg>
                    </button>
                    <button @click="viewMode = 'list'; localStorage.setItem('docuperfect_view_mode', 'list')"
                            :class="viewMode === 'list' ? 'bg-slate-900 text-white' : 'bg-white text-slate-500 hover:text-slate-700'"
                            class="px-2 py-1.5 transition-colors" title="List view">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        @if($templates->isEmpty())
            <div class="ds-status-card p-6 text-center">
                <div class="text-sm text-slate-500">No templates available yet.</div>
            </div>
        @else
            {{-- Grid View --}}
            <div x-show="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @foreach($templates as $tpl)
                <div class="ds-status-card p-4 flex flex-col"
                     x-show="typeFilter === '' || typeFilter === '{{ $tpl->document_type_id ?? 'none' }}'" x-cloak>
                    <div class="flex items-start justify-between mb-1">
                        <div class="font-semibold text-slate-900 text-sm leading-tight">{{ $tpl->name }}</div>
                        @if($tpl->documentType)
                        <span class="ds-badge ds-badge-info text-[10px] ml-2 flex-shrink-0">{{ $tpl->documentType->name }}</span>
                        @elseif($tpl->template_type)
                        <span class="ds-badge ds-badge-info text-[10px] ml-2 flex-shrink-0">{{ $tpl->template_type }}</span>
                        @endif
                    </div>
                    <div class="text-xs text-slate-500 mb-3">
                        @if($tpl->is_global)
                            Global
                        @else
                            {{ $tpl->branches->pluck('name')->join(', ') ?: 'No branches' }}
                        @endif
                    </div>
                    @if($tpl->page_count > 0)
                    <div class="flex-1 flex items-center justify-center mb-3">
                        <img src="{{ route('docuperfect.page.image', ['id' => $tpl->id, 'page' => 0]) }}"
                             alt="{{ $tpl->name }}"
                             style="max-height: 200px; width: auto;"
                             loading="lazy"
                             class="rounded shadow-sm mx-auto block" />
                    </div>
                    @endif
                    <div class="flex items-center gap-2 mt-auto pt-3 border-t border-slate-100">
                        <a href="{{ route('docuperfect.documents.create', $tpl->id) }}" class="nexus-btn-primary text-xs px-3 py-1.5">Create Document</a>
                        @if($user->isAdmin() || $user->isBranchManager())
                        <a href="{{ route('docuperfect.templates.edit', $tpl->id) }}" class="nexus-btn-outline text-xs px-3 py-1.5">Edit</a>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>

            {{-- List View --}}
            <div x-show="viewMode === 'list'" x-cloak>
                <div class="rounded-2xl border border-slate-200 bg-white overflow-hidden">
                    <table class="w-full text-sm ds-table">
                        <thead>
                            <tr>
                                <th class="text-left px-4 py-3 w-12"></th>
                                <th class="text-left px-4 py-3">Name</th>
                                <th class="text-left px-4 py-3">Type</th>
                                <th class="text-left px-4 py-3">Branches</th>
                                <th class="text-center px-4 py-3">Pages</th>
                                <th class="text-right px-4 py-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($templates as $tpl)
                            <tr x-show="typeFilter === '' || typeFilter === '{{ $tpl->document_type_id ?? 'none' }}'" x-cloak>
                                <td class="px-4 py-2">
                                    @if($tpl->page_count > 0)
                                    <img src="{{ route('docuperfect.page.image', ['id' => $tpl->id, 'page' => 0]) }}"
                                         alt="{{ $tpl->name }}"
                                         class="w-10 h-14 object-cover rounded shadow-sm"
                                         loading="lazy" />
                                    @endif
                                </td>
                                <td class="px-4 py-2 font-medium text-slate-900">{{ $tpl->name }}</td>
                                <td class="px-4 py-2">
                                    @if($tpl->documentType)
                                    <span class="ds-badge ds-badge-info text-[10px]">{{ $tpl->documentType->name }}</span>
                                    @elseif($tpl->template_type)
                                    <span class="text-xs text-slate-500">{{ $tpl->template_type }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-xs text-slate-500">
                                    @if($tpl->is_global)
                                        <span class="ds-badge ds-badge-success text-[10px]">Global</span>
                                    @else
                                        {{ $tpl->branches->pluck('name')->join(', ') ?: '—' }}
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-center text-slate-500">{{ $tpl->page_count }}</td>
                                <td class="px-4 py-2 text-right space-x-2">
                                    <a href="{{ route('docuperfect.documents.create', $tpl->id) }}" class="nexus-btn-primary text-xs px-3 py-1.5">Create</a>
                                    @if($user->isAdmin() || $user->isBranchManager())
                                    <a href="{{ route('docuperfect.templates.edit', $tpl->id) }}" class="nexus-btn-outline text-xs px-3 py-1.5">Edit</a>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>

</div>
@endsection
