<?php

namespace Tests\Feature;

use App\Models\Bpa\Application;
use App\Models\Tracking\FileRequest;
use App\Models\Tracking\FileTracker;
use App\Models\Tracking\Unit;
use App\Models\User;
use App\Services\Bpa\CommissionBpFile;
use App\Services\Bpa\RecordPayment;
use App\Services\Bpa\SubmitApplication;
use App\Services\Tracking\FileTracking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\KlaesFiles;
use Tests\Support\KlaesUsers;
use Tests\TestCase;

/** Quick Search & File Location, copied from KLAES: resolve, file search requests, Registry Monitor answers, report. */
class QuickSearchTest extends TestCase
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

    private function resolve(string $q)
    {
        return $this->actingAs($this->officer)->getJson('/file-tracking/search/resolve?query='.urlencode($q));
    }

    private function request(string $fileNumber, array $extra = [])
    {
        return $this->actingAs($this->officer)->postJson('/file-tracking/search/requests', array_merge([
            'file_number' => $fileNumber, 'requester_unit_id' => Unit::where('name', 'Architecture')->value('id'),
            'requester_name' => 'musa ali', 'purpose' => 'For inspection',
        ], $extra));
    }

    public function test_the_page_is_the_klaes_quick_search(): void
    {
        $this->actingAs($this->officer)->get('/file-tracking/search')
            ->assertOk()
            ->assertSee('File Location &amp; Status', false)
            ->assertSeeInOrder(['File Search Report', 'Requests Today', 'Total Blind / Open', 'Total Found', 'Total Not Found', 'Total Not With KADCA (NWK)', 'Total Awaiting'])
            ->assertSee('Enter File Number')->assertSee('id="qs-pick"', false)->assertSee('readonly', false)
            ->assertSee('Registry Monitor Feedback')->assertSee('File Search History');
    }

    public function test_search_outcomes(): void
    {
        $this->resolve('BP-2026-99')->assertOk()->assertJson(['found' => false, 'status' => 'FILE_NOT_FOUND', 'can_send_fr' => false]);

        $untracked = $this->application(false);
        $this->resolve($untracked->application_no)->assertJson(['found' => true, 'status' => 'PENDING_FILE', 'can_send_fr' => true]);

        $a = $this->application();
        $t = FileTracker::where('bpa_application_id', $a->id)->sole();
        $this->resolve('BP-2026-1')->assertJson(['status' => 'IN_TRANSIT', 'receiving_department' => 'Director (Arch.)', 'can_send_fr' => false, 'tracking_id' => $t->tracking_id]);
        $this->resolve($t->tracking_id)->assertJson(['file_number' => 'BP-2026-1']); // a scanned QR label

        app(FileTracking::class)->receive($t, null, $this->officer);
        $this->resolve('BP-2026-1')->assertJson(['status' => 'IN_UNIT', 'unit_name' => 'Director (Arch.)', 'can_send_fr' => true])
            ->assertJsonPath('movements.2.action', 'received');
    }

    public function test_a_request_is_answered_by_the_registry_monitor(): void
    {
        $a = $this->application();
        app(FileTracking::class)->receive(FileTracker::sole(), null, $this->officer);

        $this->request('BP-2026-1')->assertOk()->assertJsonPath('request_no', 'FR-KDC-2026-00001')
            ->assertJsonPath('result.next_action', 'Request sent; awaiting the Registry Monitor.');
        $this->request('BP-2026-1')->assertStatus(422); // one awaiting request at a time
        $fr = FileRequest::sole();
        $this->assertSame('MUSA ALI', $fr->requester_name);
        $this->assertFalse($fr->is_blind);

        $this->actingAs($this->officer)->postJson(route('tracking.quick-search.respond', $fr), ['outcome' => 'nwk', 'note' => 'Not on the shelf'])->assertOk();
        $this->resolve('BP-2026-1')->assertJson(['status' => 'MISSING_FILE', 'fr_not_found' => ['not_found_type' => 'MISSING', 'note' => 'Not on the shelf']]);
        $this->actingAs($this->officer)->postJson(route('tracking.quick-search.respond', $fr), ['outcome' => 'found'])->assertStatus(422);

        $this->request('BP-2026-1')->assertOk();
        $this->actingAs($this->officer)->postJson(route('tracking.quick-search.respond', FileRequest::latest('id')->first()), ['outcome' => 'found'])->assertOk();
        $this->resolve('BP-2026-1')->assertJson(['status' => 'IN_UNIT_FOUND', 'fr_found' => ['receiving_officer' => 'MUSA ALI']]);
    }

    public function test_report_history_and_feedback(): void
    {
        $untracked = $this->application(false);
        $this->request($untracked->application_no)->assertOk(); // blind: not tracked
        $a = $this->application();
        app(FileTracking::class)->receive(FileTracker::sole(), null, $this->officer);
        $this->request('BP-2026-1')->assertOk();
        $this->actingAs($this->officer)->postJson(route('tracking.quick-search.respond', FileRequest::where('file_number', 'BP-2026-1')->sole()), ['outcome' => 'pending'])->assertOk();

        $this->actingAs($this->officer)->getJson('/file-tracking/search/report')->assertJson(['report' => [
            'submitted_today' => 2, 'today_awaiting' => 1, 'today_not_found' => 1, 'blind' => 1, 'not_found' => 1, 'missing' => 0, 'awaiting' => 1,
        ]]);
        $this->actingAs($this->officer)->getJson('/file-tracking/search/log')->assertJson(['total' => 2, 'counts' => ['awaiting' => 1, 'not_found' => 1]]);
        $this->actingAs($this->officer)->getJson('/file-tracking/search/log?status=awaiting')->assertJsonCount(1, 'rows');
        $this->actingAs($this->officer)->getJson('/file-tracking/search/feedback')->assertJsonCount(1, 'rows')->assertJsonPath('rows.0.status_label', 'Not Found (Pending)');

        $csv = $this->actingAs($this->officer)->get('/file-tracking/search/report.csv')->streamedContent();
        $this->assertStringContainsString('FR-KDC-2026-00001', $csv);
        $this->assertStringContainsString('"Not Found (Pending)"', $csv);
    }
}
