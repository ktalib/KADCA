<?php

namespace App\Services\Ui;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * What KADCA staff did, read from KADCA's own records (there is no separate activity log yet): applications received,
 * payments recorded, BP files commissioned, desk decisions, file movements, shadow-file matching, scans and page typing.
 * Each source is read on its own so a missing table (e.g. DMS before its migration) only drops that source.
 */
class ActivityFeed
{
    /** scope => modules shown */
    public const SCOPES = [
        'dms' => ['DMS'],
        'hr' => ['BPA', 'Tracking', 'DMS'],
        'admin' => ['BPA', 'Tracking', 'DMS'],
    ];

    /** @return Collection<int, array<string, mixed>> */
    public function recent(array $modules = ['BPA', 'Tracking', 'DMS'], ?Carbon $since = null, int $limit = 300): Collection
    {
        $since ??= now()->subDays(30);
        $events = collect();

        $sources = [
            'BPA' => [
                fn () => DB::table('bpa_applications')->where('created_at', '>=', $since)->latest()->limit($limit)
                    ->get(['application_no as ref', 'developer_name as detail', 'received_by_name as who', 'created_at as at'])
                    ->map(fn ($r) => $this->event($r, 'BPA', 'Application received', 'file-plus-2')),
                fn () => DB::table('bills')->whereNotNull('payment_recorded_at')->where('payment_recorded_at', '>=', $since)->latest('payment_recorded_at')->limit($limit)
                    ->get(['bill_no as ref', 'receipt_no as detail', 'payment_recorded_by_name as who', 'payment_recorded_at as at'])
                    ->map(fn ($r) => $this->event($r, 'BPA', 'Payment recorded', 'banknote')),
                fn () => DB::table('bpa_movements as m')->join('bpa_applications as a', 'a.id', '=', 'm.bpa_application_id')
                    ->where('m.acted_at', '>=', $since)->latest('m.acted_at')->limit($limit)
                    ->get(['a.bp_file_no as ref', 'm.action as detail', 'm.acted_by_name as who', 'm.acted_at as at', 'm.to_stage'])
                    ->map(fn ($r) => $this->event($r, 'BPA', match ($r->detail) {
                        'commissioned' => 'BP file commissioned', 'recommended' => 'Recommended', 'not_recommended' => 'Not recommended', 'resent' => 'Sent back to the Director',
                        default => ucfirst(str_replace('_', ' ', (string) $r->detail)),
                    }, 'stamp')),
            ],
            'Tracking' => [
                fn () => DB::table('file_movements as m')->join('file_trackers as t', 't.id', '=', 'm.file_tracker_id')
                    ->where('m.acted_at', '>=', $since)->latest('m.acted_at')->limit($limit)
                    ->get(['t.tracking_id as ref', 't.reference as detail', 'm.acted_by_name as who', 'm.acted_at as at', 'm.action'])
                    ->map(fn ($r) => $this->event($r, 'Tracking', 'File '.$r->action, 'route')),
            ],
            'DMS' => [
                fn () => DB::table('file_indexings')->where('created_at', '>=', $since)->latest()->limit($limit)
                    ->get(['file_number as ref', 'file_title as detail', 'matched_by_name as who', 'created_at as at'])
                    ->map(fn ($r) => $this->event($r, 'DMS', 'Shadow file commissioned', 'folder-input')),
                fn () => DB::table('scannings as s')->join('file_indexings as f', 'f.id', '=', 's.file_indexing_id')
                    ->where('s.created_at', '>=', $since)
                    ->groupBy('f.file_number', 's.upload_batch', 's.uploaded_by_name')
                    ->selectRaw('f.file_number as ref, COUNT(*) as detail, s.uploaded_by_name as who, MAX(s.created_at) as at')
                    ->orderByDesc('at')->limit($limit)->get()
                    ->map(fn ($r) => $this->event($r, 'DMS', 'Pages scanned', 'scan-line', $r->detail.' page(s)')),
                fn () => DB::table('page_typings as p')->join('file_indexings as f', 'f.id', '=', 'p.file_indexing_id')
                    ->where('p.created_at', '>=', $since)->latest('p.created_at')->limit($limit)
                    ->get(['f.file_number as ref', 'p.page_type as detail', 'p.typed_by_name as who', 'p.created_at as at'])
                    ->map(fn ($r) => $this->event($r, 'DMS', 'Page typed', 'type')),
            ],
        ];

        foreach ($modules as $module) {
            foreach ($sources[$module] ?? [] as $read) {
                try {
                    $events = $events->concat($read());
                } catch (Throwable) {
                    // that source's table is not there yet: leave it out
                }
            }
        }

        return $events->sortByDesc('at')->values()->take($limit);
    }

    private function event(object $r, string $module, string $action, string $icon, ?string $detail = null): array
    {
        return [
            'module' => $module,
            'action' => $action,
            'icon' => $icon,
            'ref' => $r->ref ?? null,
            'detail' => $detail ?? (is_scalar($r->detail ?? null) ? (string) $r->detail : null),
            'who' => $r->who ?: 'Unknown',
            'at' => Carbon::parse($r->at),
        ];
    }
}
