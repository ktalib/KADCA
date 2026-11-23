{{-- MD's View > Managing Director's Dashboard (KLAES HC/PS View concept): the BPA pipeline, revenue, land-use checks
     and the latest applications, from KADCA's own records. --}}
<x-app-layout title="Managing Director's Dashboard">
    @php
        $naira = fn ($v) => '₦'.number_format((float) $v, 0);
        $n = fn ($v) => number_format((int) $v);
        $labels = array_map(fn ($m) => \Illuminate\Support\Carbon::createFromFormat('Y-m', $m)->format('M y'), array_keys($months));
        $appsChart = ['type' => 'bar', 'labels' => $labels, 'series' => [['label' => 'Applications', 'data' => array_column($months, 'apps')]]];
        $paidChart = ['type' => 'line', 'money' => true, 'labels' => $labels, 'series' => [['label' => 'Fees paid', 'data' => array_column($months, 'paid')]]];
        $stageChart = ['type' => 'hbar', 'labels' => array_keys($stages), 'series' => [['label' => 'Files', 'data' => array_values($stages)]]];
        $useChart = ['type' => 'doughnut', 'labels' => array_keys($landUse), 'series' => [['label' => 'Applications', 'data' => array_values($landUse)]]];
        $tones = ['indigo' => 'blue', 'amber' => 'amber', 'green' => 'emerald', 'blue' => 'cyan', 'rose' => 'rose', 'purple' => 'violet'];
        $icons = ['document' => 'file-text', 'cash' => 'banknote', 'folder-plus' => 'folder-plus', 'building' => 'building-2', 'check-circle' => 'check-circle-2', 'arrow-uturn' => 'undo-2', 'flag' => 'flag', 'map-pin' => 'map-pin'];
    @endphp

    <div class="space-y-6">
        <x-ui.page-head icon="presentation" title="Managing Director's Dashboard" :subtitle="'KLAES-CONTROL · Kano State Development Control Agency · '.now()->format('l, j F Y')" tone="indigo" section="MD's View">
            <a href="{{ route('reports.index') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"><i data-lucide="file-bar-chart" class="h-4 w-4"></i> Reports</a>
            <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500"><i data-lucide="printer" class="h-4 w-4"></i> Print</button>
        </x-ui.page-head>

        {{-- Revenue --}}
        <div class="grid gap-4 md:grid-cols-3">
            @foreach ($d['money'] as $m)
                <div class="rounded-2xl bg-gradient-to-br p-5 text-white shadow-sm {{ ['indigo' => 'from-indigo-600 to-indigo-800', 'green' => 'from-emerald-600 to-emerald-800', 'amber' => 'from-amber-500 to-amber-700'][$m['colour']] ?? 'from-slate-600 to-slate-800' }}">
                    <p class="text-sm font-semibold text-white/80">{{ $m['label'] }}</p>
                    <p class="mt-1 text-3xl font-black tabular-nums">{{ $naira($m['total']) }}</p>
                    <p class="mt-1 text-xs text-white/75">{{ $m['hint'] }}@if ($m['month'] !== null) · {{ $naira($m['month']) }} this month @endif</p>
                </div>
            @endforeach
        </div>

        {{-- Pipeline --}}
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($d['cards'] as $c)
                <x-ui.stat :title="$c['label']" :value="$n($c['total'])" :hint="$c['hint'].($c['month'] !== null ? ' · '.$n($c['month']).' this month' : '')"
                           :icon="$icons[$c['icon']] ?? 'circle'" :tone="$tones[$c['colour']] ?? 'slate'" :href="$c['url']" />
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <x-ui.panel title="Applications received" subtitle="Per month, last 12 months"><x-ui.chart :chart="$appsChart" label="Applications per month" /></x-ui.panel>
            <x-ui.panel title="Fees paid" subtitle="Per month, last 12 months"><x-ui.chart :chart="$paidChart" label="Fees paid per month" /></x-ui.panel>
            <x-ui.panel title="Where the files are" subtitle="Applications by current stage"><x-ui.chart :chart="$stageChart" label="Applications by stage" /></x-ui.panel>
            <x-ui.panel title="Land-use check" subtitle="Proposed use against the land file (flag only)"><x-ui.chart :chart="$useChart" label="Land-use check results" /></x-ui.panel>
        </div>

        <x-ui.panel title="Latest applications" :padded="false">
            <x-slot:actions>
                <a href="{{ route('bpa.applications.index') }}" class="text-sm font-semibold text-indigo-600 hover:underline dark:text-indigo-300">All applications</a>
            </x-slot:actions>
            @if ($recent->isEmpty())
                <x-ui.empty icon="file-text" title="No applications yet">Applications appear here as the Assessment Stage receives them.</x-ui.empty>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                            <tr><th class="px-4 py-3">S/N</th><th class="px-4 py-3">Application No.</th><th class="px-4 py-3">Developer</th><th class="px-4 py-3">Plot No.</th><th class="px-4 py-3">District</th><th class="px-4 py-3">LGA</th><th class="px-4 py-3">Location</th><th class="px-4 py-3">Stage</th><th class="px-4 py-3">Received</th><th class="px-4 py-3"><span class="sr-only">Actions</span></th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($recent as $a)
                                <tr>
                                    <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $loop->iteration }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 font-mono text-xs font-semibold text-indigo-700 dark:text-indigo-300">{{ $a->application_no }}</td>
                                    <td class="px-4 py-3 text-slate-800 dark:text-slate-200">{{ $a->developer_name }}</td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $a->plot_no ?: '—' }}</td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $a->district ?: '—' }}</td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $a->lga ?: '—' }}</td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ \Illuminate\Support\Str::limit($a->location, 40) ?: '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3"><span class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">{{ \App\Support\BpaStage::label($a->stage) }}</span></td>
                                    <td class="whitespace-nowrap px-4 py-3 text-slate-500 dark:text-slate-400">{{ $a->created_at?->format('d M Y') }}</td>
                                    <td class="px-4 py-2 text-right">
                                        <x-row-actions>
                                            <x-row-action :href="route('bpa.applications.show', $a)">
                                                <x-slot:icon><i data-lucide="eye" class="h-4 w-4"></i></x-slot:icon>
                                                Open application
                                            </x-row-action>
                                        </x-row-actions>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.panel>
    </div>
</x-app-layout>
