{{-- KAMMA > Planning Recommendation Approval, laid out like KLAES programmes.approvals.planning_recomm?url=approval:
     figure cards (Total / Approved / Declined / Pending), status filter and search, then the applications table.
     KAMMA's recommendations are kept in KAMMA's own schema; how they reach KADCA is an open question, so the table is empty. --}}
<x-app-layout title="Planning Recommendation Approval">
    @php $field = 'rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100'; @endphp

    <div class="space-y-6">
        <x-ui.page-head icon="clipboard-check" title="Planning Recommendation Approval" subtitle="KAMMA planning recommendations on land and Post-CofO applications" tone="teal" section="KAMMA" />

        <div class="flex items-start gap-3 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-800 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-300">
            <i data-lucide="info" class="mt-0.5 h-4 w-4 shrink-0"></i>
            <p>KAMMA records its recommendations in KLAES-METRO. They will be listed here, read-only, once the way KAMMA's output is shared with KADCA is agreed.</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.stat title="Total Applications" value="0" icon="layers" tone="blue" />
            <x-ui.stat title="Approved" value="0" icon="check-circle-2" tone="emerald" />
            <x-ui.stat title="Declined" value="0" icon="x-circle" tone="rose" />
            <x-ui.stat title="Pending" value="0" icon="hourglass" tone="amber" />
        </div>

        <form method="GET" class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="min-w-[16rem] flex-1">
                <label for="pr-q" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-400">Search</label>
                <input id="pr-q" type="search" name="q" value="{{ $q }}" placeholder="File no., shadow file no., applicant" class="{{ $field }} w-full">
            </div>
            <div>
                <label for="pr-s" class="mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-400">Status</label>
                <select id="pr-s" name="status" class="{{ $field }}"><option value="">All…</option>@foreach (['Pending', 'Approved', 'Declined'] as $s)<option @selected($status === $s)>{{ $s }}</option>@endforeach</select>
            </div>
            <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-500">Search</button>
        </form>

        <x-ui.panel title="Applications" :padded="false">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                        <tr>
                            @foreach (['S/N', 'File No.', 'Shadow File No.', 'Applicant', 'Application Type', 'Plot No.', 'District', 'LGA', 'Location', 'Recommendation', 'Status', 'Date'] as $h)
                                <th class="whitespace-nowrap px-4 py-3">{{ $h }}</th>
                            @endforeach
                            <th class="px-4 py-3"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                </table>
            </div>
            <x-ui.empty icon="clipboard-check" title="No planning recommendations to show">Sub-division, merger, extension and change-of-purpose recommendations from KAMMA will be listed here with their approval.</x-ui.empty>
        </x-ui.panel>
    </div>
</x-app-layout>
