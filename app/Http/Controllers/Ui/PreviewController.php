<?php

namespace App\Http\Controllers\Ui;

use App\Http\Controllers\Controller;
use App\Models\Bpa\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * UI-first phase (user 2026-11-23: "first finish the UI and frontend ... next we can focus on the backend").
 * Forms whose backend is not built post here: nothing is validated against the database and nothing is stored,
 * KLAES (dbo) least of all. The answer is "saved (preview)".
 */
class PreviewController extends Controller
{
    public function save(Request $request, string $form): JsonResponse|RedirectResponse
    {
        $name = Str::headline(str_replace(['-', '_'], ' ', mb_substr($form, 0, 60)));
        $message = "Saved (preview): {$name}. Nothing was stored — this screen's backend comes in the next phase.";

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'preview' => true, 'message' => $message]);
        }

        return back()->withInput()->with('status', $message);
    }

    /** BP files for the file selector (source "bp"): KADCA's commissioned building plan files. */
    public function bpFiles(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['files' => []]);
        }

        $files = Application::query()
            ->whereNotNull('bp_file_no')
            ->where(fn ($w) => $w->where('bp_file_no', 'like', "%{$q}%")
                ->orWhere('application_no', 'like', "%{$q}%")
                ->orWhere('developer_name', 'like', "%{$q}%")
                ->orWhere('file_number', 'like', "%{$q}%"))
            ->latest('id')->limit(15)->get()
            ->map(fn (Application $a) => [
                'id' => $a->id,
                'file_number' => $a->bp_file_no,
                'file_title' => $a->developer_name.' · '.$a->application_no.' · land file '.$a->file_number,
                'plot_no' => $a->plot_no, 'district' => $a->district, 'lga' => $a->lga, 'location' => $a->location,
            ]);

        return response()->json(['files' => $files]);
    }
}
