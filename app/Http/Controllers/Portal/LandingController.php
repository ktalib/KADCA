<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Support\BpaForm;
use App\Support\Portal\PortalSession;
use App\Support\Portal\PortalTimeline;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The portal's public landing page (user 2026-11-23: "they should have a landing page, not straight to login ... that
 * will have those login and sign up buttons"). Laid out like the KLAES LAAS and MLPP-SG portal landings. A signed-in
 * applicant goes straight to the dashboard.
 */
class LandingController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        if (PortalSession::applicant($request) !== null) {
            return redirect()->route('portal.dashboard');
        }

        return view('portal.landing', [
            'steps' => PortalTimeline::STEPS,
            'documents' => array_values(array_filter(BpaForm::DOCUMENTS, fn ($d) => $d !== BpaForm::OTHER)),
            'drawings' => BpaForm::DRAWINGS,
            'planTypes' => array_map('strtoupper', array_merge(BpaForm::PLAN_EXTENSIONS, BpaForm::CAD_EXTENSIONS)),
            'feePerPlot' => (int) config('kadca.application_fee_per_plot'),
            'uploadMaxMb' => (int) (config('kadca.upload_max_kb') / 1024),
            'demoEnabled' => (bool) config('kadca.portal.demo_enabled'),
        ]);
    }
}
