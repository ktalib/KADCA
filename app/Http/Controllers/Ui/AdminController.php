<?php

namespace App\Http\Controllers\Ui;

use App\Http\Controllers\Controller;
use App\Models\Bpa\Application;
use App\Models\Bpa\Bill;
use App\Models\Dms\FileIndexing;
use App\Models\Tracking\FileTracker;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * System Admin, copied in layout and wording from KLAES (module dashboard, Configurable Entries > Entries & Rates, User
 * Roles, Digital Signature Control, System Settings, Folder Watcher, Bulk SMS Management, Verify QRCode, KLAES Mobile Apps,
 * KLAES Documentation). Reads are real (KADCA's own tables, config, the KADCA\DOC folder, KLAES users read-only); every
 * save posts to the preview stub. Roles and permissions stay on hold (user 2026-11-22), so User Roles shows, not grants.
 */
class AdminController extends Controller
{
    /** Serial generators KADCA uses (kadca_serials name => what it numbers and its format). */
    public const SERIALS = [
        'bpa_application' => ['BPA application', 'BPA-APP/YYYY/00001'],
        'bpa_bill' => ['BPA application bill', 'BPA-BILL/YYYY/00001'],
        'bp_file' => ['BP file (commissioning)', 'BP-YYYY-n'],
    ];

    public function dashboard(): View
    {
        $count = fn (callable $q) => $this->safe($q, null);

        return view('ui.admin.dashboard', [
            'figures' => [
                'applications' => $count(fn () => Application::query()->count()),
                'bills' => $count(fn () => Bill::query()->count()),
                'trackers' => $count(fn () => FileTracker::query()->count()),
                'shadow' => $count(fn () => FileIndexing::query()->count()),
                'users' => $count(fn () => User::query()->where('is_active', 1)->count()),
            ],
            'env' => app()->environment(),
            'database' => config('database.connections.'.config('database.default').'.database'),
        ]);
    }

    public function entities(): View
    {
        $serials = $this->safe(fn () => DB::table('serials')->orderBy('name')->orderByDesc('year')->get(), collect());

        return view('ui.admin.entities', [
            'serials' => $serials,
            'fee' => (int) config('kadca.application_fee_per_plot'),
            'purposes' => config('kadca.request_purposes', []),
            'pageTypes' => config('kadca.dms_page_types', []),
        ]);
    }

    public function roles(Request $request): View
    {
        $q = mb_substr(trim((string) $request->query('q', '')), 0, 60);
        $users = $this->safe(fn () => User::query()
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%{$q}%")->orWhere('username', 'like', "%{$q}%")))
            ->orderBy('name')->paginate(20)->withQueryString(), null);

        return view('ui.admin.roles', [
            'modules' => collect(config('kadca_menu', []))->pluck('label')->all(),
            'users' => $users,
            'q' => $q,
        ]);
    }

    public function signatures(): View
    {
        return view('ui.admin.signatures');
    }

    public function settings(Request $request): View
    {
        $tab = in_array($request->query('tab'), ['general', 'company', 'email', 'security'], true) ? $request->query('tab') : 'general';

        return view('ui.admin.settings', ['tab' => $tab]);
    }

    public function folderWatcher(): View
    {
        return view('ui.admin.folder-watcher', ['disk' => config('kadca.dms_disk'), 'uploads' => config('kadca.uploads_disk')]);
    }

    public function sms(): View
    {
        $applicants = $this->safe(fn () => Application::query()->whereNotNull('developer_phone')->where('developer_phone', '<>', '')->distinct()->count('developer_phone'), 0);

        return view('ui.admin.sms', ['applicants' => $applicants]);
    }

    /** Verify QRCode: KADCA's own documents carry their number in the QR (bill no., BP file no., tracking ID, application no.). */
    public function verify(Request $request): View
    {
        $ref = mb_substr(trim((string) $request->query('ref', '')), 0, 100);
        $result = null;
        if ($ref !== '') {
            $result = $this->safe(fn () => $this->lookup($ref), ['kind' => 'error']);
        }

        return view('ui.admin.verify', ['ref' => $ref, 'result' => $result]);
    }

    public function mobileApps(): View
    {
        return view('ui.admin.mobile-apps');
    }

    public function documentation(Request $request): View
    {
        $docs = $this->docs();
        $current = $docs->firstWhere('name', (string) $request->query('doc'));

        return view('ui.admin.documentation', ['docs' => $docs, 'current' => $current, 'q' => mb_substr((string) $request->query('q', ''), 0, 60)]);
    }

    /** Serves one file of KADCA\DOC by its exact listed name (never a path), inline for PDFs and images. */
    public function documentationFile(Request $request): BinaryFileResponse
    {
        $doc = $this->docs()->firstWhere('name', (string) $request->query('name'));
        abort_unless($doc, 404);

        return $request->boolean('download') ? response()->download($doc['path'], $doc['name']) : response()->file($doc['path']);
    }

    private function docs()
    {
        $dir = base_path('DOC');
        if (! is_dir($dir)) {
            return collect();
        }

        return collect(scandir($dir))
            ->reject(fn ($f) => str_starts_with($f, '.') || is_dir($dir.DIRECTORY_SEPARATOR.$f))
            ->map(fn ($f) => [
                'name' => $f,
                'path' => $dir.DIRECTORY_SEPARATOR.$f,
                'ext' => Str::lower(pathinfo($f, PATHINFO_EXTENSION)),
                'size' => filesize($dir.DIRECTORY_SEPARATOR.$f),
                'modified' => filemtime($dir.DIRECTORY_SEPARATOR.$f),
            ])->values();
    }

    private function lookup(string $ref): array
    {
        if ($bill = Bill::query()->with('application')->where('bill_no', $ref)->first()) {
            return ['kind' => 'bill', 'bill' => $bill, 'application' => $bill->application];
        }
        if ($app = Application::query()->where('bp_file_no', $ref)->orWhere('application_no', $ref)->first()) {
            return ['kind' => 'application', 'application' => $app];
        }
        if ($tracker = FileTracker::query()->with('currentUnit')->where('tracking_id', $ref)->first()) {
            return ['kind' => 'tracker', 'tracker' => $tracker];
        }

        return ['kind' => 'none'];
    }

    private function safe(callable $fn, mixed $fallback): mixed
    {
        try {
            return $fn();
        } catch (Throwable $e) {
            report($e);

            return $fallback;
        }
    }
}
