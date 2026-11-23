<?php

namespace App\Services\Tracking;

use App\Models\Bpa\Application;
use App\Models\Tracking\FileTracker;
use App\Models\Tracking\Unit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * KADCA file and mail tracking (the KLAES File Tracker concept): log an item into a unit, send it to another
 * unit (in transit), and receive it there. Tracking IDs look like KDC-TRK-7QF3K9MZ, so they can never be
 * confused with KLAES land-file tracking IDs (TRK-…).
 */
class FileTracking
{
    public const PRIORITIES = ['Low', 'Medium', 'High']; // KLAES Log a File priorities

    /** Log a BP file (one tracker per application) or a mail into a unit. */
    public function log(array $data, User $officer): FileTracker
    {
        return DB::transaction(function () use ($data, $officer) {
            $unit = $this->activeUnit((int) $data['unit_id']);
            $application = null;

            if ($data['kind'] === 'file') {
                $application = Application::query()->lockForUpdate()->find($data['bpa_application_id'] ?? 0);
                if ($application === null) {
                    throw ValidationException::withMessages(['bpa_application_id' => 'Pick the KADCA file from the list.']);
                }
                if ($existing = FileTracker::where('bpa_application_id', $application->id)->first()) {
                    throw ValidationException::withMessages(['bpa_application_id' => "This file is already tracked as {$existing->tracking_id}."]);
                }
            }

            $tracker = FileTracker::create([
                'tracking_id' => $this->newTrackingId(),
                'kind' => $data['kind'],
                'bpa_application_id' => $application?->id,
                'reference' => $application ? ($application->bp_file_no ?? $application->application_no) : $data['reference'],
                'title' => $application ? $application->developer_name : $data['title'],
                'mail_from' => $data['kind'] === 'mail' ? ($data['mail_from'] ?? null) : null,
                'priority' => $data['priority'] ?? 'Medium',
                'status' => 'in_unit',
                'current_unit_id' => $unit->id,
                'due_on' => $data['due_on'] ?? null,
                'shelf_location' => $data['shelf_location'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $officer->getKey(),
                'created_by_name' => $officer->name,
            ]);
            $this->record($tracker, 'logged', null, $unit->id, $data['purpose'] ?? 'Logged', $data['notes'] ?? null, $officer);

            return $tracker;
        });
    }

    /** Send an item from where it is to another unit; it is in transit until received there. */
    public function send(FileTracker $tracker, int $toUnitId, ?string $purpose, ?string $notes, User $officer): FileTracker
    {
        return DB::transaction(function () use ($tracker, $toUnitId, $purpose, $notes, $officer) {
            $tracker = FileTracker::query()->lockForUpdate()->findOrFail($tracker->id);
            $to = $this->activeUnit($toUnitId);

            if ($tracker->status === 'in_transit') {
                throw ValidationException::withMessages(['to_unit_id' => "{$tracker->tracking_id} is already on its way to ".$tracker->toUnit?->name.'. It must be received before it can be sent on.']);
            }
            if ((int) $tracker->current_unit_id === $to->id) {
                throw ValidationException::withMessages(['to_unit_id' => "{$tracker->tracking_id} is already in {$to->name}."]);
            }

            $tracker->update(['status' => 'in_transit', 'to_unit_id' => $to->id]);
            $this->record($tracker, 'sent', $tracker->current_unit_id, $to->id, $purpose, $notes, $officer);

            return $tracker;
        });
    }

    /** The receiving unit confirms the item arrived. */
    public function receive(FileTracker $tracker, ?string $notes, User $officer, ?string $shelf = null): FileTracker
    {
        return DB::transaction(function () use ($tracker, $notes, $officer, $shelf) {
            $tracker = FileTracker::query()->lockForUpdate()->findOrFail($tracker->id);
            if ($tracker->status !== 'in_transit') {
                throw ValidationException::withMessages(['tracker' => "{$tracker->tracking_id} is not in transit; it is in ".$tracker->currentUnit?->name.'.']);
            }

            $from = $tracker->current_unit_id;
            $tracker->update([
                'status' => 'in_unit',
                'current_unit_id' => $tracker->to_unit_id,
                'to_unit_id' => null,
                'shelf_location' => filled($shelf) ? $shelf : $tracker->shelf_location,
            ]);
            $this->record($tracker, 'received', $from, $tracker->current_unit_id, null, $notes, $officer);

            return $tracker;
        });
    }

    /**
     * Commissioning starts tracking the new BP file in Architecture. Call inside the commissioning transaction.
     */
    public function startForApplication(Application $application, User $officer): FileTracker
    {
        $unit = Unit::where('stage', 'architecture')->first() ?? Unit::active()->firstOrFail();

        return $this->log(['kind' => 'file', 'bpa_application_id' => $application->id, 'unit_id' => $unit->id,
            'purpose' => 'BP file commissioned', 'priority' => 'Medium'], $officer);
    }

    /**
     * A desk decision sends the physical BP file to the desk it now sits with. If the file had not been received
     * yet where it was going, it is received there first (the desk could only act on it there).
     */
    public function followDesk(Application $application, string $toStage, string $purpose, User $officer): void
    {
        $tracker = FileTracker::where('bpa_application_id', $application->id)->first();
        $to = Unit::where('stage', $toStage)->where('is_active', true)->first();
        if ($tracker === null || $to === null) {
            return; // an older file without a tracker, or a desk with no unit: nothing to move
        }
        if ($tracker->status === 'in_transit') {
            $this->receive($tracker, 'Received when the desk acted on the file.', $officer);
            $tracker->refresh();
        }
        if ((int) $tracker->current_unit_id !== $to->id) {
            $this->send($tracker, $to->id, $purpose, null, $officer);
        }
    }

    /**
     * KLAES Log a File (Create File Tracker): Log-out sends the file to the requesting unit and officer, with
     * priority, purpose, timeline / expected return date and page count; Log-in records the file arriving in
     * the unit. A file in transit can only be logged out with the "Override for in-transit file" box ticked,
     * which first receives it where it was going. An untracked KADCA file starts its tracking in the origin unit.
     *
     * @param  array<string, mixed>  $d
     */
    public function logFile(array $d, User $officer): FileTracker
    {
        return DB::transaction(function () use ($d, $officer) {
            $application = Application::query()->where(fn ($w) => $w->where('bp_file_no', $d['file_number'])->orWhere('application_no', $d['file_number']))->first();
            if ($application === null) {
                throw ValidationException::withMessages(['file_number' => 'Load the file from Quick Search first.']);
            }
            $tracker = FileTracker::where('bpa_application_id', $application->id)->lockForUpdate()->first()
                ?? $this->log(['kind' => 'file', 'bpa_application_id' => $application->id, 'unit_id' => (int) $d['origin_unit_id'],
                    'purpose' => 'Logged', 'priority' => $d['priority']], $officer);
            $to = $this->activeUnit((int) $d['to_unit_id']);

            if ($tracker->status === 'in_transit') {
                if (empty($d['override_in_transit'])) {
                    throw ValidationException::withMessages(['file_number' => "{$tracker->tracking_id} is in transit to ".$tracker->toUnit?->name.'. Tick "Override for in-transit file" to log it anyway.']);
                }
                $this->receive($tracker, 'Received on override when the file was logged again.', $officer);
                $tracker->refresh();
            }

            $extra = [
                'log_id' => $d['log_id'],
                'priority' => $d['priority'],
                'timeline_days' => $d['timeline_days'] ?? null,
                'expected_return_on' => $d['expected_return_on'] ?? null,
                'num_pages' => $d['num_pages'],
                'holder_user_id' => $d['holder_user_id'] ?? null,
                'holder_name' => $d['holder_name'] ?? null,
            ];
            $tracker->update([
                'priority' => $d['priority'],
                'num_pages' => $d['num_pages'],
                'due_on' => $d['expected_return_on'] ?? null,
                'holder_user_id' => $d['holder_user_id'] ?? null,
                'holder_name' => $d['holder_name'] ?? null,
            ]);

            if ($d['status_update'] === 'log-in') {
                // The file arrives in the unit: logged in there, no transit.
                $from = $tracker->current_unit_id;
                $tracker->update(['status' => 'in_unit', 'current_unit_id' => $to->id, 'to_unit_id' => null]);
                $this->record($tracker, 'received', $from, $to->id, $d['purpose'], $d['notes'] ?? null, $officer, $extra);
            } else {
                if ((int) $tracker->current_unit_id === $to->id) {
                    throw ValidationException::withMessages(['to_unit_id' => "The file is already in {$to->name}."]);
                }
                $tracker->update(['status' => 'in_transit', 'to_unit_id' => $to->id]);
                $this->record($tracker, 'sent', $tracker->current_unit_id, $to->id, $d['purpose'], $d['notes'] ?? null, $officer, $extra);
            }

            return $tracker->refresh();
        });
    }

    private function record(FileTracker $tracker, string $action, ?int $from, ?int $to, ?string $purpose, ?string $notes, User $officer, array $extra = []): void
    {
        $tracker->movements()->create([
            'action' => $action,
            'from_unit_id' => $from,
            'to_unit_id' => $to,
            'purpose' => $purpose,
            'notes' => filled($notes) ? $notes : null,
            'acted_by' => $officer->getKey(),
            'acted_by_name' => $officer->name,
            'acted_at' => now(),
        ] + $extra);
    }

    private function activeUnit(int $id): Unit
    {
        $unit = Unit::where('id', $id)->where('is_active', true)->first();
        if ($unit === null) {
            throw ValidationException::withMessages(['unit_id' => 'Choose a unit from the list.', 'to_unit_id' => 'Choose a unit from the list.']);
        }

        return $unit;
    }

    private function newTrackingId(): string
    {
        // No 0/O, 1/I: tracking IDs get read out and typed from labels.
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        do {
            $id = 'KDC-TRK-'.collect(range(1, 8))->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])->implode('');
        } while (FileTracker::where('tracking_id', $id)->exists());

        return $id;
    }
}
