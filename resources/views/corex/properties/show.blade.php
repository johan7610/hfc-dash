@extends('layouts.corex')

@section('corex-content')
<div class="w-full space-y-4"
     x-data="{ activeTab: '{{ session('tab', $activeTab) }}' }">

    {{-- Top bar: back + flash --}}
    <div class="flex items-center gap-4 flex-wrap">
        <a href="{{ route('corex.properties.index') }}"
           class="inline-flex items-center gap-1.5 text-sm no-underline flex-shrink-0"
           style="color:var(--text-secondary);">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
            Back
        </a>
        @if(session('success'))
        <div class="flex-1 rounded-xl border px-4 py-2 text-sm font-medium" style="background:#f0fdf4;border-color:#bbf7d0;color:#166534;">
            {{ session('success') }}
        </div>
        @endif
        @if(session('error'))
        <div class="flex-1 rounded-xl border px-4 py-2 text-sm font-medium" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;">
            {{ session('error') }}
        </div>
        @endif
        @if($errors->any())
        <div class="flex-1 rounded-xl border px-4 py-2 text-sm" style="background:#fef2f2;border-color:#fecaca;color:#991b1b;">
            {{ $errors->first() }}
        </div>
        @endif
    </div>

    {{-- Two-column layout on large screens --}}
    <div class="flex gap-5 items-start" style="min-height:0;">

        {{-- LEFT: sticky property summary panel --}}
        @php
        $thumb = $property->gallery_images_json[0] ?? ($property->dawn_images_json[0] ?? null);
        $statusColors = ['active'=>'#22c55e','draft'=>'#94a3b8','sold'=>'#3b82f6','withdrawn'=>'#f59e0b'];
        $sc = $statusColors[$property->status] ?? '#94a3b8';
        @endphp
        <aside class="hidden lg:flex flex-col gap-4 flex-shrink-0" style="width:280px; position:sticky; top:0;">

            {{-- Hero image --}}
            <div class="rounded-2xl overflow-hidden" style="aspect-ratio:4/3; background:var(--surface-2);">
                @if($thumb)
                <img src="{{ $thumb }}" alt="" class="w-full h-full object-cover">
                @else
                <div class="w-full h-full flex items-center justify-center">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" class="w-16 h-16" style="color:var(--text-muted);opacity:.4;"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" /></svg>
                </div>
                @endif
            </div>

            {{-- Property info card --}}
            <div class="rounded-2xl p-5 space-y-4" style="background:var(--surface); border:1px solid var(--border);">
                <div>
                    <div class="flex items-start gap-2 flex-wrap">
                        <span class="text-xs px-2 py-0.5 rounded-full font-semibold flex-shrink-0"
                              style="background:{{ $sc }}22; color:{{ $sc }}; border:1px solid {{ $sc }}44;">
                            {{ ucfirst($property->status) }}
                        </span>
                        @if($property->isPublished())
                        <span class="text-xs px-2 py-0.5 rounded-full font-semibold flex-shrink-0" style="background:rgba(34,197,94,0.12); color:#22c55e; border:1px solid rgba(34,197,94,0.3);">LIVE</span>
                        @endif
                    </div>
                    <h1 class="text-base font-extrabold leading-tight mt-2" style="color:var(--text-primary);">{{ $property->title }}</h1>
                    <div class="text-lg font-bold mt-1" style="color:#00b4d8;">{{ $property->formattedPrice() }}</div>
                    @if($property->suburb)
                    <div class="text-xs mt-1" style="color:var(--text-muted);">
                        {{ $property->suburb }}{{ $property->city ? ', '.$property->city : '' }}
                    </div>
                    @endif
                </div>

                {{-- Room stats --}}
                <div class="grid grid-cols-3 gap-2">
                    @foreach([[$property->beds,'Beds'],[$property->baths,'Baths'],[$property->garages,'Gar']] as [$v,$l])
                    <div class="rounded-xl py-2 text-center" style="background:var(--surface-2);">
                        <div class="text-sm font-bold" style="color:var(--text-primary);">{{ $v }}</div>
                        <div class="text-[10px] font-medium" style="color:var(--text-muted);">{{ $l }}</div>
                    </div>
                    @endforeach
                </div>

                @if($property->size_m2 || $property->erf_size_m2)
                <div class="grid grid-cols-2 gap-2">
                    @if($property->size_m2)
                    <div class="rounded-xl py-2 px-3" style="background:var(--surface-2);">
                        <div class="text-xs font-bold" style="color:var(--text-primary);">{{ number_format($property->size_m2) }} m²</div>
                        <div class="text-[10px]" style="color:var(--text-muted);">Floor</div>
                    </div>
                    @endif
                    @if($property->erf_size_m2)
                    <div class="rounded-xl py-2 px-3" style="background:var(--surface-2);">
                        <div class="text-xs font-bold" style="color:var(--text-primary);">{{ number_format($property->erf_size_m2) }} m²</div>
                        <div class="text-[10px]" style="color:var(--text-muted);">Erf</div>
                    </div>
                    @endif
                </div>
                @endif

                {{-- Meta --}}
                <div class="space-y-1.5 pt-1" style="border-top:1px solid var(--border);">
                    @if($property->property_type)
                    <div class="flex items-center justify-between">
                        <span class="text-xs" style="color:var(--text-muted);">Type</span>
                        <span class="text-xs font-medium" style="color:var(--text-primary);">{{ ucwords(str_replace('_',' ',$property->property_type)) }}</span>
                    </div>
                    @endif
                    @if($property->category)
                    <div class="flex items-center justify-between">
                        <span class="text-xs" style="color:var(--text-muted);">Category</span>
                        <span class="text-xs font-medium" style="color:var(--text-primary);">{{ $property->category }}</span>
                    </div>
                    @endif
                    @if($property->mandate_type)
                    <div class="flex items-center justify-between">
                        <span class="text-xs" style="color:var(--text-muted);">Mandate</span>
                        <span class="text-xs font-medium" style="color:var(--text-primary);">{{ ucfirst($property->mandate_type) }}</span>
                    </div>
                    @endif
                    @if($property->agent)
                    <div class="flex items-center justify-between">
                        <span class="text-xs" style="color:var(--text-muted);">Agent</span>
                        <span class="text-xs font-medium truncate max-w-[120px]" style="color:var(--text-primary);">{{ $property->agent->name }}</span>
                    </div>
                    @endif
                    @if($property->listed_date)
                    <div class="flex items-center justify-between">
                        <span class="text-xs" style="color:var(--text-muted);">Listed</span>
                        <span class="text-xs font-medium" style="color:var(--text-primary);">{{ $property->listed_date->format('d M Y') }}</span>
                    </div>
                    @endif
                    @if($property->expiry_date)
                    <div class="flex items-center justify-between">
                        <span class="text-xs" style="color:var(--text-muted);">Expires</span>
                        <span class="text-xs font-medium" style="color:var(--text-primary);">{{ $property->expiry_date->format('d M Y') }}</span>
                    </div>
                    @endif
                </div>

                {{-- Actions --}}
                <div class="grid grid-cols-1 gap-2 pt-1">
                    <a href="{{ route('corex.properties.ad', $property) }}"
                       class="flex items-center justify-center gap-2 px-3 py-2 rounded-xl text-xs font-semibold no-underline transition-colors"
                       style="background:rgba(0,180,216,0.12); color:#00b4d8; border:1px solid rgba(0,180,216,0.3);"
                       onmouseover="this.style.background='rgba(0,180,216,0.22)'" onmouseout="this.style.background='rgba(0,180,216,0.12)'">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        Ad Builder
                    </a>
                </div>
            </div>
        </aside>

        {{-- RIGHT: tabs --}}
        <div class="flex-1 min-w-0" style="background:var(--surface); border:1px solid var(--border); border-radius:16px; overflow:hidden;">

        {{-- Mobile-only header strip --}}
        <div class="lg:hidden p-4" style="background:var(--surface-2); border-bottom:1px solid var(--border);">
            <div class="flex items-start gap-3">
                @if($thumb)
                <img src="{{ $thumb }}" alt="" class="w-14 h-14 rounded-xl object-cover flex-shrink-0">
                @endif
                <div class="flex-1 min-w-0">
                    <h1 class="text-base font-extrabold leading-tight" style="color:var(--text-primary);">{{ $property->title }}</h1>
                    <div class="text-base font-bold mt-0.5" style="color:#00b4d8;">{{ $property->formattedPrice() }}</div>
                    <div class="flex items-center gap-2 mt-1 flex-wrap">
                        <span class="text-xs px-2 py-0.5 rounded-full font-semibold"
                              style="background:{{ $sc }}22; color:{{ $sc }}; border:1px solid {{ $sc }}44;">{{ ucfirst($property->status) }}</span>
                        <span class="text-xs" style="color:var(--text-secondary);">{{ $property->beds }}bd · {{ $property->baths }}ba</span>
                    </div>
                </div>
            </div>
        </div>

    {{-- Tab bar (shared) --}}
        <div class="flex overflow-x-auto" style="border-bottom:1px solid var(--border);">
            @foreach([
                ['key'=>'overview', 'label'=>'Overview'],
                ['key'=>'info',     'label'=>'Info'],
                ['key'=>'gallery',  'label'=>'Gallery'],
                ['key'=>'notes',    'label'=>'Notes'],
                ['key'=>'drive',    'label'=>'Drive'],
            ] as $tab)
            <button type="button"
                    @click="activeTab = '{{ $tab['key'] }}'"
                    :class="activeTab === '{{ $tab['key'] }}' ? 'border-b-2 border-[#00b4d8] bg-[#00b4d8]/5' : 'border-b-2 border-transparent'"
                    :style="activeTab === '{{ $tab['key'] }}' ? 'color:#00b4d8;' : 'color:var(--text-secondary);'"
                    class="px-6 py-4 text-sm font-semibold whitespace-nowrap flex-shrink-0 transition-colors duration-150 outline-none focus:outline-none"
                    style="background:transparent;">
                {{ $tab['label'] }}
                @if($tab['key'] === 'notes' && $property->notes->count())
                <span class="ml-1.5 text-xs px-1.5 py-0.5 rounded-full" style="background:rgba(0,180,216,0.2);color:#00b4d8;">{{ $property->notes->count() }}</span>
                @endif
                @if($tab['key'] === 'drive' && $property->files->count())
                <span class="ml-1.5 text-xs px-1.5 py-0.5 rounded-full" style="background:rgba(0,180,216,0.2);color:#00b4d8;">{{ $property->files->count() }}</span>
                @endif
            </button>
            @endforeach
        </div>

        {{-- ── OVERVIEW TAB ──────────────────────────────────────────────── --}}
        <div x-show="activeTab === 'overview'" class="p-6 space-y-6">

            <div class="grid grid-cols-2 sm:grid-cols-4 xl:grid-cols-4 gap-4">
                @foreach([
                    ['label'=>'Price',       'value'=>$property->formattedPrice(),                        'color'=>'#00b4d8'],
                    ['label'=>'Status',      'value'=>ucfirst($property->status),                         'color'=>$statusColors[$property->status] ?? '#94a3b8'],
                    ['label'=>'Type',        'value'=>$property->property_type ? ucwords(str_replace('_',' ',$property->property_type)) : '—', 'color'=>null],
                    ['label'=>'Category',    'value'=>$property->category ?: '—',                        'color'=>null],
                ] as $kpi)
                <div class="rounded-xl p-4 text-center" style="background:var(--surface-2); border:1px solid var(--border);">
                    <div class="text-lg font-bold leading-tight" style="color:{{ $kpi['color'] ?? 'var(--text-primary)' }};">{{ $kpi['value'] }}</div>
                    <div class="text-xs mt-1 font-medium" style="color:var(--text-muted);">{{ $kpi['label'] }}</div>
                </div>
                @endforeach
            </div>

            <div class="grid grid-cols-3 sm:grid-cols-5 gap-3">
                @foreach([
                    ['label'=>'Beds',     'value'=>$property->beds],
                    ['label'=>'Baths',    'value'=>$property->baths],
                    ['label'=>'Garages',  'value'=>$property->garages],
                    ['label'=>'Floor m²', 'value'=>$property->size_m2    ? number_format($property->size_m2) : '—'],
                    ['label'=>'Erf m²',   'value'=>$property->erf_size_m2 ? number_format($property->erf_size_m2) : '—'],
                ] as $stat)
                <div class="rounded-xl p-3 text-center" style="background:var(--surface-2); border:1px solid var(--border);">
                    <div class="text-xl font-bold" style="color:var(--text-primary);">{{ $stat['value'] }}</div>
                    <div class="text-xs mt-0.5 font-medium" style="color:var(--text-muted);">{{ $stat['label'] }}</div>
                </div>
                @endforeach
            </div>

            @if($property->rates_taxes || $property->levy || $property->special_levy)
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider mb-3" style="color:var(--text-muted);">Monthly Costs</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    @if($property->rates_taxes)
                    <div class="rounded-xl p-4" style="background:var(--surface-2); border:1px solid var(--border);">
                        <div class="text-xs font-medium mb-1" style="color:var(--text-muted);">Rates & Taxes</div>
                        <div class="text-base font-bold" style="color:var(--text-primary);">R {{ number_format($property->rates_taxes) }}</div>
                    </div>
                    @endif
                    @if($property->levy)
                    <div class="rounded-xl p-4" style="background:var(--surface-2); border:1px solid var(--border);">
                        <div class="text-xs font-medium mb-1" style="color:var(--text-muted);">Levy</div>
                        <div class="text-base font-bold" style="color:var(--text-primary);">R {{ number_format($property->levy) }}</div>
                    </div>
                    @endif
                    @if($property->special_levy)
                    <div class="rounded-xl p-4" style="background:var(--surface-2); border:1px solid var(--border);">
                        <div class="text-xs font-medium mb-1" style="color:var(--text-muted);">Special Levy</div>
                        <div class="text-base font-bold" style="color:var(--text-primary);">R {{ number_format($property->special_levy) }}</div>
                    </div>
                    @endif
                </div>
            </div>
            @endif

            @if($property->features_json && count($property->features_json))
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider mb-3" style="color:var(--text-muted);">Features</h3>
                <div class="flex flex-wrap gap-2">
                    @foreach($property->features_json as $feat)
                    <span class="text-xs px-3 py-1.5 rounded-full font-medium"
                          style="background:rgba(0,180,216,0.12); color:#00b4d8; border:1px solid rgba(0,180,216,0.25);">
                        {{ $feat }}
                    </span>
                    @endforeach
                </div>
            </div>
            @endif

            @if($property->excerpt || $property->description)
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider mb-3" style="color:var(--text-muted);">Description</h3>
                @if($property->excerpt)
                <p class="text-sm font-medium mb-2" style="color:var(--text-primary);">{{ $property->excerpt }}</p>
                @endif
                @if($property->description)
                <div class="text-sm whitespace-pre-line" style="color:var(--text-secondary);">{{ $property->description }}</div>
                @endif
            </div>
            @endif

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div>
                    <div class="text-xs font-medium mb-0.5" style="color:var(--text-muted);">Loaded</div>
                    <div class="text-sm" style="color:var(--text-primary);">{{ $property->created_at->format('d M Y') }}</div>
                </div>
                <div>
                    <div class="text-xs font-medium mb-0.5" style="color:var(--text-muted);">Modified</div>
                    <div class="text-sm" style="color:var(--text-primary);">{{ $property->updated_at->format('d M Y H:i') }}</div>
                </div>
                @if($property->listed_date)
                <div>
                    <div class="text-xs font-medium mb-0.5" style="color:var(--text-muted);">Listed Date</div>
                    <div class="text-sm" style="color:var(--text-primary);">{{ $property->listed_date->format('d M Y') }}</div>
                </div>
                @endif
                @if($property->expiry_date)
                <div>
                    <div class="text-xs font-medium mb-0.5" style="color:var(--text-muted);">Expiry Date</div>
                    <div class="text-sm" style="color:var(--text-primary);">{{ $property->expiry_date->format('d M Y') }}</div>
                </div>
                @endif
            </div>

        </div>

        {{-- ── INFO TAB ───────────────────────────────────────────────────── --}}
        <div x-show="activeTab === 'info'" x-cloak class="p-6">
            <form method="POST" action="{{ route('corex.properties.update', $property) }}" class="space-y-6">
                @csrf @method('PUT')

                {{-- Classification --}}
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider mb-4" style="color:var(--text-muted);">Classification</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary);">Category</label>
                            <select name="category" class="w-full rounded-lg px-3 py-2 text-sm" style="background:var(--surface-2); border:1px solid var(--border); color:var(--text-primary);">
                                <option value="">— None —</option>
                                @foreach($settingItems['categories'] as $item)
                                <option value="{{ $item->name }}" {{ old('category', $property->category) === $item->name ? 'selected' : '' }}>
                                    {{ $item->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary);">Property Type</label>
                            <select name="property_type" class="w-full rounded-lg px-3 py-2 text-sm" style="background:var(--surface-2); border:1px solid var(--border); color:var(--text-primary);">
                                <option value="">— None —</option>
                                @foreach($settingItems['types'] as $item)
                                <option value="{{ $item->name }}" {{ old('property_type', $property->property_type) === $item->name ? 'selected' : '' }}>
                                    {{ $item->name }}
                                </option>
                                @endforeach
                                @if($settingItems['types']->isEmpty())
                                    @foreach(['House','Flat','Townhouse','Sectional Title','Smallholding','Farm','Commercial','Vacant Land','Other'] as $t)
                                    <option value="{{ $t }}" {{ old('property_type', $property->property_type) === $t ? 'selected' : '' }}>{{ $t }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary);">Property Status <span class="text-red-400">*</span></label>
                            <select name="status" required class="w-full rounded-lg px-3 py-2 text-sm" style="background:var(--surface-2); border:1px solid var(--border); color:var(--text-primary);">
                                @if($settingItems['statuses']->isNotEmpty())
                                    @foreach($settingItems['statuses'] as $item)
                                    @php $val = strtolower(str_replace(' ','_',$item->name)); @endphp
                                    <option value="{{ $val }}" {{ old('status', $property->status) === $val ? 'selected' : '' }}>
                                        {{ $item->name }}
                                    </option>
                                    @endforeach
                                @else
                                    @foreach(['draft','active','sold','withdrawn'] as $s)
                                    <option value="{{ $s }}" {{ old('status', $property->status) === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary);">Mandate Type</label>
                            <select name="mandate_type" class="w-full rounded-lg px-3 py-2 text-sm" style="background:var(--surface-2); border:1px solid var(--border); color:var(--text-primary);">
                                <option value="">— None —</option>
                                @if($settingItems['mandateTypes']->isNotEmpty())
                                    @foreach($settingItems['mandateTypes'] as $item)
                                    <option value="{{ $item->name }}" {{ old('mandate_type', $property->mandate_type) === $item->name ? 'selected' : '' }}>
                                        {{ $item->name }}
                                    </option>
                                    @endforeach
                                @else
                                    @foreach(['Sole','Joint','Open'] as $mt)
                                    <option value="{{ strtolower($mt) }}" {{ old('mandate_type', $property->mandate_type) === strtolower($mt) ? 'selected' : '' }}>{{ $mt }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Title --}}
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider mb-4" style="color:var(--text-muted);">Listing Details</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary);">Title <span class="text-red-400">*</span></label>
                            <input type="text" name="title" value="{{ old('title', $property->title) }}" required
                                   class="w-full rounded-lg px-3 py-2 text-sm"
                                   style="background:var(--surface-2); border:1px solid var(--border); color:var(--text-primary);">
                        </div>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary);">Price (ZAR) <span class="text-red-400">*</span></label>
                                <input type="number" name="price" value="{{ old('price', $property->price) }}" required min="0"
                                       class="w-full rounded-lg px-3 py-2 text-sm"
                                       style="background:var(--surface-2); border:1px solid var(--border); color:var(--text-primary);">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary);">Rates & Taxes</label>
                                <input type="number" name="rates_taxes" value="{{ old('rates_taxes', $property->rates_taxes) }}" min="0"
                                       placeholder="—"
                                       class="w-full rounded-lg px-3 py-2 text-sm"
                                       style="background:var(--surface-2); border:1px solid var(--border); color:var(--text-primary);">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary);">Levy</label>
                                <input type="number" name="levy" value="{{ old('levy', $property->levy) }}" min="0"
                                       placeholder="—"
                                       class="w-full rounded-lg px-3 py-2 text-sm"
                                       style="background:var(--surface-2); border:1px solid var(--border); color:var(--text-primary);">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary);">Special Levy</label>
                                <input type="number" name="special_levy" value="{{ old('special_levy', $property->special_levy) }}" min="0"
                                       placeholder="—"
                                       class="w-full rounded-lg px-3 py-2 text-sm"
                                       style="background:var(--surface-2); border:1px solid var(--border); color:var(--text-primary);">
                            </div>
                        </div>

                        <div class="grid grid-cols-3 sm:grid-cols-5 gap-3">
                            @foreach([['beds','Bedrooms'],['baths','Bathrooms'],['garages','Garages'],['size_m2','Floor m²'],['erf_size_m2','Erf m²']] as [$n,$lbl])
                            <div>
                                <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary);">{{ $lbl }}</label>
                                <input type="number" name="{{ $n }}" value="{{ old($n, $property->$n ?? '') }}" min="0"
                                       {{ in_array($n,['beds','baths','garages']) ? 'required max=20' : 'placeholder=—' }}
                                       class="w-full rounded-lg px-3 py-2 text-sm text-center"
                                       style="background:var(--surface-2); border:1px solid var(--border); color:var(--text-primary);">
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Features --}}
                <div x-data="featuresManager({{ json_encode($property->features_json ?? []) }})">
                    <h3 class="text-xs font-bold uppercase tracking-wider mb-4" style="color:var(--text-muted);">Property Features</h3>
                    <div class="flex flex-wrap gap-2 mb-3">
                        <template x-for="(feat, idx) in features" :key="idx">
                            <span class="inline-flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-full font-medium"
                                  style="background:rgba(0,180,216,0.12); color:#00b4d8; border:1px solid rgba(0,180,216,0.25);">
                                <span x-text="feat"></span>
                                <button type="button" @click="remove(idx)" class="hover:text-red-400 font-bold leading-none">×</button>
                                <input type="hidden" :name="'features['+idx+']'" :value="feat">
                            </span>
                        </template>
                    </div>
                    <div class="flex gap-2">
                        <input type="text" x-model="newFeat" @keydown.enter.prevent="add()"
                               placeholder="Type a feature and press Enter"
                               class="flex-1 rounded-lg px-3 py-2 text-sm"
                               style="background:var(--surface-2); border:1px solid var(--border); color:var(--text-primary);">
                        <button type="button" @click="add()"
                                class="px-4 py-2 rounded-lg text-sm font-semibold"
                                style="background:rgba(0,180,216,0.15); border:1px solid rgba(0,180,216,0.3); color:#00b4d8;">
                            Add
                        </button>
                    </div>
                </div>

                {{-- Description --}}
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider mb-4" style="color:var(--text-muted);">Description</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary);">Excerpt <span class="text-xs font-normal" style="color:var(--text-muted);">(max 500 chars)</span></label>
                            <textarea name="excerpt" rows="2"
                                      class="w-full rounded-lg px-3 py-2 text-sm"
                                      style="background:var(--surface-2); border:1px solid var(--border); color:var(--text-primary);"
                                      placeholder="Short summary shown in search results...">{{ old('excerpt', $property->excerpt) }}</textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary);">Full Description</label>
                            <textarea name="description" rows="6"
                                      class="w-full rounded-lg px-3 py-2 text-sm"
                                      style="background:var(--surface-2); border:1px solid var(--border); color:var(--text-primary);"
                                      placeholder="Full property description...">{{ old('description', $property->description) }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- Address --}}
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider mb-4" style="color:var(--text-muted);">Address</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary);">Full Address</label>
                            <input type="text" name="address" value="{{ old('address', $property->address) }}"
                                   placeholder="e.g. 21 Dee Road"
                                   class="w-full rounded-lg px-3 py-2 text-sm"
                                   style="background:var(--surface-2); border:1px solid var(--border); color:var(--text-primary);">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary);">Suburb <span class="text-red-400">*</span></label>
                            <input type="text" name="suburb" value="{{ old('suburb', $property->suburb) }}" required
                                   placeholder="e.g. Uvongo"
                                   class="w-full rounded-lg px-3 py-2 text-sm"
                                   style="background:var(--surface-2); border:1px solid var(--border); color:var(--text-primary);">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary);">City</label>
                            <input type="text" name="city" value="{{ old('city', $property->city) }}"
                                   placeholder="e.g. Margate"
                                   class="w-full rounded-lg px-3 py-2 text-sm"
                                   style="background:var(--surface-2); border:1px solid var(--border); color:var(--text-primary);">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary);">Region</label>
                            <input type="text" name="region" value="{{ old('region', $property->region) }}"
                                   placeholder="KZN South Coast"
                                   class="w-full rounded-lg px-3 py-2 text-sm"
                                   style="background:var(--surface-2); border:1px solid var(--border); color:var(--text-primary);">
                        </div>
                    </div>
                </div>

                {{-- Dates & Meta --}}
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider mb-4" style="color:var(--text-muted);">Dates & Meta</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary);">Listed Date</label>
                            <input type="date" name="listed_date" value="{{ old('listed_date', $property->listed_date?->format('Y-m-d')) }}"
                                   class="w-full rounded-lg px-3 py-2 text-sm"
                                   style="background:var(--surface-2); border:1px solid var(--border); color:var(--text-primary); color-scheme: light dark;">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary);">Expiry Date</label>
                            <input type="date" name="expiry_date" value="{{ old('expiry_date', $property->expiry_date?->format('Y-m-d')) }}"
                                   class="w-full rounded-lg px-3 py-2 text-sm"
                                   style="background:var(--surface-2); border:1px solid var(--border); color:var(--text-primary); color-scheme: light dark;">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary);">Loaded</label>
                            <input type="text" value="{{ $property->created_at->format('d M Y H:i') }}" disabled
                                   class="w-full rounded-lg px-3 py-2 text-sm cursor-not-allowed"
                                   style="background:var(--surface-2); border:1px solid var(--border); color:var(--text-muted);">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary);">Modified</label>
                            <input type="text" value="{{ $property->updated_at->format('d M Y H:i') }}" disabled
                                   class="w-full rounded-lg px-3 py-2 text-sm cursor-not-allowed"
                                   style="background:var(--surface-2); border:1px solid var(--border); color:var(--text-muted);">
                        </div>
                    </div>
                </div>

                {{-- Agent / Branch --}}
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider mb-4" style="color:var(--text-muted);">Assignment</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary);">Agent <span class="text-red-400">*</span></label>
                            <select name="agent_id" class="w-full rounded-lg px-3 py-2 text-sm" style="background:var(--surface-2); border:1px solid var(--border); color:var(--text-primary);">
                                @foreach($agents as $agent)
                                <option value="{{ $agent->id }}" {{ (int) old('agent_id', $property->agent_id) === $agent->id ? 'selected' : '' }}>{{ $agent->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary);">Branch</label>
                            <select name="branch_id" class="w-full rounded-lg px-3 py-2 text-sm" style="background:var(--surface-2); border:1px solid var(--border); color:var(--text-primary);">
                                <option value="">— None —</option>
                                @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" {{ (int) old('branch_id', $property->branch_id) === $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold mb-1" style="color:var(--text-secondary);">Publish to Website</label>
                            <div class="flex items-center gap-2 mt-1">
                                <input type="hidden" name="publish" value="0">
                                <input type="checkbox" name="publish" value="1" id="publish_toggle"
                                       {{ $property->isPublished() ? 'checked disabled' : '' }}
                                       class="w-4 h-4 rounded" style="accent-color:#00b4d8;">
                                <label for="publish_toggle" class="text-xs" style="color:var(--text-secondary);">
                                    {{ $property->isPublished() ? 'Published '.$property->published_at->diffForHumans() : 'Publish now' }}
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Save / Delete --}}
                <div class="flex items-center justify-between pt-2">
                    <button type="submit"
                            class="px-5 py-2 rounded-lg text-sm font-semibold text-white"
                            style="background:var(--brand-primary,#0b2a4a); border:1px solid var(--border);"
                            onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
                        Save Changes
                    </button>
                    <form method="POST" action="{{ route('corex.properties.destroy', $property) }}"
                          onsubmit="return confirm('Delete this property?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-sm font-semibold text-red-500 hover:text-red-600 px-4 py-2 rounded-lg hover:bg-red-500/10 transition-colors">
                            Delete Property
                        </button>
                    </form>
                </div>
            </form>
        </div>

        {{-- ── GALLERY TAB ────────────────────────────────────────────────── --}}
        <div x-show="activeTab === 'gallery'" x-cloak class="p-6 space-y-6"
             x-data="galleryManager()">

            {{-- Upload new images --}}
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider mb-3" style="color:var(--text-muted);">Upload Images</h3>
                <form method="POST" action="{{ route('corex.properties.update', $property) }}"
                      enctype="multipart/form-data">
                    @csrf @method('PUT')
                    {{-- Pass required fields silently --}}
                    <input type="hidden" name="title" value="{{ $property->title }}">
                    <input type="hidden" name="suburb" value="{{ $property->suburb }}">
                    <input type="hidden" name="price" value="{{ $property->price }}">
                    <input type="hidden" name="beds" value="{{ $property->beds }}">
                    <input type="hidden" name="baths" value="{{ $property->baths }}">
                    <input type="hidden" name="garages" value="{{ $property->garages }}">
                    <input type="hidden" name="status" value="{{ $property->status }}">

                    <label class="flex items-center gap-3 px-4 py-3 rounded-xl border border-dashed cursor-pointer transition-colors text-sm"
                           style="border-color:var(--border-hover); color:var(--text-secondary);"
                           onmouseover="this.style.borderColor='#00b4d8'" onmouseout="this.style.borderColor='var(--border-hover)'">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" /></svg>
                        <span id="gal-label">Select images to upload (multiple allowed)</span>
                        <input type="file" name="gallery_images[]" multiple accept="image/*" class="hidden"
                               onchange="document.getElementById('gal-label').textContent = this.files.length + ' file' + (this.files.length > 1 ? 's' : '') + ' selected'; document.getElementById('gal-submit').classList.remove('hidden');">
                    </label>
                    <button id="gal-submit" type="submit"
                            class="hidden mt-2 px-4 py-2 rounded-lg text-sm font-semibold text-white"
                            style="background:#00b4d8;">
                        Upload Images
                    </button>
                </form>
            </div>

            {{-- Gallery grid with drag to reorder --}}
            @php $galleryImages = $property->gallery_images_json ?? []; @endphp

            <div>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xs font-bold uppercase tracking-wider" style="color:var(--text-muted);">
                        Gallery Images ({{ count($galleryImages) }})
                    </h3>
                    <span class="text-xs" style="color:var(--text-muted);">Drag to reorder · Click to view/delete</span>
                </div>

                @if(count($galleryImages))
                <div id="gallery-grid" class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-5 xl:grid-cols-6 2xl:grid-cols-8 gap-2">
                    @foreach($galleryImages as $idx => $imgUrl)
                    <div class="gallery-item relative group rounded-lg overflow-hidden cursor-grab"
                         data-index="{{ $idx }}" style="aspect-ratio:1/1;">
                        <img src="{{ $imgUrl }}" alt=""
                             class="w-full h-full object-cover transition-transform duration-200 group-hover:scale-105">

                        {{-- Overlay actions --}}
                        <div class="absolute inset-0 flex items-center justify-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity duration-150"
                             style="background:rgba(0,0,0,0.55);">
                            {{-- View --}}
                            <button type="button"
                                    onclick="openLightbox({{ $idx }})"
                                    class="w-7 h-7 rounded-full flex items-center justify-center text-white"
                                    style="background:rgba(255,255,255,0.18);"
                                    title="View">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.641 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /></svg>
                            </button>
                            {{-- Delete --}}
                            <form method="POST" action="{{ route('corex.properties.deleteImage', $property) }}">
                                @csrf
                                <input type="hidden" name="group" value="gallery_images_json">
                                <input type="hidden" name="index" value="{{ $idx }}">
                                <button type="submit"
                                        onclick="return confirm('Delete this image?')"
                                        class="w-7 h-7 rounded-full flex items-center justify-center text-white"
                                        style="background:rgba(239,68,68,0.35);"
                                        title="Delete">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
                                </button>
                            </form>
                        </div>

                        {{-- Drag handle --}}
                        <div class="absolute top-1 left-1 opacity-0 group-hover:opacity-60 transition-opacity pointer-events-none">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="white" class="w-3 h-3"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" /></svg>
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="mt-2 text-xs" style="color:var(--text-muted);">
                    Drag to reorder · Changes saved automatically
                </div>
                @else
                <div class="rounded-xl p-8 text-center" style="background:var(--surface-2); border:1px dashed var(--border-hover);">
                    <div class="text-sm" style="color:var(--text-secondary);">No gallery images yet. Upload some above.</div>
                </div>
                @endif
            </div>

            {{-- Lightbox with prev/next navigation --}}
            @php $galleryJsonForJs = json_encode(array_values($galleryImages)); @endphp
            <div id="lightbox" class="hidden fixed inset-0 z-50 flex items-center justify-center"
                 style="background:rgba(0,0,0,0.93);">

                {{-- Close area (click outside image) --}}
                <div class="absolute inset-0" onclick="closeLightbox()"></div>

                {{-- Prev arrow --}}
                <button type="button" onclick="lightboxNav(-1)"
                        class="absolute left-4 top-1/2 -translate-y-1/2 z-10 w-11 h-11 rounded-full flex items-center justify-center text-white transition-colors"
                        style="background:rgba(255,255,255,0.12);"
                        onmouseover="this.style.background='rgba(255,255,255,0.25)'" onmouseout="this.style.background='rgba(255,255,255,0.12)'"
                        id="lightbox-prev">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
                </button>

                {{-- Image --}}
                <img id="lightbox-img" src="" alt=""
                     class="relative z-10 rounded-xl shadow-2xl select-none"
                     style="max-width:90vw; max-height:88vh; object-fit:contain;">

                {{-- Next arrow --}}
                <button type="button" onclick="lightboxNav(1)"
                        class="absolute right-4 top-1/2 -translate-y-1/2 z-10 w-11 h-11 rounded-full flex items-center justify-center text-white transition-colors"
                        style="background:rgba(255,255,255,0.12);"
                        onmouseover="this.style.background='rgba(255,255,255,0.25)'" onmouseout="this.style.background='rgba(255,255,255,0.12)'"
                        id="lightbox-next">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                </button>

                {{-- Close button --}}
                <button type="button" onclick="closeLightbox()"
                        class="absolute top-5 right-5 z-10 w-10 h-10 rounded-full flex items-center justify-center text-white text-lg font-bold"
                        style="background:rgba(255,255,255,0.12);"
                        onmouseover="this.style.background='rgba(255,255,255,0.25)'" onmouseout="this.style.background='rgba(255,255,255,0.12)'">
                    ×
                </button>

                {{-- Counter --}}
                <div id="lightbox-counter"
                     class="absolute bottom-5 left-1/2 -translate-x-1/2 z-10 px-3 py-1 rounded-full text-xs font-semibold text-white"
                     style="background:rgba(0,0,0,0.5);">
                    1 / 1
                </div>
            </div>
        </div>

        {{-- ── NOTES TAB ───────────────────────────────────────────────────── --}}
        <div x-show="activeTab === 'notes'" x-cloak class="p-6 space-y-5">

            {{-- Add note --}}
            <form method="POST" action="{{ route('corex.properties.notes.store', $property) }}" class="space-y-2">
                @csrf
                <label class="block text-xs font-bold uppercase tracking-wider mb-2" style="color:var(--text-muted);">Add Note</label>
                <textarea name="content" rows="3" required
                          class="w-full rounded-xl px-4 py-3 text-sm resize-none"
                          style="background:var(--surface-2); border:1px solid var(--border); color:var(--text-primary);"
                          placeholder="Type a note..."></textarea>
                <button type="submit"
                        class="px-4 py-2 rounded-lg text-sm font-semibold text-white"
                        style="background:#00b4d8;">
                    Add Note
                </button>
            </form>

            {{-- Notes list --}}
            <div class="space-y-3">
                @forelse($property->notes as $note)
                <div class="rounded-xl p-4" style="background:var(--surface-2); border:1px solid var(--border);">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1">
                            <div class="text-sm whitespace-pre-line" style="color:var(--text-primary);">{{ $note->content }}</div>
                            <div class="mt-2 text-xs" style="color:var(--text-muted);">
                                {{ $note->user?->name ?? 'Unknown' }} · {{ $note->created_at->format('d M Y H:i') }}
                            </div>
                        </div>
                        @if(auth()->id() === $note->user_id || in_array(auth()->user()->effectiveRole(), ['super_admin', 'admin']))
                        <form method="POST" action="{{ route('corex.properties.notes.destroy', [$property, $note]) }}"
                              onsubmit="return confirm('Delete this note?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-red-500 hover:text-red-600 font-semibold flex-shrink-0">Delete</button>
                        </form>
                        @endif
                    </div>
                </div>
                @empty
                <div class="rounded-xl p-6 text-center" style="background:var(--surface-2); border:1px dashed var(--border-hover);">
                    <div class="text-sm" style="color:var(--text-secondary);">No notes yet.</div>
                </div>
                @endforelse
            </div>
        </div>

        {{-- ── DRIVE TAB ────────────────────────────────────────────────────── --}}
        <div x-show="activeTab === 'drive'" x-cloak class="p-6 space-y-5">

            {{-- Upload --}}
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider mb-3" style="color:var(--text-muted);">Upload File</h3>
                <form method="POST" action="{{ route('corex.properties.files.store', $property) }}"
                      enctype="multipart/form-data" class="flex items-center gap-3 flex-wrap">
                    @csrf
                    <label class="flex-1 flex items-center gap-3 px-4 py-3 rounded-xl border border-dashed cursor-pointer transition-colors text-sm"
                           style="border-color:var(--border-hover); color:var(--text-secondary); min-width:200px;"
                           onmouseover="this.style.borderColor='#00b4d8'" onmouseout="this.style.borderColor='var(--border-hover)'">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M18.375 12.739l-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13" /></svg>
                        <span id="drive-label">Select a file (max 50 MB)</span>
                        <input type="file" name="file" class="hidden"
                               onchange="document.getElementById('drive-label').textContent = this.files[0].name; document.getElementById('drive-submit').classList.remove('hidden');">
                    </label>
                    <button id="drive-submit" type="submit"
                            class="hidden px-4 py-2 rounded-lg text-sm font-semibold text-white"
                            style="background:#00b4d8;">
                        Upload
                    </button>
                </form>
            </div>

            {{-- Files list --}}
            <div>
                <h3 class="text-xs font-bold uppercase tracking-wider mb-3" style="color:var(--text-muted);">
                    Files ({{ $property->files->count() }})
                </h3>
                @forelse($property->files as $file)
                <div class="flex items-center gap-4 p-3 rounded-xl mb-2" style="background:var(--surface-2); border:1px solid var(--border);">
                    {{-- File icon --}}
                    @php
                    $mime = $file->mime_type ?? '';
                    $icon = str_contains($mime,'pdf') ? '#ef4444' : (str_contains($mime,'image') ? '#00b4d8' : '#94a3b8');
                    @endphp
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                         style="background:{{ $icon }}22;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="{{ $icon }}" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" /></svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium truncate" style="color:var(--text-primary);">{{ $file->name }}</div>
                        <div class="text-xs mt-0.5" style="color:var(--text-muted);">
                            {{ $file->formattedSize() }} · {{ $file->user?->name ?? 'Unknown' }} · {{ $file->created_at->format('d M Y') }}
                        </div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <a href="{{ $file->url() }}" target="_blank"
                           class="text-xs font-semibold no-underline px-3 py-1.5 rounded-lg transition-colors"
                           style="background:rgba(0,180,216,0.12); color:#00b4d8; border:1px solid rgba(0,180,216,0.25);"
                           onmouseover="this.style.background='rgba(0,180,216,0.2)'" onmouseout="this.style.background='rgba(0,180,216,0.12)'">
                            Download
                        </a>
                        @if(auth()->id() === $file->user_id || in_array(auth()->user()->effectiveRole(), ['super_admin', 'admin']))
                        <form method="POST" action="{{ route('corex.properties.files.destroy', [$property, $file]) }}"
                              onsubmit="return confirm('Delete this file?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs font-semibold text-red-500 hover:text-red-600 px-3 py-1.5 rounded-lg hover:bg-red-500/10 transition-colors">Delete</button>
                        </form>
                        @endif
                    </div>
                </div>
                @empty
                <div class="rounded-xl p-6 text-center" style="background:var(--surface-2); border:1px dashed var(--border-hover);">
                    <div class="text-sm" style="color:var(--text-secondary);">No files uploaded yet.</div>
                </div>
                @endforelse
            </div>
        </div>

        </div>{{-- /tab container (right column) --}}

    </div>{{-- /two-column layout --}}

</div>{{-- /w-full --}}

@push('scripts')
<script>
// Features manager
function featuresManager(initial) {
    return {
        features: initial || [],
        newFeat: '',
        add() {
            const v = this.newFeat.trim();
            if (v && !this.features.includes(v)) {
                this.features.push(v);
            }
            this.newFeat = '';
        },
        remove(idx) {
            this.features.splice(idx, 1);
        }
    };
}

// Gallery lightbox with prev/next navigation
var _lbImages = {!! $galleryJsonForJs ?? '[]' !!};
var _lbIndex  = 0;

function openLightbox(idx) {
    _lbIndex = idx;
    _lbRender();
    document.getElementById('lightbox').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}
function closeLightbox() {
    document.getElementById('lightbox').classList.add('hidden');
    document.body.style.overflow = '';
}
function lightboxNav(dir) {
    _lbIndex = (_lbIndex + dir + _lbImages.length) % _lbImages.length;
    _lbRender();
}
function _lbRender() {
    var img     = document.getElementById('lightbox-img');
    var counter = document.getElementById('lightbox-counter');
    var prev    = document.getElementById('lightbox-prev');
    var next    = document.getElementById('lightbox-next');
    img.src     = _lbImages[_lbIndex] || '';
    counter.textContent = (_lbIndex + 1) + ' / ' + _lbImages.length;
    // Hide arrows when only 1 image
    var show = _lbImages.length > 1;
    prev.style.display = show ? '' : 'none';
    next.style.display = show ? '' : 'none';
}
document.addEventListener('keydown', function(e) {
    var lb = document.getElementById('lightbox');
    if (lb.classList.contains('hidden')) return;
    if (e.key === 'Escape')       closeLightbox();
    if (e.key === 'ArrowLeft')    lightboxNav(-1);
    if (e.key === 'ArrowRight')   lightboxNav(1);
});

// Gallery drag to reorder (SortableJS-style without external library)
(function() {
    const grid = document.getElementById('gallery-grid');
    if (!grid) return;

    let dragging = null;

    grid.addEventListener('dragstart', function(e) {
        const item = e.target.closest('.gallery-item');
        if (!item) return;
        dragging = item;
        item.style.opacity = '0.4';
        e.dataTransfer.effectAllowed = 'move';
    });

    grid.addEventListener('dragend', function(e) {
        const item = e.target.closest('.gallery-item');
        if (item) item.style.opacity = '1';
        dragging = null;
        saveOrder();
    });

    grid.addEventListener('dragover', function(e) {
        e.preventDefault();
        const target = e.target.closest('.gallery-item');
        if (!target || target === dragging) return;
        const rect = target.getBoundingClientRect();
        const mid  = rect.left + rect.width / 2;
        if (e.clientX < mid) {
            grid.insertBefore(dragging, target);
        } else {
            grid.insertBefore(dragging, target.nextSibling);
        }
    });

    function saveOrder() {
        const items = Array.from(grid.querySelectorAll('.gallery-item'));
        const order = items.map(i => parseInt(i.dataset.index));
        fetch('{{ route('corex.properties.reorderImages', $property) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ group: 'gallery_images_json', order: order }),
        });
    }
})();
</script>
@endpush
@endsection
