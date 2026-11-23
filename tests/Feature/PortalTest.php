<?php

namespace Tests\Feature;

use App\Services\Bpa\CommissionBpFile;
use App\Services\Bpa\MoveFile;
use App\Services\Bpa\RecordPayment;
use App\Services\Bpa\SubmitApplication;
use App\Support\Portal\PortalSession;
use App\Support\Portal\PortalTimeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\KlaesFiles;
use Tests\Support\KlaesUsers;
use Tests\TestCase;

/**
 * The KADCA applicant portal (UI first): its own sign-in, the demo applicant, every screen, the preview saves, and
 * error pages that stay inside the portal.
 */
class PortalTest extends TestCase
{
    use KlaesFiles, KlaesUsers, RefreshDatabase;

    private function asDemo(): static
    {
        return $this->withSession([PortalSession::KEY => [
            'username' => 'demo.applicant', 'name' => 'Demo Applicant', 'phone' => '08000000000', 'email' => null,
            'is_demo' => true, 'since' => now()->toDateTimeString(),
        ]]);
    }

    public function test_the_sign_in_page_is_kadca_branded_and_offers_the_demo_applicant(): void
    {
        $this->get('/portal/login')->assertOk()
            ->assertSee('KLAES-CONTROL')
            ->assertSee('Applicant Portal')
            ->assertSee('images/logo.png', false)
            ->assertSee('Sign in as the demo applicant')
            ->assertSee('demo.applicant');

        $this->get('/portal/register')->assertOk()->assertSee('Create an account')->assertSee('Mobile number');
    }

    public function test_guests_are_sent_to_the_portal_sign_in_not_the_staff_login(): void
    {
        foreach (['/portal/dashboard', '/portal/applications/create', '/portal/construction-progress', '/portal/inspections', '/portal/bills', '/portal/notifications', '/portal/profile'] as $url) {
            $this->get($url)->assertRedirect(route('portal.login'));
        }
    }

    public function test_guests_get_the_landing_page_with_sign_in_and_sign_up(): void
    {
        $this->get('/portal')->assertOk()
            ->assertSee('KLAES-CONTROL')
            ->assertSee('Applicant Portal')
            ->assertSee('images/landing/kano-1.webp', false);

        $response = $this->get('/portal')->assertOk();
        foreach (['What you can do', 'How your application moves', 'What you need', 'What it costs', 'Frequently asked', 'Talk to KADCA'] as $section) {
            $response->assertSee($section);
        }
        $response->assertSee('href="'.route('portal.login').'"', false)
            ->assertSee('href="'.route('portal.register').'"', false)
            ->assertSee('Sign in')
            ->assertSee('Create account')
            ->assertSee('₦'.number_format((int) config('kadca.application_fee_per_plot'), 2))
            ->assertSee('BP file commissioned')
            ->assertSee('app-preloader', false)
            ->assertSee("localStorage.getItem('theme')", false);

        // The sign-in and register pages lead back to the landing page.
        $this->get('/portal/login')->assertOk()->assertSee('Back to home')->assertSee('href="'.route('portal.home').'"', false);
        $this->get('/portal/register')->assertOk()->assertSee('Back to home');
    }

    public function test_a_signed_in_applicant_on_the_landing_page_goes_to_the_dashboard(): void
    {
        $this->asDemo()->get('/portal')->assertRedirect(route('portal.dashboard'));
    }

    public function test_the_demo_applicant_signs_in_by_button_or_password_and_a_wrong_password_is_refused(): void
    {
        $this->post('/portal/demo')->assertRedirect(route('portal.dashboard'));
        $this->assertTrue(session(PortalSession::KEY)['is_demo']);

        $this->post('/portal/logout')->assertRedirect(route('portal.login'));
        $this->assertNull(session(PortalSession::KEY));

        $this->post('/portal/login', ['login' => 'demo.applicant', 'password' => 'wrong'])->assertSessionHasErrors('login');
        $this->post('/portal/login', ['login' => 'Demo.Applicant', 'password' => config('kadca.portal.demo.password')])
            ->assertRedirect(route('portal.dashboard'));

        // Signed in, the sign-in page sends the applicant to the dashboard.
        $this->get('/portal/login')->assertRedirect(route('portal.dashboard'));
    }

    public function test_the_demo_can_be_switched_off(): void
    {
        config(['kadca.portal.demo_enabled' => false]);

        $this->get('/portal/login')->assertOk()->assertDontSee('Sign in as the demo applicant');
        $this->post('/portal/demo')->assertNotFound();
        $this->post('/portal/login', ['login' => 'demo.applicant', 'password' => config('kadca.portal.demo.password')])->assertSessionHasErrors('login');
    }

    public function test_register_confirms_the_phone_and_signs_in_for_the_session_only(): void
    {
        $this->post('/portal/register', ['name' => '  aminu   bello ', 'phone' => '0803 123 4567', 'password' => 'secret123', 'password_confirmation' => 'secret123', 'terms' => '1'])
            ->assertSessionHasErrors('phone');

        $this->post('/portal/register', ['name' => '  aminu   bello ', 'phone' => '08031234567', 'email' => 'Aminu@Example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123', 'terms' => '1'])
            ->assertRedirect(route('portal.verify'));
        $pending = session(PortalSession::PENDING);
        $this->assertSame('Aminu Bello', $pending['name']);
        $this->assertSame('aminu@example.com', $pending['email']);

        $this->get('/portal/register/verify')->assertOk()->assertSee($pending['code']);
        $this->post('/portal/register/verify', ['code' => '000000' === $pending['code'] ? '111111' : '000000'])->assertSessionHasErrors('code');
        $this->post('/portal/register/verify', ['code' => $pending['code']])->assertRedirect(route('portal.dashboard'));

        $this->get('/portal/dashboard')->assertOk()
            ->assertSee('Welcome, Aminu Bello')
            ->assertSee('You have not applied yet');
        $this->assertDatabaseCount('bpa_applications', 0);
    }

    public function test_every_portal_screen_renders_for_the_demo_applicant(): void
    {
        $screens = [
            '/portal/dashboard' => ['My applications', 'DEMO-BPA/2026/00004', 'Pay bill DEMO-BILL/2026/00004', 'Preview'],
            '/portal/applications/create' => ['New building plan application', 'Your land file', 'Fee &amp; review', 'Submit application'],
            '/portal/bills' => ['Bills', 'DEMO-BILL/2026/00001', 'S/N', 'aria-label="Actions"'],
            '/portal/notifications' => ['Notifications', 'BP file commissioned'],
            '/portal/construction-progress' => ['Construction progress', 'Take a photo', 'Your location', 'DEMO-BPA/2026/00001', '6 photos'],
            '/portal/inspections' => ['Schedule a site inspection', 'Request this visit', 'DEMO-BPA/2026/00002', 'Requested'],
            '/portal/profile' => ['My profile', 'demo.applicant', 'Change password'],
        ];

        foreach ($screens as $url => $texts) {
            $response = $this->asDemo()->get($url)->assertOk();
            foreach ($texts as $text) {
                $response->assertSee($text, false);
            }
            // Both themes on every screen.
            $response->assertSee('localStorage.getItem(\'theme\')', false)->assertSee('dark:bg-slate-950', false);
        }
    }

    public function test_the_dashboard_figures_follow_the_demo_applications(): void
    {
        $html = $this->asDemo()->get('/portal/dashboard')->assertOk()->getContent();

        preg_match_all('/data-figure="([^"]+)">(\d+)</', $html, $m);
        $figures = array_combine($m[1], array_map('intval', $m[2]));
        $this->assertSame(['My applications' => 4, 'Awaiting payment' => 1, 'Under review' => 2, 'Approved' => 1], $figures);
    }

    public function test_the_application_page_shows_the_timeline_bill_and_documents(): void
    {
        // Awaiting payment: pay instructions, the bill is the current step, the mismatch is flagged.
        $this->asDemo()->get('/portal/applications/DEMO-BPA/2026/00004')->assertOk()
            ->assertSeeInOrder(['Submitted', 'Application bill', 'Paid', 'BP file commissioned', 'Director (Arch.)', 'DD (Arch.)', 'Zonal Office', 'Inspection', 'Conveyance'])
            ->assertSee('How to pay')
            ->assertSee('Awaiting your payment')
            ->assertSee('Flagged for review')
            ->assertSeeInOrder(['Plot No.', 'District', 'LGA', 'Location'])
            ->assertSee('building-plan-booklet.pdf');

        // With the Deputy Director; then at inspection after a return; then approved with the conveyance.
        $this->asDemo()->get('/portal/applications/BP-2026-DEMO3')->assertOk()->assertSee('BP-2026-DEMO3')->assertSee('Now: DD (Arch.)');
        $this->asDemo()->get('/portal/applications/DEMO-BPA/2026/00002')->assertOk()->assertSee('Not recommended by the Director (Arch.)')->assertSee('Change the inspection')->assertSee('Requested: the Zonal Office confirms');
        $this->asDemo()->get('/portal/applications/DEMO-BPA/2026/00001')->assertOk()->assertSee('Approved.')->assertSee('Conveyance of Building Plan Approval');

        // An unknown number is a 404 card that stays in the portal.
        $this->asDemo()->get('/portal/applications/DEMO-BPA/2026/09999')->assertNotFound()
            ->assertSee('KLAES-CONTROL Applicant Portal')
            ->assertSee(route('portal.dashboard'), false)
            ->assertDontSee('href="'.url('/login').'"', false);
    }

    public function test_a_real_application_opens_by_number_in_preview_without_staff_names(): void
    {
        Storage::fake('local');
        $this->travelTo(now()->setDate(2026, 11, 22));
        $officer = $this->klaesUser();
        $application = app(SubmitApplication::class)->handle([
            'klaes_file_indexing_id' => $this->klaesFile(), 'proposed_use' => 'Residential', 'developer_name' => 'ISMA\'ILA HABU',
            'contact_address' => 'NO 5 ZOO ROAD', 'developer_phone' => '08031234567', 'submission_type' => 'New Submission',
            'plan_received_as' => 'Scanned at the counter', 'number_of_plots' => 1,
        ], [UploadedFile::fake()->create('plan.pdf', 10)], $officer);
        app(RecordPayment::class)->handle($application->bill, ['receipt_no' => 'R-1', 'paid_on' => '2026-11-22', 'amount_paid' => 15000, 'payment_channel' => 'Bank'], $officer);
        app(CommissionBpFile::class)->handle($application->fresh(), $officer);
        app(MoveFile::class)->decide($application->fresh(), 'director', 'not_recommended', 'Setback short.', $officer);
        $application->refresh();

        $this->asDemo()->get('/portal/applications/track?number='.strtolower($application->application_no))
            ->assertRedirect(route('portal.applications.show', $application->application_no));

        $this->asDemo()->get('/portal/applications/'.$application->application_no)->assertOk()
            ->assertSee($application->bp_file_no)
            ->assertSee('Returned for correction')
            ->assertSee('Setback short.')
            ->assertSee('plan.pdf')
            ->assertSee('read-only')
            ->assertDontSee($officer->name);

        config(['kadca.portal.preview' => false]);
        $this->asDemo()->get('/portal/applications/'.$application->application_no)->assertNotFound();
    }

    public function test_the_land_file_selector_matches_file_numbers_only_and_hides_contact_details(): void
    {
        $id = $this->klaesFile();

        $this->asDemo()->getJson('/portal/land-files/search?q=HABU')->assertOk()->assertJsonCount(0, 'files');
        $this->asDemo()->getJson('/portal/land-files/search?q=RE')->assertOk()->assertJsonCount(0, 'files');
        $found = $this->asDemo()->getJson('/portal/land-files/search?q=res-2016')->assertOk()->assertJsonPath('files.0.id', $id);
        $this->assertArrayNotHasKey('phone', $found->json('files.0'));
        $this->assertArrayNotHasKey('address', $found->json('files.0'));

        $this->asDemo()->getJson('/portal/land-files/'.$id)->assertOk()->assertJsonPath('file.file_number', 'RES-2016-452')->assertJsonMissingPath('file.phone');
        $this->asDemo()->getJson('/portal/land-files/999999')->assertNotFound();
        $this->flushSession();
        $this->getJson('/portal/land-files/search?q=RES')->assertUnauthorized();
    }

    public function test_submitting_an_application_is_checked_like_the_staff_form_and_saves_nothing(): void
    {
        $this->asDemo()->post('/portal/applications', [])->assertSessionHasErrors(['klaes_file_indexing_id', 'proposed_use', 'developer_name', 'plan_files', 'declaration']);
        $this->asDemo()->post('/portal/applications', ['plan_received_as' => 'Scanned at the counter'])->assertSessionHasErrors('plan_received_as');

        $this->asDemo()->post('/portal/applications', [
            'klaes_file_indexing_id' => $this->klaesFile(), 'proposed_use' => 'Residential', 'developer_name' => 'isma\'ila habu',
            'contact_address' => 'no 5 zoo road', 'developer_phone' => '08031234567', 'submission_type' => 'New Submission',
            'plan_received_as' => 'CAD drawing (DXF/DWG)', 'plan_files' => [UploadedFile::fake()->create('ground.dxf', 10)],
            'number_of_plots' => 2, 'declaration' => '1',
        ])->assertRedirect(route('portal.dashboard'))->assertSessionHas('status', fn ($s) => str_contains($s, 'Saved (preview)') && str_contains($s, '₦30,000.00'));

        $this->assertDatabaseCount('bpa_applications', 0);
        $this->assertDatabaseCount('bills', 0);
    }

    public function test_progress_and_inspection_requests_are_checked_and_answer_saved_preview(): void
    {
        // Construction progress: only approved applications, and the location is required.
        $base = ['application' => 'DEMO-BPA/2026/00001', 'construction_stage' => 'Foundation', 'photos' => [UploadedFile::fake()->image('site.jpg')], 'captured_at' => now()->toIso8601String()];
        $this->asDemo()->post('/portal/construction-progress', $base)->assertSessionHasErrors('latitude');
        $this->asDemo()->post('/portal/construction-progress', ['application' => 'DEMO-BPA/2026/00004', 'latitude' => 12, 'longitude' => 8.5] + $base)->assertSessionHasErrors('application');
        $this->asDemo()->post('/portal/construction-progress', ['latitude' => 12.0022, 'longitude' => 8.592] + $base)
            ->assertSessionHasNoErrors()->assertSessionHas('status', fn ($s) => str_contains($s, 'Saved (preview): 1 photo for Foundation'));

        // Site inspection: an open weekday and a listed slot, at the Zonal Office / Inspection step only.
        $day = now()->startOfDay()->addDays(2);
        while ($day->isWeekend()) {
            $day->addDay();
        }
        $ok = ['application' => 'DEMO-BPA/2026/00002', 'date' => $day->toDateString(), 'time' => '11:00', 'contact_phone' => '08031234567'];
        $this->asDemo()->post('/portal/inspections', ['date' => now()->toDateString()] + $ok)->assertSessionHasErrors('date');
        $this->asDemo()->post('/portal/inspections', ['time' => '23:00'] + $ok)->assertSessionHasErrors('time');
        $this->asDemo()->post('/portal/inspections', ['application' => 'DEMO-BPA/2026/00003'] + $ok)->assertSessionHasErrors('application');
        $this->asDemo()->post('/portal/inspections', $ok)->assertSessionHasNoErrors()->assertSessionHas('status', fn ($s) => str_contains($s, 'Saved (preview)'));

        $this->asDemo()->put('/portal/profile', ['name' => 'x'])->assertSessionHasErrors('name', null, 'details');
        $this->asDemo()->put('/portal/profile', ['name' => 'demo applicant'])->assertSessionHas('status');
        $this->asDemo()->put('/portal/profile/password', ['current_password' => 'a', 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password', null, 'password');
    }

    public function test_staff_error_pages_are_unchanged(): void
    {
        $this->get('/no-such-staff-page')->assertNotFound()->assertSee('Not Found')->assertDontSee('Applicant Portal');
    }

    public function test_the_timeline_reads_the_staff_stages(): void
    {
        $t = PortalTimeline::build(['stage' => 'dd_arch', 'bp_file_no' => 'BP-2026-1', 'bill' => ['bill_no' => 'B', 'amount' => 15000, 'status' => 'Paid', 'receipt_no' => 'R', 'paid_on' => null], 'events' => []]);
        $this->assertSame('DD (Arch.)', $t['current']);
        $this->assertSame(56, $t['percent']); // 5 of 9 steps done

        $unpaid = PortalTimeline::build(['stage' => 'assessment', 'bp_file_no' => null, 'bill' => ['bill_no' => 'B', 'amount' => 15000, 'status' => 'Unpaid'], 'events' => []]);
        $this->assertSame('Paid', $unpaid['current']);

        $returned = PortalTimeline::build(['stage' => 'architecture', 'bp_file_no' => 'BP-2026-2', 'bill' => ['bill_no' => 'B', 'amount' => 1, 'status' => 'Paid'], 'events' => [['action' => 'not_recommended', 'body' => 'Setback short.']]]);
        $this->assertSame('Director (Arch.)', $returned['current']);
        $this->assertSame('Setback short.', $returned['returned']);
    }
}
