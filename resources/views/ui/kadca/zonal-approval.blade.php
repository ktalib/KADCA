{{-- KADCA > Approvals > Head of Zonal Office > Approval. Briefing step 6: the head assigns the file to an officer with an
     assignment form "like I-to-S" (KLAES Cadastral Instruction to Surveyor: instruction no., date, officer, file, plot, job
     location, scope, instructions, issued by), then approves the officer's report before conveyance. Tabs as KLAES approval
     pages (Awaiting / Assigned / Reports). No role gating (Zonal Office roles on hold); saves post to the preview stub. --}}
<x-app-layout title="Head of Zonal Office">
    @php
        $label = 'mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-400';
        $field = 'w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100';
        $tabs = ['awaiting' => 'Awaiting assignment', 'assigned' => 'Assigned to officers', 'reports' => 'Reports to approve'];
    @endphp

    <div class="space-y-6" x-data="{ assign: null, decide: null, officerQ: '', officers: [], officer: null,
            async findOfficer() {
                if (this.officerQ.trim().length < 2) { this.officers = []; return; }
                try { const r = await fetch(@js(route('tracking.log.officers')) + '?q=' + encodeURIComponent(this.officerQ), { headers: { Accept: 'application/json' } }); this.officers = r.ok ? ((await r.json()).officers || []) : []; } catch (e) { this.officers = []; }
            } }">
        <x-ui.page-head icon="user-cog" title="Head of Zonal Office" subtitle="Assign BP files to inspecting officers and approve their reports" tone="violet" section="KADCA › Approvals" />
        <x-ui.preview-note what="Assigning officers and approving reports" />

        <div class="grid gap-4 sm:grid-cols-3">
            <x-ui.stat title="Awaiting assignment" :value="number_format($files->count())" hint="Sent on by the Deputy Director (Arch.)" icon="inbox" tone="amber" />
            <x-ui.stat title="Assigned to officers" value="0" hint="Site visit / inspection in progress" icon="user-check" tone="blue" />
            <x-ui.stat title="Reports to approve" value="0" hint="Filed by officers" icon="clipboard-check" tone="emerald" />
        </div>

        <nav class="flex gap-1 rounded-xl border border-slate-200 bg-white p-1 text-sm shadow-sm dark:border-slate-800 dark:bg-slate-900">
            @foreach ($tabs as $k => $l)
                <a href="{{ route('kadca.zonal-approval', ['tab' => $k]) }}" @class(['flex-1 rounded-lg px-3 py-2 text-center font-medium', 'bg-violet-600 text-white' => $tab === $k, 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' => $tab !== $k])>{{ $l }}</a>
            @endforeach
        </nav>

        <x-ui.panel :title="$tabs[$tab]" :padded="false">
            @if ($tab !== 'awaiting' || $files->isEmpty())
                <x-ui.empty icon="{{ $tab === 'reports' ? 'clipboard-check' : 'user-check' }}" :title="$tab === 'awaiting' ? 'No file awaiting assignment' : ($tab === 'assigned' ? 'No file assigned yet' : 'No report to approve yet')">
                    {{ $tab === 'awaiting' ? 'Files arrive when the Deputy Director (Arch.) recommends them.' : 'Assignments and filed inspection reports will be listed here once the Zonal Office backend is built.' }}
                </x-ui.empty>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                            <tr><th class="px-4 py-3">S/N</th><th class="px-4 py-3">BP File No.</th><th class="px-4 py-3">Land File No.</th><th class="px-4 py-3">Developer</th><th class="px-4 py-3">Plot No.</th><th class="px-4 py-3">District</th><th class="px-4 py-3">LGA</th><th class="px-4 py-3">Location</th><th class="px-4 py-3">Received</th><th class="sticky right-0 bg-slate-50 px-4 py-3 dark:bg-slate-800"><span class="sr-only">Actions</span></th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($files as $a)
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                    <td class="px-4 py-3 text-slate-500 dark:text-slate-400">{{ $loop->iteration }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 font-mono text-xs font-semibold text-indigo-700 dark:text-indigo-300">{{ $a->bp_file_no }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 font-mono text-xs">{{ $a->file_number }}</td>
                                    <td class="px-4 py-3">{{ $a->developer_name }}</td>
                                    <td class="px-4 py-3">{{ $a->plot_no ?: '—' }}</td><td class="px-4 py-3">{{ $a->district ?: '—' }}</td><td class="px-4 py-3">{{ $a->lga ?: '—' }}</td><td class="px-4 py-3">{{ $a->location ?: '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-slate-500 dark:text-slate-400">{{ $a->updated_at?->format('d M Y') }}</td>
                                    <td class="sticky right-0 bg-white px-4 py-2 text-right dark:bg-slate-900">
                                        <x-row-actions>
                                            <button type="button" @click.stop="assign = @js(['id' => $a->id, 'bp' => $a->bp_file_no, 'title' => $a->developer_name, 'plot' => $a->plot_no, 'loc' => collect([$a->district, $a->lga])->filter()->implode(', ')]); officer = null; officerQ = ''; officers = []; open = false"
                                                    class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800">
                                                <i data-lucide="user-plus" class="h-4 w-4 text-slate-400"></i> Assign officer
                                            </button>
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

        {{-- Assignment form (like KLAES Instruction to Surveyor) --}}
        <div x-show="assign" x-cloak class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-slate-900/50 p-4 sm:p-10" @keydown.escape.window="assign = null">
            <form method="POST" action="{{ route('ui.preview', 'zonal-assignment') }}" @click.outside="assign = null" class="w-full max-w-2xl space-y-5 rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-900">
                @csrf
                <input type="hidden" name="bpa_application_id" :value="assign?.id">
                <div class="flex items-start justify-between">
                    <div><h2 class="text-lg font-bold text-slate-900 dark:text-white">Instruction to Inspect</h2><p class="text-sm text-slate-500 dark:text-slate-400">Zonal Office assignment</p></div>
                    <button type="button" @click="assign = null" class="text-xl leading-none text-slate-400 hover:text-slate-700 dark:hover:text-slate-200" aria-label="Close">&times;</button>
                </div>
                <dl class="grid gap-3 rounded-xl bg-slate-50 p-4 text-sm sm:grid-cols-2 dark:bg-slate-800/60">
                    <div><dt class="{{ $label }}">Instruction No.</dt><dd class="text-slate-500 dark:text-slate-400">Given on save</dd></div>
                    <div><dt class="{{ $label }}">Date</dt><dd>{{ now()->format('d M Y') }}</dd></div>
                    <div><dt class="{{ $label }}">File Number</dt><dd class="font-mono" x-text="assign?.bp"></dd></div>
                    <div><dt class="{{ $label }}">File Title</dt><dd x-text="assign?.title"></dd></div>
                    <div><dt class="{{ $label }}">Plot Number</dt><dd x-text="assign?.plot || '—'"></dd></div>
                    <div><dt class="{{ $label }}">Job Location</dt><dd x-text="assign?.loc || '—'"></dd></div>
                </dl>
                <div class="relative">
                    <label for="za-off" class="{{ $label }}">Inspecting officer <span class="text-rose-500">*</span></label>
                    <input type="hidden" name="officer_id" :value="officer?.id" required>
                    <template x-if="officer"><div class="flex items-center justify-between rounded-lg bg-violet-50 px-3 py-2 text-sm dark:bg-violet-500/10"><span><span class="font-semibold" x-text="officer.name"></span> <span class="text-xs text-slate-500 dark:text-slate-400" x-text="officer.rank || ''"></span></span><button type="button" @click="officer = null" class="text-xs text-violet-700 hover:underline dark:text-violet-300">Change</button></div></template>
                    <div x-show="! officer">
                        <input id="za-off" type="search" x-model="officerQ" @input.debounce.350ms="findOfficer()" :required="! officer" placeholder="Search staff by name or username" autocomplete="off" class="{{ $field }}">
                        <ul x-show="officers.length" class="absolute z-10 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg dark:border-slate-700 dark:bg-slate-900">
                            <template x-for="o in officers" :key="o.id"><li><button type="button" @click="officer = o; officers = []" class="block w-full px-3 py-2 text-left text-sm hover:bg-slate-50 dark:hover:bg-slate-800"><span x-text="o.name"></span> <span class="text-xs text-slate-500" x-text="o.rank || ''"></span></button></li></template>
                        </ul>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">KADCA's zonal officer list is still to come; any KLAES staff account can be picked for now.</p>
                    </div>
                </div>
                <fieldset>
                    <legend class="{{ $label }}">Scope <span class="text-rose-500">*</span></legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach (['Initial Site Visit', 'Inspection / Site Assessment'] as $s)
                            <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm dark:border-slate-700"><input type="checkbox" name="scope[]" value="{{ $s }}" checked class="rounded text-violet-600 focus:ring-violet-500 dark:border-slate-600 dark:bg-slate-800"> {{ $s }}</label>
                        @endforeach
                    </div>
                </fieldset>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label for="za-due" class="{{ $label }}">Report due by <span class="text-rose-500">*</span></label><input id="za-due" type="date" name="due_date" required min="{{ now()->toDateString() }}" value="{{ now()->addWeekdays(7)->toDateString() }}" class="{{ $field }}"></div>
                    <div><span class="{{ $label }}">Issued by</span><div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800/60">{{ auth()->user()->name ?? '—' }}</div></div>
                </div>
                <div><label for="za-ins" class="{{ $label }}">Instructions</label><textarea id="za-ins" name="instructions" rows="3" class="{{ $field }}" placeholder="Sir/Madam, you are required to visit the site and inspect the development against the approved drawings…"></textarea></div>
                <div class="flex justify-end gap-3">
                    <button type="button" @click="assign = null" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">Cancel</button>
                    <button class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-500">Assign officer</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
