@extends('layouts.corex')

@section('corex-content')
@php
    $pct = $monthlyTarget > 0 ? min(100, round(($mtdPoints / $monthlyTarget) * 100)) : 0;
@endphp

<div class="space-y-5">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold tracking-tight" style="color:var(--text-primary);">Performance</h1>
            <p class="text-sm mt-0.5" style="color:var(--text-secondary);">
                How you're tracking this week and this month
            </p>
        </div>
        <a href="{{ route('corex.dashboard') }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium"
           style="background:var(--surface-2); color:var(--text-secondary);">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" /></svg>
            Back to Today
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Scorecard --}}
        <div class="corex-panel lg:col-span-1">
            <div class="corex-panel-header">
                <h3 class="corex-panel-title">My Scorecard</h3>
                <span class="text-xs" style="color:var(--text-muted);">This Week</span>
            </div>
            <div class="corex-panel-body">
                @if($scorecard)
                    @php
                        $scoreColor = match(true) {
                            $scorecard->overall_score >= 90 => '#10b981',
                            $scorecard->overall_score >= 70 => '#3b82f6',
                            $scorecard->overall_score >= 50 => '#f59e0b',
                            default                         => '#ef4444',
                        };
                    @endphp
                    <div class="text-center mb-4">
                        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full text-2xl font-bold text-white" style="background:{{ $scoreColor }};">
                            {{ $scorecard->overall_score }}
                        </div>
                        <p class="text-xs mt-2" style="color:var(--text-muted);">Overall Score</p>
                    </div>

                    @php
                        $metrics = [
                            ['label' => 'Tasks Completed', 'value' => $scorecard->tasks_completed.'/'.$scorecard->tasks_total, 'pct' => $scorecard->tasks_total > 0 ? round(($scorecard->tasks_completed / $scorecard->tasks_total) * 100) : 0],
                            ['label' => 'Properties Attended', 'value' => $scorecard->properties_attended.'/'.$scorecard->properties_total, 'pct' => $scorecard->properties_total > 0 ? round(($scorecard->properties_attended / $scorecard->properties_total) * 100) : 0],
                            ['label' => 'Events Completed', 'value' => $scorecard->events_completed.'/'.$scorecard->events_total, 'pct' => $scorecard->events_total > 0 ? round(($scorecard->events_completed / $scorecard->events_total) * 100) : 0],
                            ['label' => 'Docs Uploaded', 'value' => $scorecard->documents_uploaded, 'pct' => null],
                            ['label' => 'Avg Response', 'value' => $scorecard->avg_response_hours ? round($scorecard->avg_response_hours, 1).'h' : '—', 'pct' => null],
                        ];
                    @endphp
                    <div class="space-y-2.5 text-sm">
                        @foreach($metrics as $m)
                            <div>
                                <div class="flex justify-between mb-0.5">
                                    <span style="color:var(--text-secondary);">{{ $m['label'] }}</span>
                                    <span class="font-medium" style="color:var(--text-primary);">{{ $m['value'] }}</span>
                                </div>
                                @if($m['pct'] !== null)
                                    <div class="w-full h-1.5 rounded-full" style="background:var(--surface-2);">
                                        <div class="h-1.5 rounded-full transition-all duration-300" style="width:{{ $m['pct'] }}%; background:var(--brand-icon);"></div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @if($scorecard->tasks_overdue > 0)
                        <div class="mt-4 px-3 py-2 rounded-md text-xs" style="background:rgba(239,68,68,0.08); color:#ef4444;">
                            {{ $scorecard->tasks_overdue }} overdue task(s) this week
                        </div>
                    @endif
                @else
                    <div class="py-6 text-center">
                        <p class="text-sm" style="color:var(--text-muted);">Scorecard will be available once data accumulates.</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Activity Points --}}
        <div class="corex-panel lg:col-span-2">
            <div class="corex-panel-header">
                <h3 class="corex-panel-title">Activity Points</h3>
                <span class="text-xs" style="color:var(--text-muted);">{{ now()->format('F Y') }}</span>
            </div>
            <div class="corex-panel-body">
                <div class="flex items-end gap-6 flex-wrap">
                    <div>
                        <p class="text-xs mb-1" style="color:var(--text-muted);">Points this month</p>
                        <p class="text-3xl font-bold" style="color:var(--text-primary);">{{ number_format($mtdPoints) }}</p>
                    </div>
                    @if($monthlyTarget > 0)
                        <div>
                            <p class="text-xs mb-1" style="color:var(--text-muted);">Target</p>
                            <p class="text-xl font-semibold" style="color:var(--text-secondary);">{{ number_format($monthlyTarget) }}</p>
                        </div>
                        <div>
                            <p class="text-xs mb-1" style="color:var(--text-muted);">Progress</p>
                            <p class="text-xl font-semibold" style="color:{{ $pct >= 80 ? '#10b981' : ($pct >= 50 ? '#3b82f6' : '#f59e0b') }};">{{ $pct }}%</p>
                        </div>
                    @endif
                </div>
                @if($monthlyTarget > 0)
                    <div class="w-full h-2 rounded-full mt-4" style="background:var(--surface-2);">
                        <div class="h-2 rounded-full transition-all duration-300" style="width:{{ $pct }}%; background:var(--brand-icon,#0ea5e9);"></div>
                    </div>
                @endif
                <div class="mt-4">
                    <a href="{{ route('agent.daily') }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-sm font-medium"
                       style="background:var(--surface-2); color:var(--brand-icon);">
                        Capture Daily Activity
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" /></svg>
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Candidate Documents (supervisors only) --}}
    @if($candidateDocs->count() > 0)
        <div class="corex-panel border-l-4" style="border-left-color:#f59e0b;">
            <div class="corex-panel-header">
                <h3 class="corex-panel-title" style="color:#b45309;">Candidate Documents — Needs Authorisation</h3>
                <span class="text-xs font-medium px-2 py-0.5 rounded-full" style="background:#fef3c7; color:#92400e;">
                    {{ $candidateDocs->count() }} pending
                </span>
            </div>
            <div class="corex-panel-body">
                <div class="divide-y" style="border-color:var(--border-default);">
                    @foreach($candidateDocs as $doc)
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between py-3 gap-2">
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-sm truncate" style="color:var(--text-primary);">{{ $doc->document->name ?? 'Untitled Document' }}</p>
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1 text-xs" style="color:var(--text-secondary);">
                                    <span>Candidate: <strong>{{ $doc->creator->name ?? 'Unknown' }}</strong></span>
                                    <span>Created: {{ $doc->created_at->format('d M Y') }}</span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $doc->status === 'awaiting_supervisor' ? 'bg-yellow-100 text-yellow-800' : 'bg-orange-100 text-orange-800' }}">
                                        {{ $doc->status === 'awaiting_supervisor' ? 'Initial Review' : 'Final Sign-off' }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex-shrink-0">
                                <a href="{{ route('docuperfect.signatures.review', $doc->document_id) }}"
                                   class="inline-flex items-center gap-1.5 px-4 py-2 rounded-md text-sm font-semibold text-white shadow"
                                   style="background:#f59e0b;">
                                    Review & Authorise
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Property Health --}}
    <div class="corex-panel">
        <div class="corex-panel-header">
            <h3 class="corex-panel-title">Properties Needing Attention</h3>
            <div class="flex items-center gap-2 text-xs">
                @if($propHealthSummary['critical'] > 0)
                    <span class="px-2 py-0.5 rounded-full font-medium" style="background:rgba(239,68,68,0.1); color:#ef4444;">{{ $propHealthSummary['critical'] }} critical</span>
                @endif
                @if($propHealthSummary['attention'] > 0)
                    <span class="px-2 py-0.5 rounded-full font-medium" style="background:rgba(245,158,11,0.1); color:#f59e0b;">{{ $propHealthSummary['attention'] }} attention</span>
                @endif
                <span class="px-2 py-0.5 rounded-full font-medium" style="background:rgba(16,185,129,0.1); color:#10b981;">{{ $propHealthSummary['good'] }} good</span>
            </div>
        </div>
        <div class="corex-panel-body">
            @if($propsNeedingAttention->isEmpty())
                <div class="py-8 text-center">
                    <svg class="w-10 h-10 mx-auto mb-2 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                    <p class="text-sm" style="color:var(--text-muted);">All your properties are in good health.</p>
                </div>
            @else
                <div class="divide-y" style="border-color:var(--border-default);">
                    @foreach($propsNeedingAttention as $health)
                        @php
                            $prop = $health->property;
                            $gradeColor = match($health->grade) {
                                'critical'  => '#ef4444',
                                'attention' => '#f59e0b',
                                default     => '#10b981',
                            };
                        @endphp
                        <div class="flex items-center gap-3 py-3">
                            <div class="flex-shrink-0 w-10 h-10 rounded-md flex items-center justify-center text-sm font-bold text-white" style="background:{{ $gradeColor }};">
                                {{ $health->score }}
                            </div>
                            <div class="flex-1 min-w-0">
                                @if($prop)
                                    <a href="{{ route('corex.properties.show', $prop) }}"
                                       class="text-sm font-medium truncate block hover:underline"
                                       style="color:var(--text-primary);">
                                        {{ $prop->buildDisplayAddress() ?: ($prop->title ?: 'Property #'.$prop->id) }}
                                    </a>
                                @endif
                                <div class="flex flex-wrap gap-x-3 gap-y-0.5 mt-0.5">
                                    @if(is_array($health->factors))
                                        @foreach($health->factors as $key => $factor)
                                            @if(($factor['penalty'] ?? 0) > 0)
                                                <span class="text-xs" style="color:{{ ($factor['status'] ?? null) === 'critical' ? '#ef4444' : '#f59e0b' }};">{{ $factor['label'] ?? $key }}</span>
                                            @endif
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                            <div class="flex-shrink-0 text-xs" style="color:var(--text-muted);">
                                {{ $prop?->agent?->name ?? 'Unassigned' }}
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

</div>
@endsection
