{{-- KLAES REV-M > Receipting > e-Receipt / Print Receipt (KLAES receipts.index {mode}): paid bills and their receipts. --}}
<x-app-layout :title="$mode === 'print' ? 'Print Receipt' : 'e-Receipt'">
    <div class="space-y-6">
        <x-ui.page-head :icon="$mode === 'print' ? 'printer' : 'receipt'" :title="$mode === 'print' ? 'Print Receipt' : 'e-Receipt'"
                        :subtitle="$mode === 'print' ? 'Print the Original, Duplicate or Triplicate of a receipt' : 'Electronic receipts for paid bills'" tone="emerald" section="KLAES REV-M › Receipting" />

        <form method="GET" class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="min-w-[16rem] flex-1">
                <label for="rc-q" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-400">Search</label>
                <input id="rc-q" type="search" name="q" value="{{ $q }}" placeholder="Bill no., receipt no., developer, application no." class="w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100">
            </div>
            <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Search</button>
        </form>

        <x-ui.panel :title="number_format($bills->total()).' receipt(s)'" :padded="false">
            @include('ui.revenue.partials.bills-table', ['bills' => $bills, 'receiptMode' => $mode])
        </x-ui.panel>
    </div>
</x-app-layout>
