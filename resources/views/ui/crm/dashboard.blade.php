{{-- CRM > Dashboard: the KLAES customer master (read-only) and KADCA's own applicants. --}}
<x-app-layout title="CRM Dashboard">
    @php
        $n = fn ($v) => number_format((int) $v);
        $typeChart = ['type' => 'doughnut', 'labels' => array_keys($types), 'series' => [['label' => 'Customers', 'data' => array_values($types)]]];
        $trend = $stats['trend'] ?? [];
        $trendChart = ['type' => 'bar', 'labels' => array_map(fn ($m) => \Illuminate\Support\Carbon::createFromFormat('Y-m', $m)->format('M y'), array_keys($trend)), 'series' => [['label' => 'Customers added', 'data' => array_values($trend)]]];
        $tiles = [
            ['Individual', 'user', 'individual'], ['Group', 'users', 'group'], ['Multiple Owners', 'users-round', 'multiple-owners'], ['Corporate', 'building-2', 'corporate'],
        ];
    @endphp

    <div class="space-y-6">
        <x-ui.page-head icon="contact" title="Customer Relationship Management" subtitle="Customers, applicants and their appointments" tone="indigo" section="CRM">
            <a href="{{ route('crm.appointments') }}" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-500"><i data-lucide="calendar-plus" class="h-4 w-4"></i> New appointment</a>
        </x-ui.page-head>

        <x-ui.klaes-note tables="entities, customers" :error="$error" />

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($tiles as [$label, $icon, $slug])
                <x-ui.stat :title="$label" :value="$n($types[$label] ?? 0)" hint="In the KLAES customer master" :icon="$icon" tone="blue" :href="route('crm.customers', $slug)" />
            @endforeach
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui.stat title="KADCA applicants" :value="$n($applicants)" hint="Developers who applied for building plan approval" icon="hard-hat" tone="emerald" :href="route('bpa.applications.index')" />
            <x-ui.stat title="Reachable by phone" :value="$n($withPhone)" hint="Applicants with a phone number" icon="phone" tone="violet" />
            <x-ui.stat title="Appointments today" value="0" hint="Appointment book opens with the backend" icon="calendar-clock" tone="amber" :href="route('crm.calendar')" />
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <x-ui.panel title="Customers by type" subtitle="KLAES customer master"><x-ui.chart :chart="$typeChart" label="Customers by type" /></x-ui.panel>
            <x-ui.panel title="Customers added" subtitle="Per month, last 12 months" class="xl:col-span-2"><x-ui.chart :chart="$trendChart" label="Customers added per month" /></x-ui.panel>
        </div>

        <x-ui.panel title="Latest KADCA applicants" :padded="false">
            @if ($recentApplicants->isEmpty())
                <x-ui.empty icon="user-round-search" title="No applicants yet">Developers appear here when their first building plan application is received.</x-ui.empty>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                            <tr><th class="px-4 py-3">S/N</th><th class="px-4 py-3">Developer</th><th class="px-4 py-3">Phone</th><th class="px-4 py-3">Email</th><th class="px-4 py-3">Application No.</th><th class="px-4 py-3">Applied</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($recentApplicants as $a)
                                <tr>
                                    <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $loop->iteration }}</td>
                                    <td class="px-4 py-3 font-medium text-slate-800 dark:text-slate-200">{{ $a->developer_name }}</td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $a->developer_phone ?: '—' }}</td>
                                    <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $a->developer_email ?: '—' }}</td>
                                    <td class="px-4 py-3 font-mono text-xs text-indigo-700 dark:text-indigo-300">{{ $a->application_no }}</td>
                                    <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $a->created_at?->format('d M Y') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.panel>
    </div>
</x-app-layout>
