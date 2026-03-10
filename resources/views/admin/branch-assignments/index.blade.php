@extends('layouts.corex')

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">

    <div style="background:#0b2a4a;" class="rounded-2xl px-6 py-4">
        <h2 class="text-xl font-bold text-white leading-tight">Branch Assignments</h2>
        <div class="text-sm text-white/60">Manage branches and their per-branch settings.</div>
    </div>

    {{-- Flash messages handled by global toast system --}}

    @if($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 text-rose-900 px-4 py-3 dark:border-rose-900/40 dark:bg-rose-900/20 dark:text-rose-100">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Add / Delete Branches --}}
    <div class="ds-status-card p-4 space-y-4">
        <h3 class="ds-section-header">Add Branch</h3>

        <form method="POST" action="{{ route('admin.branches.store') }}" class="flex flex-wrap gap-3 items-end">
            @csrf
            <div>
                <label class="block text-xs text-slate-600 dark:text-slate-300 mb-1">Name</label>
                <input class="rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 py-2 text-sm" name="name" required>
            </div>
            <div>
                <label class="block text-xs text-slate-600 dark:text-slate-300 mb-1">Code</label>
                <input class="rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 py-2 text-sm" name="code" required>
            </div>
            <button type="submit" class="corex-btn-primary text-sm">Add Branch</button>
        </form>

        <div class="pt-4 space-y-2">
            <h4 class="text-sm font-semibold text-slate-900 dark:text-slate-100">Existing Branches</h4>

            @foreach($branches as $branch)
                <div class="flex items-center justify-between gap-4 border-b border-slate-200 dark:border-slate-800 pb-2">
                    <div class="font-medium text-slate-900 dark:text-slate-100">
                        {{ $branch->name }} <span class="text-slate-500 dark:text-slate-400">({{ $branch->code }})</span>
                    </div>

                    <form method="POST" action="{{ route('admin.branches.delete', $branch) }}"
                          onsubmit="return confirm('Delete this branch? This cannot be undone.');">
                        @csrf
                        <button class="text-red-600 text-sm font-semibold hover:text-red-700">Delete</button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Branch Contact Details --}}
    <div class="ds-status-card p-4 space-y-4">
        <div>
            <h3 class="ds-section-header">Branch Contact Details</h3>
            <div class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Leave blank to inherit from Agency settings.
            </div>
        </div>

        <div class="space-y-4">
            @foreach($branches as $branch)
                <form method="POST" action="{{ route('admin.branch-settings.update', $branch) }}"
                      enctype="multipart/form-data"
                      class="rounded-2xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-4 space-y-4"
                      x-data="{ removelogo: false }">
                    @csrf

                    <div class="flex items-center justify-between gap-4">
                        <div class="font-semibold text-slate-900 dark:text-slate-100">
                            {{ $branch->name }} <span class="text-slate-500 dark:text-slate-400">({{ $branch->code }})</span>
                        </div>
                        <button type="submit" class="corex-btn-primary text-sm">Save</button>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-slate-600 dark:text-slate-300 mb-1">Trading Name Override</label>
                            <input class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 py-2 text-sm"
                                   name="trading_name" value="{{ old('trading_name', $branch->trading_name) }}"
                                   placeholder="e.g. Johan and Elize Properties T/A">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600 dark:text-slate-300 mb-1">Tagline Override</label>
                            <input class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 py-2 text-sm"
                                   name="tagline" value="{{ old('tagline', $branch->tagline) }}"
                                   placeholder="e.g. THE MANDATE COMPANY">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs text-slate-600 dark:text-slate-300 mb-1">Address Override</label>
                        <textarea name="address" rows="2"
                                  class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 py-2 text-sm"
                                  placeholder="Physical address">{{ old('address', $branch->address) }}</textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-slate-600 dark:text-slate-300 mb-1">Phone Override</label>
                            <input class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 py-2 text-sm"
                                   name="phone" value="{{ old('phone', $branch->phone) }}"
                                   placeholder="e.g. 071 351 0291">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600 dark:text-slate-300 mb-1">Secondary Cell Override</label>
                            <input class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 py-2 text-sm"
                                   name="phone_secondary" value="{{ old('phone_secondary', $branch->phone_secondary) }}"
                                   placeholder="e.g. 079 495 5994">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs text-slate-600 dark:text-slate-300 mb-1">Fax</label>
                            <input class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 py-2 text-sm"
                                   name="fax" value="{{ old('fax', $branch->fax) }}"
                                   placeholder="e.g. 086 233 2395">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600 dark:text-slate-300 mb-1">Email</label>
                            <input class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 py-2 text-sm"
                                   name="email" value="{{ old('email', $branch->email) }}"
                                   placeholder="e.g. info@hfcoastal.co.za">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-slate-600 dark:text-slate-300 mb-1">Registration No Override</label>
                            <input class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 py-2 text-sm"
                                   name="reg_no" value="{{ old('reg_no', $branch->reg_no) }}"
                                   placeholder="e.g. 2009/228978/23">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600 dark:text-slate-300 mb-1">VAT No Override</label>
                            <input class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 py-2 text-sm"
                                   name="vat_no" value="{{ old('vat_no', $branch->vat_no) }}"
                                   placeholder="e.g. 4870264498">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600 dark:text-slate-300 mb-1">FFC No Override</label>
                            <input class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 py-2 text-sm"
                                   name="ffc_no" value="{{ old('ffc_no', $branch->ffc_no) }}"
                                   placeholder="e.g. FFC40/43916/5">
                        </div>
                        <div>
                            <label class="block text-xs text-slate-600 dark:text-slate-300 mb-1">FIC No Override</label>
                            <input class="w-full rounded-lg border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-slate-100 px-3 py-2 text-sm"
                                   name="fic_no" value="{{ old('fic_no', $branch->fic_no) }}"
                                   placeholder="e.g. 58538">
                        </div>
                    </div>

                    {{-- Logo --}}
                    <div class="border-t border-slate-200 dark:border-slate-800 pt-4 space-y-3">
                        <div class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300">Branch Logo</div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">JPG, PNG, or WebP — max 2 MB. Leave blank to inherit Agency logo.</p>

                        @if($branch->logo_path)
                            <div class="flex items-center gap-4">
                                <img src="{{ asset('storage/' . $branch->logo_path) }}" alt="Branch logo"
                                     class="h-14 rounded border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-900 p-1">
                                <div class="flex items-center gap-2">
                                    <input type="checkbox" name="remove_logo" value="1" id="remove_logo_{{ $branch->id }}" x-model="removelogo"
                                           class="w-4 h-4 rounded border-slate-300 cursor-pointer">
                                    <label for="remove_logo_{{ $branch->id }}" class="text-xs text-slate-600 dark:text-slate-300 cursor-pointer">Remove current logo</label>
                                </div>
                            </div>
                        @endif

                        <div x-show="!removelogo">
                            <input type="file" name="logo" accept="image/jpeg,image/png,image/webp"
                                   class="w-full text-sm text-slate-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:text-white file:cursor-pointer file:bg-slate-700">
                        </div>
                    </div>
                </form>
            @endforeach
        </div>
    </div>

</div>
@endsection
