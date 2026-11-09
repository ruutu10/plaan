<?php

namespace Tests\Feature;

use App\Actions\SaveTechnicalPlan;
use App\Enums\PerformanceStaffRole;
use App\Enums\TechnicalPlanStatus;
use App\Http\Resources\PerformanceRecording as PerformanceRecordingResource;
use App\Models\Performance;
use App\Models\PerformanceRecording;
use App\Models\TechnicalPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Downloading a night's video through this app, with no Jellyfin account.
 */
class PerformanceRecordingDownloadTest extends TestCase
{
    use RefreshDatabase;

    private const ITEM_ID = 'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.jellyfin.url', 'https://jellyfin.test');
        config()->set('services.jellyfin.api_key', 'test-key');
        config()->set('services.jellyfin.max_concurrent_downloads', 1);
    }

    public function test_somebody_on_stage_from_outside_the_team_can_download(): void
    {
        $this->fakeLibrary();
        [$performance] = $this->recorded();
        $player = User::factory()->create(['email' => 'guest@gmail.com']);
        $performance->staff()->attach($player, ['role' => PerformanceStaffRole::Performer->value]);

        $response = $this->actingAs($player)->get($this->url($performance));

        $response->assertOk()->assertStreamed();
        $this->assertSame('video-bytes', $response->streamedContent());
        $response->assertHeader('Content-Type', 'video/mp4');
        $response->assertHeader('Content-Length', '11');
        $response->assertHeader('Accept-Ranges', 'bytes');
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('.mkv', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_the_author_of_an_approved_plan_can_download(): void
    {
        $this->fakeLibrary();
        [$performance] = $this->recorded();
        $author = User::factory()->create();
        TechnicalPlan::factory()->approved()->for($performance)->for($author, 'user')->create();

        $response = $this->actingAs($author)->get($this->url($performance));

        $response->assertOk();
        $response->streamedContent();
    }

    /**
     * Anybody with a login can file a plan against any night, so filing one
     * must not open the video.
     *
     * @return array<string, array{TechnicalPlanStatus}>
     */
    public static function unapprovedStatuses(): array
    {
        return [
            'draft' => [TechnicalPlanStatus::Draft],
            'submitted' => [TechnicalPlanStatus::Submitted],
            'archived without approval' => [TechnicalPlanStatus::Archived],
        ];
    }

    #[DataProvider('unapprovedStatuses')]
    public function test_the_author_of_an_unapproved_plan_is_refused(TechnicalPlanStatus $status): void
    {
        $this->fakeLibrary();
        [$performance] = $this->recorded();
        $author = User::factory()->create();
        TechnicalPlan::factory()->for($performance)->for($author, 'user')->create(['status' => $status]);

        $this->actingAs($author)->get($this->url($performance))->assertForbidden();

        Http::assertNothingSent();
    }

    /**
     * Recordings arrive after the show, by when the daily job has archived the
     * plan.
     */
    public function test_approval_survives_archiving(): void
    {
        $this->fakeLibrary();
        [$performance] = $this->recorded();
        $author = User::factory()->create();
        $plan = TechnicalPlan::factory()->approved()->for($performance)->for($author, 'user')->create();

        $plan->update(['status' => TechnicalPlanStatus::Archived]);

        $response = $this->actingAs($author)->get($this->url($performance));

        $response->assertOk();
        $response->streamedContent();
    }

    public function test_a_technician_approving_the_plan_grants_access(): void
    {
        $this->fakeLibrary();
        [$performance] = $this->recorded();
        $author = User::factory()->create();
        $plan = TechnicalPlan::factory()->submitted()->for($performance)->for($author, 'user')->create();

        $this->actingAs($this->technician())
            ->patch(route('technical-plans.update-status', $plan), ['status' => TechnicalPlanStatus::Received->value]);

        $this->assertNotNull($plan->refresh()->approved_at);
        $this->assertTrue($author->can('downloadRecording', $performance));
    }

    public function test_withdrawing_an_approval_withdraws_access(): void
    {
        $this->fakeLibrary();
        [$performance] = $this->recorded();
        $author = User::factory()->create();
        $plan = TechnicalPlan::factory()->approved()->for($performance)->for($author, 'user')->create();

        $plan->update(['status' => TechnicalPlanStatus::Submitted]);

        $this->actingAs($author)->get($this->url($performance))->assertForbidden();
    }

    public function test_an_approved_plan_moved_from_the_wizard_loses_its_approval(): void
    {
        [$target] = $this->recorded();
        $author = User::factory()->create();
        $plan = TechnicalPlan::factory()->approved()->for($author, 'user')->create();

        app(SaveTechnicalPlan::class)->handle($plan, $this->wizardInput($plan, $target), $author, false);

        $this->assertSame($target->id, $plan->refresh()->performance_id);
        $this->assertNull($plan->approved_at);
        $this->assertFalse($author->can('downloadRecording', $target));
    }

    public function test_saving_an_approved_plan_on_its_own_night_keeps_the_approval(): void
    {
        $author = User::factory()->create();
        $plan = TechnicalPlan::factory()->approved()->for($author, 'user')->create();

        app(SaveTechnicalPlan::class)->handle($plan, $this->wizardInput($plan, $plan->performance), $author, false);

        $this->assertNotNull($plan->refresh()->approved_at);
    }

    public function test_a_technician_moving_an_approved_plan_keeps_the_approval(): void
    {
        $target = Performance::factory()->create();
        $plan = TechnicalPlan::factory()->approved()->create();

        $plan->update(['performance_id' => $target->id]);

        $this->assertNotNull($plan->refresh()->approved_at);
    }

    public function test_the_backfill_reads_approval_out_of_the_audit_trail(): void
    {
        $approved = $this->archivedPlanWithHistory(['status_changed' => ['from' => 'submitted', 'to' => 'received']]);
        $neverApproved = $this->archivedPlanWithHistory([]);
        $withdrawn = $this->archivedPlanWithHistory([
            'status_changed' => ['from' => 'received', 'to' => 'submitted'],
        ]);
        $resubmitted = $this->archivedPlanWithHistory([
            'status_changed' => ['from' => 'submitted', 'to' => 'received'],
            'submitted' => [],
        ]);
        $received = TechnicalPlan::factory()->approved()->create();
        DB::table('technical_plans')->where('id', $received->id)->update(['approved_at' => null]);

        (require database_path('migrations/2026_11_09_173035_backfill_approved_at_on_technical_plans.php'))->up();

        $this->assertNotNull($approved->refresh()->approved_at);
        $this->assertNull($neverApproved->refresh()->approved_at);
        $this->assertNull($withdrawn->refresh()->approved_at);
        $this->assertNull($resubmitted->refresh()->approved_at);
        $this->assertNotNull($received->refresh()->approved_at);
    }

    public function test_somebody_unconnected_to_the_night_is_refused(): void
    {
        $this->fakeLibrary();
        [$performance] = $this->recorded();

        $this->actingAs(User::factory()->create())->get($this->url($performance))->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_the_crew_on_the_staff_list_gain_nothing_from_being_on_it(): void
    {
        $this->fakeLibrary();
        [$performance] = $this->recorded();
        $bar = User::factory()->create();
        $performance->staff()->attach($bar, ['role' => PerformanceStaffRole::Bar->value]);

        $this->actingAs($bar)->get($this->url($performance))->assertForbidden();
    }

    public function test_a_guest_is_sent_to_log_in(): void
    {
        [$performance] = $this->recorded();

        $this->get($this->url($performance))->assertRedirect(route('login'));
    }

    public function test_a_night_with_no_recording_is_not_found(): void
    {
        $this->actingAs($this->technician())
            ->get($this->url(Performance::factory()->create()))
            ->assertNotFound();
    }

    public function test_no_library_configured_refuses(): void
    {
        [$performance] = $this->recorded();
        config()->set('services.jellyfin.url', '');

        $this->actingAs($this->technician())->get($this->url($performance))->assertForbidden();
    }

    public function test_a_range_is_passed_through_for_resuming(): void
    {
        $this->fakeLibrary(status: 206, headers: ['Content-Range' => 'bytes 5-15/100']);
        [$performance] = $this->recorded();

        $response = $this->actingAs($this->technician())
            ->withHeaders(['Range' => 'bytes=5-15', 'If-Range' => 'Fri, 03 Apr 2026 23:36:24 GMT'])
            ->get($this->url($performance));

        $response->assertStatus(206)->assertHeader('Content-Range', 'bytes 5-15/100');
        $response->streamedContent();

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/Download')
            && $request->header('Range') === ['bytes=5-15']
            && $request->header('If-Range') === ['Fri, 03 Apr 2026 23:36:24 GMT']
            && $request->header('Authorization') === ['MediaBrowser Token="test-key"']);
    }

    public function test_a_malformed_range_is_not_forwarded(): void
    {
        $this->fakeLibrary();
        [$performance] = $this->recorded();

        $this->actingAs($this->technician())
            ->withHeaders(['Range' => 'bytes=0-1,5-9'])
            ->get($this->url($performance))
            ->streamedContent();

        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/Download')
            && $request->hasHeader('Range'));
    }

    public function test_an_unsatisfiable_range_is_answered_as_such_and_frees_the_slot(): void
    {
        $this->fakeLibrary(status: 416, headers: ['Content-Range' => 'bytes */100']);
        [$performance] = $this->recorded();

        $this->actingAs($this->technician())
            ->withHeaders(['Range' => 'bytes=500-'])
            ->get($this->url($performance))
            ->assertStatus(416)
            ->assertHeader('Content-Range', 'bytes */100');

        $this->assertSlotIsFree();
    }

    public function test_the_download_is_audited_once_and_not_on_resume(): void
    {
        $this->fakeLibrary(status: 206);
        [$performance, $recording] = $this->recorded();
        $crew = $this->technician();

        $this->actingAs($crew)->get($this->url($performance))->streamedContent();
        $this->actingAs($crew)->withHeaders(['Range' => 'bytes=4096-'])->get($this->url($performance))->streamedContent();

        $activity = Activity::query()->forSubject($recording)->forEvent('recording_downloaded')->sole();
        $this->assertTrue($crew->is($activity->causer));
    }

    public function test_the_slot_is_given_back_when_the_stream_ends(): void
    {
        $this->fakeLibrary();
        [$performance] = $this->recorded();

        $this->actingAs($this->technician())->get($this->url($performance))->streamedContent();

        $this->assertSlotIsFree();
    }

    public function test_when_every_slot_is_busy_the_user_is_asked_to_come_back(): void
    {
        $this->fakeLibrary();
        [$performance] = $this->recorded();
        $held = Cache::lock('recording-download-slot-0', 60);
        $held->get();

        $this->actingAs($this->technician())
            ->get($this->url($performance))
            ->assertStatus(503)
            ->assertHeader('Retry-After', '120')
            ->assertSee('liiga palju korraga');

        Http::assertNothingSent();
        $held->release();
    }

    public function test_a_library_that_is_down_gets_a_readable_page_and_frees_the_slot(): void
    {
        Http::fake(fn () => throw new ConnectionException('refused'));
        [$performance] = $this->recorded();

        $this->actingAs($this->technician())
            ->get($this->url($performance))
            ->assertStatus(502)
            ->assertSee('Proovi hiljem uuesti');

        $this->assertSlotIsFree();
    }

    public function test_a_library_error_is_not_passed_on_as_a_file(): void
    {
        $this->fakeLibrary(status: 500);
        [$performance] = $this->recorded();

        $this->actingAs($this->technician())->get($this->url($performance))->assertStatus(502);

        $this->assertSlotIsFree();
    }

    public function test_a_link_to_something_other_than_an_episode_is_refused(): void
    {
        $this->fakeLibrary(type: 'Series');
        [$performance] = $this->recorded();

        $this->actingAs($this->technician())->get($this->url($performance))->assertStatus(502);

        Http::assertNotSent(fn (Request $request): bool => str_ends_with($request->url(), '/Download'));
    }

    public function test_a_head_request_takes_no_slot(): void
    {
        [$performance] = $this->recorded();

        $this->actingAs($this->technician())->call('HEAD', $this->url($performance))->assertOk();

        $this->assertSlotIsFree();
    }

    public function test_the_resource_says_who_gets_the_button(): void
    {
        [$performance, $recording] = $this->recorded();
        $resource = new PerformanceRecordingResource($recording);

        $this->actingAs($this->technician());
        $this->assertTrue($resource->toArray(request())['canDownload']);

        $this->actingAs(User::factory()->create());
        $this->assertFalse($resource->toArray(request())['canDownload']);
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function fakeLibrary(int $status = 200, string $type = 'Episode', array $headers = []): void
    {
        Http::fake([
            'jellyfin.test/Items?*' => Http::response([
                'Items' => [['Id' => self::ITEM_ID, 'Type' => $type]],
                'TotalRecordCount' => 1,
            ]),
            'jellyfin.test/Items/*/Download' => Http::response('video-bytes', $status, $headers + [
                'Content-Type' => 'video/mp4',
                'Content-Length' => '11',
                'Content-Disposition' => 'attachment; filename="Something S01E04.mkv"',
            ]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function wizardInput(TechnicalPlan $plan, Performance $performance): array
    {
        return [
            'meta' => ['performanceId' => $performance->id],
            'sound' => $plan->sound,
            'scenes' => $plan->scenes,
            'equipment' => $plan->equipment,
            'extra' => $plan->extra,
        ];
    }

    /**
     * An archived plan from before `approved_at`, with the given audit entries
     * written oldest first.
     *
     * @param  array<string, array<string, string>>  $entries  event => properties
     */
    private function archivedPlanWithHistory(array $entries): TechnicalPlan
    {
        $plan = TechnicalPlan::factory()->archived()->create();

        foreach ($entries as $event => $properties) {
            activity()->performedOn($plan)->event($event)->withProperties($properties)->log($event);
        }

        return $plan;
    }

    /**
     * @return array{0: Performance, 1: PerformanceRecording}
     */
    private function recorded(): array
    {
        $performance = Performance::factory()->create();

        return [$performance, PerformanceRecording::factory()->for($performance)->forItem(self::ITEM_ID)->create()];
    }

    private function url(Performance $performance): string
    {
        return route('performances.recording.download', $performance);
    }

    private function assertSlotIsFree(): void
    {
        $lock = Cache::lock('recording-download-slot-0', 60);

        $this->assertTrue($lock->get(), 'The download slot should have been released.');

        $lock->release();
    }
}
