<?php

namespace App\Http\Controllers\Bpa;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bpa\StoreApplicationRequest;
use App\Models\Bpa\Application;
use App\Models\Bpa\ApplicationFile;
use App\Services\Bpa\SubmitApplication;
use App\Support\BpaForm;
use App\Support\LandUse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Assessment Stage > Application Form: Building Plan Approval applications. */
class ApplicationController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $check = (string) $request->query('land_use_check', '');

        $applications = Application::query()
            ->with('bill')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    foreach (['application_no', 'file_number', 'developer_name', 'plot_no', 'district', 'lga'] as $col) {
                        $q->orWhere($col, 'like', '%'.$search.'%');
                    }
                });
            })
            ->when(in_array($check, [LandUse::MATCH, LandUse::MISMATCH, LandUse::NOT_CHECKED], true), fn ($q) => $q->where('land_use_check', $check))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('bpa.applications.index', compact('applications', 'search', 'check'));
    }

    public function create()
    {
        return view('bpa.applications.create', [
            'proposedUses' => [...array_keys(LandUse::PROPOSED), LandUse::OTHER],
            'proposedCodes' => LandUse::PROPOSED,
            'landUseLabels' => LandUse::LABELS,
            'submissionTypes' => BpaForm::SUBMISSION_TYPES,
            'documents' => BpaForm::DOCUMENTS,
            'drawings' => BpaForm::DRAWINGS,
            'receivedAs' => BpaForm::PLAN_RECEIVED_AS,
            'accept' => '.'.implode(',.', array_merge(BpaForm::PLAN_EXTENSIONS, BpaForm::CAD_EXTENSIONS)),
            'feePerPlot' => (int) config('kadca.application_fee_per_plot'),
        ]);
    }

    public function store(StoreApplicationRequest $request, SubmitApplication $submit)
    {
        $application = $submit->handle($request->validated(), $request->file('plan_files', []), $request->user());

        return redirect()->route('bpa.applications.show', $application)
            ->with('status', "Application {$application->application_no} saved, and its bill {$application->bill->bill_no} raised.");
    }

    public function show(Application $application)
    {
        $application->load(['files', 'bill', 'tracker.currentUnit', 'tracker.toUnit', 'movements' => fn ($q) => $q->orderBy('acted_at')->orderBy('id')]);

        return view('bpa.applications.show', compact('application'));
    }

    public function download(Application $application, ApplicationFile $file)
    {
        abort_unless((int) $file->bpa_application_id === (int) $application->id, 404);

        return Storage::disk(config('kadca.uploads_disk'))->download($file->stored_path, $file->original_name);
    }
}
