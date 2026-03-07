@extends('layouts.corex')

@section('corex-content')
<div class="-m-4 lg:-m-6">
    <x-page-header title="Document Signed" :flush="true" back-route="{{ route('docuperfect.esign.create') }}" back-label="Back to E-Sign" />
    <div class="p-4 lg:p-6">
        <div class="max-w-lg mx-auto text-center py-12">
            <div class="w-16 h-16 mx-auto mb-6 rounded-full bg-emerald-100 flex items-center justify-center">
                <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
            </div>

            <h2 class="text-2xl font-bold text-gray-900 mb-2">Document Signed!</h2>

            <p class="text-gray-600 mb-6">
                You have successfully signed
                <span class="font-semibold">{{ $template->name ?? 'the document' }}</span>.
            </p>

            @if($nextRecipient)
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6 text-left">
                    <div class="text-sm font-semibold text-blue-800 mb-1">Next: Sent for signature</div>
                    <div class="text-sm text-blue-700">
                        Sent to <span class="font-medium">{{ $nextRecipient['name'] ?? 'Unknown' }}</span>
                        ({{ ucfirst($nextRecipient['role'] ?? 'signer') }})
                        @if(!empty($nextRecipient['email']))
                            at {{ $nextRecipient['email'] }}
                        @endif
                    </div>
                </div>
            @endif

            @if($document)
                <div class="flex items-center justify-center gap-3">
                    <a href="{{ route('docuperfect.signatures.audit', ['document' => $document->id]) }}"
                       class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition">
                        View Audit Trail
                    </a>
                    <a href="{{ route('docuperfect.esign.create') }}"
                       class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">
                        Create Another
                    </a>
                </div>
            @else
                <a href="{{ route('docuperfect.esign.create') }}"
                   class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition">
                    Create Another
                </a>
            @endif
        </div>
    </div>
</div>
@endsection
