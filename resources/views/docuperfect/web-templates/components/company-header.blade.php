{{-- Company Header — reusable across all web document templates --}}
@php
    $logoUrl = $logo_url ?? null;
    if (!$logoUrl) {
        $agency = \App\Models\Agency::where('slug', 'hfc-coastal')->first();
        if ($agency && $agency->logo_path) {
            $logoUrl = asset('storage/' . $agency->logo_path);
        }
    }
@endphp
<div class="company-header">
    <div class="header-left">
        @if($logoUrl)
            <img src="{{ $logoUrl }}" alt="Home Finders Coastal" class="header-logo">
        @endif
        <div class="trading-name">Johan and Elize Properties T/A</div>
        <div class="company-name">HOME FINDERS COASTAL</div>
        <div class="tagline">THE MANDATE COMPANY</div>
    </div>
    <div class="header-right">
        <div class="header-address">Shop 5 The Emporium, cnr King Rd &amp; Marine Drive, Shelly Beach</div>
        <table class="header-details">
            <tr><td>Reg No:</td><td>{{ $reg_no ?? '2009/228978/23' }}</td></tr>
            <tr><td>Vat No:</td><td>{{ $vat_no ?? '4870264498' }}</td></tr>
            <tr><td>FFC No:</td><td>{{ $ffc_no ?? 'FFC40/43916/5' }}</td></tr>
            <tr><td>FIC No:</td><td>{{ $fic_no ?? '58538' }}</td></tr>
        </table>
        <div class="header-contact">
            <span>Fax: 086 233 2395</span>
            <span>Email: info@hfcoastal.co.za</span>
            <span>Cell: 079 495 5994</span>
        </div>
    </div>
</div>
