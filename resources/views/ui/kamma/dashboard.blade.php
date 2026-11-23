{{-- KAMMA > Dashboard (KLAES module-dashboard layout): what KAMMA has put on KLAES land files, as KADCA sees it. --}}
<x-app-layout title="KAMMA Dashboard">
    @php
        $n = fn ($v) => $v === null ? '—' : number_format((int) $v);
        $trend = $shadow['trend'] ?? [];
        $trendChart = ['type' => 'bar', 'labels' => array_map(fn ($m) => \Illuminate\Support\Carbon::createFromFormat('Y-m', $m)->format('M y'), array_keys($trend)),
            'series' => [['label' => 'Shadow files matched', 'data' => array_values($trend)]]];
        $landUse = $shadow['breakdowns'][0]['rows'] ?? [];
        $lga = $shadow['breakdowns'][1]['rows'] ?? [];
        $tsTypes = $status['breakdowns'][0]['rows'] ?? [];
        $luChart = ['type' => 'doughnut', 'labels' => array_column(array_slice($landUse, 0, 6), 'label'), 'series' => [['label' => 'Files', 'data' => array_column(array_slice($landUse, 0, 6), 'n')]]];
        $tsChart = ['type' => 'hbar', 'labels' => array_column(array_slice($tsTypes, 0, 8), 'label'), 'series' => [['label' => 'Records', 'data' => array_column(array_slice($tsTypes, 0, 8), 'n')]]];
    @endphp

    <div class="space-y-6">
        <x-ui.page-head icon="building-2" title="KAMMA Dashboard" subtitle="KLAES-METRO output on KLAES land files: shadow files, title statuses and planning recommendations" tone="teal" section="KAMMA" />
        <x-ui.klaes-note tables="file_indexings.kamma_matching, title_status_applications" :error="$error" />

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.stat title="Shadow files" :value="$n($shadow['total'] ?? null)" hint="Land files KAMMA has matched" icon="files" tone="cyan" :href="route('records.show', 'kamma-shadow-files')" />
            <x-ui.stat title="Matched this year" :value="$n($shadow['year'] ?? null)" :hint="'Since 1 January '.now()->year" icon="calendar-range" tone="emerald" />
            <x-ui.stat title="Title statuses" :value="$n($status['total'] ?? null)" hint="Withdrawals, revocations, litigation…" icon="file-warning" tone="rose" :href="route('kamma.title-status')" />
            <x-ui.stat title="Planning recommendations" value="—" hint="Shared once KAMMA output reaches KADCA" icon="clipboard-check" tone="slate" :href="route('kamma.planning-approval')" />
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <x-ui.panel title="Shadow files matched" subtitle="Per month, last 12 months" class="xl:col-span-2"><x-ui.chart :chart="$trendChart" label="Shadow files matched per month" /></x-ui.panel>
            <x-ui.panel title="Shadow files by land use"><x-ui.chart :chart="$luChart" label="Shadow files by land use" /></x-ui.panel>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-ui.panel title="Title statuses by type"><x-ui.chart :chart="$tsChart" label="Title statuses by type" /></x-ui.panel>
            <x-ui.panel title="Shadow files by LGA" :padded="false">
                @if (empty($lga))
                    <x-ui.empty icon="map" title="No shadow files yet">Files appear here when KAMMA commissions them as shadow files.</x-ui.empty>
                @else
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400"><tr><th class="px-4 py-3">S/N</th><th class="px-4 py-3">LGA</th><th class="px-4 py-3 text-right">Files</th></tr></thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach (array_slice($lga, 0, 10) as $row)
                                <tr><td class="px-4 py-2.5 text-slate-500 dark:text-slate-400">{{ $loop->iteration }}</td><td class="px-4 py-2.5 text-slate-800 dark:text-slate-200">{{ $row['label'] }}</td><td class="px-4 py-2.5 text-right tabular-nums font-semibold text-slate-900 dark:text-white">{{ number_format($row['n']) }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-ui.panel>
        </div>
    </div>
</x-app-layout>
