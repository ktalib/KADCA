<?php

namespace Tests\Feature;

use App\Models\Bpa\Application;
use App\Models\User;
use App\Services\Bpa\CommissionBpFile;
use App\Services\Bpa\MoveFile;
use App\Services\Bpa\RecordPayment;
use App\Services\Bpa\SubmitApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\KlaesFiles;
use Tests\Support\KlaesUsers;
use Tests\TestCase;

/** KADCA > Reports: figures for a date range, each chart with its table, and the applications register. */
class ReportTest extends TestCase
{
    use KlaesFiles, KlaesUsers, RefreshDatabase;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->officer = $this->klaesUser();

        // Last year: one application (outside the default "this year" range).
        $this->travelTo(now()->setDate(2025, 6, 1));
        $this->apply('Residential', 'Kumbotso');

        // This year: three applications in two LGAs (one LGA written in two cases), one flagged.
        $this->travelTo(now()->setDate(2026, 3, 10));
        $a = $this->apply('Residential', 'Kumbotso');
        $this->apply('Commercial', 'KUMBOTSO');
        $this->travelTo(now()->setDate(2026, 4, 2));
        $c = $this->apply('Residential', 'Nassarawa', 2);

        foreach ([[$a, 'R1'], [$c, 'R2']] as [$x, $r]) {
            app(RecordPayment::class)->handle($x->bill, ['receipt_no' => $r, 'paid_on' => now()->toDateString(), 'amount_paid' => (float) $x->bill->amount, 'payment_channel' => 'Bank'], $this->officer);
            app(CommissionBpFile::class)->handle($x, $this->officer);
        }
        app(MoveFile::class)->decide($a, 'director', 'recommended', null, $this->officer);
        app(MoveFile::class)->decide($c, 'director', 'not_recommended', 'Setback short.', $this->officer);
        $this->travelTo(now()->setDate(2026, 11, 22));
    }

    private function apply(string $use, string $lga, int $plots = 1): Application
    {
        return app(SubmitApplication::class)->handle([
            'klaes_file_indexing_id' => $this->klaesFile(['lga' => $lga]), 'proposed_use' => $use, 'developer_name' => 'DEV '.$lga,
            'contact_address' => 'KANO', 'developer_phone' => '08031234567', 'submission_type' => 'New Submission',
            'plan_received_as' => 'Scanned at the counter', 'number_of_plots' => $plots,
        ], [UploadedFile::fake()->create('p.pdf', 10)], $this->officer);
    }

    public function test_the_report_covers_this_year_by_default(): void
    {
        $response = $this->actingAs($this->officer)->get('/reports');

        $response->assertOk()
            ->assertSee('covering 1 Jan 2026 to 31 Dec 2026')
            ->assertSee('₦60,000.00')        // billed this year: 1 + 1 + 2 plots
            ->assertSee('₦45,000.00')        // paid: 15k + 30k
            ->assertSeeInOrder(['KUMBOTSO', '2', '1', 'NASSARAWA', '1', '0'])   // one row for Kumbotso whatever the case
            ->assertSeeInOrder(['Director (Arch.)', '1', '1', 'Deputy Director (Arch.)', '0', '0'])
            ->assertSee('rp-monthly', false);

        $kpis = $response->viewData('kpis');
        $this->assertSame(3, $kpis['applications']['received']);
        $this->assertSame(4, $kpis['applications']['plots']);
        $this->assertSame(1, $kpis['applications']['flagged']);
        $this->assertSame(2, $kpis['payments']['commissioned']);
        $this->assertSame(1, $kpis['desks']['deputy']);
        $this->assertSame(1, $kpis['desks']['returned']);
    }

    public function test_the_range_can_be_changed(): void
    {
        $kpis = $this->actingAs($this->officer)->get('/reports?from=2025-01-01&to=2025-12-31')->viewData('kpis');
        $this->assertSame(1, $kpis['applications']['received']);
        $this->assertSame(0, $kpis['payments']['commissioned']);

        $kpis = $this->actingAs($this->officer)->get('/reports?from=2026-04-30&to=2026-03-01')->viewData('kpis'); // swapped dates
        $this->assertSame(3, $kpis['applications']['received']);
    }

    public function test_the_register_lists_the_applications_in_the_range(): void
    {
        $response = $this->actingAs($this->officer)->get('/reports/applications-register.csv');

        $response->assertOk();
        $lines = array_values(array_filter(explode("\n", trim($response->streamedContent()))));
        $this->assertCount(4, $lines); // header + 3 this year
        $this->assertStringContainsString('"Plot No.",District,LGA,Location', $lines[0]);
        $this->assertStringContainsString('BPA-APP/2026/00001', $lines[1]);
        $this->assertStringContainsString('BP-2026-1', $lines[1]);
        $this->assertStringContainsString('"Deputy Director (Arch.)"', $lines[1]);
        $this->assertStringContainsString('Mismatch', $lines[2]);
        $this->assertStringContainsString('Architecture', $lines[3]);
    }
}
