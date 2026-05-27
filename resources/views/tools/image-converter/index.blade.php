{{-- DESIGN SYSTEM COMPLIANCE: UI_DESIGN_SYSTEM.md v 2026-04-20 --}}
@extends('layouts.corex')

@section('corex-content')
<div class="space-y-5">

    {{-- Page header (Pattern A — branded) --}}
    <div class="rounded-md px-6 py-5" style="background: var(--brand-default, #0b2a4a);">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-white leading-tight">Image Converter</h1>
                <p class="text-sm text-white/60">Convert HEIC, JPG, PNG, WEBP, BMP, TIFF or GIF photos into any other image format.</p>
            </div>
        </div>
    </div>

    <div class="max-w-5xl mx-auto w-full">
        @include('tools.pdf-suite._alerts')

        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
            {{-- Left: when to use --}}
            <div class="lg:col-span-2">
                <div class="rounded-md p-6 h-full" style="background: var(--surface); border: 1px solid var(--border);">
                    <div class="flex items-center justify-center w-12 h-12 rounded-md mb-4"
                         style="background: color-mix(in srgb, var(--brand-icon, #0ea5e9) 12%, transparent); color: var(--brand-icon, #0ea5e9);">
                        <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24"
                             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m21 16-4-4-4 4"/><path d="M17 20V12"/>
                            <path d="M3 8l4 4 4-4"/><path d="M7 4v8"/>
                        </svg>
                    </div>
                    <h3 class="font-semibold text-base mb-2" style="color: var(--text-primary);">When to use</h3>
                    <ul class="text-sm space-y-2" style="color: var(--text-secondary);">
                        <li>• Convert iPhone HEIC photos to PNG or JPG</li>
                        <li>• Strip transparency for email attachments (JPG)</li>
                        <li>• Re-encode TIFF or BMP scans to web-friendly PNG</li>
                        <li>• EXIF orientation is auto-corrected</li>
                        <li>• Multiple files are returned as a ZIP</li>
                    </ul>
                </div>
            </div>

            {{-- Right: convert form --}}
            <div class="lg:col-span-3">
                <div class="rounded-md p-6" style="background: var(--surface); border: 1px solid var(--border); border-left: 3px solid var(--brand-icon, #0ea5e9);">
                    <h3 class="font-semibold text-base mb-1" style="color: var(--text-primary);">Convert images</h3>
                    <p class="text-sm mb-5" style="color: var(--text-secondary);">Up to 50 files, 50 MB each.</p>

                    <form method="POST" action="{{ route('tools.image_converter.run') }}" enctype="multipart/form-data"
                          x-data="{ hasFile: false }">
                        @csrf

                        <label class="block text-xs font-semibold uppercase tracking-wide mb-1.5" style="color: var(--text-secondary);">Images</label>
                        <input type="file" name="images[]" accept="image/*,.heic,.heif"
                               multiple required
                               @change="hasFile = $event.target.files.length > 0"
                               class="w-full px-3 py-2.5 rounded-md text-sm mb-5"
                               style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-primary);">

                        <label class="block text-xs font-semibold uppercase tracking-wide mb-1.5" style="color: var(--text-secondary);">Output format</label>
                        <select name="format" required
                                class="w-full px-3 py-2.5 rounded-md text-sm mb-5"
                                style="background: var(--surface-2); border: 1px solid var(--border); color: var(--text-primary);">
                            <option value="png" selected>PNG — lossless, supports transparency</option>
                            <option value="jpg">JPG — smaller, no transparency</option>
                            <option value="webp">WEBP — modern, compact</option>
                        </select>

                        <button type="submit"
                                :disabled="!hasFile"
                                :class="hasFile ? 'corex-btn-primary' : 'opacity-50 cursor-not-allowed corex-btn-primary'"
                                class="text-sm w-full">
                            Convert &amp; Download
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
