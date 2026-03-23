@extends('layouts.corex')

@section('corex-content')
<div class="max-w-6xl mx-auto px-6 py-8">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">My E-Sign Documents</h1>
            <p class="text-sm text-slate-500 mt-1">Track all your e-sign flows, signing progress, and approvals.</p>
        </div>
        <a href="{{ route('docuperfect.esign.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
            New E-Sign
        </a>
    </div>

    {{-- Pending approval banner --}}
    @if($pendingApprovalCount > 0)
    <div class="rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 mb-6 flex items-center gap-3">
        <svg class="w-6 h-6 text-amber-500 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
        </svg>
        <div>
            <span class="text-sm font-semibold text-amber-800">{{ $pendingApprovalCount }} document{{ $pendingApprovalCount > 1 ? 's' : '' }} pending your approval</span>
            <span class="text-sm text-amber-600 ml-1">— a signer has completed, review and advance the signing flow.</span>
        </div>
    </div>
    @endif

    {{-- Documents table --}}
    @if($templates->isEmpty())
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm p-12 text-center">
        <svg class="w-12 h-12 text-slate-300 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
        </svg>
        <h3 class="text-lg font-semibold text-slate-600 mb-2">No e-sign documents yet</h3>
        <p class="text-sm text-slate-400 mb-4">Create your first e-sign flow to get started.</p>
        <a href="{{ route('docuperfect.esign.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">
            Create E-Sign Document
        </a>
    </div>
    @else
    <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Document</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Status</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Signing Progress</th>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Created</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($templates as $tpl)
                @php
                    $doc = $tpl->document;
                    $totalRequests = $tpl->requests->count();
                    $completedRequests = $tpl->requests->where('status', 'completed')->count();
                    $statusColors = [
                        'draft' => 'bg-slate-100 text-slate-600',
                        'ready' => 'bg-blue-100 text-blue-700',
                        'signing' => 'bg-indigo-100 text-indigo-700',
                        'pending_agent_approval' => 'bg-amber-100 text-amber-700',
                        'completed' => 'bg-emerald-100 text-emerald-700',
                        'expired' => 'bg-red-100 text-red-600',
                        'declined' => 'bg-red-100 text-red-600',
                        'rejected' => 'bg-red-100 text-red-600',
                        'partial' => 'bg-yellow-100 text-yellow-700',
                        'amendment_review' => 'bg-purple-100 text-purple-700',
                    ];
                    $statusLabels = [
                        'draft' => 'Draft',
                        'ready' => 'Ready',
                        'signing' => 'Signing',
                        'pending_agent_approval' => 'Needs Approval',
                        'awaiting_tenant' => 'Awaiting Tenant',
                        'awaiting_landlord' => 'Awaiting Landlord',
                        'awaiting_buyer' => 'Awaiting Buyer',
                        'awaiting_seller' => 'Awaiting Seller',
                        'awaiting_supervisor' => 'Awaiting Supervisor',
                        'awaiting_supervisor_final' => 'Final Review',
                        'returned_to_candidate' => 'Returned',
                        'completed' => 'Completed',
                        'expired' => 'Expired',
                        'declined' => 'Declined',
                        'rejected' => 'Rejected',
                        'partial' => 'Partial',
                        'awaiting_deferred' => 'Awaiting Deferred',
                        'amendment_review' => 'Amendment Review',
                    ];
                    $colorClass = $statusColors[$tpl->status] ?? 'bg-slate-100 text-slate-600';
                    $label = $statusLabels[$tpl->status] ?? ucfirst(str_replace('_', ' ', $tpl->status));
                @endphp
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-5 py-3.5">
                        <div class="text-sm font-medium text-slate-800">{{ $doc->name ?? 'Untitled' }}</div>
                        @if($doc && $doc->template)
                        <div class="text-xs text-slate-400 mt-0.5">{{ $doc->template->name ?? '' }}</div>
                        @endif
                    </td>
                    <td class="px-5 py-3.5">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $colorClass }}">
                            {{ $label }}
                        </span>
                    </td>
                    <td class="px-5 py-3.5">
                        @if($totalRequests > 0)
                        <div class="flex items-center gap-2">
                            <div class="w-20 h-2 bg-slate-200 rounded-full overflow-hidden">
                                <div class="h-full bg-emerald-500 rounded-full" style="width: {{ $totalRequests > 0 ? round(($completedRequests / $totalRequests) * 100) : 0 }}%"></div>
                            </div>
                            <span class="text-xs text-slate-500">{{ $completedRequests }}/{{ $totalRequests }}</span>
                        </div>
                        @else
                        <span class="text-xs text-slate-400">No signers</span>
                        @endif
                    </td>
                    <td class="px-5 py-3.5">
                        <span class="text-sm text-slate-500">{{ $tpl->created_at->format('d M Y') }}</span>
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <div class="flex items-center justify-end gap-2">
                            @if($tpl->status === 'pending_agent_approval' && $doc)
                            <a href="{{ route('docuperfect.signatures.review', $doc->id) }}"
                               class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg bg-amber-600 text-white hover:bg-amber-700 transition-colors">
                                Review
                            </a>
                            @elseif($doc)
                            <a href="{{ route('docuperfect.signatures.review', $doc->id) }}"
                               class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-medium rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 transition-colors">
                                View
                            </a>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="mt-4">
        {{ $templates->links() }}
    </div>
    @endif
</div>
@endsection
