<?php

namespace Tests\Feature;

use App\Models\Bpa\Application;
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

/** The KADCA dashboard counts files at each stage and links each figure to its list. */
class DashboardTest extends TestCase
{
    use KlaesFiles, KlaesUsers, RefreshDatabase;

    public function test_the_dashboard_speaks_to_kadca_and_counts_each_stage(): void
    {
        Storage::fake('local');
        $this->travelTo(now()->setDate(2026, 11, 22));
        $officer = $this->klaesUser();
        $make = fn (string $use) => app(SubmitApplication::class)->handle([
            'klaes_file_indexing_id' => $this->klaesFile(), 'proposed_use' => $use, 'developer_name' => 'A', 'contact_address' => 'B',
            'developer_phone' => '08031234567', 'submission_type' => 'New Submission', 'plan_received_as' => 'Scanned at the counter',
            'number_of_plots' => 1,
        ], [UploadedFile::fake()->create('p.pdf', 10)], $officer);

        $unpaid = $make('Commercial');            // flagged, awaiting payment
        $paid = $make('Residential');             // paid, awaiting commissioning
        $atDirector = $make('Residential');       // with the Director (Arch.)
        $returned = $make('Residential');         // returned to Architecture
        foreach ([[$paid, 'R1'], [$atDirector, 'R2'], [$returned, 'R3']] as [$a, $r]) {
            app(RecordPayment::class)->handle($a->bill, ['receipt_no' => $r, 'paid_on' => '2026-11-22', 'amount_paid' => 15000, 'payment_channel' => 'Bank'], $officer);
        }
        app(CommissionBpFile::class)->handle($atDirector, $officer);
        app(CommissionBpFile::class)->handle($returned, $officer);
        app(MoveFile::class)->decide($returned, 'director', 'not_recommended', 'Setback short.', $officer);

        $response = $this->actingAs($officer)->get('/dashboard');

        $response->assertOk()
            ->assertSee('Kano State Development Control Agency')
            ->assertSee('Welcome, Amina Bello')
            ->assertSee('₦60,000.00')   // billed: 4 × 15,000
            ->assertSee('₦45,000.00')   // paid: 3
            ->assertSee('₦15,000.00')   // outstanding: 1
            ->assertDontSee('Total agents');

        $figures = $this->figures($response->getContent());
        $this->assertSame(4, $figures['Applications']);
        $this->assertSame(1, $figures['Awaiting payment']);
        $this->assertSame(1, $figures['Awaiting commissioning']);
        $this->assertSame(2, $figures['BP files']);
        $this->assertSame(1, $figures['With Director (Arch.)']);
        $this->assertSame(1, $figures['Returned to Architecture']);
        $this->assertSame(1, $figures['Land-use flagged']);
        $this->assertSame(0, $figures['With Zonal Office']);

        $response->assertSee(route('bpa.building-plans.index', ['returned' => 1]), false);
        $this->assertSame(4, Application::count());
    }

    /** @return array<string, int> card label => figure */
    private function figures(string $html): array
    {
        preg_match_all('#uppercase tracking-wide text-slate-500 dark:text-slate-400">([^<]+)</div>\s*<div class="mt-2 text-3xl font-bold[^"]*">([\d,]+)</div>#', $html, $m, PREG_SET_ORDER);

        return collect($m)->mapWithKeys(fn ($x) => [html_entity_decode(trim($x[1]), ENT_QUOTES) => (int) str_replace(',', '', $x[2])])->all();
    }
}
