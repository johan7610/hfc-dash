@extends('layouts.corex-app')

@section('corex-content')
<div class="-m-4 lg:-m-6 flex flex-col" style="height:calc(100vh - 56px);" x-data="evalApp()" x-init="init()">

    {{-- TOP BAR --}}
    <div class="flex-shrink-0 flex items-center gap-2 px-3 py-2"
        style="background:#fff;border-bottom:1px solid #e2e8f0;position:relative;z-index:1000;">

        {{-- Mode toggle --}}
        <div class="flex items-center flex-shrink-0" style="background:#f1f5f9;border-radius:4px;padding:3px;gap:2px;">
            <button @click="mode='search'"
                :style="mode==='search' ? 'background:#00d4aa;color:#0f172a;' : 'background:transparent;color:#64748b;'"
                style="display:flex;align-items:center;gap:5px;padding:5px 12px;border:none;cursor:pointer;border-radius:3px;font-size:11px;font-weight:700;transition:all .15s;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="width:12px;height:12px;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                Search
            </button>
            <button @click="switchToMap()"
                :style="mode==='map' ? 'background:#00d4aa;color:#0f172a;' : 'background:transparent;color:#64748b;'"
                style="display:flex;align-items:center;gap:5px;padding:5px 12px;border:none;cursor:pointer;border-radius:3px;font-size:11px;font-weight:700;transition:all .15s;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="width:12px;height:12px;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 0 0-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0Z"/></svg>
                Prospecting
            </button>
        </div>

        <div style="width:1px;height:20px;background:#e2e8f0;flex-shrink:0;"></div>

        {{-- Search bar --}}
        <div class="relative" style="flex:1;max-width:520px;">
            <svg style="position:absolute;left:10px;top:50%;transform:translateY(-50%);width:13px;height:13px;color:#94a3b8;flex-shrink:0;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
            <input type="text" x-model="searchQuery"
                @input.debounce.350ms="runSearch()"
                @keydown.escape="searchQuery='';results=[]"
                placeholder="Search by address, ERF number, suburb or owner..."
                style="width:100%;background:#f8fafc;border:1px solid #e2e8f0;border-radius:3px;padding:7px 32px 7px 30px;font-size:12px;color:#0f172a;outline:none;transition:border-color .15s;"
                onfocus="this.style.borderColor='#00d4aa'" onblur="this.style.borderColor='#e2e8f0'">
            <button x-show="searchQuery" @click="searchQuery='';results=[]"
                style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#94a3b8;font-size:16px;line-height:1;padding:0;">×</button>
        </div>

        {{-- Search type pills --}}
        <div x-show="mode==='search'" class="flex items-center gap-1 flex-shrink-0">
            @foreach(['Full Title','Person','ERF','Suburb','Street','Transfer'] as $t)
            <button @click="searchType='{{ strtolower($t) }}';runSearch()"
                :style="searchType==='{{ strtolower($t) }}' ? 'background:#00d4aa;color:#0f172a;border-color:#00d4aa;' : 'background:#fff;color:#64748b;border-color:#e2e8f0;'"
                style="padding:4px 9px;font-size:10px;font-weight:600;border:1px solid;border-radius:3px;cursor:pointer;white-space:nowrap;transition:all .15s;">
                {{ $t }}
            </button>
            @endforeach
        </div>

        {{-- Map tab pills --}}
        <div x-show="mode==='map'" class="flex items-center gap-1 flex-shrink-0">
            @foreach(['Suburb / Province','Sales & Transfers','Prospecting','Documents'] as $t)
            <button
                :style="mapTab==='{{ $t }}' ? 'background:#00d4aa;color:#0f172a;border-color:#00d4aa;' : 'background:#fff;color:#64748b;border-color:#e2e8f0;'"
                @click="mapTab='{{ $t }}'"
                style="padding:4px 9px;font-size:10px;font-weight:600;border:1px solid;border-radius:3px;cursor:pointer;white-space:nowrap;transition:all .15s;">
                {{ $t }}
            </button>
            @endforeach
        </div>
    </div>

    {{-- MAIN BODY --}}
    <div class="flex flex-1 overflow-hidden" style="background:#f8fafc;">

        {{-- ── SEARCH MODE ── --}}
        <div x-show="mode==='search'" class="flex flex-col flex-1 overflow-hidden">

            {{-- Empty state --}}
            <div x-show="!searchQuery" class="flex flex-col items-center justify-center flex-1 gap-4">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" style="width:52px;height:52px;color:#cbd5e1;"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/></svg>
                <div style="text-align:center;">
                    <div style="font-size:14px;font-weight:600;color:#334155;margin-bottom:4px;">Search for a property</div>
                    <div style="font-size:12px;color:#94a3b8;">Enter an address, ERF number, suburb or owner name</div>
                </div>
                <div class="flex flex-wrap gap-2 justify-center" style="max-width:460px;">
                    @foreach(['198 John Dory Drive','Newlands East','ERF 1438','Palm Beach','Ramsgate','Shelly Beach'] as $q)
                    <button @click="searchQuery='{{ $q }}';runSearch()"
                        style="padding:5px 12px;font-size:11px;border:1px solid #e2e8f0;border-radius:20px;background:#fff;color:#475569;cursor:pointer;">
                        {{ $q }}
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- Loading --}}
            <div x-show="searchQuery && loading" class="flex items-center justify-center flex-1 gap-2">
                <div style="width:16px;height:16px;border:2px solid #e2e8f0;border-top-color:#00d4aa;border-radius:50%;animation:corex-spin .7s linear infinite;flex-shrink:0;"></div>
                <span style="font-size:12px;color:#64748b;">Searching...</span>
            </div>

            {{-- Results --}}
            <div x-show="searchQuery && !loading && results.length > 0" class="flex-1 overflow-y-auto p-3">
                <div class="flex items-center justify-between mb-2 px-1">
                    <span style="font-size:11px;color:#94a3b8;" x-text="results.length + ' properties found'"></span>
                    <span style="font-size:11px;color:#94a3b8;">KZN South Coast</span>
                </div>
                <template x-for="(r,i) in results" :key="i">
                    <div @click="selectedProperty=r"
                        :style="selectedProperty && selectedProperty.id===r.id ? 'border-color:#00d4aa;background:#f0fdfb;' : 'border-color:#e2e8f0;background:#fff;'"
                        style="border:1px solid;border-radius:4px;padding:10px 12px;margin-bottom:6px;cursor:pointer;transition:all .15s;"
                        onmouseover="if(!this.classList.contains('selected'))this.style.borderColor='rgba(0,212,170,0.5)'"
                        onmouseout="this.style.borderColor=''">
                        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;">
                            <div style="min-width:0;flex:1;">
                                <div style="font-size:13px;font-weight:700;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" x-text="r.address"></div>
                                <div style="font-size:11px;color:#94a3b8;margin-top:2px;" x-text="r.suburb+' · ERF '+r.erf+' · '+r.size"></div>
                            </div>
                            <div style="text-align:right;flex-shrink:0;">
                                <div style="font-size:12px;font-weight:700;color:#00d4aa;" x-text="r.lastSale"></div>
                                <div style="font-size:10px;color:#94a3b8;" x-text="r.saleYear"></div>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:12px;margin-top:6px;">
                            <span style="font-size:10px;color:#64748b;"><span style="font-weight:600;color:#334155;" x-text="r.muniValue"></span> muni</span>
                            <span style="font-size:10px;color:#94a3b8;" x-text="r.titleDeed"></span>
                            <span style="font-size:10px;font-weight:700;margin-left:auto;" :style="r.bond==='R 0' ? 'color:#22c55e' : 'color:#f97316'" x-text="r.bond==='R 0' ? 'Bond free' : 'Bond '+r.bond"></span>
                        </div>
                    </div>
                </template>
            </div>

            {{-- No results --}}
            <div x-show="searchQuery && !loading && results.length===0" class="flex flex-col items-center justify-center flex-1 gap-2">
                <div style="font-size:13px;color:#94a3b8;">No properties found</div>
                <div style="font-size:11px;color:#cbd5e1;" x-text="'Searched for: '+searchQuery"></div>
            </div>
        </div>

        {{-- ── MAP / PROSPECTING MODE ── --}}
        <div x-show="mode==='map'" class="relative flex-1" style="min-width:0;">
            <div id="evaluation-map" class="w-full h-full"></div>

            {{-- Left toolbar --}}
            <div style="position:absolute;left:10px;top:10px;z-index:1000;display:flex;flex-direction:column;gap:3px;background:rgba(255,255,255,0.95);backdrop-filter:blur(4px);border-radius:4px;padding:4px;border:1px solid #e2e8f0;box-shadow:0 1px 8px rgba(0,0,0,0.08);"
                x-data="{lyr:false,ovr:false,cad:false}">

                <button title="Street View" onclick="alert('Street View — requires Google Maps API key')"
                    style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;background:transparent;border:none;cursor:pointer;border-radius:3px;color:#64748b;"
                    onmouseover="this.style.color='#00d4aa';this.style.background='rgba(0,212,170,0.08)'"
                    onmouseout="this.style.color='#64748b';this.style.background='transparent'">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:16px;height:16px;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                </button>

                <div style="position:relative;">
                    <button title="Switch Layer" @click="lyr=!lyr;ovr=false;cad=false"
                        style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;background:transparent;border:none;cursor:pointer;border-radius:3px;color:#64748b;"
                        onmouseover="this.style.color='#00d4aa';this.style.background='rgba(0,212,170,0.08)'"
                        onmouseout="this.style.color='#64748b';this.style.background='transparent'">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:16px;height:16px;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M6.429 9.75 2.25 12l4.179 2.25m0-4.5 5.571 3 5.571-3m-11.142 0L2.25 7.5 12 2.25l9.75 5.25-4.179 2.25m0 0L21.75 12l-4.179 2.25m0 0 4.179 2.25L12 21.75 2.25 16.5l4.179-2.25m11.142 0-5.571 3-5.571-3"/></svg>
                    </button>
                    <div x-show="lyr" x-cloak @click.outside="lyr=false"
                        style="position:absolute;left:38px;top:0;background:#fff;border:1px solid #e2e8f0;border-radius:4px;padding:6px;min-width:165px;z-index:1001;box-shadow:0 4px 12px rgba(0,0,0,0.1);">
                        <div style="font-size:9px;color:#94a3b8;text-transform:uppercase;letter-spacing:.08em;padding:2px 4px 5px;">Base Layer</div>
                        @foreach(['Streets Light'=>'streets_light','Streets Dark'=>'streets_dark','Satellite'=>'satellite','Terrain'=>'terrain'] as $lbl=>$lkey)
                        <button onclick="setLayer('{{ $lkey }}')" id="ldot-btn-{{ $lkey }}"
                            style="display:flex;align-items:center;gap:8px;width:100%;padding:5px 4px;background:transparent;border:none;cursor:pointer;border-radius:2px;font-size:11px;color:#334155;text-align:left;"
                            onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                            <span id="ldot-{{ $lkey }}" style="{{ $lkey==='streets_light' ? 'width:8px;height:8px;border-radius:50%;background:#00d4aa;flex-shrink:0;' : 'width:8px;height:8px;border-radius:50%;border:1.5px solid #cbd5e1;flex-shrink:0;' }}"></span>
                            {{ $lbl }}
                        </button>
                        @endforeach
                    </div>
                </div>

                <div style="position:relative;">
                    <button title="Overlays" @click="ovr=!ovr;lyr=false;cad=false"
                        style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;background:transparent;border:none;cursor:pointer;border-radius:3px;color:#64748b;"
                        onmouseover="this.style.color='#00d4aa';this.style.background='rgba(0,212,170,0.08)'"
                        onmouseout="this.style.color='#64748b';this.style.background='transparent'">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:16px;height:16px;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/></svg>
                    </button>
                    <div x-show="ovr" x-cloak @click.outside="ovr=false"
                        x-data="{erf:true,str:false,srv:false,sub:true}"
                        style="position:absolute;left:38px;top:0;background:#fff;border:1px solid #e2e8f0;border-radius:4px;padding:6px;min-width:165px;z-index:1001;box-shadow:0 4px 12px rgba(0,0,0,0.1);">
                        <div style="font-size:9px;color:#94a3b8;text-transform:uppercase;letter-spacing:.08em;padding:2px 4px 5px;">Overlays</div>
                        @foreach(['erf'=>'ERF Numbers','str'=>'Street Numbers','srv'=>'Servitudes','sub'=>'Suburb Boundaries'] as $m=>$lbl)
                        <button @click="{{ $m }}=!{{ $m }}"
                            style="display:flex;align-items:center;justify-content:space-between;width:100%;padding:5px 4px;background:transparent;border:none;cursor:pointer;border-radius:2px;font-size:11px;color:#334155;"
                            onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                            <span>{{ $lbl }}</span>
                            <span :style="{{ $m }} ? 'background:#00d4aa;justify-content:flex-end;' : 'background:#cbd5e1;justify-content:flex-start;'" style="width:26px;height:14px;border-radius:7px;display:flex;align-items:center;padding:2px;flex-shrink:0;transition:background .15s;">
                                <span style="width:10px;height:10px;border-radius:50%;background:#fff;display:block;flex-shrink:0;"></span>
                            </span>
                        </button>
                        @endforeach
                    </div>
                </div>

                <div style="height:1px;background:#f1f5f9;margin:2px 0;"></div>

                <div style="position:relative;">
                    <button title="Cadastral Info" @click="cad=!cad;lyr=false;ovr=false"
                        style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;background:transparent;border:none;cursor:pointer;border-radius:3px;color:#64748b;"
                        onmouseover="this.style.color='#00d4aa';this.style.background='rgba(0,212,170,0.08)'"
                        onmouseout="this.style.color='#64748b';this.style.background='transparent'">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:16px;height:16px;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3.75v4.5m0-4.5h4.5m-4.5 0L9 9M3.75 20.25v-4.5m0 4.5h4.5m-4.5 0L9 15M20.25 3.75h-4.5m4.5 0v4.5m0-4.5L15 9m5.25 11.25h-4.5m4.5 0v-4.5m0 4.5L15 15"/></svg>
                    </button>
                    <div x-show="cad" x-cloak @click.outside="cad=false"
                        style="position:absolute;left:38px;top:0;background:#fff;border:1px solid rgba(0,212,170,0.3);border-radius:4px;padding:10px 12px;min-width:170px;z-index:1001;box-shadow:0 4px 12px rgba(0,0,0,0.1);">
                        <div style="font-size:9px;color:#00d4aa;text-transform:uppercase;letter-spacing:.08em;margin-bottom:7px;font-weight:700;">Cadastral</div>
                        @foreach(['ERF'=>'1438','Area'=>'363 m²','Perimeter'=>'76.4 m','Township'=>'Newlands Ext 16','Portion'=>'0'] as $k=>$v)
                        <div style="display:flex;justify-content:space-between;margin-bottom:4px;">
                            <span style="font-size:10px;color:#94a3b8;">{{ $k }}</span>
                            <span style="font-size:10px;color:#0f172a;font-weight:600;">{{ $v }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>

                <button title="Measure" onclick="alert('Measure tool — next build')"
                    style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;background:transparent;border:none;cursor:pointer;border-radius:3px;color:#64748b;"
                    onmouseover="this.style.color='#00d4aa';this.style.background='rgba(0,212,170,0.08)'"
                    onmouseout="this.style.color='#64748b';this.style.background='transparent'">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:16px;height:16px;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0 0 12 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 0 1-2.031.352 5.988 5.988 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971Zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0 2.62 10.726c.122.499-.106 1.028-.59 1.202a5.989 5.989 0 0 1-2.031.352 5.989 5.989 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971Z"/></svg>
                </button>

                <button title="Vicinity Radius" id="btn-radius" onclick="toggleRadius()"
                    style="width:32px;height:32px;display:flex;align-items:center;justify-content:center;background:transparent;border:none;cursor:pointer;border-radius:3px;color:#64748b;"
                    onmouseover="if(!window.radiusActive){this.style.color='#00d4aa';this.style.background='rgba(0,212,170,0.08)';}"
                    onmouseout="if(!window.radiusActive){this.style.color='#64748b';this.style.background='transparent';}">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:16px;height:16px;flex-shrink:0;"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="2" fill="currentColor"/></svg>
                </button>
            </div>

            {{-- Map layer toggles --}}
            <div x-data="{s:true,l:true,v:false,t:false}"
                style="position:absolute;bottom:16px;left:10px;z-index:1000;background:rgba(255,255,255,0.95);backdrop-filter:blur(4px);border:1px solid #e2e8f0;border-radius:4px;padding:8px 10px;min-width:185px;box-shadow:0 1px 8px rgba(0,0,0,0.08);">
                <div style="font-size:9px;color:#94a3b8;text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px;font-weight:600;">Map Layers</div>
                @foreach([
                    ['s','Recent Sales','#3b82f6','sales','17'],
                    ['l','Active Listings','#22c55e','listings','22'],
                    ['v','Listed vs Sold','#f97316','soldlisted','8'],
                    ['t','Transfers / Bonds','#a855f7','transfers','5'],
                ] as $row)
                <button @click="{{ $row[0] }}=!{{ $row[0] }};toggleMapLayer('{{ $row[3] }}',{{ $row[0] }})"
                    style="display:flex;align-items:center;gap:7px;width:100%;padding:3px 0;background:transparent;border:none;cursor:pointer;">
                    <span :style="{{ $row[0] }} ? 'background:{{ $row[2] }};box-shadow:0 0 5px {{ $row[2] }}66;' : 'background:#cbd5e1;'"
                        style="width:9px;height:9px;border-radius:50%;flex-shrink:0;transition:background .15s;"></span>
                    <span style="font-size:11px;color:#334155;flex:1;text-align:left;">{{ $row[1] }}</span>
                    <span style="font-size:10px;color:#94a3b8;">{{ $row[4] }}</span>
                </button>
                @endforeach
            </div>
        </div>

        {{-- ── RIGHT PANEL ── --}}
        <div class="flex flex-col overflow-hidden flex-shrink-0" style="width:380px;background:#fff;border-left:1px solid #e2e8f0;">

            {{-- No property selected --}}
            <div x-show="!selectedProperty" class="flex flex-col items-center justify-center flex-1 gap-3" style="padding:32px;color:#94a3b8;">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor" style="width:40px;height:40px;color:#e2e8f0;"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 21v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21m0 0h4.5V3.545M12.75 21h7.5V10.75M2.25 21h1.5m18 0h-18M2.25 9l4.5-1.636M18.75 3l-1.5.545m0 6.205 3 1m1.5.5-1.5-.5M6.75 7.364V3h-3v18m3-13.636 10.5-3.819"/></svg>
                <div style="font-size:12px;text-align:center;color:#94a3b8;">Select a property<br>to view its details</div>
            </div>

            {{-- Property detail --}}
            <div x-show="selectedProperty" class="flex flex-col h-full overflow-hidden">

                {{-- Title bar --}}
                <div style="padding:10px 14px;border-bottom:1px solid #e2e8f0;flex-shrink:0;">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;">
                        <div style="min-width:0;">
                            <div style="font-size:13px;font-weight:700;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" x-text="selectedProperty?.address"></div>
                            <div style="font-size:10px;color:#94a3b8;margin-top:1px;" x-text="(selectedProperty?.suburb??'')+' · ERF '+(selectedProperty?.erf??'')+' · '+(selectedProperty?.size??'')"></div>
                        </div>
                        <div style="display:flex;gap:4px;flex-shrink:0;">
                            <button style="padding:3px 8px;font-size:10px;font-weight:600;border-radius:3px;border:1px solid rgba(0,212,170,0.4);color:#00d4aa;background:rgba(0,212,170,0.06);cursor:pointer;">Vicinity</button>
                            <button style="padding:3px 8px;font-size:10px;font-weight:600;border-radius:3px;border:1px solid #e2e8f0;color:#64748b;background:#fff;cursor:pointer;">Docs</button>
                        </div>
                    </div>
                </div>

                {{-- KPI cards --}}
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;border-bottom:1px solid #e2e8f0;flex-shrink:0;">
                    <div style="padding:10px 12px;text-align:center;border-right:1px solid #e2e8f0;">
                        <div style="font-size:9px;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px;">Muni Value</div>
                        <div style="font-size:13px;font-weight:700;color:#00d4aa;" x-text="selectedProperty?.muniValue"></div>
                        <div style="font-size:9px;color:#94a3b8;">2017</div>
                    </div>
                    <div style="padding:10px 12px;text-align:center;border-right:1px solid #e2e8f0;">
                        <div style="font-size:9px;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px;">Last Sale</div>
                        <div style="font-size:13px;font-weight:700;color:#00d4aa;" x-text="selectedProperty?.lastSale"></div>
                        <div style="font-size:9px;color:#94a3b8;" x-text="selectedProperty?.saleYear"></div>
                    </div>
                    <div style="padding:10px 12px;text-align:center;">
                        <div style="font-size:9px;color:#94a3b8;text-transform:uppercase;letter-spacing:.06em;margin-bottom:3px;">Stand</div>
                        <div style="font-size:13px;font-weight:700;color:#00d4aa;" x-text="selectedProperty?.size"></div>
                        <div style="font-size:9px;font-weight:600;" :style="selectedProperty?.bond==='R 0'?'color:#22c55e':'color:#f97316'" x-text="selectedProperty?.bond==='R 0'?'Bond free':'Bond '+selectedProperty?.bond"></div>
                    </div>
                </div>

                {{-- Accordion --}}
                <div class="flex-1 overflow-y-auto" x-data="{open:'property'}">
                    @php
                    $secs=[
                        ['property','Property Information',[['Province','KwaZulu-Natal'],['Suburb','Newlands East'],['Street','198 John Dory Drive'],['ERF','1438'],['Stand Size','363 m²'],['Extension','Newlands Ext 16'],['Portion','0']]],
                        ['sale','Sale Information',[['Title Deed','T4788/1997'],['Sale Date','22 Aug 1996'],['Transfer Date','21 Feb 1997'],['Purchase Amount','R 12 900'],['Bond Amount','R 0'],['Bond Holder','—']]],
                        ['muni','Municipal Valuation',[['Valuation','R 750 000'],['Valuation Date','1 Jul 2017']]],
                        ['serv','Servitudes / Endorsements',[]],
                        ['transfer','Transfer History',[['Date','22 Aug 1996'],['Price','R 12 900'],['Bond','R 0'],['Title Deed','T4788/1997']]],
                        ['street','Street Summary',[['2019 — 2 sales','Avg R 787 500'],['2020 — 0 sales','—'],['2021 — 2 sales','Avg R 622 500']]],
                        ['suburb','Suburb Summary',[['2017 — 16 sales','Med R 550 000'],['2018 — 13 sales','Med R 630 000'],['2019 — 22 sales','Med R 652 500'],['2020 — 18 sales','Med R 629 000'],['2021 — 24 sales','Med R 765 000']]],
                        ['accom','Accommodation / Zoning',[['Zoning','Residential'],['Type','Freehold'],['Sectional Title','No']]],
                    ];
                    @endphp
                    @foreach($secs as [$key,$title,$rows])
                    <div style="border-bottom:1px solid #f1f5f9;">
                        <button @click="open=open==='{{ $key }}' ? null : '{{ $key }}'"
                            style="width:100%;display:flex;align-items:center;justify-content:space-between;padding:9px 14px;background:transparent;border:none;cursor:pointer;text-align:left;"
                            onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                            <span style="font-size:11px;font-weight:600;color:#334155;">{{ $title }}</span>
                            <span x-text="open==='{{ $key }}' ? '▾' : '▸'"
                                :style="open==='{{ $key }}' ? 'color:#00d4aa' : 'color:#cbd5e1'"
                                style="font-size:12px;flex-shrink:0;line-height:1;"></span>
                        </button>
                        <div x-show="open==='{{ $key }}'" style="padding:2px 14px 10px;">
                            @if(empty($rows))
                                <p style="font-size:11px;color:#94a3b8;font-style:italic;margin:0;">No data registered.</p>
                            @else
                                @foreach($rows as $row)
                                <div style="display:flex;justify-content:space-between;align-items:baseline;padding:3px 0;border-bottom:1px solid #f8fafc;">
                                    <span style="font-size:10px;color:#94a3b8;">{{ $row[0] }}</span>
                                    <span style="font-size:11px;font-weight:600;color:#0f172a;">{{ $row[1] }}</span>
                                </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>

                {{-- CTA --}}
                <div style="padding:12px;border-top:1px solid #e2e8f0;flex-shrink:0;">
                    <button onclick="window.location.href='{{ route('presentations.create') }}'"
                        style="width:100%;padding:10px;background:#00d4aa;color:#0f172a;font-size:12px;font-weight:700;border:none;border-radius:3px;cursor:pointer;letter-spacing:.02em;transition:opacity .15s;"
                        onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
                        Generate Evaluation Report
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Leaflet --}}
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
@keyframes corex-spin { to { transform:rotate(360deg); } }
@keyframes corex-pulse {
    0%   { box-shadow:0 0 0 0 rgba(0,212,170,.5); }
    70%  { box-shadow:0 0 0 8px rgba(0,212,170,0); }
    100% { box-shadow:0 0 0 0 rgba(0,212,170,0); }
}
/* Strip Leaflet popup chrome completely */
.cx-popup .leaflet-popup-content-wrapper { background:transparent!important;box-shadow:none!important;padding:0!important; }
.cx-popup .leaflet-popup-tip-container { display:none!important; }
.cx-popup .leaflet-popup-content { margin:0!important; }
.cx-popup .leaflet-popup-close-button { display:none!important; }
</style>

<script>
function evalApp() {
    return {
        mode: 'search',
        mapTab: 'Suburb / Province',
        searchQuery: '',
        searchType: 'full title',
        loading: false,
        results: [],
        selectedProperty: null,

        init() {},

        switchToMap() {
            this.mode = 'map';
            this.$nextTick(() => {
                if (!window.evalMapReady) { initEvalMap(); window.evalMapReady = true; }
                else if (window.evalMap) { window.evalMap.invalidateSize(); }
            });
        },

        runSearch() {
            if (!this.searchQuery || this.searchQuery.length < 2) { this.results = []; return; }
            this.loading = true;
            setTimeout(() => {
                var q = this.searchQuery.toLowerCase();
                var all = [
                    {id:1,address:'198 John Dory Drive',suburb:'Newlands East',erf:'1438',size:'363 m²',lastSale:'R 12 900',saleYear:'1996',muniValue:'R 750 000',bond:'R 0',titleDeed:'T4788/1997'},
                    {id:2,address:'167 John Dory Drive',suburb:'Newlands East',erf:'1421',size:'387 m²',lastSale:'R 799 000',saleYear:'2021',muniValue:'R 820 000',bond:'R 560 000',titleDeed:'T1234/2021'},
                    {id:3,address:'32 Bream Close',suburb:'Newlands East',erf:'1502',size:'302 m²',lastSale:'R 980 000',saleYear:'2021',muniValue:'R 900 000',bond:'R 0',titleDeed:'T5566/2021'},
                    {id:4,address:'10 Shiner Place',suburb:'Newlands East',erf:'1389',size:'501 m²',lastSale:'R 385 000',saleYear:'2020',muniValue:'R 410 000',bond:'R 308 000',titleDeed:'T9988/2020'},
                    {id:5,address:'33 Chimeara Place',suburb:'Newlands East',erf:'1610',size:'382 m²',lastSale:'R 1 500 000',saleYear:'2020',muniValue:'R 1 350 000',bond:'R 0',titleDeed:'T7711/2020'},
                ];
                this.results = all.filter(r => r.address.toLowerCase().includes(q) || r.suburb.toLowerCase().includes(q) || r.erf.includes(q));
                this.loading = false;
            }, 350);
        }
    };
}

// ── MAP ──
window.evalMapReady = false;
window.evalMap = null;
window.radiusActive = false;
var radiusCircle = null;
var activeBase = null;
var salesLayer, listingsLayer, soldListedLayer, transfersLayer;

var tileSets = {
    streets_light: L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png',   {attribution:'© CartoDB'}),
    streets_dark:  L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',    {attribution:'© CartoDB'}),
    satellite:     L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {attribution:'© Esri'}),
    terrain:       L.tileLayer('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',                 {attribution:'© OpenTopoMap'}),
};

function initEvalMap() {
    window.evalMap = L.map('evaluation-map', {zoomControl:true}).setView([-30.9347, 30.0930], 16);
    activeBase = tileSets.streets_light;
    activeBase.addTo(window.evalMap);

    // Subject ERF polygon
    L.polygon([[-30.9340,30.0920],[-30.9334,30.0930],[-30.9340,30.0940],[-30.9347,30.0930]],
        {color:'#00d4aa',weight:2,fillColor:'#00d4aa',fillOpacity:.15}).addTo(window.evalMap);

    // Pulsing subject marker
    L.marker([-30.9347,30.0930], {icon:L.divIcon({
        className:'',
        html:'<div style="width:14px;height:14px;background:#00d4aa;border-radius:50%;border:2px solid #fff;animation:corex-pulse 2s infinite;box-shadow:0 0 0 0 rgba(0,212,170,.5);"></div>',
        iconSize:[14,14],iconAnchor:[7,7]
    })}).addTo(window.evalMap);

    // Layer groups
    salesLayer      = L.layerGroup();
    listingsLayer   = L.layerGroup();
    soldListedLayer = L.layerGroup();
    transfersLayer  = L.layerGroup();

    function mkPin(label, color, shape) {
        var r = shape==='sq' ? '3px' : '50%';
        return '<div style="background:'+color+';color:#fff;font-size:9px;font-weight:700;width:20px;height:20px;border-radius:'+r+';display:flex;align-items:center;justify-content:center;border:2px solid rgba(255,255,255,.9);box-shadow:0 2px 6px '+color+'66;">'+label+'</div>';
    }

    function mkPopup(type, addr, priceHtml, detail, sub) {
        return '<div style="background:#1e293b;border-radius:5px;padding:10px 12px;min-width:175px;font-family:inherit;box-shadow:0 4px 16px rgba(0,0,0,0.25);">'
            +'<div style="font-size:9px;color:rgba(255,255,255,.35);text-transform:uppercase;letter-spacing:.07em;margin-bottom:4px;">'+type+'</div>'
            +'<div style="font-size:12px;font-weight:700;color:#fff;margin-bottom:5px;">'+addr+'</div>'
            +'<div style="font-size:12px;margin-bottom:3px;">'+priceHtml+'</div>'
            +'<div style="font-size:10px;color:rgba(255,255,255,.45);">'+detail+'</div>'
            +(sub ? '<div style="font-size:10px;color:rgba(255,255,255,.3);margin-top:2px;">'+sub+'</div>' : '')
            +'</div>';
    }

    // Recent Sales — blue
    [{lat:-30.9328,lng:30.0918,l:'A',addr:'32 Bream Close',price:'R 980 000',date:'Aug 2021',sqm:'302 m²',dist:'0.3 km'},
     {lat:-30.9355,lng:30.0925,l:'B',addr:'153 Sawfish Rd',price:'R 150 000',date:'Dec 2019',sqm:'300 m²',dist:'0.3 km'},
     {lat:-30.9332,lng:30.0944,l:'C',addr:'124 John Dory Dr',price:'R 650 000',date:'Dec 2020',sqm:'516 m²',dist:'0.3 km'},
     {lat:-30.9360,lng:30.0910,l:'D',addr:'33 Chimeara Pl',price:'R 1 500 000',date:'Jun 2020',sqm:'382 m²',dist:'0.2 km'},
     {lat:-30.9322,lng:30.0933,l:'E',addr:'10 Shiner Pl',price:'R 385 000',date:'Aug 2020',sqm:'501 m²',dist:'0.2 km'},
    ].forEach(d => {
        L.marker([d.lat,d.lng],{icon:L.divIcon({className:'',html:mkPin(d.l,'#3b82f6','circle'),iconSize:[20,20],iconAnchor:[10,10]})})
         .bindPopup(mkPopup('Recent Sale',d.addr,'<span style="color:#60a5fa;font-weight:700;">'+d.price+'</span>',d.sqm+' · '+d.dist,'Sold '+d.date),{className:'cx-popup'})
         .addTo(salesLayer);
    });
    salesLayer.addTo(window.evalMap);

    // Active Listings — green
    [{lat:-30.9315,lng:30.0935,addr:'437 Sir Frances Drake',price:'R 1 335 000',info:'4 bed · 42 DOM'},
     {lat:-30.9370,lng:30.0945,addr:'629 Dick King Rd',price:'R 1 195 000',info:'2 bed · 18 DOM'},
     {lat:-30.9342,lng:30.0900,addr:'99 Lord Macartney Rd',price:'R 820 000',info:'3 bed · 67 DOM'},
    ].forEach(d => {
        L.marker([d.lat,d.lng],{icon:L.divIcon({className:'',html:mkPin('L','#22c55e','sq'),iconSize:[20,20],iconAnchor:[10,10]})})
         .bindPopup(mkPopup('Active Listing',d.addr,'<span style="color:#4ade80;font-weight:700;">Listed '+d.price+'</span>',d.info,''),{className:'cx-popup'})
         .addTo(listingsLayer);
    });
    listingsLayer.addTo(window.evalMap);

    // Listed vs Sold — orange
    [{lat:-30.9338,lng:30.0955,addr:'167 John Dory Dr',listed:'R 799 000',sold:'R 730 000',dom:'47 DOM'},
     {lat:-30.9358,lng:30.0938,addr:'30 Bream Close',listed:'R 1 050 000',sold:'R 980 000',dom:'31 DOM'},
    ].forEach(d => {
        var body = '<div style="background:#1e293b;border-radius:5px;padding:10px 12px;min-width:190px;box-shadow:0 4px 16px rgba(0,0,0,0.25);">'
            +'<div style="font-size:12px;font-weight:700;color:#fff;margin-bottom:7px;">'+d.addr+'</div>'
            +'<div style="display:flex;justify-content:space-between;margin-bottom:3px;"><span style="font-size:10px;color:rgba(255,255,255,.4);">Listed</span><span style="font-size:11px;color:#fb923c;font-weight:700;">'+d.listed+'</span></div>'
            +'<div style="display:flex;justify-content:space-between;margin-bottom:5px;"><span style="font-size:10px;color:rgba(255,255,255,.4);">Sold</span><span style="font-size:11px;color:#4ade80;font-weight:700;">'+d.sold+'</span></div>'
            +'<div style="font-size:10px;color:rgba(255,255,255,.3);">'+d.dom+'</div></div>';
        L.marker([d.lat,d.lng],{icon:L.divIcon({className:'',html:mkPin('S','#f97316','sq'),iconSize:[20,20],iconAnchor:[10,10]})})
         .bindPopup(body,{className:'cx-popup'}).addTo(soldListedLayer);
    });

    // Transfers — purple
    [{lat:-30.9325,lng:30.0912,addr:'21 Shiner Pl',price:'R 790 000',info:'Bond R 632 000 · Nedbank',date:'Oct 2020'}
    ].forEach(d => {
        L.marker([d.lat,d.lng],{icon:L.divIcon({className:'',html:mkPin('T','#a855f7','circle'),iconSize:[20,20],iconAnchor:[10,10]})})
         .bindPopup(mkPopup('Transfer',d.addr,'<span style="color:#c084fc;font-weight:700;">'+d.price+'</span>',d.info,'Transferred '+d.date),{className:'cx-popup'})
         .addTo(transfersLayer);
    });
}

window.setLayer = function(key) {
    if (!window.evalMap) return;
    if (activeBase) window.evalMap.removeLayer(activeBase);
    activeBase = tileSets[key] || tileSets.streets_light;
    activeBase.addTo(window.evalMap);
    document.querySelectorAll('[id^="ldot-"]').forEach(el => {
        if (el.id.startsWith('ldot-btn-')) return;
        el.style.background = '';
        el.style.border = '1.5px solid #cbd5e1';
    });
    var dot = document.getElementById('ldot-'+key);
    if (dot) { dot.style.background = '#00d4aa'; dot.style.border = 'none'; }
};

window.toggleMapLayer = function(name, on) {
    if (!window.evalMap) return;
    var layers = {sales:salesLayer,listings:listingsLayer,soldlisted:soldListedLayer,transfers:transfersLayer};
    if (!layers[name]) return;
    if (on) layers[name].addTo(window.evalMap);
    else window.evalMap.removeLayer(layers[name]);
};

window.toggleRadius = function() {
    if (!window.evalMap) return;
    var btn = document.getElementById('btn-radius');
    if (radiusCircle) {
        window.evalMap.removeLayer(radiusCircle); radiusCircle = null; window.radiusActive = false;
        btn.style.color = '#64748b'; btn.style.background = 'transparent';
    } else {
        radiusCircle = L.circle([-30.9347,30.0930],{radius:500,color:'#00d4aa',weight:1.5,dashArray:'5 4',fillOpacity:.04}).addTo(window.evalMap);
        window.radiusActive = true;
        btn.style.color = '#00d4aa'; btn.style.background = 'rgba(0,212,170,.1)';
    }
};
</script>
@endsection
