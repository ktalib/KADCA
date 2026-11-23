{{-- KLAES REV-M > Billing > Automated Billing > Bill Master (KLAES revenue.bills.all): bill types, then every KADCA bill. --}}
<x-app-layout title="Bill Master">
    @php $field = 'rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100'; @endphp

    <div class="space-y-6">
        <x-ui.page-head icon="receipt" title="Bill Master" subtitle="All KADCA bills, by type" tone="emerald" section="KLAES REV-M › Billing" />

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach (\App\Http\Controllers\Ui\RevenueController::TYPES as $code => $t)
                <a href="{{ route($t['route']) }}" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ $t[1] }}</span>
                        <span @class(['rounded-full px-2 py-0.5 text-[11px] font-semibold', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' => $t[2], 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' => ! $t[2]])>{{ $t[2] ? 'Live' : 'Preview' }}</span>
                    </div>
                    <p class="mt-3 font-bold text-slate-900 dark:text-white">{{ $t[0] }}</p>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ number_format($typeCounts[$code] ?? 0) }} bill(s)</p>
                </a>
            @endforeach
        </div>

        <form method="GET" class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="min-w-[16rem] flex-1">
                <label for="bm-q" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-400">Search</label>
                <input id="bm-q" type="search" name="q" value="{{ $q }}" placeholder="Bill no., receipt no., application no., developer, file no." class="{{ $field }} w-full">
            </div>
            <div>
                <label for="bm-s" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-400">Status</label>
                <select id="bm-s" name="status" class="{{ $field }}"><option value="">All</option>@foreach (['Paid', 'Unpaid'] as $s)<option @selected($status === $s)>{{ $s }}</option>@endforeach</select>
            </div>
            <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Search</button>
            <a href="{{ route('revenue.bill-master') }}" class="rounded-lg border border-slate-300 px-3 py-2 text-sm text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Reset</a>
        </form>

        <x-ui.panel title="Bills" :subtitle="number_format($bills->total()).' bill(s)'" :padded="false">
            @include('ui.revenue.partials.bills-table', ['bills' => $bills])
        </x-ui.panel>
    </div>
</x-app-layout>
