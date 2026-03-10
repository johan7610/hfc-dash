@extends('layouts.corex')

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    {{-- Page Header --}}
    <div style="background: var(--brand-default, #0b2a4a);" class="rounded-md px-6 py-4">
        <h2 class="text-xl font-bold text-white leading-tight tracking-tight">Import Listings (Propcon XLSX)</h2>
        <div class="text-sm text-white/60 mt-1">Upload the Propcon export as-is. We store the file locally and apply updates into listing stock.</div>
    </div>

    {{-- Success message --}}
    @if(session('status'))
        <div class="rounded-md px-4 py-3" style="border: 1px solid rgba(16,185,129,0.3); background: rgba(16,185,129,0.08); color: var(--text-primary);">
            {{ session('status') }}
        </div>
    @endif

    {{-- Error messages --}}
    @if($errors->any())
        <div class="rounded-md px-4 py-3" style="border: 1px solid rgba(244,63,94,0.3); background: rgba(244,63,94,0.08); color: var(--text-primary);">
            <div class="font-semibold mb-1">Import problem</div>
            <ul class="list-disc pl-5 text-sm space-y-1">
                @foreach($errors->all() as $e)
                    <li>{{ $e }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Upload Card --}}
    <div class="rounded-md p-5" style="border: 1px solid var(--border); background: var(--surface);">
        <div>
            <h3 class="text-sm font-semibold" style="color: var(--text-primary);">Upload XLSX</h3>
            <div class="text-xs mt-1" style="color: var(--text-muted);">We will upsert into listing stock using the Code/Reference fields. Manual pricing fields will be preserved in a later phase.</div>
        </div>

        <form method="post" action="{{ route('admin.listings.import.store') }}" enctype="multipart/form-data" class="mt-4 flex flex-col sm:flex-row gap-3 sm:items-end">
            @csrf
            <div class="flex-1">
                <label class="block text-xs mb-1" style="color: var(--text-secondary);">Propcon XLSX file</label>
                <input type="file" name="file" accept=".xlsx" required
                       class="block w-full text-sm rounded-md px-3 py-2 transition-all duration-300
                              file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium file:cursor-pointer"
                       style="border: 1px solid var(--border); background: var(--surface-2); color: var(--text-primary);
                              --file-bg: var(--brand-button, #0ea5e9);"
                />
            </div>
            <button class="corex-btn-primary">
                Import
            </button>
        </form>
    </div>

    {{-- Recent Import Runs --}}
    <div class="rounded-md overflow-hidden" style="border: 1px solid var(--border); background: var(--surface);">
        <div class="px-4 py-3" style="border-bottom: 1px solid var(--border);">
            <div class="text-sm font-medium" style="color: var(--text-primary);">Recent import runs</div>
            <div class="text-xs" style="color: var(--text-muted);">Audit trail from listing_import_runs</div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm ds-table">
                <thead>
                    <tr style="background: var(--surface-2);">
                        <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">ID</th>
                        <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">When</th>
                        <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Filename</th>
                        <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Status</th>
                        <th class="text-left px-4 py-2.5 text-xs font-medium uppercase tracking-wider" style="color: var(--text-muted);">Error</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($runs as $r)
                        <tr class="transition-all duration-300" style="border-bottom: 1px solid var(--border);" onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background='transparent'">
                            <td class="px-4 py-3 font-medium" style="color: var(--text-primary);">#{{ $r->id }}</td>
                            <td class="px-4 py-3" style="color: var(--text-secondary);">{{ $r->created_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-3" style="color: var(--text-primary);">{{ $r->original_filename }}</td>
                            <td class="px-4 py-3">
                                @if($r->status === 'applied')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium border border-emerald-500/30 bg-emerald-500/10 text-emerald-400">
                                        {{ $r->status }}
                                    </span>
                                @elseif($r->status === 'failed')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium border border-rose-500/30 bg-rose-500/10 text-rose-400">
                                        {{ $r->status }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium" style="border: 1px solid var(--border); background: var(--surface-2); color: var(--text-secondary);">
                                        {{ $r->status }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs" style="color: var(--text-muted);">
                                {{ $r->error_message }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td class="px-4 py-8 text-center" style="color: var(--text-muted);" colspan="5">No imports yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
