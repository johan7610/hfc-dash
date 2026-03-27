<x-app-layout>
    <div class="max-w-7xl mx-auto p-6 space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-white">Fault Reports</h1>
                <p class="text-sm text-white/70">System errors, warnings, and manual reports.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.fault-reports', ['status' => 'new']) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-medium {{ request('status') === 'new' ? 'bg-red-500/20 text-red-400 border border-red-500/30' : 'bg-white/10 text-white/70 border border-white/10 hover:bg-white/15' }}">
                    New
                </a>
                <a href="{{ route('admin.fault-reports', ['status' => 'investigating']) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-medium {{ request('status') === 'investigating' ? 'bg-amber-500/20 text-amber-400 border border-amber-500/30' : 'bg-white/10 text-white/70 border border-white/10 hover:bg-white/15' }}">
                    Investigating
                </a>
                <a href="{{ route('admin.fault-reports', ['status' => 'fixed']) }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-medium {{ request('status') === 'fixed' ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-white/10 text-white/70 border border-white/10 hover:bg-white/15' }}">
                    Fixed
                </a>
                <a href="{{ route('admin.fault-reports') }}"
                   class="px-3 py-1.5 rounded-lg text-xs font-medium {{ !request('status') ? 'bg-blue-500/20 text-blue-400 border border-blue-500/30' : 'bg-white/10 text-white/70 border border-white/10 hover:bg-white/15' }}">
                    All
                </a>
            </div>
        </div>

        @if($reports->isEmpty())
        <div class="rounded-2xl border border-white/10 bg-white/5 p-12 text-center">
            <div class="text-white/40 text-lg">No fault reports found.</div>
        </div>
        @else
        <div class="space-y-2">
            @foreach($reports as $report)
            <div class="rounded-xl border border-white/10 bg-white/5 p-4 hover:bg-white/[0.07] transition-colors" x-data="{ expanded: false }">
                <div class="flex items-start gap-3 cursor-pointer" @click="expanded = !expanded">
                    {{-- Severity indicator --}}
                    <div class="mt-1 w-2 h-2 rounded-full flex-shrink-0
                        {{ $report->severity === 'error' ? 'bg-red-500' : ($report->severity === 'warning' ? 'bg-amber-500' : 'bg-blue-500') }}">
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="text-sm font-medium text-white truncate max-w-[500px]">{{ $report->title }}</span>
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-medium uppercase
                                {{ $report->type === 'backend' ? 'bg-purple-500/20 text-purple-400' : ($report->type === 'frontend' ? 'bg-cyan-500/20 text-cyan-400' : 'bg-white/10 text-white/60') }}">
                                {{ $report->type }}
                            </span>
                            @if($report->occurrence_count > 1)
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-orange-500/20 text-orange-400">
                                {{ $report->occurrence_count }}x
                            </span>
                            @endif
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-medium uppercase
                                {{ $report->status === 'new' ? 'bg-red-500/20 text-red-400' : ($report->status === 'investigating' ? 'bg-amber-500/20 text-amber-400' : ($report->status === 'fixed' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-white/10 text-white/40')) }}">
                                {{ $report->status }}
                            </span>
                        </div>
                        <div class="mt-1 flex items-center gap-3 text-[11px] text-white/40">
                            @if($report->file)
                            <span class="truncate max-w-[300px]">{{ basename($report->file) }}:{{ $report->line }}</span>
                            @endif
                            <span>{{ $report->last_seen_at?->diffForHumans() }}</span>
                            @if($report->url)
                            <span class="truncate max-w-[200px]">{{ $report->method }} {{ parse_url($report->url, PHP_URL_PATH) }}</span>
                            @endif
                        </div>
                    </div>

                    <a href="{{ route('admin.fault-reports.show', $report->id) }}" class="text-[11px] text-white/30 hover:text-blue-400 transition-colors px-2" @click.stop>View</a>

                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"
                         class="w-4 h-4 text-white/30 transition-transform" :class="expanded ? 'rotate-180' : ''">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                    </svg>
                </div>

                {{-- Expanded detail --}}
                <div x-show="expanded" x-cloak x-transition class="mt-3 pt-3 border-t border-white/10 space-y-2">
                    @if($report->exception_class)
                    <div class="text-xs text-white/50"><span class="text-white/30">Class:</span> {{ $report->exception_class }}</div>
                    @endif
                    @if($report->file)
                    <div class="text-xs text-white/50"><span class="text-white/30">File:</span> {{ $report->file }}:{{ $report->line }}</div>
                    @endif
                    @if($report->message)
                    <div class="text-xs text-white/60 bg-black/20 rounded-lg p-3 font-mono whitespace-pre-wrap break-all">{{ Str::limit($report->message, 1000) }}</div>
                    @endif
                    @if($report->trace)
                    <details class="text-xs">
                        <summary class="text-white/40 cursor-pointer hover:text-white/60">Stack trace</summary>
                        <div class="mt-1 bg-black/20 rounded-lg p-3 font-mono text-white/50 whitespace-pre-wrap break-all max-h-60 overflow-y-auto">{{ Str::limit($report->trace, 3000) }}</div>
                    </details>
                    @endif
                    @if($report->request_data)
                    <details class="text-xs">
                        <summary class="text-white/40 cursor-pointer hover:text-white/60">Request data</summary>
                        <div class="mt-1 bg-black/20 rounded-lg p-3 font-mono text-white/50 whitespace-pre-wrap break-all max-h-40 overflow-y-auto">{{ json_encode($report->request_data, JSON_PRETTY_PRINT) }}</div>
                    </details>
                    @endif
                    <div class="flex items-center gap-4 text-[11px] text-white/30">
                        <span>First seen: {{ $report->first_seen_at?->format('Y-m-d H:i') }}</span>
                        <span>Last seen: {{ $report->last_seen_at?->format('Y-m-d H:i') }}</span>
                        @if($report->user) <span>User: {{ $report->user->name }}</span> @endif
                        @if($report->ip_address) <span>IP: {{ $report->ip_address }}</span> @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $reports->withQueryString()->links() }}
        </div>
        @endif
    </div>
</x-app-layout>
