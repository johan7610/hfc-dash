@extends('layouts.nexus')

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    <div style="background:#0b2a4a;" class="rounded-2xl px-6 py-4 flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-white leading-tight">
                {{ isset($pack) ? 'Edit Pack — ' . $pack->name : 'Create Document Pack' }}
            </h2>
            <div class="text-sm text-white/60">Select templates to include in this pack.</div>
        </div>
        <a href="{{ route('docuperfect.packs.index') }}" class="text-sm text-white/70 hover:text-white">Back</a>
    </div>

    @if($errors->any())
        <div class="rounded-2xl border border-red-200 bg-red-50 text-red-900 px-4 py-3 text-sm">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST"
          action="{{ isset($pack) ? route('docuperfect.packs.update', $pack->id) : route('docuperfect.packs.store') }}"
          class="space-y-6">
        @csrf
        @if(isset($pack))
            @method('PUT')
        @endif

        {{-- Name & Description --}}
        <div class="ds-status-card p-4 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="ds-label block mb-1">Pack Name</label>
                    <input type="text" name="name" value="{{ old('name', $pack->name ?? '') }}" required
                           class="w-full rounded-lg border border-slate-300 bg-white text-slate-900 px-3 py-2 text-sm"
                           placeholder="e.g. Full Mandate Pack">
                </div>
                <div>
                    <label class="ds-label block mb-1">Description (optional)</label>
                    <input type="text" name="description" value="{{ old('description', $pack->description ?? '') }}"
                           class="w-full rounded-lg border border-slate-300 bg-white text-slate-900 px-3 py-2 text-sm"
                           placeholder="e.g. All documents needed for a new mandate">
                </div>
            </div>
        </div>

        {{-- Visibility --}}
        <div class="ds-status-card p-4 space-y-3">
            <h3 class="ds-section-header">Visibility</h3>
            <div class="flex items-center gap-2">
                <input type="hidden" name="is_global" value="0">
                <input type="checkbox" name="is_global" value="1" id="packGlobal"
                       {{ old('is_global', $pack->is_global ?? false) ? 'checked' : '' }}
                       class="rounded border-slate-300">
                <label for="packGlobal" class="text-sm text-slate-700">Global (all branches)</label>
            </div>
            <div>
                <label class="ds-label block mb-1">Branch Access</label>
                <div class="flex flex-wrap gap-2">
                    @foreach($branches as $branch)
                    <label class="flex items-center gap-1 text-sm text-slate-700">
                        <input type="checkbox" name="branch_ids[]" value="{{ $branch->id }}"
                               {{ isset($pack) && $pack->branches->contains('id', $branch->id) ? 'checked' : '' }}
                               class="rounded border-slate-300">
                        {{ $branch->name }}
                    </label>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Template Selection --}}
        <div class="ds-status-card p-4 space-y-3">
            <h3 class="ds-section-header">Templates</h3>
            <div class="text-xs text-slate-500 mb-2">Select templates to include in this pack. Check the box next to each template you want.</div>

            @php
                $selectedTemplateIds = isset($pack) ? $pack->templates->pluck('id')->toArray() : [];
                $groupedTemplates = $templates->groupBy(fn($t) => $t->documentType ? $t->documentType->name : 'Uncategorized');
            @endphp

            @foreach($groupedTemplates as $typeName => $typeTemplates)
            <div class="mb-4">
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2">{{ $typeName }}</div>
                <div class="space-y-1">
                    @foreach($typeTemplates as $tpl)
                    <label class="flex items-center gap-2 text-sm text-slate-700 hover:bg-slate-50 rounded-lg px-2 py-1.5 cursor-pointer">
                        <input type="checkbox" name="template_ids[]" value="{{ $tpl->id }}"
                               {{ in_array($tpl->id, $selectedTemplateIds) ? 'checked' : '' }}
                               class="rounded border-slate-300">
                        <span>{{ $tpl->name }}</span>
                        <span class="text-xs text-slate-400 ml-auto">{{ $tpl->page_count }} pg</span>
                    </label>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="nexus-btn-primary text-sm">
                {{ isset($pack) ? 'Update Pack' : 'Create Pack' }}
            </button>
            <a href="{{ route('docuperfect.packs.index') }}" class="text-sm text-slate-500 hover:text-slate-700">Cancel</a>
        </div>
    </form>

</div>
@endsection
