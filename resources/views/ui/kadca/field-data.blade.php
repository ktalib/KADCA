{{-- KADCA > Field Data Integration. What the KLAES-CONTROL mobile app (one app: Initial Site Visit, then Inspection / Site
     Assessment; briefing 2026-11-22) sends from site, matched to the BP file and merged into the officer's web report.
     Fresh design in KLAES style (KLAES's nearest is SLTR Field Data). The app is not built yet, so nothing has synced;
     an export from the app can be imported (preview). --}}
<x-app-layout title="Field Data Integration">
    @php
        $label = 'mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-400';
        $field = 'w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100';
    @endphp

    <div class="space-y-6" x-data="{ tab: 'synced' }">
        <x-ui.page-head icon="smartphone" title="Field Data Integration" subtitle="Site visits and inspections captured on the KLAES-CONTROL app, matched to BP files" tone="violet" section="KADCA">
            <a href="{{ route('admin.mobile-apps') }}" class="inline-flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800"><i data-lucide="download" class="h-4 w-4"></i> Get the app</a>
        </x-ui.page-head>
        <x-ui.preview-note what="Importing field data">The KLAES-CONTROL app is not published yet, so no submission has synced.</x-ui.preview-note>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-ui.stat title="Synced submissions" value="0" hint="Received from the app" icon="cloud-download" tone="blue" />
            <x-ui.stat title="Awaiting integration" value="0" hint="Not yet matched to a report" icon="hourglass" tone="amber" />
            <x-ui.stat title="Integrated" value="0" hint="Merged into an inspection report" icon="check-circle-2" tone="emerald" />
            <x-ui.stat title="Files awaiting site data" :value="number_format($files->count())" hint="BP files at the Zonal Office" icon="map-pinned" tone="violet" />
        </div>

        <div class="flex gap-1 rounded-xl border border-slate-200 bg-white p-1 text-sm shadow-sm dark:border-slate-800 dark:bg-slate-900">
            @foreach (['synced' => 'Synced from the app', 'files' => 'Files awaiting site data', 'import' => 'Import an export'] as $k => $l)
                <button type="button" @click="tab = '{{ $k }}'" :class="tab === '{{ $k }}' ? 'bg-violet-600 text-white' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'" class="flex-1 rounded-lg px-3 py-2 font-medium">{{ $l }}</button>
            @endforeach
        </div>

        <x-ui.panel title="Synced from the app" :padded="false" x-show="tab === 'synced'">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                        <tr>@foreach (['S/N', 'Submission', 'Type', 'BP File No.', 'Plot No.', 'District', 'LGA', 'Location', 'Officer', 'Photos', 'Captured', 'Status'] as $h)<th class="whitespace-nowrap px-4 py-3">{{ $h }}</th>@endforeach<th class="px-4 py-3"><span class="sr-only">Actions</span></th></tr>
                    </thead>
                </table>
            </div>
            <x-ui.empty icon="smartphone" title="Nothing has synced yet">Each Initial Site Visit and Inspection saved on the app appears here with its geocoded photos, ready to merge into the report.</x-ui.empty>
        </x-ui.panel>

        <x-ui.panel title="Files awaiting site data" :padded="false" x-show="tab === 'files'" x-cloak>
            @if ($files->isEmpty())
                <x-ui.empty icon="map-pinned" title="No file at the Zonal Office" />
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                            <tr><th class="px-4 py-3">S/N</th><th class="px-4 py-3">BP File No.</th><th class="px-4 py-3">Developer</th><th class="px-4 py-3">Plot No.</th><th class="px-4 py-3">District</th><th class="px-4 py-3">LGA</th><th class="px-4 py-3">Location</th><th class="px-4 py-3">Site visit</th><th class="px-4 py-3">Inspection</th><th class="sticky right-0 bg-slate-50 px-4 py-3 dark:bg-slate-800"><span class="sr-only">Actions</span></th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($files as $a)
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                    <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $loop->iteration }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 font-mono text-xs font-semibold text-indigo-700 dark:text-indigo-300">{{ $a->bp_file_no }}</td>
                                    <td class="px-4 py-3">{{ $a->developer_name }}</td>
                                    <td class="px-4 py-3">{{ $a->plot_no ?: '—' }}</td><td class="px-4 py-3">{{ $a->district ?: '—' }}</td><td class="px-4 py-3">{{ $a->lga ?: '—' }}</td><td class="px-4 py-3">{{ $a->location ?: '—' }}</td>
                                    <td class="px-4 py-3"><span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">Not yet</span></td>
                                    <td class="px-4 py-3"><span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">Not yet</span></td>
                                    <td class="sticky right-0 bg-white px-4 py-2 text-right dark:bg-slate-900">
                                        <x-row-actions>
                                            <x-row-action :href="route('kadca.inspection', ['file' => $a->id])"><x-slot:icon><i data-lucide="camera" class="h-4 w-4"></i></x-slot:icon> File report on the web</x-row-action>
                                            <x-row-action :href="route('bpa.applications.show', $a)"><x-slot:icon><i data-lucide="file-text" class="h-4 w-4"></i></x-slot:icon> Open application</x-row-action>
                                        </x-row-actions>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-ui.panel>

        <x-ui.panel title="Import an export from the app" subtitle="For a phone that could not sync: its export file (ZIP with photos, or JSON)" x-show="tab === 'import'" x-cloak>
            <form method="POST" action="{{ route('ui.preview', 'field-data-import') }}" enctype="multipart/form-data" class="grid gap-4 sm:grid-cols-2">
                @csrf
                <div class="sm:col-span-2"><x-ui.file-picker name="bpa_application_id" source="bp" label="BP file" /></div>
                <div><label for="fd-type" class="{{ $label }}">Submission type <span class="text-rose-500">*</span></label><select id="fd-type" name="type" required class="{{ $field }}"><option value="">Select…</option><option>Initial Site Visit</option><option>Inspection / Site Assessment</option></select></div>
                <div><label for="fd-file" class="{{ $label }}">Export file <span class="text-rose-500">*</span></label><input id="fd-file" type="file" name="export" required accept=".zip,.json" class="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-violet-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-violet-700 dark:text-slate-300 dark:file:bg-violet-500/10 dark:file:text-violet-300"></div>
                <div class="flex justify-end sm:col-span-2"><button class="rounded-lg bg-violet-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-violet-500">Import</button></div>
            </form>
        </x-ui.panel>
    </div>
</x-app-layout>
