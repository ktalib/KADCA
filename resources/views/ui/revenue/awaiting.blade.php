{{-- KLAES REV-M > Receipting > Generate Receipt > Awaiting Receipts (KLAES receipts.awaiting): paid bills waiting for an
     official KADCA receipt (RCT- number); the bank/POS receipt number recorded at payment is shown. --}}
<x-app-layout title="Awaiting Receipts">
    <div class="space-y-6">
        <x-ui.page-head icon="hourglass" title="Awaiting Receipts" subtitle="Paid bills waiting for an official receipt" tone="amber" section="KLAES REV-M › Receipting" />
        <x-ui.preview-note what="Issuing official (RCT-) receipts">Each paid bill's e-Receipt can already be viewed and printed.</x-ui.preview-note>
        <x-ui.panel :title="number_format($bills->total()).' paid bill(s)'" :padded="false">
            @include('ui.revenue.partials.bills-table', ['bills' => $bills])
        </x-ui.panel>
    </div>
</x-app-layout>
