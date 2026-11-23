<?php

namespace App\Services\Bpa;

use App\Models\Bpa\Application;
use App\Models\User;
use App\Services\Serials;
use App\Services\Tracking\FileTracking;
use App\Support\BpaStage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Commissions the BP file of a paid application: BP-[Year]-[Serial] (user 2026-11-22: "it should be BP-2026-1") (briefing 2026-11-22: "BP is the prefix,
 * the year, and serial number"). The serial restarts on 1 January. Unpadded, like KLAES land-file numbers
 * (RES-2016-452), e.g. BP-2026-1.
 */
class CommissionBpFile
{
    public function __construct(private Serials $serials, private MoveFile $moves, private FileTracking $tracking)
    {
    }

    public function handle(Application $application, User $officer): Application
    {
        return DB::transaction(function () use ($application, $officer) {
            $application = Application::query()->with('bill')->lockForUpdate()->findOrFail($application->getKey());

            if ($application->bp_file_no !== null) {
                throw ValidationException::withMessages(['application' => "{$application->application_no} is already commissioned as {$application->bp_file_no}."]);
            }
            if ($application->bill?->status !== 'Paid') {
                throw ValidationException::withMessages(['application' => "{$application->application_no} cannot be commissioned until its application bill is paid."]);
            }

            $year = (int) now()->format('Y');
            $application->update([
                'bp_file_no' => sprintf('BP-%d-%d', $year, $this->serials->next('bp_file', $year)),
                // A new BP file goes straight to the Director (Arch.) for recommendation (briefing 2026-11-22).
                'stage' => BpaStage::DIRECTOR,
                'status' => BpaStage::status(BpaStage::DIRECTOR),
                'commissioned_at' => now(),
                'commissioned_by' => $officer->getKey(),
                'commissioned_by_name' => $officer->name,
            ]);
            $this->moves->record($application, BpaStage::ARCHITECTURE, BpaStage::DIRECTOR, 'commissioned', null, $officer);

            // The physical BP file starts its tracking in Architecture and is sent to the Director (Arch.).
            $this->tracking->startForApplication($application, $officer);
            $this->tracking->followDesk($application, BpaStage::DIRECTOR, 'Commissioned; for recommendation', $officer);

            return $application;
        });
    }
}
