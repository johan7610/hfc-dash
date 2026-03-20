@extends('layouts.corex')

@section('corex-content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    {{-- Page Header --}}
    <div style="background: var(--brand-default, #0b2a4a);" class="rounded-md px-6 py-4">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-bold text-white leading-tight tracking-tight">Document Packs</h2>
                <div class="text-sm text-white/60">Launch a pack to create all its documents at once.</div>
            </div>
            @if($canManage)
            <a href="{{ route('docuperfect.packs.create') }}" class="corex-btn-primary text-sm">
                + New Pack
            </a>
            @endif
        </div>
    </div>

    @if(session('status'))
        <div class="rounded-md px-4 py-3 text-sm" style="border: 1px solid var(--ds-green, #10b981); background: rgba(16,185,129,0.1); color: var(--text-primary);">
            {{ session('status') }}
        </div>
    @endif

    @if($packs->isEmpty())
        <div class="rounded-md p-6 text-center" style="background: var(--surface); border: 1px solid var(--border);">
            <div class="text-sm" style="color: var(--text-muted);">No document packs available yet.</div>
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach($packs as $pack)
            <div class="rounded-md p-4 flex flex-col transition-all duration-300 hover:shadow-lg"
                 style="background: var(--surface); border: 1px solid var(--border);"
                 onmouseover="this.style.borderColor='var(--brand-icon)'" onmouseout="this.style.borderColor='var(--border)'">
                <div class="font-semibold text-sm leading-tight mb-1" style="color: var(--text-primary);">{{ $pack->name }}</div>
                @if($pack->description)
                <div class="text-xs mb-2" style="color: var(--text-muted);">{{ $pack->description }}</div>
                @endif
                <div class="text-xs mb-3 flex flex-wrap items-center gap-1" style="color: var(--text-muted);">
                    @if($pack->usesSlots())
                        <span>{{ $pack->slots->count() }} slot{{ $pack->slots->count() !== 1 ? 's' : '' }}</span>
                        <span>&middot;</span>
                        <span class="text-[10px]" style="color: {{ $pack->creation_mode === 'linked' ? 'var(--brand-icon)' : 'var(--text-muted)' }};">{{ ucfirst($pack->creation_mode) }}</span>
                    @else
                        <span>{{ $pack->templates->count() }} template{{ $pack->templates->count() !== 1 ? 's' : '' }}</span>
                    @endif
                    <span>&middot;</span>
                    @if($pack->is_global)
                        <span class="ds-badge ds-badge-success text-[10px]">Global</span>
                    @else
                        <span>{{ $pack->branches->pluck('name')->join(', ') ?: 'No branches' }}</span>
                    @endif
                    @if($pack->esign_eligible)
                        <span class="ds-badge text-[10px]" style="background: rgba(20,184,166,0.15); color: #14b8a6;">E-Sign Eligible</span>
                    @else
                        <span class="ds-badge text-[10px]" style="background: rgba(148,163,184,0.15); color: #94a3b8;">Not E-Sign Eligible</span>
                    @endif
                </div>

                {{-- Slot / Template list --}}
                @if($pack->usesSlots() && $pack->slots->isNotEmpty())
                <div class="flex-1 mb-3">
                    <div class="text-[11px] uppercase tracking-wider mb-1.5" style="color: var(--text-muted);">Pack contents</div>
                    <ul class="text-xs space-y-1" style="color: var(--text-secondary);">
                        @foreach($pack->slots as $slot)
                        <li class="flex items-center gap-1.5">
                            @if($slot->slot_type === 'required')
                                <span class="w-1.5 h-1.5 rounded-full bg-green-500 flex-shrink-0"></span>
                            @elseif($slot->slot_type === 'selectable')
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-500 flex-shrink-0"></span>
                            @else
                                <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background: var(--brand-icon);"></span>
                            @endif
                            {{ $slot->label }}
                        </li>
                        @endforeach
                    </ul>
                </div>
                @elseif($pack->templates->isNotEmpty())
                <div class="flex-1 mb-3">
                    <div class="text-[11px] uppercase tracking-wider mb-1.5" style="color: var(--text-muted);">Templates included</div>
                    <ul class="text-xs space-y-1" style="color: var(--text-secondary);">
                        @foreach($pack->templates as $tpl)
                        <li class="flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background: var(--brand-icon);"></span>
                            {{ $tpl->name }}
                        </li>
                        @endforeach
                    </ul>
                </div>
                @endif

                <div class="flex flex-wrap items-center gap-2 mt-auto pt-3" style="border-top: 1px solid var(--border);">
                    <a href="{{ route('docuperfect.packs.showLaunch', $pack->id) }}" class="corex-btn-primary text-xs px-3 py-1.5 inline-block">Launch Pack</a>
                    @if($canManage)
                    <a href="{{ route('docuperfect.packs.edit', $pack->id) }}" class="corex-btn-outline text-xs px-3 py-1.5">Edit</a>
                    <form method="POST" action="{{ route('docuperfect.packs.destroy', $pack->id) }}" class="inline ml-auto" onsubmit="return confirm('Delete this pack?');">
                        @csrf
                        @method('DELETE')
                        <button class="text-xs transition-all duration-300 hover:opacity-80" style="color: #ef4444;">Delete</button>
                    </form>
                    @endif
                </div>
            </div>
            @endforeach
        </div>
    @endif

</div>
@endsection
