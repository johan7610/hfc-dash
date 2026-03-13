@if(($cmaCount ?? 0) > 0)
    @php
        $cp = (int)($cmaCorrectlyPricedPercent ?? 0);
        $op = (int)($cmaOverpricedPercent ?? 0);
    @endphp

    <div class="mt-2 text-xs rounded-md p-2" style="background: var(--surface-2); color: var(--text-secondary); border: 1px solid var(--border);">
        CMA Coverage: {{ $cmaCount }} listings |
        Overpriced: {{ $op }}% |
        Correctly Priced: {{ $cp }}%
    </div>
@endif
