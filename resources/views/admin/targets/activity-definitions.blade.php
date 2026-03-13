@extends('layouts.corex')

@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    {{-- Page Header --}}
    <div style="background: var(--brand-default, #0b2a4a);" class="rounded-md px-6 py-4">
        <h2 class="text-xl font-bold text-white leading-tight tracking-tight">Activity Definitions (V2)</h2>
        <div class="text-sm text-white/60">Define activities, weights, and scoring modes.</div>
    </div>

    @if (session('status'))
        <div class="rounded-md px-4 py-3 text-sm" style="background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: #10b981;">{{ session('status') }}</div>
    @endif

    @if($errors->any())
        <div class="rounded-md px-4 py-3 text-sm" style="background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #ef4444;">{{ $errors->first() }}</div>
    @endif

    {{-- Add Activity --}}
    <div class="rounded-md overflow-hidden" style="background: var(--surface); border: 1px solid var(--border);">
        <div class="px-5 py-4" style="border-bottom: 1px solid var(--border);">
            <h3 class="text-sm font-semibold" style="color: var(--text-primary);">Add New Activity</h3>
        </div>

        <div class="px-5 py-4">
            <form method="POST" action="{{ route('admin.targets.activity.definitions.save') }}" class="grid grid-cols-1 sm:grid-cols-6 gap-4 items-end">
                @csrf

                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-secondary);">Name</label>
                    <input name="name" required placeholder="Appointments"
                           class="w-full rounded-md px-3 py-2 text-sm transition-all duration-300"
                           style="background: var(--surface-2, #f0f2f8); border: 1px solid var(--border); color: var(--text-primary);"
                           onfocus="this.style.borderColor='var(--brand-button, #0ea5e9)';this.style.boxShadow='0 0 0 2px rgba(14,165,233,0.15)'"
                           onblur="this.style.borderColor='var(--border)';this.style.boxShadow='none'" />
                </div>

                <div class="sm:col-span-1">
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-secondary);">Weight</label>
                    <input name="weight" type="number" step="0.01" min="0" value="1"
                           class="w-full rounded-md px-3 py-2 text-sm text-right transition-all duration-300"
                           style="background: var(--surface-2, #f0f2f8); border: 1px solid var(--border); color: var(--text-primary);"
                           onfocus="this.style.borderColor='var(--brand-button, #0ea5e9)';this.style.boxShadow='0 0 0 2px rgba(14,165,233,0.15)'"
                           onblur="this.style.borderColor='var(--border)';this.style.boxShadow='none'" />
                </div>

                <div class="sm:col-span-1">
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-secondary);">Order</label>
                    <input name="sort_order" type="number" min="0" value="100"
                           class="w-full rounded-md px-3 py-2 text-sm text-right transition-all duration-300"
                           style="background: var(--surface-2, #f0f2f8); border: 1px solid var(--border); color: var(--text-primary);"
                           onfocus="this.style.borderColor='var(--brand-button, #0ea5e9)';this.style.boxShadow='0 0 0 2px rgba(14,165,233,0.15)'"
                           onblur="this.style.borderColor='var(--border)';this.style.boxShadow='none'" />
                </div>

                <div class="sm:col-span-1">
                    <label class="block text-xs font-medium mb-1.5" style="color: var(--text-secondary);">Scoring</label>
                    <select name="scoring_mode"
                            class="w-full rounded-md px-3 py-2 text-sm transition-all duration-300"
                            style="background: var(--surface-2, #f0f2f8); border: 1px solid var(--border); color: var(--text-primary);">
                        <option value="count" selected>Per action</option>
                        <option value="once">Once (tick)</option>
                    </select>
                </div>

                <div class="sm:col-span-1 flex items-end gap-3">
                    <label class="inline-flex items-center gap-2 text-sm pb-2" style="color: var(--text-primary);">
                        <input type="checkbox" name="is_enabled" value="1" checked class="rounded-md transition-all duration-300" style="border-color: var(--border);">
                        Active
                    </label>
                    <button class="corex-btn-primary text-sm ml-auto">Add</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Existing Definitions --}}
    <div class="rounded-md overflow-hidden" style="background: var(--surface); border: 1px solid var(--border);">
        <div class="px-5 py-4" style="border-bottom: 1px solid var(--border);">
            <h3 class="text-sm font-semibold" style="color: var(--text-primary);">Existing Definitions</h3>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm ds-table">
                <thead>
                    <tr style="background: var(--surface-2, var(--surface));">
                        <th class="text-left px-4 py-3" style="color: var(--text-secondary);">Name</th>
                        <th class="text-left px-4 py-3 w-28" style="color: var(--text-secondary);">Weight</th>
                        <th class="text-left px-4 py-3 w-28" style="color: var(--text-secondary);">Order</th>
                        <th class="text-left px-4 py-3 w-36" style="color: var(--text-secondary);">Scoring</th>
                        <th class="text-left px-4 py-3 w-28" style="color: var(--text-secondary);">Enabled</th>
                        <th class="text-right px-4 py-3 w-24" style="color: var(--text-secondary);"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($definitions as $d)
                        <tr class="transition-all duration-300" style="border-bottom: 1px solid var(--border);">
                            <form method="POST" action="{{ route('admin.targets.activity.definitions.save') }}">
                                @csrf
                                <input type="hidden" name="id" value="{{ $d->id }}">

                                <td class="px-4 py-3">
                                    <input name="name"
                                           value="{{ $d->name }}"
                                           class="w-full rounded-md px-2.5 py-1.5 text-sm transition-all duration-300"
                                           style="background: var(--surface-2, #f0f2f8); border: 1px solid var(--border); color: var(--text-primary);"
                                           onfocus="this.style.borderColor='var(--brand-button, #0ea5e9)';this.style.boxShadow='0 0 0 2px rgba(14,165,233,0.15)'"
                                           onblur="this.style.borderColor='var(--border)';this.style.boxShadow='none'">
                                </td>

                                <td class="px-4 py-3">
                                    <input name="weight"
                                           type="number"
                                           step="0.01"
                                           min="0"
                                           value="{{ number_format((float)$d->weight, 2, '.', '') }}"
                                           class="w-24 rounded-md px-2.5 py-1.5 text-sm text-right transition-all duration-300"
                                           style="background: var(--surface-2, #f0f2f8); border: 1px solid var(--border); color: var(--text-primary);"
                                           onfocus="this.style.borderColor='var(--brand-button, #0ea5e9)';this.style.boxShadow='0 0 0 2px rgba(14,165,233,0.15)'"
                                           onblur="this.style.borderColor='var(--border)';this.style.boxShadow='none'">
                                </td>

                                <td class="px-4 py-3">
                                    <input name="sort_order"
                                           type="number"
                                           min="0"
                                           value="{{ (int)$d->sort_order }}"
                                           class="w-24 rounded-md px-2.5 py-1.5 text-sm text-right transition-all duration-300"
                                           style="background: var(--surface-2, #f0f2f8); border: 1px solid var(--border); color: var(--text-primary);"
                                           onfocus="this.style.borderColor='var(--brand-button, #0ea5e9)';this.style.boxShadow='0 0 0 2px rgba(14,165,233,0.15)'"
                                           onblur="this.style.borderColor='var(--border)';this.style.boxShadow='none'">
                                </td>

                                <td class="px-4 py-3">
                                    <select name="scoring_mode"
                                            class="w-32 rounded-md px-2.5 py-1.5 text-sm transition-all duration-300"
                                            style="background: var(--surface-2, #f0f2f8); border: 1px solid var(--border); color: var(--text-primary);">
                                        @php($sm = (string)($d->scoring_mode ?? 'count'))
                                        <option value="count" @selected($sm === 'count')>Per action</option>
                                        <option value="once" @selected($sm === 'once')>Once (tick)</option>
                                    </select>
                                </td>

                                <td class="px-4 py-3">
                                    <input type="checkbox"
                                           name="is_enabled"
                                           value="1"
                                           @checked((int)$d->is_enabled === 1)
                                           class="rounded-md transition-all duration-300"
                                           style="border-color: var(--border);">
                                </td>

                                <td class="px-4 py-3 text-right">
                                    <button class="corex-btn-primary text-xs">Save</button>
                                </td>
                            </form>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-sm text-center" style="color: var(--text-muted);">
                                No activity definitions yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
