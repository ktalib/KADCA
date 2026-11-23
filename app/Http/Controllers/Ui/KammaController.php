<?php

namespace App\Http\Controllers\Ui;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Records\RecordsController;
use App\Services\Records\KlaesRecords;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The KAMMA block of the KADCA menu (KADCA users see KAMMA's output, kadca-workflow skill). What KAMMA writes to KLAES
 * (shadow files flagged kamma_matching on dbo.file_indexings, title statuses) is read live and read-only. KAMMA's own
 * planning recommendations live in KAMMA's schema, which KADCA cannot read yet (open question: how KAMMA output reaches KADCA).
 */
class KammaController extends Controller
{
    public function dashboard(KlaesRecords $records): View
    {
        [$shadow, $shadowError] = $records->attempt(fn () => $records->stats($records->definition('kamma-shadow-files')), null);
        [$status, $statusError] = $records->attempt(fn () => $records->stats($records->definition('title-status')), null);

        return view('ui.kamma.dashboard', [
            'shadow' => $shadow,
            'status' => $status,
            'error' => $shadowError ?? $statusError,
        ]);
    }

    /** Title Status Update: KLAES title_status_applications (read-only) with KLAES's "Create Title Status" pop-up. */
    public function titleStatus(Request $request): View
    {
        return app(RecordsController::class)->show($request, 'title-status');
    }

    /** Planning Recommendation Approval, laid out like KLAES programmes.approvals.planning_recomm?url=approval. */
    public function planningApproval(Request $request): View
    {
        $status = in_array($request->query('status'), ['Pending', 'Approved', 'Declined'], true) ? $request->query('status') : null;

        return view('ui.kamma.planning-approval', [
            'status' => $status,
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'rows' => collect(), // KAMMA's recommendations are not shared with KADCA yet
        ]);
    }
}
