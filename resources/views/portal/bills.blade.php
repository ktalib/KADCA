@extends('portal.layouts.portal')

@section('title', 'Bills')

@section('content')
@php
    $th = 'whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400';
    $td = 'whitespace-nowrap px-4 py-3 text-sm text-slate-700 dark:text-slate-300';
    $pill = fn ($s) => $s === 'Paid'
        ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300'
        : 'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-300';
    $outstanding = $applications->filter(fn ($a) => $a['bill_outstanding'])->sum(fn ($a) => $a['bill']['amount']);
@endphp

<div class="mb-6 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Bills</h1>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-400">Every bill on your applications. Open an application to see how to pay.</p>
    </div>
    @if ($outstanding > 0)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 dark:border-amber-500/30 dark:bg-amber-500/10">
            <p class="text-[10px] font-bold uppercase tracking-widest text-amber-700 dark:text-amber-300">To pay</p>
            <p class="text-lg font-bold text-amber-900 dark:text-amber-100">₦{{ number_format($outstanding, 2) }}</p>
        </div>
    @endif
</div>

@if ($applications->isEmpty())
    <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center dark:border-slate-700 dark:bg-slate-900">
        <i data-lucide="receipt" class="mx-auto mb-4 h-10 w-10 text-slate-300 dark:text-slate-600" aria-hidden="true"></i>
        <p class="text-base font-semibold text-slate-900 dark:text-white">No bills yet</p>
        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">The application bill appears here as soon as you submit an application.</p>
    </div>
@else
    {{-- Phones: one card per bill --}}
    <ul class="space-y-3 md:hidden">
        @foreach ($applications as $a)
            <li>
                <a href="{{ route('portal.applications.show', $a['ref']) }}#bill" class="block rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate font-mono text-sm font-bold text-slate-900 dark:text-white">{{ $a['bill']['bill_no'] }}</p>
                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ $a['bill']['bill_type'] }} &middot; {{ $a['ref'] }}</p>
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide {{ $pill($a['bill']['status']) }}">{{ $a['bill']['status'] }}</span>
                    </div>
                    <p class="mt-2 text-lg font-bold text-slate-900 dark:text-white">₦{{ number_format($a['bill']['amount'], 2) }}</p>
                </a>
            </li>
        @endforeach
    </ul>

    {{-- Larger screens: table, S/N by display order, actions in the three-dots menu (KLAES rules) --}}
    <div class="hidden overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm md:block dark:border-slate-800 dark:bg-slate-900">
        <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800">
            <thead class="bg-slate-50 dark:bg-slate-800/60">
                <tr>
                    <th class="{{ $th }}">S/N</th>
                    <th class="{{ $th }}">Bill No.</th>
                    <th class="{{ $th }}">Application</th>
                    <th class="{{ $th }}">Type</th>
                    <th class="{{ $th }} text-right">Amount</th>
                    <th class="{{ $th }}">Status</th>
                    <th class="{{ $th }}">Paid</th>
                    <th class="{{ $th }} sticky right-0 bg-slate-50 text-right dark:bg-slate-800"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                @foreach ($applications as $a)
                    <tr>
                        <td class="{{ $td }}">{{ $loop->iteration }}</td>
                        <td class="{{ $td }} font-mono font-semibold text-slate-900 dark:text-white">{{ $a['bill']['bill_no'] }}</td>
                        <td class="{{ $td }} font-mono">{{ $a['ref'] }}</td>
                        <td class="{{ $td }}">{{ $a['bill']['bill_type'] }}</td>
                        <td class="{{ $td }} text-right font-semibold">₦{{ number_format($a['bill']['amount'], 2) }}</td>
                        <td class="{{ $td }}"><span class="rounded-full px-2.5 py-1 text-[10px] font-bold uppercase tracking-wide {{ $pill($a['bill']['status']) }}">{{ $a['bill']['status'] }}</span></td>
                        <td class="{{ $td }}">{{ $a['bill']['paid_on'] ? $a['bill']['paid_on']->format('j M Y') : '—' }}</td>
                        <td class="{{ $td }} sticky right-0 bg-white text-right dark:bg-slate-900">
                            <x-row-actions>
                                <x-row-action :href="route('portal.applications.show', $a['ref']).'#bill'">
                                    <x-slot name="icon"><i data-lucide="{{ $a['bill_outstanding'] ? 'wallet' : 'receipt' }}" class="h-4 w-4"></i></x-slot>
                                    {{ $a['bill_outstanding'] ? 'How to pay' : 'View bill' }}
                                </x-row-action>
                                <x-row-action :href="route('portal.applications.show', $a['ref'])">
                                    <x-slot name="icon"><i data-lucide="folder-open" class="h-4 w-4"></i></x-slot>
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
@endsection
