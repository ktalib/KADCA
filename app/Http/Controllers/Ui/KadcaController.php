<?php

namespace App\Http\Controllers\Ui;

use App\Http\Controllers\Controller;
use App\Models\Bpa\Application;
use App\Support\BpaStage;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * KADCA module screens after the Deputy Director (Arch.): the Building Plan Approval pipeline dashboard, the Zonal Office
 * (Head of Zonal Office assigns an officer, I-to-S style; the officer's Inspection with geocoded photos; Field Data
 * Integration from the mobile app), GIS BaseMap / Charting, KADCA Memo and Final Conveyance (kadca-workflow skill).
 * UI first: the files listed are KADCA's real BP files; every save posts to the preview stub. No role gating (Zonal
 * Office roles are on hold).
 */
class KadcaController extends Controller
{
    /** The KADCA column of the briefing diagram, in order. */
    public const PIPELINE = [
        ['Application & initial bill', 'file-plus-2', 'bpa.applications.index', BpaStage::ASSESSMENT],
        ['Architecture: payment & commissioning', 'drafting-compass', 'bpa.commissioning.index', BpaStage::ARCHITECTURE],
        ['Director (Arch.)', 'user-check', 'bpa.desks.index:director', BpaStage::DIRECTOR],
        ['Deputy Director (Arch.)', 'user-check', 'bpa.desks.index:deputy-director', BpaStage::DEPUTY_DIRECTOR],
        ['Zonal Office', 'map-pinned', 'kadca.zonal-approval', BpaStage::ZONAL_OFFICE],
        ['Site visit & inspection', 'camera', 'kadca.inspection', null],
        ['Final Conveyance', 'stamp', 'kadca.conveyance', null],
    ];

    public function dashboard(): View
    {
        $byStage = Application::query()->selectRaw('stage, COUNT(*) as n')->groupBy('stage')->pluck('n', 'stage')->all();
        $commissioned = Application::query()->whereNotNull('bp_file_no')->count();

        return view('ui.kadca.dashboard', [
            'byStage' => $byStage,
            'total' => array_sum($byStage),
            'commissioned' => $commissioned,
            'mismatch' => Application::query()->where('land_use_check', 'Mismatch')->count(),
            'zonal' => $this->atZonalOffice()->take(8),
        ]);
    }

    public function inspection(Request $request): View
    {
        $files = $this->atZonalOffice();
        $selected = $files->firstWhere('id', (int) $request->query('file'));

        return view('ui.kadca.inspection', ['files' => $files, 'selected' => $selected]);
    }

    public function fieldData(): View
    {
        return view('ui.kadca.field-data', ['files' => $this->atZonalOffice()]);
    }

    public function zonalApproval(Request $request): View
    {
        $tab = in_array($request->query('tab'), ['awaiting', 'assigned', 'reports'], true) ? $request->query('tab') : 'awaiting';

        return view('ui.kadca.zonal-approval', ['files' => $this->atZonalOffice(), 'tab' => $tab]);
    }

    public function basemap(): View
    {
        return view('ui.kadca.basemap', ['files' => $this->commissioned()]);
    }

    public function charting(): View
    {
        return view('ui.kadca.charting');
    }

    public function memo(): View
    {
        return view('ui.kadca.memo', ['files' => $this->commissioned()->take(10)]);
    }

    public function conveyance(): View
    {
        return view('ui.kadca.conveyance', ['files' => $this->atZonalOffice()]);
    }

    /** BP files on the Zonal Office desk (sent on by the Deputy Director). */
    private function atZonalOffice(): Collection
    {
        return Application::query()->where('stage', BpaStage::ZONAL_OFFICE)->latest('updated_at')->limit(200)->get();
    }

    private function commissioned(): Collection
    {
        return Application::query()->whereNotNull('bp_file_no')->latest('commissioned_at')->limit(200)->get();
    }
}
