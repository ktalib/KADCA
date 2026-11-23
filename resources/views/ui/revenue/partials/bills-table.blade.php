{{-- KADCA bills table shared by the REV-M screens. $bills (collection or paginator), $paginated, $receiptMode (e-receipt|print|null). --}}
@if ($bills->isEmpty())
    <x-ui.empty icon="receipt" title="No bills here yet">Bills are raised when the Assessment Stage receives an application; payments are recorded under Architecture › Payment.</x-ui.empty>
@else
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                <tr>
                    <th class="px-4 py-3">S/N</th><th class="px-4 py-3">Bill No.</th><th class="px-4 py-3">Bill Type</th><th class="px-4 py-3">Payer</th><th class="px-4 py-3">File No.</th>
                    <th class="px-4 py-3">Plot No.</th><th class="px-4 py-3">District</th><th class="px-4 py-3">LGA</th>
                    <th class="px-4 py-3 text-right">Amount</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Receipt No.</th><th class="px-4 py-3">Paid</th>
                    <th class="sticky right-0 bg-slate-50 px-4 py-3 dark:bg-slate-800"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($bills as $b)
                    @php $a = $b->application; @endphp
                    <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                        <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ ($paginated ?? true) ? $bills->firstItem() + $loop->index : $loop->iteration }}</td>
                        <td class="whitespace-nowrap px-4 py-3 font-mono text-xs font-semibold text-indigo-700 dark:text-indigo-300">{{ $b->bill_no }}</td>
                        <td class="px-4 py-3 text-slate-700 dark:text-slate-300">{{ $b->bill_type }}</td>
                        <td class="px-4 py-3 text-slate-800 dark:text-slate-200">{{ $a?->developer_name ?: '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-slate-600 dark:text-slate-300">{{ $a?->bp_file_no ?: $a?->file_number }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $a?->plot_no ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $a?->district ?: '—' }}</td>
                        <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $a?->lga ?: '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums font-semibold text-slate-900 dark:text-white">₦{{ number_format((float) $b->amount, 2) }}</td>
                        <td class="px-4 py-3"><span @class(['rounded-full px-2 py-0.5 text-xs font-medium', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' => $b->status === 'Paid', 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300' => $b->status !== 'Paid'])>{{ $b->status }}</span></td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-600 dark:text-slate-300">{{ $b->receipt_no ?: '—' }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-slate-500 dark:text-slate-400">{{ $b->paid_on?->format('d M Y') ?: '—' }}</td>
                        <td class="sticky right-0 bg-white px-4 py-2 text-right dark:bg-slate-900">
                            <x-row-actions>
                                @if ($a)
                                    <x-row-action :href="route('bpa.applications.show', $a)">
                                        <x-slot:icon><i data-lucide="file-text" class="h-4 w-4"></i></x-slot:icon>
                                        Open application
                                    </x-row-action>
                                @endif
                                @if ($b->status === 'Paid')
                                    <x-row-action :href="route('revenue.receipt', $b)" target="_blank">
                                        <x-slot:icon><i data-lucide="receipt" class="h-4 w-4"></i></x-slot:icon>
                                        View e-Receipt
                                    </x-row-action>
                                    <x-row-action :href="route('revenue.receipt', ['bill' => $b, 'copy' => 'Duplicate', 'print' => 1])" target="_blank">
                                        <x-slot:icon><i data-lucide="printer" class="h-4 w-4"></i></x-slot:icon>
                                        Print receipt
                                    </x-row-action>
                                @else
                                    <x-row-action :href="route('bpa.payments.index')">
                                        <x-slot:icon><i data-lucide="banknote" class="h-4 w-4"></i></x-slot:icon>
                                        Record payment
                                    </x-row-action>
                                @endif
                            </x-row-actions>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if ($paginated ?? true)
        <div class="border-t border-slate-100 px-6 py-4 dark:border-slate-800">{{ $bills->links() }}</div>
    @endif
@endif
