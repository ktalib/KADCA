{{-- KLAES REV-M e-Receipt / Print (copied in layout from KLAES revenue/receipts/official): one paid KADCA bill as an official
     receipt, Original / Duplicate / Triplicate. ?print=1 opens the print dialog. The QR carries the bill number so Verify QRCode
     can find it. KADCA has no RCT- receipt series yet, so the receipt is numbered by its bill. --}}
@php
    $a = $bill->application;
    $copies = ['Original', 'Duplicate', 'Triplicate'];
    $copy = in_array($copy, $copies, true) ? $copy : 'Original';
    $words = function (float $n) {
        try {
            return ucfirst((new \NumberFormatter('en', \NumberFormatter::SPELLOUT))->format((int) round($n))).' naira only';
        } catch (\Throwable) {
            return number_format($n, 2).' naira';
        }
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <x-theme-init />
    <title>Receipt {{ $bill->bill_no }} &mdash; {{ config('app.name', 'KADCA') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>@media print { .no-print { display: none !important; } body { background: #fff !important; } @page { size: A5 landscape; margin: 8mm; } }</style>
</head>
<body class="min-h-screen bg-slate-100 py-8 font-sans text-slate-800 dark:bg-slate-950 dark:text-slate-200 print:py-0">
    <div class="no-print mx-auto mb-4 flex max-w-3xl flex-wrap items-center justify-between gap-3 px-4">
        <div class="flex flex-wrap gap-2">
            @foreach ($copies as $c)
                <a href="{{ route('revenue.receipt', ['bill' => $bill, 'copy' => $c]) }}" @class(['rounded-lg px-3 py-1.5 text-sm font-medium', 'bg-indigo-600 text-white' => $c === $copy, 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300' => $c !== $copy])>{{ $c }}</a>
            @endforeach
        </div>
        <div class="flex items-center gap-2">
            <x-theme-toggle />
            <button type="button" onclick="window.print()" class="rounded-lg bg-emerald-600 px-4 py-1.5 text-sm font-semibold text-white hover:bg-emerald-500">Print</button>
        </div>
    </div>

    <article class="mx-auto max-w-3xl rounded-2xl border border-slate-200 bg-white p-8 shadow-sm dark:border-slate-800 dark:bg-slate-900 print:max-w-none print:rounded-none print:border-0 print:p-0 print:shadow-none print:text-black">
        <header class="flex items-start justify-between gap-6 border-b-2 border-emerald-600 pb-4">
            <div class="flex items-center gap-4">
                <img src="{{ asset('images/logo.png') }}" alt="" class="h-16 w-16 object-contain">
                <div>
                    <p class="text-lg font-black uppercase tracking-wide text-slate-900 dark:text-white print:text-black">Kano State Development Control Agency</p>
                    <p class="text-xs text-slate-500 dark:text-slate-400">KLAES-CONTROL &middot; Part of the KLAES 2.0 Suite</p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-xl font-black tracking-wider text-emerald-700 dark:text-emerald-400">OFFICIAL RECEIPT</p>
                <p class="mt-1 inline-block rounded bg-slate-100 px-2 py-0.5 text-xs font-semibold uppercase tracking-wide text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $copy }}</p>
            </div>
        </header>

        <div class="mt-6 grid gap-6 sm:grid-cols-[1fr_auto]">
            <dl class="grid grid-cols-[10rem_1fr] gap-x-4 gap-y-2 text-sm">
                <dt class="text-slate-500 dark:text-slate-400">Receipt for bill</dt><dd class="font-mono font-semibold">{{ $bill->bill_no }}</dd>
                <dt class="text-slate-500 dark:text-slate-400">Payment reference</dt><dd class="font-mono">{{ $bill->receipt_no ?: '—' }}</dd>
                <dt class="text-slate-500 dark:text-slate-400">Date paid</dt><dd>{{ $bill->paid_on?->format('d F Y') ?: '—' }}</dd>
                <dt class="text-slate-500 dark:text-slate-400">Received from</dt><dd class="font-semibold">{{ $a?->developer_name ?: '—' }}</dd>
                <dt class="text-slate-500 dark:text-slate-400">The sum of</dt><dd class="italic">{{ $words((float) ($bill->amount_paid ?? $bill->amount)) }}</dd>
                <dt class="text-slate-500 dark:text-slate-400">Being payment for</dt><dd>{{ $bill->bill_type }}{{ $a ? ' — application '.$a->application_no : '' }}</dd>
                <dt class="text-slate-500 dark:text-slate-400">File No.</dt><dd class="font-mono">{{ $a?->bp_file_no ?: ($a?->file_number ?: '—') }}</dd>
                <dt class="text-slate-500 dark:text-slate-400">Plot No.</dt><dd>{{ $a?->plot_no ?: '—' }}</dd>
                <dt class="text-slate-500 dark:text-slate-400">District</dt><dd>{{ $a?->district ?: '—' }}</dd>
                <dt class="text-slate-500 dark:text-slate-400">LGA</dt><dd>{{ $a?->lga ?: '—' }}</dd>
                <dt class="text-slate-500 dark:text-slate-400">Location</dt><dd>{{ $a?->location ?: '—' }}</dd>
                <dt class="text-slate-500 dark:text-slate-400">Method</dt><dd>{{ $bill->payment_channel === 'Other' ? ($bill->payment_channel_other ?: 'Other') : ($bill->payment_channel ?: '—') }}</dd>
            </dl>
            <div class="flex flex-col items-center gap-2">
                <div class="rounded-lg bg-white p-2">{!! \App\Support\Qr::svg($bill->bill_no, 130) !!}</div>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">Verify under System Admin › Verify QRCode</p>
            </div>
        </div>

        <div class="mt-6 flex items-center justify-between rounded-xl bg-emerald-50 px-5 py-4 dark:bg-emerald-500/10 print:bg-transparent print:border print:border-black">
            <span class="text-sm font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-300 print:text-black">Amount</span>
            <span class="text-2xl font-black tabular-nums text-emerald-800 dark:text-emerald-300 print:text-black">₦{{ number_format((float) ($bill->amount_paid ?? $bill->amount), 2) }}</span>
        </div>

        <footer class="mt-10 grid grid-cols-2 gap-10 text-center text-xs text-slate-500 dark:text-slate-400">
            <div><div class="border-t border-slate-400 pt-1">Customer Signature</div></div>
            <div><div class="border-t border-slate-400 pt-1">For: Kano State Development Control Agency<br>{{ $bill->payment_recorded_by_name ?: '' }}</div></div>
        </footer>
    </article>

    @if (request()->boolean('print'))
        <script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
    @endif
</body>
</html>
