@extends('layouts.corex')

@section('corex-content')
<div class="w-full max-w-3xl space-y-4" x-data="{ tier: 'tier_1', hasProperty: {{ $property ? 'true' : 'false' }}, propertyId: '{{ $property?->id ?? '' }}' }">

    {{-- Back + header --}}
    <div class="flex items-center gap-4 flex-wrap">
        <a href="{{ route('compliance.whistleblow.index') }}" class="inline-flex items-center gap-1.5 text-sm no-underline" style="color:var(--text-secondary);">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
            Back to Queue
        </a>
    </div>

    <div class="rounded-md p-5" style="background:var(--surface); border:1px solid var(--border);">
        <h1 class="text-lg font-bold" style="color:var(--text-primary);">File a Compliance Report</h1>
        <p class="text-sm mt-1" style="color:var(--text-secondary);">Report a competing agency or practitioner operating without proper compliance documentation.</p>
    </div>

    @if($errors->any())
    <div class="rounded-md p-3 text-sm" style="background:color-mix(in srgb, var(--ds-red) 10%, transparent); color:var(--ds-red);">
        @foreach($errors->all() as $error)
        <p>{{ $error }}</p>
        @endforeach
    </div>
    @endif

    <form method="POST" action="{{ route('compliance.whistleblow.store') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf

        {{-- Tier selection --}}
        <div class="rounded-md p-5" style="background:var(--surface); border:1px solid var(--border);">
            <h3 class="text-xs font-bold uppercase tracking-wider mb-3" style="color:var(--text-muted);">Complaint Type</h3>
            <div class="space-y-3">
                <label class="flex items-start gap-3 cursor-pointer rounded-md p-3 transition-colors" :style="tier === 'tier_1' ? 'background:color-mix(in srgb, var(--brand-default) 6%, transparent); border:1px solid var(--brand-default)' : 'border:1px solid var(--border)'">
                    <input type="radio" name="tier" value="tier_1" x-model="tier" class="mt-0.5">
                    <span>
                        <span class="text-sm font-semibold" style="color:var(--text-primary);">Tier 1 — Paperwork breach (seller confirmed)</span>
                        <span class="block text-xs mt-0.5" style="color:var(--text-muted);">Seller confirms no mandate, FICA, or MDF was signed. Cites PPA §47, §67, FICA §21A.</span>
                    </span>
                </label>
                <label class="flex items-start gap-3 cursor-pointer rounded-md p-3 transition-colors" :style="tier === 'tier_2' ? 'background:color-mix(in srgb, var(--brand-default) 6%, transparent); border:1px solid var(--brand-default)' : 'border:1px solid var(--border)'">
                    <input type="radio" name="tier" value="tier_2" x-model="tier" class="mt-0.5">
                    <span>
                        <span class="text-sm font-semibold" style="color:var(--text-primary);">Tier 2 — No FFC displayed on advert</span>
                        <span class="block text-xs mt-0.5" style="color:var(--text-muted);">Advert missing valid FFC number. Cites PPA §61.</span>
                    </span>
                </label>
                <label class="flex items-start gap-3 cursor-pointer rounded-md p-3 transition-colors" :style="tier === 'tier_3' ? 'background:color-mix(in srgb, var(--brand-default) 6%, transparent); border:1px solid var(--brand-default)' : 'border:1px solid var(--border)'">
                    <input type="radio" name="tier" value="tier_3" x-model="tier" class="mt-0.5">
                    <span>
                        <span class="text-sm font-semibold" style="color:var(--text-primary);">Tier 3 — Unregistered practitioner</span>
                        <span class="block text-xs mt-0.5" style="color:var(--text-muted);">Practitioner not found on PPRA register. Criminal offence under PPA §49.</span>
                    </span>
                </label>
            </div>
        </div>

        {{-- Subject info --}}
        <div class="rounded-md p-5 space-y-4" style="background:var(--surface); border:1px solid var(--border);">
            <h3 class="text-xs font-bold uppercase tracking-wider" style="color:var(--text-muted);">Subject of Complaint</h3>

            <div>
                <label class="text-sm font-medium" style="color:var(--text-primary);">Agency name *</label>
                <input type="text" name="subject_agency_name" value="{{ old('subject_agency_name') }}" required
                       class="mt-1 w-full rounded-md text-sm px-3 py-2" style="background:var(--input-bg); border:1px solid var(--border); color:var(--text-primary);"
                       placeholder="Name of the competing agency">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-medium" style="color:var(--text-primary);">Practitioner name</label>
                    <input type="text" name="subject_practitioner_name" value="{{ old('subject_practitioner_name') }}"
                           class="mt-1 w-full rounded-md text-sm px-3 py-2" style="background:var(--input-bg); border:1px solid var(--border); color:var(--text-primary);"
                           placeholder="If known">
                </div>
                <div>
                    <label class="text-sm font-medium" style="color:var(--text-primary);">FFC number</label>
                    <input type="text" name="subject_ffc_number" value="{{ old('subject_ffc_number') }}"
                           class="mt-1 w-full rounded-md text-sm px-3 py-2" style="background:var(--input-bg); border:1px solid var(--border); color:var(--text-primary);"
                           placeholder="If known">
                </div>
            </div>
        </div>

        {{-- Property --}}
        <div class="rounded-md p-5 space-y-4" style="background:var(--surface); border:1px solid var(--border);">
            <h3 class="text-xs font-bold uppercase tracking-wider" style="color:var(--text-muted);">Property Details</h3>

            <div>
                <label class="text-sm font-medium" style="color:var(--text-primary);">Link to existing property</label>
                <select name="property_id" x-model="propertyId" @change="hasProperty = (propertyId !== '')"
                        class="mt-1 w-full rounded-md text-sm px-3 py-2" style="background:var(--input-bg); border:1px solid var(--border); color:var(--text-primary);">
                    <option value="">-- Not in CoreX (enter address below) --</option>
                    @foreach($properties as $p)
                    <option value="{{ $p->id }}" {{ ($property?->id == $p->id) ? 'selected' : '' }}>{{ $p->address ?? $p->title }} — {{ $p->suburb }}</option>
                    @endforeach
                </select>
            </div>

            <div x-show="!hasProperty" x-cloak>
                <label class="text-sm font-medium" style="color:var(--text-primary);">Property address *</label>
                <input type="text" name="property_address" value="{{ old('property_address', $property?->address) }}"
                       class="mt-1 w-full rounded-md text-sm px-3 py-2" style="background:var(--input-bg); border:1px solid var(--border); color:var(--text-primary);"
                       placeholder="Full street address, suburb, town">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="text-sm font-medium" style="color:var(--text-primary);">Portal URL</label>
                    <input type="url" name="property_portal_url" value="{{ old('property_portal_url') }}"
                           class="mt-1 w-full rounded-md text-sm px-3 py-2" style="background:var(--input-bg); border:1px solid var(--border); color:var(--text-primary);"
                           placeholder="https://www.property24.com/...">
                </div>
                <div>
                    <label class="text-sm font-medium" style="color:var(--text-primary);">Portal</label>
                    <select name="portal_source" class="mt-1 w-full rounded-md text-sm px-3 py-2" style="background:var(--input-bg); border:1px solid var(--border); color:var(--text-primary);">
                        <option value="">Select...</option>
                        <option value="p24" {{ old('portal_source') === 'p24' ? 'selected' : '' }}>Property24</option>
                        <option value="pp" {{ old('portal_source') === 'pp' ? 'selected' : '' }}>Private Property</option>
                        <option value="other" {{ old('portal_source') === 'other' ? 'selected' : '' }}>Other</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Tier 1: Seller info --}}
        <div x-show="tier === 'tier_1'" x-cloak class="rounded-md p-5 space-y-4" style="background:color-mix(in srgb, var(--ds-amber) 4%, var(--surface)); border:1px solid var(--border);">
            <h3 class="text-xs font-bold uppercase tracking-wider" style="color:var(--ds-amber);">Seller Information (Tier 1)</h3>

            <div>
                <label class="text-sm font-medium" style="color:var(--text-primary);">Seller statement *</label>
                <textarea name="seller_statement" rows="4"
                          class="mt-1 w-full rounded-md text-sm px-3 py-2" style="background:var(--input-bg); border:1px solid var(--border); color:var(--text-primary);"
                          placeholder="Capture exactly what the seller told you about the competing agency's listing...">{{ old('seller_statement') }}</textarea>
                <p class="text-xs mt-1" style="color:var(--text-muted);">This statement will appear verbatim in the PPRA complaint PDF.</p>
            </div>

            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="seller_consents_to_named_complaint" value="1" {{ old('seller_consents_to_named_complaint') ? 'checked' : '' }}>
                <span class="text-sm" style="color:var(--text-primary);">Seller consents to being named in the complaint</span>
            </label>
        </div>

        {{-- Notes --}}
        <div class="rounded-md p-5 space-y-4" style="background:var(--surface); border:1px solid var(--border);">
            <h3 class="text-xs font-bold uppercase tracking-wider" style="color:var(--text-muted);">Agent Notes</h3>
            <textarea name="agent_notes" rows="3"
                      class="w-full rounded-md text-sm px-3 py-2" style="background:var(--input-bg); border:1px solid var(--border); color:var(--text-primary);"
                      placeholder="Internal notes — context for the approver (not included in the PPRA complaint PDF)">{{ old('agent_notes') }}</textarea>
        </div>

        {{-- Evidence uploads --}}
        <div class="rounded-md p-5 space-y-4" style="background:var(--surface); border:1px solid var(--border);">
            <h3 class="text-xs font-bold uppercase tracking-wider" style="color:var(--text-muted);">Evidence</h3>
            <div x-show="tier === 'tier_1'" class="text-xs" style="color:var(--text-secondary);">
                A clear seller statement above is the primary evidence. File attachments are optional but recommended where available (call recording, screenshot of the offending advert).
            </div>
            <div x-show="tier === 'tier_2'" x-cloak class="text-xs" style="color:var(--ds-amber);">
                Required: a screenshot of the advert showing the missing FFC number.
            </div>
            <div x-show="tier === 'tier_3'" x-cloak class="text-xs" style="color:var(--ds-amber);">
                Required: a screenshot of the advert AND a screenshot of the PPRA "Find a Property Practitioner" register search showing no result.
            </div>
            <input type="file" name="evidence_files[]" multiple accept="image/*,.pdf,.doc,.docx"
                   class="w-full text-sm" style="color:var(--text-primary);">
        </div>

        {{-- Submit --}}
        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('compliance.whistleblow.index') }}" class="px-4 py-2 rounded-md text-sm font-medium no-underline" style="color:var(--text-secondary);">Cancel</a>
            <button type="submit" class="px-5 py-2.5 rounded-md text-sm font-semibold text-white" style="background:var(--brand-default);">
                Submit Report
            </button>
        </div>
    </form>
</div>
@endsection
