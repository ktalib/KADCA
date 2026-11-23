<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\PortalApplicationRequest;
use App\Services\Klaes\IndexedFiles;
use App\Services\Portal\PortalApplications;
use App\Support\BpaForm;
use App\Support\LandUse;
use App\Support\Portal\PortalSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Applicant portal: New Building Plan Application (the staff wizard's steps, in applicant wording), the application
 * page, and a portal copy of the KLAES land-file selector.
 *
 * UI first (user 2026-11-23): Submit checks every field like the staff form and answers "Saved (preview)". It does
 * not create the application, raise the bill or keep the files; App\Services\Bpa\SubmitApplication does that once
 * the portal backend is built.
 */
class ApplicationController extends Controller
{
    public function create(): View
    {
        return view('portal.applications.create', [
            'proposedUses' => [...array_keys(LandUse::PROPOSED), LandUse::OTHER],
            'proposedCodes' => LandUse::PROPOSED,
            'landUseLabels' => LandUse::LABELS,
            'submissionTypes' => BpaForm::SUBMISSION_TYPES,
            'documents' => BpaForm::DOCUMENTS,
            'drawings' => BpaForm::DRAWINGS,
            'receivedAs' => PortalApplicationRequest::RECEIVED_AS,
            'accept' => '.'.implode(',.', array_merge(BpaForm::PLAN_EXTENSIONS, BpaForm::CAD_EXTENSIONS)),
            'feePerPlot' => (int) config('kadca.application_fee_per_plot'),
            'applicant' => PortalSession::applicant(),
        ]);
    }

    public function store(PortalApplicationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $plots = (int) $data['number_of_plots'];
        $files = count($request->file('plan_files', []));

        return redirect()->route('portal.dashboard')->with('status', sprintf(
            'Saved (preview): your application for %s was checked (%d plan file%s, bill ₦%s) but not submitted. Submitting opens with the portal backend.',
            $data['developer_name'],
            $files,
            $files === 1 ? '' : 's',
            number_format($plots * (int) config('kadca.application_fee_per_plot'), 2),
        ));
    }

    public function show(string $reference, PortalApplications $portal): View
    {
        $application = $portal->find($reference, PortalSession::applicant());
        abort_if($application === null, 404);

        return view('portal.applications.show', ['a' => $application]);
    }

    /** "Track an application" box: open an application by its application or BP number. */
    public function track(Request $request): RedirectResponse
    {
        $data = $request->validate(['number' => ['required', 'string', 'max:60']], [], ['number' => 'application or BP number']);

        return redirect()->route('portal.applications.show', ['reference' => mb_strtoupper(trim($data['number']))]);
    }

    /** Land-file selector: file numbers only (never the holder's name) and no phone or address in the answer. */
    public function fileSearch(Request $request, IndexedFiles $files): JsonResponse
    {
        $found = $files->search((string) $request->query('q', ''), 10, true);

        return response()->json(['files' => array_map(fn ($f) => $this->publicFile($f), $found)]);
    }

    public function fileShow(int $id, IndexedFiles $files): JsonResponse
    {
        $file = $files->find($id);

        return $file
            ? response()->json(['file' => $this->publicFile($file)])
            : response()->json(['message' => 'That land file was not found in KLAES.'], 404);
    }

    /** @param array<string, mixed> $file */
    private function publicFile(array $file): array
    {
        return array_intersect_key($file, array_flip([
            'id', 'file_number', 'file_title', 'plot_no', 'district', 'lga', 'location', 'plot_size', 'street_name',
            'land_use', 'land_use_code', 'title_status',
        ]));
    }
}
