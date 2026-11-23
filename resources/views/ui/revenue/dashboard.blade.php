{{-- KLAES REV-M > Dashboard (KLAES Revenue Dashboard): billed, collected, outstanding, monthly trend, channels, latest payments. --}}
<x-app-layout title="REV-M Dashboard">
    @php
        $naira = fn ($v) => '₦'.number_format((float) $v, 0);
        $labels = array_map(fn ($m) => \Illuminate\Support\Carbon::createFromFormat('Y-m', $m)->format('M y'), array_keys($months));
        $trend = ['type' => 'bar', 'money' => true, 'labels' => $labels, 'series' => [['label' => 'Billed', 'data' => array_column($months, 'billed')], ['label' => 'Paid', 'data' => array_column($months, 'paid')]]];
        $chan = ['type' => 'doughnut', 'money' => true, 'labels' => array_keys($channels), 'series' => [['label' => 'Paid', 'data' => array_map('floatval', array_values($channels))]]];
        $rate = $billed > 0 ? round($paid / $billed * 100) : 0;
    @endphp

    <div class="space-y-6">
        <x-ui.page-head icon="wallet" title="REV-M Dashboard" subtitle="KADCA revenue: bills raised, payments and receipts" tone="emerald" section="KLAES REV-M">
            <a href="{{ route('revenue.bill-master') }}" class="inline-flex items-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-500"><i data-lucide="receipt" class="h-4 w-4"></i> Bill Master</a>
        </x-ui.page-head>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.stat title="Total billed" :value="$naira($billed)" :hint="number_format(array_sum($counts)).' bill(s)'" icon="file-text" tone="blue" />
            <x-ui.stat title="Collected" :value="$naira($paid)" :hint="number_format($counts['Paid'] ?? 0).' paid bill(s)'" icon="banknote" tone="emerald" />
            <x-ui.stat title="Outstanding" :value="$naira($outstanding)" :hint="number_format($counts['Unpaid'] ?? 0).' unpaid bill(s)'" icon="alarm-clock" tone="amber" :href="route('bpa.payments.index')" />
            <x-ui.stat title="Collection rate" :value="$rate.'%'" hint="Paid against billed" icon="percent" tone="violet" />
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <x-ui.panel title="Billed and paid" subtitle="Per month, last 12 months" class="xl:col-span-2"><x-ui.chart :chart="$trend" label="Billed and paid per month" height="h-72" /></x-ui.panel>
            <x-ui.panel title="Payment channels" subtitle="Amount paid by channel"><x-ui.chart :chart="$chan" label="Amount paid by channel" height="h-72" /></x-ui.panel>
        </div>

        <x-ui.panel title="Latest payments" :padded="false">
            <x-slot:actions><a href="{{ route('revenue.receipts', 'e-receipt') }}" class="text-sm font-semibold text-indigo-600 hover:underline dark:text-indigo-300">e-Receipts</a></x-slot:actions>
            @include('ui.revenue.partials.bills-table', ['bills' => $latest, 'paginated' => false, 'receiptMode' => 'e-receipt'])
        </x-ui.panel>
    </div>
</x-app-layout>
