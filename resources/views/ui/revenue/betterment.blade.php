{{-- KLAES REV-M > Automated Billing > Betterment Bill (KLAES betterment calc: rate × recommended area; briefing: a building on
     site without approval is billed at 4 × the normal price). Wizard: File, Assessment, Review. --}}
<x-app-layout title="Betterment Bill">
    @php
        $label = 'mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-400';
        $field = 'w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100';
        $card = 'space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900';
    @endphp

    <div class="space-y-6">
        <x-ui.page-head icon="trending-up" title="Betterment Bill" subtitle="Rate × recommended area; 4 × when the building has no approved plan" tone="emerald" section="KLAES REV-M › Automated Billing" />
        <x-ui.preview-note what="Raising a betterment bill">The rate table is not set yet (System Admin › Entities &amp; Rates); enter the rate to see the amount.</x-ui.preview-note>

        <form method="POST" action="{{ route('ui.preview', 'betterment-bill') }}" novalidate class="space-y-6"
              x-data="formWizard({ file: null, area: '', rate: '', approved: 'Yes', get factor() { return this.approved === 'No' ? 4 : 1 }, get amount() { return (parseFloat(this.area) || 0) * (parseFloat(this.rate) || 0) * this.factor }, guard(n) { if (n === 1 && ! this.file) { alert('Pick the land file first.'); return false } } }, { steps: 3 })"
              @submit="submitAll($event)" @file-picked="file = $event.detail">
            @csrf
            <x-wizard-steps :steps="['Land file', 'Assessment', 'Review']" />

            <section data-step="1" x-show="step === 1" class="{{ $card }}">
                <x-ui.file-picker name="file_indexing_id" label="Land file (KLAES)" />
            </section>

            <section data-step="2" x-show="step === 2" x-cloak class="{{ $card }}">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.select-other name="category" label="Land use category" :options="['Residential', 'Commercial', 'Industrial', 'Institutional', 'Mixed Use']" required />
                    <div>
                        <label for="bt-area" class="{{ $label }}">Recommended area (m²) <span class="text-rose-500">*</span></label>
                        <input id="bt-area" type="number" step="0.01" min="1" name="area" x-model="area" required class="{{ $field }}">
                    </div>
                    <div>
                        <label for="bt-rate" class="{{ $label }}">Rate per m² (₦) <span class="text-rose-500">*</span></label>
                        <input id="bt-rate" type="number" step="0.01" min="0.01" name="rate" x-model="rate" required class="{{ $field }}">
                    </div>
                    <div>
                        <span class="{{ $label }}">Approved building plan on site? <span class="text-rose-500">*</span></span>
                        <div class="flex gap-3 pt-1">
                            @foreach (['Yes', 'No'] as $v)
                                <label class="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-300"><input type="radio" name="bpa_approved" value="{{ $v }}" x-model="approved" class="text-indigo-600"> {{ $v }}</label>
                            @endforeach
                        </div>
                        <p x-show="approved === 'No'" x-cloak class="mt-1 text-xs font-semibold text-rose-600 dark:text-rose-400">No approval: the bill is 4 × the normal price.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="bt-notes" class="{{ $label }}">Remarks</label>
                        <textarea id="bt-notes" name="remarks" rows="2" class="{{ $field }}"></textarea>
                    </div>
                </div>
            </section>

            <section data-step="3" x-show="step === 3" x-cloak class="{{ $card }}">
                <div class="grid gap-4 text-sm sm:grid-cols-3">
                    <div><p class="{{ $label }}">Land file</p><p class="font-mono font-semibold text-slate-900 dark:text-white" x-text="file ? file.file_number : '—'"></p></div>
                    <div><p class="{{ $label }}">Area × rate</p><p class="text-slate-800 dark:text-slate-200" x-text="(area || 0) + ' m² × ₦' + (rate || 0)"></p></div>
                    <div><p class="{{ $label }}">Penalty factor</p><p class="text-slate-800 dark:text-slate-200" x-text="factor + ' ×'"></p></div>
                </div>
                <div class="rounded-xl bg-emerald-50 p-5 text-center dark:bg-emerald-500/10">
                    <p class="text-sm font-semibold text-emerald-700 dark:text-emerald-300">Bill amount</p>
                    <p class="text-3xl font-black text-emerald-800 dark:text-emerald-200" x-text="'₦' + amount.toLocaleString('en-NG', { minimumFractionDigits: 2 })"></p>
                </div>
            </section>

            <x-wizard-nav :cancel="route('revenue.bill-master')">Generate bill</x-wizard-nav>
        </form>

        <x-ui.panel title="Betterment bills" :padded="false">
            <x-ui.empty icon="trending-up" title="No betterment bills yet">Generated bills will be listed here with their file, area, rate, factor and payment status.</x-ui.empty>
        </x-ui.panel>
    </div>
</x-app-layout>
