@extends('layouts.corex-app')

@section('corex-content')
<div class="p-6 lg:p-8 max-w-4xl">
    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
        <div>
            <a href="{{ route('compliance.fica.index') }}" class="text-sm text-slate-500 hover:text-slate-700 mb-2 inline-block">&larr; Back to Compliance</a>
            <h1 class="text-2xl font-bold text-slate-900">Risk Management and Compliance Programme</h1>
            <p class="text-sm text-slate-500 mt-1">{{ auth()->user()->effectiveAgencyId() ? \App\Models\Agency::find(auth()->user()->effectiveAgencyId())?->name : 'Home Finders Coastal' }} — FICA Compliance</p>
        </div>
        <div class="flex items-center gap-2 mt-3 sm:mt-0">
            <button onclick="window.print()" class="inline-flex items-center gap-1.5 px-3 py-2 border border-slate-300 text-slate-700 text-sm font-semibold hover:bg-slate-50 transition">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.829c-.24.03-.48.062-.72.096m.72-.096a42.415 42.415 0 0 1 10.56 0m-10.56 0L6.34 18m10.94-4.171c.24.03.48.062.72.096m-.72-.096L17.66 18m0 0 .229 2.523a1.125 1.125 0 0 1-1.12 1.227H7.231c-.662 0-1.18-.568-1.12-1.227L6.34 18m11.318 0h1.091A2.25 2.25 0 0 0 21 15.75V9.456c0-1.081-.768-2.015-1.837-2.175a48.055 48.055 0 0 0-1.913-.247M6.34 18H5.25A2.25 2.25 0 0 1 3 15.75V9.456c0-1.081.768-2.015 1.837-2.175a48.041 48.041 0 0 1 1.913-.247m0 0a48.1 48.1 0 0 1 10.5 0m-10.5 0V5.625A2.625 2.625 0 0 1 9.875 3h4.25a2.625 2.625 0 0 1 2.625 2.625v3.18" /></svg>
                Print
            </button>
        </div>
    </div>

    {{-- RMCP Document --}}
    <div class="bg-white border border-slate-200 rmcp-doc">
        <style>
            .rmcp-doc { font-size: 0.9375rem; color: #334155; line-height: 1.7; }
            .rmcp-doc .rmcp-cover { background: #0f172a; color: #fff; padding: 3rem 2.5rem; text-align: center; }
            .rmcp-doc .rmcp-cover h1 { font-size: 1.5rem; font-weight: 700; margin: 0 0 0.5rem; }
            .rmcp-doc .rmcp-cover p { font-size: 0.875rem; color: #94a3b8; margin: 0.25rem 0; }
            .rmcp-doc .rmcp-body { padding: 2.5rem; }
            .rmcp-doc .rmcp-section { margin-bottom: 2rem; }
            .rmcp-doc .rmcp-section h2 { font-size: 1.125rem; font-weight: 700; color: #0f172a; border-bottom: 2px solid #0d9488; padding-bottom: 0.375rem; margin-bottom: 0.75rem; }
            .rmcp-doc .rmcp-section h3 { font-size: 1rem; font-weight: 600; color: #1e293b; margin: 1rem 0 0.5rem; }
            .rmcp-doc .rmcp-placeholder { background: #f8fafc; border: 1px dashed #cbd5e1; padding: 1rem; color: #94a3b8; font-size: 0.8125rem; font-style: italic; }
            .rmcp-doc .rmcp-toc { list-style: none; padding: 0; }
            .rmcp-doc .rmcp-toc li { padding: 0.375rem 0; border-bottom: 1px solid #f1f5f9; font-size: 0.875rem; }
            .rmcp-doc .rmcp-toc li span { color: #64748b; float: right; }
            .rmcp-doc .rmcp-schedule { background: #f1f5f9; border-left: 3px solid #0d9488; padding: 1rem 1.25rem; margin: 0.75rem 0; }
            @media print {
                .rmcp-doc { border: none !important; }
                .rmcp-doc .rmcp-cover { break-after: page; }
            }
        </style>

        {{-- Cover --}}
        <div class="rmcp-cover">
            <p style="color: #0d9488; font-weight: 600; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 2px;">Financial Intelligence Centre Act 38 of 2001</p>
            <h1>Risk Management and Compliance Programme</h1>
            <p>Prepared in terms of Section 42 of the FIC Act</p>
            <p style="margin-top: 1.5rem; color: #cbd5e1;">Version 1.0 — {{ now()->format('F Y') }}</p>
        </div>

        <div class="rmcp-body">
            {{-- Table of Contents --}}
            <div class="rmcp-section">
                <h2>Table of Contents</h2>
                <ol class="rmcp-toc">
                    @foreach([
                        'Definitions', 'Introduction', 'Our Business Services',
                        'Level of Risk as Accountable Institution', 'What Are Our Risks',
                        'Who Must We Apply These Rules To', 'Compliance with Section 20A',
                        'Establishment and Verification of Identity', 'Ongoing Due Diligence',
                        'Additional Due Diligence — Legal Persons, Trusts, Partnerships',
                        'Complex or Unusually Large Transactions', 'Unusual Patterns',
                        'Identification of Clients and Basic CDD',
                        'Additional Requirements — Legal Persons',
                        'Additional Requirements — Partnerships',
                        'Additional Requirements — Trusts',
                        'Retention of Records', 'Duty to Report Suspicious Transactions',
                        'Inability to Conduct Due Diligence',
                        'Foreign & Domestic Prominent Persons',
                        'Different Levels of CDD',
                        'Co-operation with Other Accountable Institutions',
                        'How to Make Reports', 'Implementation',
                        'FICA Compliance Officer', 'Offences and Penalties',
                        'Reassessment of Risk',
                    ] as $i => $title)
                        <li>{{ $i + 1 }}. {{ $title }} <span>{{ $i + 3 }}</span></li>
                    @endforeach
                </ol>
            </div>

            {{-- Sections --}}
            @foreach([
                ['Definitions', 'Key terms used throughout this RMCP, including definitions from the FIC Act, Regulations, and PPRA guidelines.'],
                ['Introduction', 'Overview of the agency\'s obligations under the Financial Intelligence Centre Act 38 of 2001 as amended, including the General Laws (Anti-Money Laundering and Combating of Terrorism Financing) Amendment Act.'],
                ['Our Business Services', 'Description of the property practitioner services offered by the agency: sales, rentals, property management, commercial property, and related advisory services.'],
                ['Level of Risk as Accountable Institution', 'Assessment of the agency\'s risk profile as an accountable institution under Schedule 1 of the FIC Act, including inherent risks associated with property transactions.'],
                ['What Are Our Risks', 'Identification of money laundering and terrorist financing risks specific to property transactions, including cash-intensive deals, cross-border transactions, and complex ownership structures.'],
                ['Who Must We Apply These Rules To', 'Identification of all clients and prospective clients to whom CDD obligations apply, including buyers, sellers, landlords, tenants, and other parties to property transactions.'],
                ['Compliance with Section 20A', 'Procedures for screening clients against the United Nations Security Council sanctions list and the FIC\'s Targeted Financial Sanctions (TFS) list.'],
                ['Establishment and Verification of Identity', 'Procedures for verifying the identity of natural persons, including acceptable forms of identification and the verification process.'],
                ['Ongoing Due Diligence', 'Procedures for monitoring client relationships and transactions on an ongoing basis, including triggers for enhanced due diligence.'],
                ['Additional Due Diligence — Legal Persons, Trusts, Partnerships', 'Enhanced CDD procedures applicable to legal entities, trusts, and partnerships, including beneficial ownership identification.'],
                ['Complex or Unusually Large Transactions', 'Procedures for identifying and handling transactions that are complex, unusually large, or have no apparent business or lawful purpose.'],
                ['Unusual Patterns', 'Indicators of unusual transaction patterns that may warrant further investigation or reporting.'],
                ['Identification of Clients and Basic CDD', 'Standard client due diligence procedures applicable to all client engagements, including the FICA questionnaire process.'],
                ['Additional Requirements — Legal Persons', 'Specific requirements for verifying companies, close corporations, and other legal entities, including registration documents, beneficial ownership, and authority to act.'],
                ['Additional Requirements — Partnerships', 'Specific requirements for verifying partnerships, including partnership agreements, partner identification, and authority documentation.'],
                ['Additional Requirements — Trusts', 'Specific requirements for verifying trusts, including trust deeds, trustee identification, beneficiary information, and donor verification.'],
                ['Retention of Records', 'Record retention obligations under the FIC Act — minimum five years from date of transaction or termination of business relationship.'],
                ['Duty to Report Suspicious Transactions', 'Obligations under Sections 28 and 29 of the FIC Act to report suspicious and unusual transactions to the FIC.'],
                ['Inability to Conduct Due Diligence', 'Procedures when the agency is unable to complete CDD, including obligations under Section 21B of the FIC Act.'],
                ['Foreign & Domestic Prominent Persons', 'Procedures for identifying and applying enhanced due diligence to foreign prominent public officials (FPPOs) and domestic prominent influential persons (DPIPs).'],
                ['Different Levels of CDD', 'Risk-based approach to CDD: simplified, standard, and enhanced due diligence — when each level applies and what it requires.'],
                ['Co-operation with Other Accountable Institutions', 'Obligations to cooperate with other accountable institutions, supervisory bodies, and the FIC in terms of the Act.'],
                ['How to Make Reports', 'Step-by-step procedures for filing reports with the FIC, including STRs, CTRs, and TPRs via the goAML system.'],
                ['Implementation', 'How this RMCP is implemented across the agency, including staff responsibilities, workflows, and technology systems (CoreX OS).'],
                ['FICA Compliance Officer', 'Designation and responsibilities of the agency\'s FICA Compliance Officer, including reporting lines and accountability.'],
                ['Offences and Penalties', 'Summary of offences under the FIC Act and associated penalties for non-compliance, including administrative sanctions.'],
                ['Reassessment of Risk', 'Procedures for periodic reassessment of the agency\'s risk profile, including triggers for ad-hoc reviews.'],
            ] as $i => $sec)
                <div class="rmcp-section">
                    <h2>{{ $i + 1 }}. {{ $sec[0] }}</h2>
                    <div class="rmcp-placeholder">{{ $sec[1] }}<br><br>Content to be populated from agency RMCP document.</div>
                </div>
            @endforeach

            {{-- Schedules --}}
            <div class="rmcp-section">
                <h2>Schedules</h2>

                <div class="rmcp-schedule">
                    <h3>Schedule 1: Domestic Prominent Influential Persons (DPIPs)</h3>
                    <p class="rmcp-placeholder">Full list of DPIP categories as defined in the FIC Act and Regulations. Content to be populated from agency RMCP document.</p>
                </div>

                <div class="rmcp-schedule">
                    <h3>Schedule 2: Foreign Prominent Public Officials (FPPOs)</h3>
                    <p class="rmcp-placeholder">Full list of FPPO categories as defined in the FIC Act. Content to be populated from agency RMCP document.</p>
                </div>

                <div class="rmcp-schedule">
                    <h3>Schedule 3: Immediate Family Members</h3>
                    <p class="rmcp-placeholder">Definition of immediate family members and close associates in relation to PEPs. Content to be populated from agency RMCP document.</p>
                </div>

                <div class="rmcp-schedule">
                    <h3>Schedules 4-7: FICA Questionnaires</h3>
                    <p style="font-size: 0.875rem; color: #334155;">
                        The FICA client due diligence questionnaires (Natural Person, Company/CC, Partnership, Trust) are managed digitally through the CoreX FICA Compliance module.
                    </p>
                    <a href="{{ route('compliance.fica.create') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-teal-600 hover:text-teal-800 mt-1">
                        Send a FICA Request
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                    </a>
                </div>

                <div class="rmcp-schedule">
                    <h3>Schedule 8: Employee Verification and Vetting</h3>
                    <p class="rmcp-placeholder">Procedures for verifying employee identities, conducting background checks, and ongoing vetting requirements for staff with access to client information and funds. Content to be populated from agency RMCP document.</p>
                </div>
            </div>

            {{-- Footer --}}
            <div style="margin-top: 3rem; padding-top: 1.5rem; border-top: 2px solid #0d9488; text-align: center;">
                <p style="font-size: 0.8125rem; color: #64748b;">Last updated: {{ now()->format('d F Y') }}</p>
                <p style="font-size: 0.8125rem; color: #94a3b8; margin-top: 0.25rem;">This document must be reviewed at intervals of no more than five years, or sooner if there is a material change in the agency's risk profile.</p>
            </div>
        </div>
    </div>
</div>
@endsection
