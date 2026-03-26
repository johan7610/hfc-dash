{{-- Person Completing Form --}}
<div class="bg-white border border-slate-200 p-5">
    <h3 class="text-sm font-bold text-slate-900 mb-3 pb-2 border-b border-teal-500">Person Completing Form</h3>
    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
        <div><dt class="text-slate-400 text-xs">Full Name</dt><dd class="text-slate-900 font-medium">{{ $personal['full_name'] ?? '—' }}</dd></div>
        <div><dt class="text-slate-400 text-xs">ID / Passport</dt><dd class="text-slate-900 font-medium">{{ $personal['id_number'] ?? '—' }}</dd></div>
        <div><dt class="text-slate-400 text-xs">SA Citizen/Resident</dt><dd class="text-slate-900">{{ ucfirst($personal['sa_citizen'] ?? '—') }}</dd></div>
        <div><dt class="text-slate-400 text-xs">Phone</dt><dd class="text-slate-900">{{ $personal['phone'] ?? '—' }}</dd></div>
        <div><dt class="text-slate-400 text-xs">Email</dt><dd class="text-slate-900">{{ $personal['email'] ?? '—' }}</dd></div>
        @if(!empty($personal['tax_number']))
        <div><dt class="text-slate-400 text-xs">Tax Number</dt><dd class="text-slate-900">{{ $personal['tax_number'] }}</dd></div>
        @endif
        <div class="col-span-2"><dt class="text-slate-400 text-xs">Residential Address</dt><dd class="text-slate-900">{{ $personal['residential_address'] ?? '—' }}</dd></div>
    </dl>
</div>

{{-- Entity Details --}}
@if($submission->entity_type !== 'natural')
<div class="bg-white border border-slate-200 p-5">
    <h3 class="text-sm font-bold text-slate-900 mb-3 pb-2 border-b border-teal-500">
        {{ ['company' => 'Company / CC', 'trust' => 'Trust', 'partnership' => 'Partnership'][$submission->entity_type] ?? 'Entity' }} Details
    </h3>

    @if($submission->entity_type === 'company')
    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
        <div><dt class="text-slate-400 text-xs">Company Name</dt><dd class="text-slate-900 font-medium">{{ $entity['company_name'] ?? '—' }}</dd></div>
        <div><dt class="text-slate-400 text-xs">Registration No</dt><dd class="text-slate-900">{{ $entity['company_reg_number'] ?? '—' }}</dd></div>
        <div class="col-span-2"><dt class="text-slate-400 text-xs">SA Presence</dt><dd class="text-slate-900">{{ $entity['company_sa_presence'] ?? '—' }}</dd></div>
        @if(!empty($entity['company_stock_exchange']))<div><dt class="text-slate-400 text-xs">Stock Exchange</dt><dd class="text-slate-900">{{ $entity['company_stock_exchange'] }}</dd></div>@endif
        <div class="col-span-2"><dt class="text-slate-400 text-xs">Registered Address</dt><dd class="text-slate-900">{{ $entity['company_address'] ?? '—' }}</dd></div>
        <div class="col-span-2"><dt class="text-slate-400 text-xs">Authority to Act</dt><dd class="text-slate-900">{{ $entity['company_authority_source'] ?? '—' }}</dd></div>
        <div class="col-span-2"><dt class="text-slate-400 text-xs">Business Description</dt><dd class="text-slate-900">{{ $entity['company_business_description'] ?? '—' }}</dd></div>
        <div class="col-span-2"><dt class="text-slate-400 text-xs">Ownership Structure</dt><dd class="text-slate-900">{{ $entity['company_ownership_structure'] ?? '—' }}</dd></div>
    </dl>
    @if(!empty($entity['beneficial_owners']))
        <div class="mt-3 pt-3 border-t border-slate-100">
            <p class="text-xs font-semibold text-slate-700 mb-2">Beneficial Owners:</p>
            @foreach($entity['beneficial_owners'] as $bo)
            <div class="text-xs text-slate-600 mb-1">{{ $bo['name'] ?? '' }} — ID: {{ $bo['id_number'] ?? '' }} — {{ $bo['address'] ?? '' }}</div>
            @endforeach
        </div>
    @endif
    @endif

    @if($submission->entity_type === 'trust')
    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
        <div><dt class="text-slate-400 text-xs">Trust Name</dt><dd class="text-slate-900 font-medium">{{ $entity['trust_name'] ?? '—' }}</dd></div>
        <div><dt class="text-slate-400 text-xs">Master's Ref</dt><dd class="text-slate-900">{{ $entity['trust_master_ref'] ?? '—' }}</dd></div>
        <div class="col-span-2"><dt class="text-slate-400 text-xs">Authority to Act</dt><dd class="text-slate-900">{{ $entity['trust_authority_source'] ?? '—' }}</dd></div>
        <div class="col-span-2"><dt class="text-slate-400 text-xs">Trust Purpose</dt><dd class="text-slate-900">{{ $entity['trust_purpose'] ?? '—' }}</dd></div>
    </dl>
    <div class="mt-3 pt-3 border-t border-slate-100 text-xs">
        <p class="font-semibold text-slate-700 mb-1">Donor: {{ $entity['donor_name'] ?? '' }} — ID: {{ $entity['donor_id_number'] ?? '' }}</p>
    </div>
    @if(!empty($entity['trustees']))
        <div class="mt-2 pt-2 border-t border-slate-100"><p class="text-xs font-semibold text-slate-700 mb-1">Trustees:</p>
        @foreach($entity['trustees'] as $tr)<div class="text-xs text-slate-600 mb-1">{{ $tr['name'] ?? '' }} — ID: {{ $tr['id_number'] ?? '' }}</div>@endforeach</div>
    @endif
    @if(!empty($entity['beneficiaries']))
        <div class="mt-2 pt-2 border-t border-slate-100"><p class="text-xs font-semibold text-slate-700 mb-1">Beneficiaries:</p>
        @foreach($entity['beneficiaries'] as $bn)<div class="text-xs text-slate-600 mb-1">{{ $bn['name'] ?? '' }} — ID: {{ $bn['id_number'] ?? '' }}</div>@endforeach</div>
    @endif
    @endif

    @if($submission->entity_type === 'partnership')
    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
        <div><dt class="text-slate-400 text-xs">Partnership Name</dt><dd class="text-slate-900 font-medium">{{ $entity['partnership_name'] ?? '—' }}</dd></div>
        <div class="col-span-2"><dt class="text-slate-400 text-xs">Business Description</dt><dd class="text-slate-900">{{ $entity['partnership_business_description'] ?? '—' }}</dd></div>
    </dl>
    @if(!empty($entity['partners']))
        <div class="mt-3 pt-3 border-t border-slate-100"><p class="text-xs font-semibold text-slate-700 mb-1">Partners:</p>
        @foreach($entity['partners'] as $pt)<div class="text-xs text-slate-600 mb-1">{{ $pt['name'] ?? '' }} — ID: {{ $pt['id_number'] ?? '' }}</div>@endforeach</div>
    @endif
    @endif
</div>
@endif

{{-- Principal & Representative --}}
@if($submission->entity_type === 'natural' && ($principalData['acting_on_behalf'] ?? '') === 'yes')
<div class="bg-white border border-slate-200 p-5">
    <h3 class="text-sm font-bold text-slate-900 mb-3 pb-2 border-b border-teal-500">Principal (Acting on Behalf)</h3>
    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
        <div><dt class="text-slate-400 text-xs">Full Name</dt><dd class="text-slate-900 font-medium">{{ $principalData['full_name'] ?? '—' }}</dd></div>
        <div><dt class="text-slate-400 text-xs">ID / Passport</dt><dd class="text-slate-900">{{ $principalData['id_number'] ?? '—' }}</dd></div>
        <div class="col-span-2"><dt class="text-slate-400 text-xs">Address</dt><dd class="text-slate-900">{{ $principalData['residential_address'] ?? '—' }}</dd></div>
    </dl>
</div>
@endif

{{-- Service & Payment --}}
<div class="bg-white border border-slate-200 p-5">
    <h3 class="text-sm font-bold text-slate-900 mb-3 pb-2 border-b border-teal-500">Service & Payment</h3>
    <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
        <div><dt class="text-slate-400 text-xs">Purpose</dt><dd class="text-slate-900">{{ $service['transaction_purpose'] ?? '—' }}{{ !empty($service['purpose_other']) ? ': ' . $service['purpose_other'] : '' }}</dd></div>
        <div><dt class="text-slate-400 text-xs">Cash Over R50,000</dt><dd class="text-slate-900 font-semibold {{ ($service['cash_over_50k'] ?? '') === 'yes' ? 'text-red-600' : '' }}">{{ ucfirst($service['cash_over_50k'] ?? '—') }}</dd></div>
        <div class="col-span-2"><dt class="text-slate-400 text-xs">Payment Method</dt><dd class="text-slate-900">{{ $service['payment_method'] ?? '—' }}</dd></div>
    </dl>
</div>

{{-- PEP --}}
<div class="bg-white border border-slate-200 p-5">
    <h3 class="text-sm font-bold text-slate-900 mb-3 pb-2 border-b border-teal-500">Politically Exposed Person</h3>
    @php
        $foreignPep = $pepData['foreign_pep'] ?? [];
        $domesticPep = $pepData['domestic_pep'] ?? [];
        $hasPep = !empty($foreignPep) || !empty($domesticPep) || ($pepData['is_family_associate'] ?? '') === 'yes';
    @endphp
    @if(!empty($foreignPep))
    <div class="mb-2"><p class="text-xs font-semibold text-red-600">Foreign PEP:</p>
    @foreach($foreignPep as $pos)<span class="inline-block px-2 py-0.5 bg-red-50 text-red-700 text-xs mr-1 mb-1">{{ str_replace('_', ' ', ucfirst($pos)) }}</span>@endforeach</div>
    @endif
    @if(!empty($domesticPep))
    <div class="mb-2"><p class="text-xs font-semibold text-red-600">Domestic PEP:</p>
    @foreach($domesticPep as $pos)<span class="inline-block px-2 py-0.5 bg-red-50 text-red-700 text-xs mr-1 mb-1">{{ str_replace('_', ' ', ucfirst($pos)) }}</span>@endforeach</div>
    @endif
    @if(!empty($pepData['source_of_wealth']))
    <div class="mt-2 text-sm"><dt class="text-slate-400 text-xs">Source of Wealth</dt><dd class="text-slate-900">{{ $pepData['source_of_wealth'] }}</dd></div>
    @endif
    @if(!$hasPep)<p class="text-emerald-600 text-sm font-medium">No PEP indicators</p>@endif
</div>

{{-- Documents --}}
<div class="bg-white border border-slate-200 p-5">
    <h3 class="text-sm font-bold text-slate-900 mb-3 pb-2 border-b border-teal-500">Uploaded Documents</h3>
    @forelse($submission->documents as $doc)
        <div class="flex items-center justify-between py-2 {{ !$loop->last ? 'border-b border-slate-100' : '' }}">
            <div>
                <span class="text-xs font-semibold text-slate-500 uppercase">{{ $doc->document_type_label }}</span>
                <p class="text-sm text-slate-900">{{ $doc->file_name }}</p>
                <p class="text-xs text-slate-400">{{ number_format($doc->file_size / 1024) }} KB</p>
            </div>
            <a href="{{ Storage::url($doc->file_path) }}" target="_blank" class="text-teal-600 hover:text-teal-800 text-xs font-medium">View</a>
        </div>
    @empty
        <p class="text-slate-400 text-sm">No documents uploaded.</p>
    @endforelse
</div>

{{-- Signature --}}
@if($submission->signature_data)
<div class="bg-white border border-slate-200 p-5">
    <h3 class="text-sm font-bold text-slate-900 mb-3 pb-2 border-b border-teal-500">Electronic Signature</h3>
    <img src="{{ $submission->signature_data }}" alt="Recipient Signature" style="max-height: 120px; border: 1px solid #e2e8f0; padding: 0.5rem; background: #fff;">
    <p class="text-xs text-slate-400 mt-1">Signed at: {{ $declData['signed_at_location'] ?? '' }} — {{ $submission->signed_at?->format('d M Y H:i') }}</p>
</div>
@endif
