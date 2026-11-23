<x-app-layout title="Dashboard">
    @php
        // Card colour => [icon tile, figure text], light and dark (KLAES module dashboard tones).
        $tone = [
            'indigo' => ['bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-300', 'text-indigo-700 dark:text-indigo-300'],
            'blue' => ['bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-300', 'text-sky-700 dark:text-sky-300'],
            'green' => ['bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-300', 'text-emerald-700 dark:text-emerald-300'],
            'amber' => ['bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-300', 'text-amber-700 dark:text-amber-300'],
            'rose' => ['bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-300', 'text-rose-700 dark:text-rose-300'],
            'purple' => ['bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-300', 'text-violet-700 dark:text-violet-300'],
        ];
        $icons = [
            'document' => 'M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z',
            'cash' => 'M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z',
            'folder-plus' => 'M12 10.5v6m3-3H9m4.06-7.19-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z',
            'building' => 'M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z',
            'check-circle' => 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
            'arrow-uturn' => 'M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3',
            'flag' => 'M3 3v1.5M3 21v-6m0 0 2.77-.693a9 9 0 0 1 6.208.682l.108.054a9 9 0 0 0 6.086.71l3.114-.732a48.524 48.524 0 0 1-.005-10.499l-3.11.732a9 9 0 0 1-6.085-.711l-.108-.054a9 9 0 0 0-6.208-.682L3 4.5M3 15V4.5',
            'map-pin' => 'M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z',
            'plus' => 'M12 4.5v15m7.5-7.5h-15',
        ];
        $svg = fn ($name, $class) => '<svg class="'.$class.'" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7"><path stroke-linecap="round" stroke-linejoin="round" d="'.$icons[$name].'" /></svg>';
        $naira = fn ($n) => '₦'.number_format((float) $n, 2);
    @endphp

    <div class="space-y-6">
        <div class="flex flex-wrap items-center gap-4 rounded-2xl border border-slate-200 bg-white px-6 py-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <img src="{{ asset('images/logo.png') }}" alt="" class="h-14 w-14 shrink-0 object-contain">
            <div class="min-w-0 flex-1">
                <p class="text-[10px] font-semibold uppercase tracking-[0.25em] text-indigo-600 dark:text-indigo-400">KLAES-CONTROL · Kano State Development Control Agency</p>
                <h1 class="mt-1 text-xl font-semibold text-slate-900 sm:text-2xl dark:text-slate-100">Welcome, {{ Auth::user()->name }}</h1>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Building Plan Approval at a glance: where every file is, and what is waiting on each desk.</p>
            </div>
            <a href="{{ route('bpa.applications.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">
                {!! $svg('plus', 'h-4 w-4') !!} New application
            </a>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($d['money'] as $m)
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $m['label'] }}</div>
                    <div class="mt-2 text-2xl font-bold {{ $tone[$m['colour']][1] }}">{{ $naira($m['total']) }}</div>
                    <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $m['hint'] }}</div>
                    @if ($m['month'] !== null)
                        <div class="mt-3 border-t border-slate-100 pt-2 text-xs dark:border-slate-800 {{ $m['month'] > 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-400' }}">
                            {{ $m['month'] > 0 ? '+'.$naira($m['month']) : 'None' }} this month
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($d['cards'] as $card)
                @php [$tile, $figure] = $tone[$card['colour']]; @endphp
                <{{ $card['url'] ? 'a' : 'div' }} @if ($card['url']) href="{{ $card['url'] }}" @endif
                    class="block rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 {{ $card['url'] ? 'transition hover:border-slate-300 hover:shadow-md dark:hover:border-slate-700' : '' }}">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $card['label'] }}</div>
                            <div class="mt-2 text-3xl font-bold {{ $figure }}">{{ number_format($card['total']) }}</div>
                        </div>
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $tile }}">{!! $svg($card['icon'], 'h-5 w-5') !!}</span>
                    </div>
                    <div class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $card['hint'] }}</div>
                    @if ($card['month'] !== null)
                        <div class="mt-3 border-t border-slate-100 pt-2 text-xs dark:border-slate-800 {{ $card['month'] > 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-slate-400' }}">
                            {{ $card['month'] > 0 ? '+'.number_format($card['month']) : 'None' }} this month
                        </div>
                    @endif
                </{{ $card['url'] ? 'a' : 'div' }}>
            @endforeach
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">Go to</h2>
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($d['links'] as $link)
                    <a href="{{ $link['url'] }}" class="flex items-center gap-3 rounded-lg border border-slate-100 px-3 py-2.5 text-sm text-slate-700 hover:border-slate-200 hover:bg-slate-50 dark:border-slate-800 dark:text-slate-300 dark:hover:border-slate-700 dark:hover:bg-slate-800">
                        {!! $svg($link['icon'], 'h-4 w-4 text-slate-500 dark:text-slate-400') !!}
                        <span class="flex-1">{{ $link['label'] }}</span>
                        <svg class="h-4 w-4 text-slate-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>
                    </a>
                @endforeach
            </div>
        </div>

        <p class="text-xs text-slate-400">Figures are live. "This month" counts from {{ now()->startOfMonth()->format('j F Y') }}.</p>
    </div>
</x-app-layout>
