<?php

namespace App\Services\Portal;

use App\Models\Bpa\Application;
use App\Support\BpaStage;
use App\Support\LandUse;
use App\Support\Portal\PortalTimeline;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Throwable;

/**
 * The applications an applicant sees on the portal, in one shape for every screen.
 *
 * UI first (user 2026-11-23): applications are not linked to applicant accounts yet, so
 * - the demo applicant sees sample applications (numbers start DEMO-, never a real file), one at each step;
 * - an account made on Register sees none until the backend links its applications;
 * - in preview, a real KADCA application can be opened by its application or BP number (read-only) to check the
 *   layout against real data. That lookup is preview only: in production an applicant must see only their own.
 * Staff names are never shown to applicants: a step names the desk, not the officer.
 */
class PortalApplications
{
    /** @return Collection<int, array<string, mixed>> */
    public function forApplicant(?array $applicant): Collection
    {
        if (! $applicant || empty($applicant['is_demo'])) {
            return collect();
        }

        return collect($this->demo())->map(fn (array $a) => $this->finish($a))->values();
    }

    /** @return array<string, mixed>|null */
    public function find(string $reference, ?array $applicant): ?array
    {
        $reference = trim($reference);
        $own = $this->forApplicant($applicant)->first(fn ($a) => strcasecmp($a['ref'], $reference) === 0
            || ($a['bp_file_no'] && strcasecmp($a['bp_file_no'], $reference) === 0));
        if ($own) {
            return $own;
        }

        return config('kadca.portal.preview') ? $this->real($reference) : null;
    }

    /** Updates on every application, newest first. @return Collection<int, array<string, mixed>> */
    public function notifications(?array $applicant): Collection
    {
        return $this->forApplicant($applicant)
            ->flatMap(fn ($a) => collect($a['events'])->map(fn ($e) => $e + ['ref' => $a['ref'], 'bp_file_no' => $a['bp_file_no']]))
            ->sortByDesc(fn ($e) => $e['at']->getTimestamp())
            ->values();
    }

    /** Applications the applicant may report construction progress on: approved ones (building before approval is penalised). */
    public function forProgress(?array $applicant): Collection
    {
        return $this->forApplicant($applicant)->filter(fn ($a) => $a['can_report_progress'])->values();
    }

    /** Applications whose site inspection can be booked: at the Zonal Office or Inspection step. */
    public function forInspection(?array $applicant): Collection
    {
        return $this->forApplicant($applicant)->filter(fn ($a) => $a['can_book_inspection'])->values();
    }

    /** A real KADCA application, read-only (preview). */
    private function real(string $reference): ?array
    {
        try {
            $model = Application::query()
                ->with(['bill', 'files', 'movements' => fn ($q) => $q->orderBy('acted_at')->orderBy('id')])
                ->where('application_no', $reference)
                ->orWhere('bp_file_no', $reference)
                ->first();
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        return $model ? $this->finish($this->fromModel($model)) : null;
    }

    /** @return array<string, mixed> */
    private function fromModel(Application $m): array
    {
        $bill = $m->bill;
        $events = [[
            'title' => 'Application received',
            'body' => "Application {$m->application_no} received.".($bill ? " Bill {$bill->bill_no} raised: ₦".number_format((float) $bill->amount, 2).'.' : ''),
            'at' => Carbon::parse($m->created_at),
            'action' => 'submitted',
        ]];
        if ($bill && $bill->status === 'Paid') {
            $events[] = [
                'title' => 'Payment confirmed',
                'body' => 'Bill '.$bill->bill_no.' paid'.($bill->receipt_no ? ', receipt '.$bill->receipt_no : '').'.',
                'at' => Carbon::parse($bill->payment_recorded_at ?? $bill->paid_on ?? $m->updated_at),
                'action' => 'paid',
            ];
        }
        foreach ($m->movements as $mv) {
            $events[] = $this->movementEvent($mv->action, $mv->from_stage, $mv->remarks, Carbon::parse($mv->acted_at), $m->bp_file_no);
        }

        return [
            'ref' => $m->application_no,
            'is_demo' => false,
            'application_no' => $m->application_no,
            'bp_file_no' => $m->bp_file_no,
            'file_number' => $m->file_number,
            'file_title' => $m->file_title,
            'developer_name' => $m->developer_name,
            'plot_no' => $m->plot_no,
            'district' => $m->district,
            'lga' => $m->lga,
            'location' => $m->location,
            'plot_size' => $m->plot_size,
            'file_land_use' => $m->file_land_use,
            'proposed_use' => $m->proposed_use === LandUse::OTHER ? ($m->proposed_use_other ?: 'Other') : $m->proposed_use,
            'land_use_check' => $m->land_use_check,
            'land_use_note' => $m->land_use_note,
            'submission_type' => $m->submission_type === 'Other' ? ($m->submission_type_other ?: 'Other') : $m->submission_type,
            'documents_submitted' => (array) $m->documents_submitted,
            'drawings_submitted' => (array) $m->drawings_submitted,
            'number_of_plots' => (int) $m->number_of_plots,
            'submitted_at' => Carbon::parse($m->created_at),
            'stage' => $m->stage ?: BpaStage::ASSESSMENT,
            'bill' => $bill ? [
                'bill_no' => $bill->bill_no,
                'bill_type' => $bill->bill_type,
                'amount' => (float) $bill->amount,
                'status' => $bill->status,
                'receipt_no' => $bill->receipt_no,
                'paid_on' => $bill->paid_on ? Carbon::parse($bill->paid_on) : null,
                'raised_on' => Carbon::parse($bill->created_at),
            ] : null,
            'documents' => $m->files->map(fn ($f) => [
                'name' => $f->original_name,
                'kind' => $f->kind === 'cad' ? 'CAD drawing' : 'Building plan',
                'size' => (int) $f->size_bytes,
                'at' => Carbon::parse($f->created_at),
            ])->all(),
            'office_documents' => [],
            'events' => $events,
            'inspection' => null,
            'progress' => [],
        ];
    }

    /** One desk movement in applicant wording (the desk, never the officer's name). */
    private function movementEvent(string $action, ?string $from, ?string $remarks, Carbon $at, ?string $bp): array
    {
        $desk = BpaStage::label($from);

        return match ($action) {
            'commissioned' => ['title' => 'BP file commissioned', 'body' => 'Your Building Plan file number is '.($bp ?: 'issued').'. The file is with the Director (Arch.).', 'at' => $at, 'action' => $action],
            BpaStage::RECOMMENDED => ['title' => 'Recommended by the '.$desk, 'body' => 'Your file has moved on to the next desk.', 'at' => $at, 'action' => $action],
            BpaStage::NOT_RECOMMENDED => ['title' => 'Not recommended by the '.$desk, 'body' => $remarks ?: 'Returned for correction.', 'at' => $at, 'action' => $action],
            'resent' => ['title' => 'Sent back to the Director (Arch.)', 'body' => 'The correction was made and the file went back for review.', 'at' => $at, 'action' => $action],
            default => ['title' => ucfirst(str_replace('_', ' ', $action)), 'body' => $remarks, 'at' => $at, 'action' => $action],
        };
    }

    /** Adds what every screen derives: the timeline, the location line and what the applicant may do next. */
    private function finish(array $a): array
    {
        $a['timeline'] = PortalTimeline::build($a);
        $a['location_line'] = collect([$a['plot_no'] ? 'Plot '.$a['plot_no'] : null, $a['district'], $a['lga']])->filter()->implode(', ');
        $a['bill_outstanding'] = $a['bill'] && $a['bill']['status'] !== 'Paid';
        $a['can_book_inspection'] = in_array($a['stage'], [BpaStage::ZONAL_OFFICE, PortalTimeline::INSPECTION], true);
        $a['can_report_progress'] = $a['stage'] === PortalTimeline::APPROVED;
        $a['status_label'] = match (true) {
            $a['timeline']['complete'] => 'Approved',
            $a['bill_outstanding'] => 'Awaiting payment',
            $a['timeline']['returned'] !== null => 'Returned for correction',
            default => 'In progress',
        };

        return $a;
    }

    /**
     * Sample applications for the demo applicant: one awaiting payment, one with the Deputy Director, one at
     * inspection, one approved and being built. Dates are relative to today so the screens always look current.
     *
     * @return list<array<string, mixed>>
     */
    private function demo(): array
    {
        $d = fn (int $days, string $time = '10:00') => now()->startOfDay()->subDays($days)->setTimeFromTimeString($time);
        $base = [
            'is_demo' => true,
            'file_title' => 'DEMO APPLICANT',
            'developer_name' => 'DEMO APPLICANT',
            'location' => null,
            'plot_size' => null,
            'submission_type' => 'New Submission',
            'documents_submitted' => ['Certificate of Occupancy', 'Title Deed Plan', 'Architect/Town Planner'],
            'drawings_submitted' => ['Site Plan', 'Architectural', 'Structural', 'Electrical'],
            'number_of_plots' => 1,
            'office_documents' => [],
            'inspection' => null,
            'progress' => [],
        ];
        $plan = fn (int $days) => [
            ['name' => 'building-plan-booklet.pdf', 'kind' => 'Building plan', 'size' => 4_812_331, 'at' => $d($days)],
            ['name' => 'ground-floor.dwg', 'kind' => 'CAD drawing', 'size' => 1_204_771, 'at' => $d($days)],
        ];
        $bill = fn (string $no, int $days, ?string $receipt = null, ?int $paidDays = null) => [
            'bill_no' => $no, 'bill_type' => 'BPA Application Fee', 'amount' => 15000.0,
            'status' => $receipt ? 'Paid' : 'Unpaid', 'receipt_no' => $receipt,
            'paid_on' => $paidDays !== null ? $d($paidDays) : null, 'raised_on' => $d($days),
        ];
        $received = fn (string $no, string $billNo, int $days) => ['title' => 'Application received', 'body' => "Application {$no} received. Bill {$billNo} raised: ₦15,000.00.", 'at' => $d($days, '09:12'), 'action' => 'submitted'];
        $paid = fn (string $billNo, string $receipt, int $days) => ['title' => 'Payment confirmed', 'body' => "Bill {$billNo} paid, receipt {$receipt}.", 'at' => $d($days, '13:40'), 'action' => 'paid'];
        $commissioned = fn (string $bp, int $days) => ['title' => 'BP file commissioned', 'body' => "Your Building Plan file number is {$bp}. The file is with the Director (Arch.).", 'at' => $d($days, '11:05'), 'action' => 'commissioned'];
        $recommended = fn (string $desk, int $days) => ['title' => 'Recommended by the '.$desk, 'body' => 'Your file has moved on to the next desk.', 'at' => $d($days, '15:20'), 'action' => BpaStage::RECOMMENDED];

        return [
            [
                'ref' => 'DEMO-BPA/2026/00004', 'application_no' => 'DEMO-BPA/2026/00004', 'bp_file_no' => null,
                'file_number' => 'COM-DEMO-0004', 'plot_no' => '12', 'district' => 'NASSARAWA GRA', 'lga' => 'NASSARAWA',
                'file_land_use' => 'RESIDENTIAL', 'proposed_use' => 'Commercial', 'land_use_check' => LandUse::MISMATCH,
                'land_use_note' => 'Commercial building proposed on a Residential land file. Flagged for review.',
                'submitted_at' => $d(1, '09:12'), 'stage' => BpaStage::ASSESSMENT,
                'bill' => $bill('DEMO-BILL/2026/00004', 1),
                'documents' => $plan(1),
                'events' => [$received('DEMO-BPA/2026/00004', 'DEMO-BILL/2026/00004', 1)],
            ] + $base,
            [
                'ref' => 'DEMO-BPA/2026/00003', 'application_no' => 'DEMO-BPA/2026/00003', 'bp_file_no' => 'BP-2026-DEMO3',
                'file_number' => 'RES-DEMO-0003', 'plot_no' => '45', 'district' => 'TARAUNI', 'lga' => 'TARAUNI',
                'file_land_use' => 'RESIDENTIAL', 'proposed_use' => 'Residential', 'land_use_check' => LandUse::MATCH, 'land_use_note' => null,
                'submitted_at' => $d(16, '09:12'), 'stage' => BpaStage::DEPUTY_DIRECTOR,
                'bill' => $bill('DEMO-BILL/2026/00003', 16, 'RCPT-DEMO-3', 14),
                'documents' => $plan(16),
                'events' => [
                    $received('DEMO-BPA/2026/00003', 'DEMO-BILL/2026/00003', 16),
                    $paid('DEMO-BILL/2026/00003', 'RCPT-DEMO-3', 14),
                    $commissioned('BP-2026-DEMO3', 13),
                    $recommended('Director (Arch.)', 6),
                ],
            ] + $base,
            [
                'ref' => 'DEMO-BPA/2026/00002', 'application_no' => 'DEMO-BPA/2026/00002', 'bp_file_no' => 'BP-2026-DEMO2',
                'file_number' => 'RES-DEMO-0002', 'plot_no' => '7', 'district' => 'GWALE', 'lga' => 'GWALE',
                'file_land_use' => 'RESIDENTIAL', 'proposed_use' => 'Residential', 'land_use_check' => LandUse::MATCH, 'land_use_note' => null,
                'submitted_at' => $d(40, '09:12'), 'stage' => PortalTimeline::INSPECTION,
                'bill' => $bill('DEMO-BILL/2026/00002', 40, 'RCPT-DEMO-2', 38),
                'documents' => $plan(40),
                'inspection' => ['date' => now()->startOfDay()->addWeekdays(3), 'time' => '11:00', 'status' => 'Requested', 'contact_phone' => '08000000000', 'notes' => 'Gate is on the north side.'],
                'events' => [
                    $received('DEMO-BPA/2026/00002', 'DEMO-BILL/2026/00002', 40),
                    $paid('DEMO-BILL/2026/00002', 'RCPT-DEMO-2', 38),
                    $commissioned('BP-2026-DEMO2', 37),
                    ['title' => 'Not recommended by the Director (Arch.)', 'body' => 'Front setback is less than the 6 m required. Correct the site plan.', 'at' => $d(30, '12:30'), 'action' => BpaStage::NOT_RECOMMENDED],
                    ['title' => 'Sent back to the Director (Arch.)', 'body' => 'The correction was made and the file went back for review.', 'at' => $d(26, '10:00'), 'action' => 'resent'],
                    $recommended('Director (Arch.)', 22),
                    $recommended('Deputy Director (Arch.)', 15),
                    ['title' => 'Site inspection requested', 'body' => 'You asked for a visit on '.now()->startOfDay()->addWeekdays(3)->format('D j M Y').' at 11:00.', 'at' => $d(2, '17:45'), 'action' => 'inspection_requested'],
                ],
            ] + $base,
            [
                'ref' => 'DEMO-BPA/2026/00001', 'application_no' => 'DEMO-BPA/2026/00001', 'bp_file_no' => 'BP-2026-DEMO1',
                'file_number' => 'RES-DEMO-0001', 'plot_no' => '3', 'district' => 'FAGGE', 'lga' => 'FAGGE',
                'file_land_use' => 'RESIDENTIAL', 'proposed_use' => 'Residential', 'land_use_check' => LandUse::MATCH, 'land_use_note' => null,
                'submitted_at' => $d(90, '09:12'), 'stage' => PortalTimeline::APPROVED,
                'bill' => $bill('DEMO-BILL/2026/00001', 90, 'RCPT-DEMO-1', 88),
                'documents' => $plan(90),
                'office_documents' => [['name' => 'Conveyance of Building Plan Approval', 'at' => $d(45)]],
                'progress' => [
                    ['stage' => 'Setting out', 'photos' => 4, 'at' => $d(35, '08:30'), 'lat' => 12.0022, 'lng' => 8.5920],
                    ['stage' => 'Foundation', 'photos' => 6, 'at' => $d(20, '16:10'), 'lat' => 12.0023, 'lng' => 8.5921],
                ],
                'events' => [
                    $received('DEMO-BPA/2026/00001', 'DEMO-BILL/2026/00001', 90),
                    $paid('DEMO-BILL/2026/00001', 'RCPT-DEMO-1', 88),
                    $commissioned('BP-2026-DEMO1', 87),
                    $recommended('Director (Arch.)', 80),
                    $recommended('Deputy Director (Arch.)', 74),
                    ['title' => 'Site inspected', 'body' => 'The zonal officer inspected the site.', 'at' => $d(60, '11:30'), 'action' => 'inspected'],
                    ['title' => 'Building plan approved', 'body' => 'The Conveyance of Building Plan Approval is ready for you.', 'at' => $d(45, '14:00'), 'action' => 'approved'],
                    ['title' => 'Construction progress received', 'body' => '4 photos for Setting out.', 'at' => $d(35, '08:30'), 'action' => 'progress'],
                    ['title' => 'Construction progress received', 'body' => '6 photos for Foundation.', 'at' => $d(20, '16:10'), 'action' => 'progress'],
                ],
            ] + $base,
        ];
    }
}
