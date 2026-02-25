@extends('layouts.nexus')

@section('nexus-content')
<div class="max-w-6xl mx-auto px-4 py-6" x-data="calculatorsApp()">

    {{-- Navy Header --}}
    <div style="background:#0b2a4a;" class="rounded-2xl px-6 py-4 mb-6">
        <h2 class="text-xl font-bold text-white leading-tight">Calculators</h2>
        <div class="text-sm text-white/60">Commission, bond repayments, transfer duty & costs</div>
    </div>

    {{-- 2-column grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        {{-- CARD 1: Commission Calculator --}}
        <div class="ds-status-card flex flex-col" style="min-height: 420px;">
            <h3 class="ds-section-header" style="margin-bottom:0.75rem;">Commission Calculator</h3>

            <div class="space-y-3 flex-1">
                <div>
                    <label class="ds-label block mb-1">Sale Price</label>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-gray-500">R</span>
                        <input type="text" x-model="comm.salePrice" @input="formatInput($event)" placeholder="e.g. 2,500,000"
                               class="w-full border border-slate-300 rounded-lg p-2 text-sm focus:border-cyan-500 focus:outline-none" />
                    </div>
                </div>

                <div>
                    <label class="ds-label block mb-1">Commission Rate</label>
                    <div class="flex items-center gap-2 flex-wrap">
                        <template x-for="r in [5, 6, 7.5]" :key="r">
                            <button type="button" @click="comm.rate = r; comm.customRate = ''"
                                    :class="comm.rate == r && comm.customRate === '' ? 'bg-[#0b2a4a] text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'"
                                    class="px-4 py-1.5 rounded-lg text-sm font-semibold transition-colors"
                                    x-text="r + '%'"></button>
                        </template>
                        <input type="text" x-model="comm.customRate" @input="comm.rate = parseFloat(comm.customRate) || 0" placeholder="Custom %"
                               class="w-24 border border-slate-300 rounded-lg p-1.5 text-sm focus:border-cyan-500 focus:outline-none" />
                    </div>
                </div>

                <button type="button" @click="calcCommission()" class="nexus-btn-primary px-6 py-2 text-sm font-semibold rounded-lg">
                    Calculate
                </button>
            </div>

            {{-- Results --}}
            <div x-show="comm.result" x-transition class="mt-4 pt-4 border-t border-slate-200 space-y-2">
                <div class="flex justify-between"><span class="ds-label">Commission exc VAT</span><span class="ds-value" x-text="'R ' + fmt(comm.result?.commission_exc_vat)"></span></div>
                <div class="flex justify-between"><span class="ds-label">VAT (15%)</span><span class="ds-value" x-text="'R ' + fmt(comm.result?.vat)"></span></div>
                <div class="flex justify-between"><span class="ds-label">Commission inc VAT</span><span class="ds-value font-bold" x-text="'R ' + fmt(comm.result?.commission_inc_vat)"></span></div>
                <div class="border-t border-slate-100 pt-2 mt-2 space-y-1">
                    <div class="flex justify-between"><span class="ds-label">Agent split at 50%</span><span class="ds-value" x-text="'R ' + fmt(comm.result?.agent_split_50)"></span></div>
                    <div class="flex justify-between"><span class="ds-label">Agent split at 60%</span><span class="ds-value" x-text="'R ' + fmt(comm.result?.agent_split_60)"></span></div>
                    <div class="flex justify-between"><span class="ds-label">Agent split at 70%</span><span class="ds-value" x-text="'R ' + fmt(comm.result?.agent_split_70)"></span></div>
                </div>
            </div>
        </div>

        {{-- CARD 2: Bond Repayment Calculator --}}
        <div class="ds-status-card flex flex-col" style="min-height: 420px;">
            <h3 class="ds-section-header" style="margin-bottom:0.75rem;">Bond Repayment</h3>

            <div class="space-y-3 flex-1">
                <div>
                    <label class="ds-label block mb-1">Loan Amount</label>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-gray-500">R</span>
                        <input type="text" x-model="bond.loanAmount" @input="formatInput($event)" placeholder="e.g. 1,500,000"
                               class="w-full border border-slate-300 rounded-lg p-2 text-sm focus:border-cyan-500 focus:outline-none" />
                    </div>
                </div>

                <div>
                    <label class="ds-label block mb-1">Interest Rate (%)</label>
                    <input type="text" x-model="bond.interestRate" placeholder="e.g. 11.75"
                           class="w-full border border-slate-300 rounded-lg p-2 text-sm focus:border-cyan-500 focus:outline-none" />
                    <div class="text-xs text-gray-500 mt-1">Current prime: {{ $primeRate }}%</div>
                </div>

                <div>
                    <label class="ds-label block mb-1">Loan Term</label>
                    <select x-model="bond.termYears" class="w-full border border-slate-300 rounded-lg p-2 text-sm focus:border-cyan-500 focus:outline-none">
                        <template x-for="y in [10, 15, 20, 25, 30]" :key="y">
                            <option :value="y" x-text="y + ' years'" :selected="y === 20"></option>
                        </template>
                    </select>
                </div>

                <button type="button" @click="calcBond()" class="nexus-btn-primary px-6 py-2 text-sm font-semibold rounded-lg">
                    Calculate
                </button>
            </div>

            {{-- Results --}}
            <div x-show="bond.result" x-transition class="mt-4 pt-4 border-t border-slate-200 space-y-2">
                <div class="flex justify-between items-baseline">
                    <span class="ds-label">Monthly repayment</span>
                    <span class="ds-value-lg" x-text="'R ' + fmt(bond.result?.monthly_repayment)"></span>
                </div>
                <div class="flex justify-between"><span class="ds-label">Total repaid over term</span><span class="ds-value" x-text="'R ' + fmt(bond.result?.total_repaid)"></span></div>
                <div class="flex justify-between"><span class="ds-label">Total interest</span><span class="ds-value" x-text="'R ' + fmt(bond.result?.total_interest)"></span></div>
                <div class="border-t border-slate-100 pt-2 mt-2 space-y-1">
                    <div class="text-xs text-gray-500 mb-1">Comparison at higher rates:</div>
                    <div class="flex justify-between"><span class="ds-label" x-text="'At ' + (parseFloat(bond.interestRate) + 1).toFixed(2) + '%'"></span><span class="ds-value" x-text="'R ' + fmt(bond.result?.monthly_plus_1) + '/mo'"></span></div>
                    <div class="flex justify-between"><span class="ds-label" x-text="'At ' + (parseFloat(bond.interestRate) + 2).toFixed(2) + '%'"></span><span class="ds-value" x-text="'R ' + fmt(bond.result?.monthly_plus_2) + '/mo'"></span></div>
                </div>
            </div>
        </div>

        {{-- CARD 3: Transfer Duty --}}
        <div class="ds-status-card flex flex-col" style="min-height: 420px;">
            <h3 class="ds-section-header" style="margin-bottom:0.75rem;">Transfer Duty</h3>

            <div class="space-y-3 flex-1">
                <div>
                    <label class="ds-label block mb-1">Purchase Price</label>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-gray-500">R</span>
                        <input type="text" x-model="duty.purchasePrice" @input="formatInput($event)" placeholder="e.g. 2,500,000"
                               class="w-full border border-slate-300 rounded-lg p-2 text-sm focus:border-cyan-500 focus:outline-none" />
                    </div>
                </div>

                <button type="button" @click="calcTransferDuty()" class="nexus-btn-primary px-6 py-2 text-sm font-semibold rounded-lg">
                    Calculate
                </button>
            </div>

            {{-- Results --}}
            <div x-show="duty.result" x-transition class="mt-4 pt-4 border-t border-slate-200 space-y-2">
                <div class="flex justify-between items-baseline">
                    <span class="ds-label">Transfer Duty</span>
                    <span class="ds-value-lg" x-text="'R ' + fmt(duty.result?.transfer_duty)"></span>
                </div>
                <div class="flex justify-between"><span class="ds-label">Effective rate</span><span class="ds-value" x-text="duty.result?.effective_rate?.toFixed(2) + '%'"></span></div>
                <div class="mt-2 text-xs text-gray-600 bg-gray-50 rounded-lg p-2" x-text="duty.result?.bracket"></div>
                <div class="text-xs text-gray-500 mt-2">Transfer duty paid by the BUYER. Properties under R1.1M = R0 duty.</div>
            </div>
        </div>

        {{-- CARD 4: Transfer Cost Estimator --}}
        <div class="ds-status-card flex flex-col" style="min-height: 420px;">
            <h3 class="ds-section-header" style="margin-bottom:0.75rem;">Total Transfer Costs</h3>

            <div class="space-y-3 flex-1">
                <div>
                    <label class="ds-label block mb-1">Purchase Price</label>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-gray-500">R</span>
                        <input type="text" x-model="costs.purchasePrice" @input="formatInput($event)" placeholder="e.g. 2,500,000"
                               class="w-full border border-slate-300 rounded-lg p-2 text-sm focus:border-cyan-500 focus:outline-none" />
                    </div>
                </div>

                <div>
                    <label class="ds-label block mb-1">Buyer needs bond?</label>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" x-model="costs.needsBond" class="rounded" />
                        <span class="text-sm text-gray-700" x-text="costs.needsBond ? 'Yes' : 'No'"></span>
                    </label>
                </div>

                <div x-show="costs.needsBond" x-transition>
                    <label class="ds-label block mb-1">Bond Amount</label>
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-gray-500">R</span>
                        <input type="text" x-model="costs.bondAmount" @input="formatInput($event)" placeholder="Default = purchase price"
                               class="w-full border border-slate-300 rounded-lg p-2 text-sm focus:border-cyan-500 focus:outline-none" />
                    </div>
                </div>

                <button type="button" @click="calcTransferCosts()" class="nexus-btn-primary px-6 py-2 text-sm font-semibold rounded-lg">
                    Calculate
                </button>
            </div>

            {{-- Results --}}
            <div x-show="costs.result" x-transition class="mt-4 pt-4 border-t border-slate-200 space-y-2">
                <div class="flex justify-between"><span class="ds-label">Transfer duty</span><span class="ds-value" x-text="'R ' + fmt(costs.result?.transfer_duty)"></span></div>
                <div class="flex justify-between"><span class="ds-label">Conveyancing fees (est.)</span><span class="ds-value" x-text="'R ' + fmt(costs.result?.conveyancing_fees)"></span></div>
                <div class="flex justify-between"><span class="ds-label">Deeds office & petties</span><span class="ds-value" x-text="'R ' + fmt(costs.result?.deeds_office_petties)"></span></div>
                <div class="flex justify-between border-t border-slate-100 pt-2"><span class="ds-label">Subtotal (transfer)</span><span class="ds-value" x-text="'R ' + fmt(costs.result?.subtotal_transfer)"></span></div>
                <div x-show="costs.needsBond" class="flex justify-between"><span class="ds-label">Bond registration</span><span class="ds-value" x-text="'R ' + fmt(costs.result?.bond_registration)"></span></div>
                <div class="flex justify-between border-t border-slate-200 pt-2 mt-2">
                    <span class="ds-label font-bold">GRAND TOTAL</span>
                    <span class="ds-value-lg" x-text="'R ' + fmt(costs.result?.grand_total)"></span>
                </div>
                <div class="text-xs text-gray-500 mt-2">Estimates only — request formal quotation from conveyancer.</div>
            </div>
        </div>

    </div>
</div>

<script>
function calculatorsApp() {
    return {
        comm: {
            salePrice: '',
            rate: 5,
            customRate: '',
            result: null,
        },
        bond: {
            loanAmount: '',
            interestRate: '{{ $primeRate }}',
            termYears: 20,
            result: null,
        },
        duty: {
            purchasePrice: '',
            result: null,
        },
        costs: {
            purchasePrice: '',
            needsBond: true,
            bondAmount: '',
            result: null,
        },

        parseAmount(val) {
            if (!val) return 0;
            let s = String(val).replace(/[Rr\s,]/g, '');
            // Handle "2.5m" or "2.5M"
            const mMatch = s.match(/^([\d.]+)[mM]$/);
            if (mMatch) return parseFloat(mMatch[1]) * 1000000;
            return parseFloat(s) || 0;
        },

        formatInput(event) {
            let el = event.target;
            let raw = el.value.replace(/[^0-9]/g, '');
            if (raw === '') { return; }
            el.value = Number(raw).toLocaleString('en-ZA');
            // Update the bound model
            el.dispatchEvent(new Event('input', { bubbles: true }));
        },

        fmt(val) {
            if (val === null || val === undefined) return '0';
            return Number(val).toLocaleString('en-ZA', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        },

        async post(url, body) {
            const resp = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify(body),
            });
            return resp.json();
        },

        async calcCommission() {
            const rate = this.comm.customRate !== '' ? parseFloat(this.comm.customRate) : this.comm.rate;
            const res = await this.post('{{ route("calculators.commission") }}', {
                sale_price: this.parseAmount(this.comm.salePrice),
                commission_rate: rate,
            });
            if (res.ok) this.comm.result = res;
        },

        async calcBond() {
            const res = await this.post('{{ route("calculators.bond") }}', {
                loan_amount: this.parseAmount(this.bond.loanAmount),
                interest_rate: parseFloat(this.bond.interestRate) || 0,
                term_years: parseInt(this.bond.termYears) || 20,
            });
            if (res.ok) this.bond.result = res;
        },

        async calcTransferDuty() {
            const res = await this.post('{{ route("calculators.transferDuty") }}', {
                purchase_price: this.parseAmount(this.duty.purchasePrice),
            });
            if (res.ok) this.duty.result = res;
        },

        async calcTransferCosts() {
            const bondAmt = this.costs.bondAmount ? this.parseAmount(this.costs.bondAmount) : this.parseAmount(this.costs.purchasePrice);
            const res = await this.post('{{ route("calculators.transferCosts") }}', {
                purchase_price: this.parseAmount(this.costs.purchasePrice),
                needs_bond: this.costs.needsBond,
                bond_amount: bondAmt,
            });
            if (res.ok) this.costs.result = res;
        },
    };
}
</script>
@endsection
