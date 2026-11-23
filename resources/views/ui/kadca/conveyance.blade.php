{{-- KADCA > Final Conveyance: the Conveyance of Building Plan Approval raised after the zonal inspection (workflow phase 4).
     The certificate follows KADCA FORM.pdf template 1, "Development Permit", field for field (applicant/developer, plot no.,
     permit no., date of approval, commencement and expiry dates, the invalidation conditions, the declaration and the
     authorized officer). The paper template names "Gombe State Urban Planning and Development Authority"; the screen prints
     KADCA / Kano and flags the defect (kadca-workflow skill). Which conveyance templates KADCA wants is still open.
     A wizard with a review step; saves post to the preview stub. --}}
<x-app-layout title="Final Conveyance">
    @php
        $label = 'mb-1 block text-xs font-semibold text-slate-600 dark:text-slate-400';
        $field = 'w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-100';
        $steps = ['BP file', 'Permit particulars', 'Conditions', 'Declaration and signature', 'Review'];
        $conditions = [
            'Development is not commenced within the prescribed period',
            'The approved land use is changed without authorization;',
            'Substantial changes are made to the approved plans without approval',
            'False information or documents are discovered;',
            'Development is undertaken outside the approved plot or boundaries; or other applicable statutory requirements are not complied with',
        ];
    @endphp

    <div class="space-y-6">
        <x-ui.page-head icon="stamp" title="Final Conveyance" subtitle="Conveyance of Building Plan Approval (Development Permit)" tone="emerald" section="KADCA" />
        <x-ui.preview-note what="Raising a conveyance">The permit number is given on save.</x-ui.preview-note>
        <div class="flex items-start gap-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
            <i data-lucide="triangle-alert" class="mt-0.5 h-4 w-4 shrink-0"></i>
            <p><span class="font-semibold">Template defect.</span> The paper Development Permit (KADCA FORM.pdf, page 1) says "regulations of Gombe State Urban Planning and Development Authority". This screen prints Kano State Development Control Agency (KADCA) instead; the source template still needs correcting by KADCA.</p>
        </div>

        <form method="POST" action="{{ route('ui.preview', 'final-conveyance') }}" novalidate class="space-y-6"
              x-data="formWizard({ file: null, applicant: '', approval: @js(now()->toDateString()), commence: '', expiry: '', officer: @js(auth()->user()->name ?? ''),
                    pick(f) { this.file = f; if (f) { this.applicant = (f.file_title || '').split(' · ')[0]; } },
                    guard(n) { if (n === 1 && ! this.file) { alert('Pick the BP file to convey.'); return false; } if (n === 2 && this.expiry && this.commence && this.expiry <= this.commence) { alert('The expiry date must be after the commencement date.'); return false; } return true; } }, { steps: {{ count($steps) }} })"
              @submit="submitAll($event)" @file-picked="pick($event.detail)">
            @csrf
            <x-wizard-steps :steps="$steps" />

            <x-ui.panel title="BP file" subtitle="A file whose inspection report the Head of Zonal Office has approved" data-step="1" x-show="step === 1">
                <x-ui.file-picker name="bpa_application_id" source="bp" label="BP file" />
                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">{{ number_format($files->count()) }} file(s) are at the Zonal Office now.</p>
            </x-ui.panel>

            <x-ui.panel title="Permit particulars" data-step="2" x-show="step === 2" x-cloak>
                <p class="mb-4 text-sm text-slate-600 dark:text-slate-300">This is to certify that Permission is hereby granted to:</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2"><label for="cv-app" class="{{ $label }}">Applicant/Developer <span class="text-rose-500">*</span></label><input id="cv-app" name="applicant" x-model="applicant" required class="{{ $field }}"></div>
                    <div><span class="{{ $label }}">To undertake the approved development at Plot No.</span><div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm dark:border-slate-700 dark:bg-slate-800/60" x-text="file?.plot_no || '—'"></div></div>
                    <div><span class="{{ $label }}">Development Permit No.</span><div class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-800/60 dark:text-slate-400">Given on save</div></div>
                    <div><label for="cv-appr" class="{{ $label }}">Date of Approval <span class="text-rose-500">*</span></label><input id="cv-appr" type="date" name="date_of_approval" x-model="approval" required class="{{ $field }}"></div>
                    <div><label for="cv-com" class="{{ $label }}">Permit Commencement Date <span class="text-rose-500">*</span></label><input id="cv-com" type="date" name="commencement_date" x-model="commence" required class="{{ $field }}"></div>
                    <div><label for="cv-exp" class="{{ $label }}">Permit Expiry Date <span class="text-rose-500">*</span></label><input id="cv-exp" type="date" name="expiry_date" x-model="expiry" required class="{{ $field }}"></div>
                </div>
                <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">In accordance with the approved plans, documents and conditions attached to this permit.</p>
            </x-ui.panel>

            <x-ui.panel title="Conditions" subtitle="Note that this permit shall become invalid if:" data-step="3" x-show="step === 3" x-cloak>
                <ul class="space-y-2 text-sm">
                    @foreach ($conditions as $c)
                        <li class="flex items-start gap-2"><input type="checkbox" name="conditions[]" value="{{ $c }}" checked class="mt-0.5 rounded text-emerald-600 focus:ring-emerald-500 dark:border-slate-600 dark:bg-slate-800"> <span>{{ $c }}</span></li>
                    @endforeach
                </ul>
                <p class="mt-4 rounded-lg bg-slate-50 p-3 text-sm text-slate-600 dark:bg-slate-800/60 dark:text-slate-300">The permit may be renewed, suspended or revoked in accordance with applicable laws and regulations of Kano State Development Control Agency (KADCA).</p>
                <div class="mt-4"><label for="cv-extra" class="{{ $label }}">Additional conditions</label><textarea id="cv-extra" name="additional_conditions" rows="3" class="{{ $field }}"></textarea></div>
            </x-ui.panel>

            <x-ui.panel title="Declaration and signature" data-step="4" x-show="step === 4" x-cloak>
                <p class="mb-4 text-sm text-slate-600 dark:text-slate-300">I/We confirm that the information supplied in this application is true and complete.</p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div><label for="cv-dn" class="{{ $label }}">Name <span class="text-rose-500">*</span></label><input id="cv-dn" name="declarant_name" x-model="applicant" required class="{{ $field }}"></div>
                    <div><label for="cv-dd" class="{{ $label }}">Signature/Date</label><input id="cv-dd" type="date" name="declarant_date" class="{{ $field }}"></div>
                    <div><label for="cv-off" class="{{ $label }}">Authorized Officer <span class="text-rose-500">*</span></label><input id="cv-off" name="authorized_officer" x-model="officer" required class="{{ $field }}"></div>
                    <div><label for="cv-od" class="{{ $label }}">Signature / Date</label><input id="cv-od" type="date" name="officer_date" value="{{ now()->toDateString() }}" class="{{ $field }}"></div>
                </div>
            </x-ui.panel>

            <x-ui.panel title="Review" subtitle="The certificate as it will print" data-step="5" x-show="step === 5" x-cloak>
                <article class="mx-auto max-w-2xl rounded-xl border-4 border-double border-emerald-700 bg-white p-8 text-center text-sm text-slate-900">
                    <img src="{{ asset('images/logo.png') }}" alt="" class="mx-auto h-16 w-16 object-contain">
                    <p class="mt-2 font-black uppercase tracking-wide">Kano State Development Control Agency (KADCA)</p>
                    <p class="mt-3 text-xl font-black tracking-widest text-emerald-800">DEVELOPMENT PERMIT</p>
                    <p class="mt-4">This is to certify that Permission is hereby granted to:</p>
                    <p class="mt-1 text-base font-bold" x-text="applicant || '________________'"></p>
                    <p class="mt-2">To undertake the approved development at Plot No. <span class="font-semibold" x-text="file?.plot_no || '______'"></span><span x-show="file?.district">, <span x-text="file?.district"></span></span><span x-show="file?.lga">, <span x-text="file?.lga"></span></span></p>
                    <p class="mt-1 text-xs">In accordance with the approved plans, documents and conditions attached to this permit.</p>
                    <dl class="mx-auto mt-4 grid max-w-md grid-cols-2 gap-1 text-left">
                        <dt>Development Permit No.:</dt><dd class="font-mono">Given on save</dd>
                        <dt>Date of Approval:</dt><dd x-text="approval"></dd>
                        <dt>Permit Commencement Date:</dt><dd x-text="commence || '—'"></dd>
                        <dt>Permit Expiry Date:</dt><dd x-text="expiry || '—'"></dd>
                        <dt>BP File No.:</dt><dd class="font-mono" x-text="file?.file_number || '—'"></dd>
                    </dl>
                    <div class="mt-10 grid grid-cols-2 gap-8 text-xs">
                        <div class="border-t border-slate-500 pt-1" x-text="applicant"></div>
                        <div class="border-t border-slate-500 pt-1"><span x-text="officer"></span><br>Authorized Officer</div>
                    </div>
                </article>
            </x-ui.panel>

            <x-wizard-nav :cancel="route('kadca.dashboard')">Raise conveyance</x-wizard-nav>
        </form>
    </div>
</x-app-layout>
