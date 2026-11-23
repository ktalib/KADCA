<?php

namespace App\Http\Controllers\Tracking;

use App\Http\Controllers\Controller;
use App\Models\Bpa\Application;
use App\Models\Tracking\FileMovement;
use App\Models\Tracking\FileTracker;
use App\Models\Tracking\Unit;
use App\Services\Tracking\FileTracking;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * KADCA > File Tracking, copied in concept from KLAES's File Tracking (File Tracker Dashboard, Find File,
 * Quick Search, Log a File, File/Mail Movement), over KADCA's own files and mails.
 */
class FileTrackingController extends Controller
{
    /** File Tracker Dashboard (KLAES file-tracker.dashboard): cards, filters and "Files in Transit". */
    public function dashboard()
    {
        $trackers = FileTracker::query()->with(['currentUnit', 'toUnit'])->orderByDesc('updated_at')->limit(1000)->get();
        $archive = Unit::where('name', 'Archive')->value('id');
        $active = $trackers->reject(fn ($t) => $t->status === 'in_unit' && (int) $t->current_unit_id === (int) $archive);
        $since = FileMovement::query()->whereIn('file_tracker_id', $trackers->pluck('id'))->selectRaw('file_tracker_id, max(acted_at) as at')->groupBy('file_tracker_id')->pluck('at', 'file_tracker_id');

        $rows = $trackers->map(fn ($t) => [
            'tracking_id' => $t->tracking_id,
            'url' => route('tracking.show', $t),
            'file_number' => $t->reference,
            'file_title' => $t->title,
            'kind' => $t->kind === 'mail' ? 'Mail' : 'File',
            'office' => $t->status === 'in_transit' ? $t->toUnit?->name : $t->currentUnit?->name,
            'office_id' => $t->status === 'in_transit' ? $t->to_unit_id : $t->current_unit_id,
            'since' => isset($since[$t->id]) ? \Illuminate\Support\Carbon::parse($since[$t->id])->diffForHumans(null, true) : '—',
            'priority' => $t->priority,
            'status' => $t->status === 'in_transit' ? 'In Transit' : ((int) $t->current_unit_id === (int) $archive ? 'Completed' : 'Active'),
        ])->values();

        return view('tracking.dashboard', [
            'rows' => $rows,
            'units' => Unit::active()->get(['id', 'name']),
            'figures' => [
                'total' => $trackers->count(),
                'new_this_week' => $trackers->where('created_at', '>=', now()->startOfWeek())->count(),
                'active' => $active->count(),
                'high' => $active->where('priority', 'High')->count(),
                'offices' => $active->map(fn ($t) => $t->status === 'in_transit' ? $t->to_unit_id : $t->current_unit_id)->unique()->count(),
            ],
            'byOffice' => $rows->where('status', '!=', 'Completed')->countBy('office')->sortDesc(),
            'byPriority' => $rows->where('status', '!=', 'Completed')->countBy('priority'),
        ]);
    }

    /** Track File (Archive) (KLAES track-file-archive): search by file number or QR scan, colour-tiled file details. */
    public function find()
    {
        return view('tracking.find');
    }

    /** File details for Track File (Archive). */
    public function archiveLookup(Request $request, \App\Services\Tracking\QuickSearch $search): JsonResponse
    {
        $d = $search->resolve((string) $request->query('q', ''));
        if (! $d['found']) {
            return response()->json(['found' => false, 'message' => 'No KADCA file or mail has this number.']);
        }
        $tracker = $d['tracking_id'] ? FileTracker::with(['movements' => fn ($m) => $m->with(['fromUnit', 'toUnit'])->orderBy('acted_at')->orderBy('id')])->where('tracking_id', $d['tracking_id'])->first() : null;
        $shelf = (string) ($tracker?->shelf_location ?? '');
        preg_match('/RACK\s*([\w-]+)/i', $shelf, $rack);
        preg_match('/SHELF\s*([\w-]+)/i', $shelf, $sh);

        return response()->json($d + [
            'land_use' => \App\Models\Bpa\Application::where('application_no', $d['application_no'])->value('file_land_use'),
            'origin' => $tracker?->movements->first()?->toUnit?->name,
            'destination' => $d['status'] === 'IN_TRANSIT' ? 'In transit to '.$d['current_location'] : $d['current_location'],
            'rack' => $rack[1] ?? null,
            'shelf_no' => $sh[1] ?? null,
            'full_rack_shelf' => $shelf !== '' ? $shelf : null,
            'log_count' => $tracker?->movements->whereNotNull('log_id')->count() ?? 0,
            'last_updated' => $tracker?->updated_at?->format('d M Y, g:i a'),
        ]);
    }

    public function show(FileTracker $tracker)
    {
        $tracker->load(['application', 'currentUnit', 'toUnit', 'movements' => fn ($m) => $m->with(['fromUnit', 'toUnit'])->orderBy('acted_at')->orderBy('id')]);

        return view('tracking.show', ['tracker' => $tracker, 'units' => Unit::active()->get()]);
    }

    public function label(FileTracker $tracker)
    {
        return view('tracking.label', ['tracker' => $tracker->load(['application', 'currentUnit'])]);
    }

    public function send(Request $request, FileTracker $tracker, FileTracking $tracking)
    {
        $data = $request->validateWithBag('send', [
            'to_unit_id' => ['required', 'integer'],
            'purpose' => ['required', 'string', 'max:300'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $tracking->send($tracker, (int) $data['to_unit_id'], trim($data['purpose']), $data['notes'] ?? null, $request->user());
        } catch (ValidationException $e) {
            throw $e->errorBag('send');
        }

        return back()->with('status', "{$tracker->tracking_id} sent to ".Unit::find($data['to_unit_id'])->name.'. It shows as in transit until received.');
    }

    public function receive(Request $request, FileTracker $tracker, FileTracking $tracking)
    {
        $data = $request->validate(['notes' => ['nullable', 'string', 'max:2000'], 'shelf_location' => ['nullable', 'string', 'max:150']]);
        $tracker = $tracking->receive($tracker, $data['notes'] ?? null, $request->user(),
            isset($data['shelf_location']) ? mb_strtoupper(trim($data['shelf_location'])) : null);

        return back()->with('status', "{$tracker->tracking_id} received in ".$tracker->currentUnit->name.'.');
    }

    /** KADCA files for the Log a File picker (BP number, application number, file number or developer). */
    public function lookup(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['files' => []]);
        }
        $like = '%'.$q.'%';
        $files = Application::query()
            ->where(fn ($w) => $w->where('bp_file_no', 'like', $like)->orWhere('application_no', 'like', $like)
                ->orWhere('file_number', 'like', $like)->orWhere('developer_name', 'like', $like))
            ->orderByDesc('id')->limit(15)
            ->get(['id', 'bp_file_no', 'application_no', 'file_number', 'developer_name', 'plot_no', 'district', 'lga']);
        $tracked = FileTracker::whereIn('bpa_application_id', $files->pluck('id'))->pluck('tracking_id', 'bpa_application_id');

        return response()->json(['files' => $files->map(fn ($a) => [
            'id' => $a->id,
            'label' => $a->bp_file_no ?? $a->application_no,
            'sub' => collect([$a->bp_file_no ? $a->application_no : null, $a->file_number, $a->developer_name, $a->plot_no ? 'Plot '.$a->plot_no : null, $a->district, $a->lga])->filter()->implode(' · '),
            'tracked' => $tracked[$a->id] ?? null,
        ])->values()]);
    }

    private function filtered(Builder $query, string $q): Builder
    {
        $like = '%'.$q.'%';

        return $query->where(fn ($w) => $w->where('tracking_id', 'like', $like)->orWhere('reference', 'like', $like)->orWhere('title', 'like', $like)
            ->orWhereHas('application', fn ($a) => $a->where('application_no', 'like', $like)->orWhere('bp_file_no', 'like', $like)->orWhere('file_number', 'like', $like)));
    }
}
