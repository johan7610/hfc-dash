{{-- DESIGN SYSTEM COMPLIANCE: UI_DESIGN_SYSTEM.md v 2026-04-20 --}}
@extends('layouts.corex')

@section('corex-content')
<div class="w-full space-y-5">
    <div class="rounded-md px-6 py-5 corex-page-banner">
        <h1 class="text-base font-bold leading-tight" style="color: var(--text-primary);">Rental Application Settings</h1>
        <p class="text-xs" style="color: var(--text-muted);">
            The supporting-document checklist shown per employment type. Nothing here is ever
            enforced at submission — it only drives what shows as outstanding on a returned application.
        </p>
    </div>

    @if(session('success'))
        <div class="rounded-md px-4 py-3 text-sm" style="background: var(--ds-emerald-soft, #ecfdf5); color: var(--ds-emerald, #059669);">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('corex.settings.rental-applications.update') }}" class="space-y-4">
        @csrf

        @foreach(\App\Models\RentalApplication::EMPLOYMENT_TYPES as $type)
        <div class="rounded-md p-4" style="background: var(--surface); border: 1px solid var(--border);">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold" style="color: var(--text-primary);">{{ str_replace('_', ' ', ucfirst($type)) }}</h2>
                @if($isConfigured[$type])
                    <span class="ds-badge ds-badge-info">Saved{{ empty($checklists[$type]) ? ' — none required' : '' }}</span>
                @else
                    <span class="ds-badge ds-badge-default" title="Showing the standard checklist — not yet saved for this agency">Default (not yet saved)</span>
                @endif
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                @foreach($documentTypes as $dt)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="checklists[{{ $type }}][]" value="{{ $dt->id }}"
                               @checked(in_array($dt->id, $checklists[$type]))>
                        {{ $dt->label }}
                    </label>
                @endforeach
            </div>
        </div>
        @endforeach

        <div class="flex justify-end">
            <button type="submit" class="corex-btn-primary text-xs">Save Checklist</button>
        </div>
    </form>

    {{-- AT-392 Phase 2 — Johan: "qualifying formula - agency can set this."
         Separate <form>/route so this save can never interfere with the
         checklist form above. --}}
    <div class="rounded-md p-4" style="background: var(--surface); border: 1px solid var(--border);">
        <h2 class="text-sm font-semibold mb-1" style="color: var(--text-primary);">Qualifying Formula</h2>
        <p class="text-xs mb-3" style="color: var(--text-muted);">
            Used on the application review screen to suggest whether a tenant's stated income
            covers the rent — a prompt for the agent to look closer, never a rule that blocks or
            decides anything.
        </p>
        <form method="POST" action="{{ route('corex.settings.rental-applications.qualifying-formula') }}" class="flex items-end gap-3">
            @csrf
            <div>
                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">
                    Gross monthly income must be at least this many times the rent
                </label>
                <input type="number" name="income_to_rent_multiplier" step="0.01" min="0.1" max="99.99"
                       value="{{ old('income_to_rent_multiplier', $qualifyingMultiplier) }}"
                       class="corex-input text-sm" style="width: 100px;">
            </div>
            <button type="submit" class="corex-btn-primary text-xs">Save Formula</button>
        </form>
    </div>

    {{-- AT-392 authoriser flow, 2026-09-08 — Johan: "each agency will want
         their own wording on declined." A suggested default ships until the
         agency saves their own — same forAgency()-never-writes-on-read
         pattern as Qualifying Formula above. Merge fields are limited to
         what this email can always honestly populate — applicant name,
         agency name, and (optionally) the property the application was
         for — no invented "how to improve" guidance (Johan was explicit
         that part is still an open idea, not settled). --}}
    <div class="rounded-md p-4" style="background: var(--surface); border: 1px solid var(--border);">
        <h2 class="text-sm font-semibold mb-1" style="color: var(--text-primary);">Decline Email</h2>
        <p class="text-xs mb-3" style="color: var(--text-muted);">
            Sent to the applicant if the authoriser declines their application. A suggested wording
            is shown below — edit it to your own. Available merge fields:
            <code>@{{applicant_name}}</code>, <code>@{{agency_name}}</code>,
            <code>@{{property_reference}}</code> (optional — resolves to nothing if the
            application has no property linked).
        </p>
        <form method="POST" action="{{ route('corex.settings.rental-applications.decline-email') }}" class="space-y-3">
            @csrf
            <div>
                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Subject</label>
                <input type="text" name="subject" value="{{ old('subject', $declineEmail['subject']) }}" class="corex-input text-sm w-full">
            </div>
            <div>
                <label class="block text-xs font-medium mb-1" style="color: var(--text-secondary);">Body</label>
                <textarea name="body" rows="10" class="corex-input text-sm w-full">{{ old('body', $declineEmail['body']) }}</textarea>
            </div>
            <button type="submit" class="corex-btn-primary text-xs">Save Decline Email</button>
        </form>
    </div>

    {{-- AT-392 authoriser flow, 2026-09-08 — Johan, verbatim: "there like on
         esign needs to be the ro then co approval process? so admin or bm
         acts like the co. selected agents act as ro... ro can approve /
         decline. but then lets say the tenant speaks to admin and they
         decide they want to override ro, then can approve / decline with
         reasons given... like an admin override. Both configured as agency
         settings, multi-select from users, exactly like the existing CO and
         RO settings." Copied precisely from settings.blade.php's "Section B:
         MLROs / Reporting Officers" (FICA) — checkboxes over $agencyUsers,
         name="..._user_ids[]", one form per tier, same as MLRO's
         mlro_user_ids[] shape. Deliberately NOT fica_officer_appointments'
         dated-appointment table — no legal appointment-history requirement
         here, just "who currently holds this tier" (see
         .ai/specs/rental-applications.md for the full reasoning). --}}
    <div class="rounded-md p-4" style="background: var(--surface); border: 1px solid var(--border);">
        <h2 class="text-sm font-semibold mb-1" style="color: var(--text-primary);">RO — Application Reviewers</h2>
        <p class="text-xs mb-3" style="color: var(--text-muted);">
            These users can approve, decline, or request more information on an application
            an agent has submitted for approval.
        </p>
        <form method="POST" action="{{ route('corex.settings.rental-applications.ro') }}">
            @csrf
            <div class="space-y-1 max-h-48 overflow-y-auto mb-3 rounded-md p-2" style="border: 1px solid var(--border); background: var(--surface);">
                @forelse($agencyUsers as $u)
                    <label class="flex items-center gap-2 py-1 px-1 text-sm cursor-pointer hover:bg-[color:var(--surface-2)] rounded">
                        <input type="checkbox" name="rental_application_ro_user_ids[]" value="{{ $u->id }}"
                               {{ in_array($u->id, $roUserIds) ? 'checked' : '' }} style="accent-color: var(--brand-button, #0ea5e9);">
                        <span style="color: var(--text-primary);">{{ $u->name }}</span>
                        <span class="text-xs" style="color: var(--text-muted);">{{ $u->role }}</span>
                    </label>
                @empty
                    <p class="text-xs px-1 py-1" style="color: var(--text-muted);">No active users in this agency.</p>
                @endforelse
            </div>
            <button type="submit" class="corex-btn-primary text-xs">Save Reviewers</button>
        </form>
    </div>

    <div class="rounded-md p-4" style="background: var(--surface); border: 1px solid var(--border);">
        <h2 class="text-sm font-semibold mb-1" style="color: var(--text-primary);">CO — Overrides</h2>
        <p class="text-xs mb-3" style="color: var(--text-muted);">
            Typically an admin or branch manager. These users can do everything a Reviewer can, and
            can also OVERRIDE a Reviewer's existing decision (change an approve to a decline, or a
            decline to an approve) — a reason is required whenever they do.
        </p>
        <form method="POST" action="{{ route('corex.settings.rental-applications.co') }}">
            @csrf
            <div class="space-y-1 max-h-48 overflow-y-auto mb-3 rounded-md p-2" style="border: 1px solid var(--border); background: var(--surface);">
                @forelse($agencyUsers as $u)
                    <label class="flex items-center gap-2 py-1 px-1 text-sm cursor-pointer hover:bg-[color:var(--surface-2)] rounded">
                        <input type="checkbox" name="rental_application_co_user_ids[]" value="{{ $u->id }}"
                               {{ in_array($u->id, $coUserIds) ? 'checked' : '' }} style="accent-color: var(--brand-button, #0ea5e9);">
                        <span style="color: var(--text-primary);">{{ $u->name }}</span>
                        <span class="text-xs" style="color: var(--text-muted);">{{ $u->role }}</span>
                    </label>
                @empty
                    <p class="text-xs px-1 py-1" style="color: var(--text-muted);">No active users in this agency.</p>
                @endforelse
            </div>
            <button type="submit" class="corex-btn-primary text-xs">Save Overrides</button>
        </form>
    </div>
</div>
@endsection
