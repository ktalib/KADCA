{{-- "Create Title Status" pop-up, copied from KLAES title_status/index: pick the type first, then the file (selector, never
     typed), the title particulars, who initiated it and why. KADCA does not write KLAES title-status records: preview save. --}}
@php
    $tsTypes = [
        'Withdrawal (Application)' => 'New Files',
        'Cancellation (RofO)' => 'Existing or New Files',
        'Revocation (CofO)' => 'Existing Files',
        'Litigation' => 'Existing Files',
        'Amendment / Reconsideration' => 'Application / RofO / CofO',
        'Surrender' => 'Voluntary relinquishment',
        'Re-grant' => 'Links to an additional file',
        'Resettlement' => 'Links to an additional file',
    ];
    $tsLabel = 'mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-400';
    $tsField = 'w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100';
@endphp

<div x-data="{ open: false, type: '' }" class="contents">
    <button type="button" @click.stop="open = true; type = ''" class="inline-flex items-center gap-2 rounded-lg bg-rose-600 px-3 py-2 text-sm font-semibold text-white hover:bg-rose-500"><i data-lucide="plus" class="h-4 w-4"></i> Create Title Status</button>

    <div x-show="open" x-cloak class="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-slate-900/50 p-4 sm:p-10" @keydown.escape.window="open = false">
        <form method="POST" action="{{ route('ui.preview', 'title-status') }}" novalidate x-data="formWizard({}, { steps: 2 })" @submit="submitAll($event)" @click.outside="open = false" @click.stop
              class="w-full max-w-3xl space-y-5 rounded-2xl bg-white p-6 text-left shadow-xl dark:bg-slate-900">
            @csrf
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">Create Title Status</h2>
                    <p class="text-sm text-slate-500 dark:text-slate-400">Select the type of title status to proceed.</p>
                </div>
                <button type="button" @click="open = false" class="rounded p-1 text-xl leading-none text-slate-400 hover:text-slate-700 dark:hover:text-slate-200" aria-label="Close">&times;</button>
            </div>
            <x-wizard-steps :steps="['Type', 'File and particulars']" />

            <div data-step="1" x-show="step === 1" class="grid gap-3 sm:grid-cols-2">
                @foreach ($tsTypes as $t => $hint)
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition" :class="type === @js($t) ? 'border-rose-400 bg-rose-50 dark:border-rose-500/50 dark:bg-rose-500/10' : 'border-slate-200 hover:bg-slate-50 dark:border-slate-700 dark:hover:bg-slate-800'">
                        <input type="radio" name="title_type" value="{{ $t }}" x-model="type" required class="mt-1 text-rose-600 focus:ring-rose-500">
                        <span><span class="block font-semibold text-slate-900 dark:text-white">{{ $t }}</span><span class="text-xs text-slate-500 dark:text-slate-400">{{ $hint }}</span></span>
                    </label>
                @endforeach
            </div>

            <div data-step="2" x-show="step === 2" x-cloak class="space-y-4">
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">Type: <span x-text="type" class="text-rose-700 dark:text-rose-300"></span></p>
                <x-ui.file-picker name="file_indexing_id" label="File Number" />
                <div x-show="type === 'Re-grant' || type === 'Resettlement'" x-cloak>
                    <x-ui.file-picker name="see_file_id" label="Additional file number" :required="false" hint='The related file number written as "See ..." on the physical file.' />
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-3"><label for="ts-holder" class="{{ $tsLabel }}">Allottee / Holder Name <span class="text-rose-500">*</span></label><input id="ts-holder" name="applicant_name" required class="{{ $tsField }}"></div>
                    <div><label for="ts-cofo" class="{{ $tsLabel }}">Certificate of Occupancy No.</label><input id="ts-cofo" name="cofo_number" class="{{ $tsField }}"></div>
                    <div><label for="ts-date" class="{{ $tsLabel }}">Dated (Date of Issue)</label><input id="ts-date" type="date" name="date_of_issue" class="{{ $tsField }}"></div>
                    <div><label for="ts-reg" class="{{ $tsLabel }}">Registered as No.</label><input id="ts-reg" name="title_no" class="{{ $tsField }}"></div>
                    <div><label for="ts-page" class="{{ $tsLabel }}">At Page</label><input id="ts-page" name="reg_page" class="{{ $tsField }}"></div>
                    <div><label for="ts-vol" class="{{ $tsLabel }}">In Volume</label><input id="ts-vol" name="reg_volume" class="{{ $tsField }}"></div>
                    <x-ui.select-other name="initiated_by" label="Initiated By" :options="['Applicant / Holder', 'Ministry', 'Court Order', 'Governor', 'KAMMA', 'KADCA']" required />
                </div>
                <div><label for="ts-reason" class="{{ $tsLabel }}">Reason <span class="text-rose-500">*</span></label><textarea id="ts-reason" name="reason" rows="2" required class="{{ $tsField }}"></textarea></div>
                <div><label for="ts-remark" class="{{ $tsLabel }}">Remark / Comment</label><textarea id="ts-remark" name="remark" rows="2" class="{{ $tsField }}"></textarea></div>
                <x-ui.preview-note what="Creating a title status" />
            </div>

            <x-wizard-nav>Submit for approval</x-wizard-nav>
        </form>
    </div>
</div>
