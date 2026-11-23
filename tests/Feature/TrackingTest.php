<?php

namespace Tests\Feature;

use App\Models\Bpa\Application;
use App\Models\Tracking\FileTracker;
use App\Models\Tracking\Unit;
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

/**
 * KADCA File Tracking (KLAES concept): tracking IDs and QR labels, Send / Receive between units, and the physical
 * BP file following the desk decisions. Never mixed with KLAES land-file tracking.
 */
class TrackingTest extends TestCase
{
    use KlaesFiles, KlaesUsers, RefreshDatabase;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo(now()->setDate(2026, 11, 22));
        $this->officer = $this->klaesUser();
    }

    private function application(bool $commission = true): Application
    {
        $a = app(SubmitApplication::class)->handle([
            'klaes_file_indexing_id' => $this->klaesFile(), 'proposed_use' => 'Residential', 'developer_name' => "ISMA'ILA HABU",
            'contact_address' => 'KANO', 'developer_phone' => '08031234567', 'submission_type' => 'New Submission',
            'plan_received_as' => 'Scanned at the counter', 'number_of_plots' => 1,
        ], [UploadedFile::fake()->create('p.pdf', 10)], $this->officer);
        if ($commission) {
            app(RecordPayment::class)->handle($a->bill, ['receipt_no' => 'R'.$a->id, 'paid_on' => '2026-11-22', 'amount_paid' => 15000, 'payment_channel' => 'Bank'], $this->officer);
            $a = app(CommissionBpFile::class)->handle($a, $this->officer);
        }

        return $a;
    }

    private function unit(string $name): Unit
    {
        return Unit::where('name', $name)->firstOrFail();
    }

    public function test_commissioning_starts_tracking_and_sends_the_file_to_the_director(): void
    {
        $a = $this->application();
        $t = FileTracker::where('bpa_application_id', $a->id)->sole();

        $this->assertMatchesRegularExpression('/^KDC-TRK-[2-9A-HJ-NP-Z]{8}$/', $t->tracking_id);
        $this->assertSame('BP-2026-1', $t->reference);
        $this->assertSame('in_transit', $t->status);
        $this->assertSame($this->unit('Architecture')->id, (int) $t->current_unit_id);
        $this->assertSame($this->unit('Director (Arch.)')->id, (int) $t->to_unit_id);
        $this->assertSame(['logged', 'sent'], $t->movements()->orderBy('id')->pluck('action')->all());
    }

    public function test_the_physical_file_follows_the_desk_decisions(): void
    {
        $a = $this->application();
        $t = FileTracker::where('bpa_application_id', $a->id)->sole();

        // The Director acts before the file was received: it is received there, then sent to the Deputy Director.
        app(MoveFile::class)->decide($a, 'director', 'recommended', null, $this->officer);
        $t->refresh();
        $this->assertSame('in_transit', $t->status);
        $this->assertSame($this->unit('Director (Arch.)')->id, (int) $t->current_unit_id);
        $this->assertSame($this->unit('Deputy Director (Arch.)')->id, (int) $t->to_unit_id);

        // Received at the Deputy Director, who returns it: it goes back to the Director.
        $this->actingAs($this->officer)->post(route('tracking.receive', $t))->assertSessionHasNoErrors();
        app(MoveFile::class)->decide($a, 'deputy-director', 'not_recommended', 'Fire report missing.', $this->officer);
        $t->refresh();
        $this->assertSame($this->unit('Director (Arch.)')->id, (int) $t->to_unit_id);
        $this->assertSame('Not recommended by the Deputy Director (Arch.); returned', $t->movements()->latest('id')->first()->purpose);
    }

    public function test_a_mail_is_logged_sent_and_received(): void
    {
        $t = app(\App\Services\Tracking\FileTracking::class)->log([
            'kind' => 'mail', 'reference' => 'KADCA/MD/12', 'title' => 'OBJECTION TO BUILDING ON PLOT 3075', 'mail_from' => 'RESIDENTS OF KUMBOTSO',
            'unit_id' => $this->unit('Registry')->id, 'priority' => 'High', 'due_on' => '2026-11-30',
        ], $this->officer);
        $this->assertSame('KADCA/MD/12', $t->reference);

        // Not to the unit it is already in, and not twice while in transit.
        $this->actingAs($this->officer)->post(route('tracking.send', $t), ['to_unit_id' => $this->unit('Registry')->id, 'purpose' => 'x'])
            ->assertSessionHasErrors('to_unit_id', null, 'send');
        $this->actingAs($this->officer)->post(route('tracking.send', $t), ['to_unit_id' => $this->unit("MD's Office")->id, 'purpose' => 'For the MD'])
            ->assertSessionHasNoErrors();
        $this->actingAs($this->officer)->post(route('tracking.send', $t), ['to_unit_id' => $this->unit('Archive')->id, 'purpose' => 'x'])
            ->assertSessionHasErrors('to_unit_id', null, 'send');

        $this->actingAs($this->officer)->getJson('/file-tracking/movement/data?office='.$this->unit("MD's Office")->id.'&kind=mails&dir=incoming&state=pending')
            ->assertOk()->assertJsonPath('rows.0.tracking_id', $t->tracking_id)->assertJsonPath('rows.0.can_receive', true)
            ->assertJsonPath('counts.pending.mails', 1);

        $this->actingAs($this->officer)->post(route('tracking.receive', $t), ['shelf_location' => 'md cabinet 2'])->assertSessionHasNoErrors();
        $t->refresh();
        $this->assertSame('in_unit', $t->status);
        $this->assertSame($this->unit("MD's Office")->id, (int) $t->current_unit_id);
        $this->assertSame('MD CABINET 2', $t->shelf_location);
        $this->actingAs($this->officer)->post(route('tracking.receive', $t))->assertSessionHasErrors('tracker');
    }

    public function test_track_file_archive_shows_the_file_details(): void
    {
        $this->application();
        $t = FileTracker::sole();
        app(\App\Services\Tracking\FileTracking::class)->receive($t, null, $this->officer, 'RACK 3 SHELF 2');

        $this->actingAs($this->officer)->get('/file-tracking/find')->assertOk()->assertSee('Archive Search')->assertSee('File Details');
        $this->actingAs($this->officer)->getJson('/file-tracking/find/lookup?q='.$t->tracking_id)
            ->assertJson(['found' => true, 'file_number' => 'BP-2026-1', 'land_use' => 'RESIDENTIAL', 'origin' => 'Architecture',
                'destination' => 'Director (Arch.)', 'rack' => '3', 'shelf_no' => '2', 'full_rack_shelf' => 'RACK 3 SHELF 2']);
        $this->actingAs($this->officer)->getJson('/file-tracking/find/lookup?q=BP-2026-9')->assertJson(['found' => false]);
    }

    public function test_file_movement_department_scans_and_logs_manually(): void
    {
        $this->application();
        $t = FileTracker::sole();
        $director = $this->unit('Director (Arch.)')->id;

        $this->actingAs($this->officer)->get('/file-tracking/movement?unit='.$director)->assertOk()
            ->assertSee('File Movement (Department)')->assertSee('Pending Receipt')->assertSee('Log manually');
        $this->actingAs($this->officer)->getJson('/file-tracking/movement/scan?q='.strtolower($t->tracking_id))
            ->assertJson(['found' => true, 'file_number' => 'BP-2026-1', 'status' => 'in_transit', 'to_unit_id' => $director]);
        $this->actingAs($this->officer)->getJson('/file-tracking/movement/scan?q=BP-2026-1')->assertJson(['found' => true]);
        $this->actingAs($this->officer)->getJson('/file-tracking/movement/scan?q=NOTHING')->assertJson(['found' => false]);
        $this->actingAs($this->officer)->getJson('/file-tracking/movement/data?office='.$director)->assertJson(['counts' => ['pending' => ['files' => 1, 'total' => 1]]]);

        $this->actingAs($this->officer)->postJson('/file-tracking/movement/log', ['office' => $director, 'kind' => 'mail', 'reference' => 'kadca/dir/7', 'title' => 'site visit report'])
            ->assertOk()->assertJsonStructure(['tracking_id', 'label_url']);
        $mail = FileTracker::where('kind', 'mail')->sole();
        $this->assertSame(['KADCA/DIR/7', 'SITE VISIT REPORT', 'in_unit', $director], [$mail->reference, $mail->title, $mail->status, (int) $mail->current_unit_id]);
        $this->actingAs($this->officer)->postJson('/file-tracking/movement/log', ['office' => $director, 'kind' => 'mail'])->assertStatus(422);
    }

    public function test_the_page_label_and_dashboard_show_the_tracking(): void
    {
        $a = $this->application();
        $t = FileTracker::sole();

        $this->actingAs($this->officer)->get(route('tracking.show', $t))
            ->assertOk()->assertSee('<svg', false)->assertSee('Receive in Director (Arch.)')
            ->assertSeeInOrder(['Logged into Architecture', 'Sent from Architecture to Director (Arch.)']);
        $this->actingAs($this->officer)->get(route('tracking.label', $t))->assertOk()->assertSee('<svg', false)->assertSee('BP-2026-1')->assertSee('KLAES-CONTROL');
        $this->actingAs($this->officer)->get('/file-tracking')->assertOk()->assertSee('File Tracker Dashboard')->assertSee('Director (Arch.)');
        $this->actingAs($this->officer)->get(route('bpa.applications.show', $a))->assertSee($t->tracking_id);
    }

    public function test_units_can_be_added_renamed_and_retired_but_not_while_holding_files(): void
    {
        $this->actingAs($this->officer)->post('/file-tracking/units', ['name' => '  Survey   Unit '])->assertSessionHasNoErrors();
        $this->assertTrue(Unit::where('name', 'Survey Unit')->exists());
        $this->actingAs($this->officer)->post('/file-tracking/units', ['name' => 'survey unit'])->assertSessionHasErrors('name', null, 'unit');

        $this->application(); // a file now heads to the Director (Arch.)
        $director = $this->unit('Director (Arch.)');
        $this->actingAs($this->officer)->patch(route('tracking.units.update', $director), ['name' => 'Director (Arch.)', 'is_active' => '0'])
            ->assertSessionHasErrors('name', null, 'unit');
        $this->assertTrue($director->fresh()->is_active);

        $survey = Unit::where('name', 'Survey Unit')->sole();
        $this->actingAs($this->officer)->patch(route('tracking.units.update', $survey), ['name' => 'Survey Section', 'is_active' => '0'])->assertSessionHasNoErrors();
        $this->assertFalse($survey->fresh()->is_active);
        $this->assertSame('Survey Section', $survey->fresh()->name);
    }

    public function test_the_menu_links_file_tracking(): void
    {
        $html = $this->actingAs($this->officer)->get('/file-tracking/find')->getContent();

        foreach (['tracking.dashboard', 'tracking.create', 'tracking.search', 'tracking.movement'] as $name) {
            $this->assertStringContainsString('href="'.route($name).'"', $html);
        }
        $this->assertMatchesRegularExpression('#href="'.preg_quote(route('tracking.find'), '#').'"\s+aria-current="page"#', $html);
    }
}
