<?php

namespace Tests\Feature;

use App\Enums\TechnicalPlanStatus;
use App\Http\Resources\TechnicalPlan as TechnicalPlanResource;
use App\Models\PendingUpload;
use App\Models\Performance;
use App\Models\TechnicalPlan;
use App\Models\User;
use App\Services\TechnicalPlanReviewer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class TechnicalPlanTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // The whole plan flow sits behind authentication; sign a user in so
        // the guarded endpoints are reachable.
        $this->user = User::factory()->create(['email' => 'mart@naide.ee']);
        $this->actingAs($this->user);
    }

    /**
     * A minimal, valid plan payload as sent by the wizard.
     *
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'token' => null,
            'submit' => false,
            'meta' => [
                'performanceId' => null,
                'performer' => 'Märold',
                'showName' => 'Festival 2026',
                'showDate' => '2026-08-01',
                'duration' => 25,
                'description' => 'Improetendus kolmes osas.',
            ],
            'sound' => [
                'micsMode' => 'yes',
                'micsDetail' => '2 käsimikrofoni',
                'musicianMode' => 'no',
                'musicianDetail' => '',
            ],
            'scenes' => [
                ['id' => 'stseen-1', 'name' => 'Lavale tulek', 'light' => 'Soe üldvalgus', 'soundUrl' => '', 'soundFile' => null, 'sound' => '', 'notes' => ''],
            ],
            'equipment' => [
                'items' => [
                    ['id' => 'e1', 'name' => 'Suitsumasin', 'use' => 'Lavaletuleku ajal'],
                ],
                'smoke' => 'yes',
                'suggestions' => 'yes',
                'suggestNote' => '',
            ],
            'extra' => [
                'notes' => 'Palun jälgida ajakava.',
                'files' => [],
            ],
        ], $overrides);
    }

    public function test_the_landing_page_renders_the_wizard(): void
    {
        $response = $this->get(route('technical-plan.index'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('TechnicalPlan')
            ->where('initialPlan', null)
            ->has('config.deadlineHours')
            ->has('config.techEmail')
            ->has('config.allowedExtensions')
            ->has('config.maxFileSize'));
    }

    public function test_a_plan_can_be_stored_and_returns_a_token(): void
    {
        $performance = Performance::factory()->create();

        $response = $this->postJson(route('technical-plan.store'), $this->validPayload([
            'meta' => ['performanceId' => $performance->id],
        ]));

        $response->assertOk();
        $response->assertJsonStructure(['token', 'status', 'publicUrl', 'files']);
        $year = date('Y');
        $this->assertStringStartsWith('R10-'.$year.'-', $response->json('token'));
        $this->assertSame('draft', $response->json('status'));

        $this->assertDatabaseHas('technical_plans', [
            'performance_id' => $performance->id,
            'status' => 'draft',
        ]);

        $plan = TechnicalPlan::first();
        $this->assertSame('mart@naide.ee', $plan->user->email);
        $this->assertSame($performance->id, $plan->performance_id);
        $this->assertSame('yes', $plan->sound['micsMode']);
        $this->assertCount(1, $plan->scenes);
        $this->assertSame('Suitsumasin', $plan->equipment['items'][0]['name']);
    }

    public function test_a_plan_can_be_stored_without_a_performance(): void
    {
        $this->postJson(route('technical-plan.store'), $this->validPayload([
            'meta' => ['performanceId' => null],
        ]))->assertOk();

        $this->assertSame(0, Performance::count());
        $this->assertNull(TechnicalPlan::first()->performance_id);
    }

    public function test_storing_links_the_selected_performance(): void
    {
        $performance = Performance::factory()->create();

        $this->postJson(route('technical-plan.store'), $this->validPayload([
            'meta' => ['performanceId' => $performance->id],
        ]))->assertOk();

        $this->assertSame(1, Performance::count());
        $this->assertSame($performance->id, TechnicalPlan::first()->performance_id);
    }

    public function test_storing_rejects_an_unknown_performance(): void
    {
        $response = $this->postJson(route('technical-plan.store'), $this->validPayload([
            'meta' => ['performanceId' => 999],
        ]));

        $response->assertUnprocessable();
        $this->assertArrayHasKey('meta.performanceId', $response->json('errors'));
        $this->assertSame(0, TechnicalPlan::count());
    }

    public function test_storing_links_the_plan_to_the_authenticated_user(): void
    {
        $this->postJson(route('technical-plan.store'), $this->validPayload())->assertOk();

        // The plan is tied to the signed-in user, not to any e-mail in the body.
        $this->assertSame($this->user->id, TechnicalPlan::first()->user_id);
        $this->assertSame(1, User::count());
    }

    public function test_storing_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $response = $this->postJson(route('technical-plan.store'), $this->validPayload());

        $response->assertUnauthorized();
        $this->assertSame(0, TechnicalPlan::count());
    }

    public function test_submitting_marks_the_plan_as_submitted(): void
    {
        $response = $this->postJson(route('technical-plan.store'), $this->validPayload(['submit' => true]));

        $response->assertOk();
        $this->assertSame('submitted', $response->json('status'));

        $plan = TechnicalPlan::first();
        $this->assertSame(TechnicalPlanStatus::Submitted, $plan->status);
        $this->assertNotNull($plan->submitted_at);
    }

    public function test_storing_with_an_existing_token_updates_the_plan(): void
    {
        $first = Performance::factory()->create();
        $second = Performance::factory()->create();

        $token = $this->postJson(route('technical-plan.store'), $this->validPayload([
            'meta' => ['performanceId' => $first->id],
        ]))->json('token');

        $this->postJson(route('technical-plan.store'), $this->validPayload([
            'token' => $token,
            'meta' => ['performanceId' => $second->id],
        ]))->assertOk();

        $this->assertSame(1, TechnicalPlan::count());
        $this->assertSame($second->id, TechnicalPlan::first()->performance_id);
    }

    public function test_a_saved_plan_can_be_fetched_by_token(): void
    {
        $plan = TechnicalPlan::factory()->create();

        $response = $this->getJson(route('technical-plan.show', $plan));

        $response->assertOk();
        $response->assertJson([
            'token' => $plan->token,
            'meta' => [
                'performanceId' => $plan->performance_id,
                'performer' => $plan->performance->team->name,
                'showName' => $plan->performance->show_name,
            ],
        ]);
    }

    public function test_a_plan_without_a_performance_can_be_fetched_by_token(): void
    {
        $plan = TechnicalPlan::factory()->create(['performance_id' => null]);

        $response = $this->getJson(route('technical-plan.show', $plan));

        $response->assertOk();
        $response->assertJson([
            'token' => $plan->token,
            'meta' => [
                'performanceId' => null,
                'performer' => '',
                'showName' => '',
                'showDate' => '',
            ],
        ]);
    }

    public function test_the_public_link_renders_the_wizard_prefilled(): void
    {
        $plan = TechnicalPlan::factory()->submitted()->create();

        $response = $this->get(route('technical-plan.public', $plan));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('TechnicalPlan')
            ->where('initialPlan.token', $plan->token)
            ->where('initialPlan.meta.performer', $plan->performance->team->name));
    }

    public function test_the_public_link_never_hands_the_wizard_null_text(): void
    {
        // Every wizard field is optional, so a stored plan can hold nulls where
        // the frontend's `Plan` shape promises text. The wizard trims those
        // fields as it renders, so a null would break the review step.
        $plan = TechnicalPlan::factory()->submitted()->create([
            'sound' => ['micsMode' => null, 'micsDetail' => null, 'musicianMode' => null, 'musicianDetail' => null],
            'scenes' => [['id' => null, 'name' => null, 'light' => null, 'soundUrl' => null, 'soundFile' => null, 'sound' => null, 'notes' => null]],
            'equipment' => [
                'items' => [['id' => null, 'name' => null, 'use' => null]],
                'smoke' => null,
                'suggestions' => null,
                'suggestNote' => null,
            ],
            'extra' => ['notes' => null],
        ]);

        $response = $this->get(route('technical-plan.public', $plan));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->where('initialPlan.sound.micsDetail', '')
            ->where('initialPlan.sound.musicianDetail', '')
            ->where('initialPlan.scenes.0.name', '')
            ->where('initialPlan.scenes.0.light', '')
            ->where('initialPlan.scenes.0.soundUrl', '')
            ->where('initialPlan.scenes.0.sound', '')
            ->where('initialPlan.scenes.0.notes', '')
            ->where('initialPlan.equipment.items.0.name', '')
            ->where('initialPlan.equipment.items.0.use', '')
            ->where('initialPlan.equipment.suggestNote', '')
            ->where('initialPlan.extra.notes', '')
            // The choice fields fall back to the wizard's own defaults, and the
            // ids stay set — they key the rendered rows.
            ->where('initialPlan.sound.micsMode', 'no')
            ->where('initialPlan.sound.musicianMode', 'no')
            ->where('initialPlan.equipment.smoke', 'yes')
            ->where('initialPlan.equipment.suggestions', 'yes')
            ->where('initialPlan.scenes.0.id', 'stseen-1')
            ->where('initialPlan.equipment.items.0.id', 'seade-1'));
    }

    public function test_the_performances_endpoint_returns_only_upcoming_performances(): void
    {
        $upcoming = Performance::factory()->create(['show_name' => 'Tulevane etendus']);
        Performance::factory()->past()->create(['show_name' => 'Möödunud etendus']);

        $response = $this->getJson(route('technical-plan.performances'));

        $response->assertOk();
        $response->assertJsonCount(1, 'results');
        $response->assertJsonFragment([
            'id' => $upcoming->id,
            'showName' => 'Tulevane etendus',
            'performer' => $upcoming->team->name,
        ]);
        $response->assertJsonMissing(['showName' => 'Möödunud etendus']);
    }

    public function test_performances_surface_the_users_prior_plans_for_the_same_show(): void
    {
        // Upcoming staging of "Kevadetendus".
        $upcoming = Performance::factory()->create([
            'show_name' => 'Kevadetendus',
            'show_date' => now()->addWeek()->toDateString(),
        ]);

        // A past staging of the same show, with the user's submitted plan.
        $past = Performance::factory()->past()->create(['show_name' => 'Kevadetendus']);
        $ownPlan = TechnicalPlan::factory()->for($this->user)->for($past)->submitted()->create();

        // Noise that must NOT be offered as a copy source:
        TechnicalPlan::factory()->for($past)->submitted()->create(); // another user's plan
        TechnicalPlan::factory()->for($this->user)->submitted()->create([ // different show
            'performance_id' => Performance::factory()->past()->create(['show_name' => 'Suveetendus']),
        ]);
        TechnicalPlan::factory()->for($this->user)->create([ // unsubmitted draft, same show
            'performance_id' => Performance::factory()->past()->create(['show_name' => 'Kevadetendus']),
        ]);

        $response = $this->getJson(route('technical-plan.performances'));

        $response->assertOk();
        $response->assertJsonCount(1, 'results');

        $priorPlans = $response->json('results.0.priorPlans');
        $this->assertCount(1, $priorPlans);
        $this->assertSame($ownPlan->token, $priorPlans[0]['token']);
        $this->assertSame($past->show_date->format('d.m.Y'), $priorPlans[0]['label']);
    }

    public function test_lookup_returns_the_authenticated_users_submitted_plans(): void
    {
        $performance = Performance::factory()->create(['show_name' => 'Esitatud plaan']);
        TechnicalPlan::factory()->for($this->user)->for($performance)->submitted()->create();
        TechnicalPlan::factory()->for($this->user)->create(); // draft — excluded
        TechnicalPlan::factory()->submitted()->create();       // another user — excluded

        $response = $this->postJson(route('technical-plan.lookup'));

        $plan = TechnicalPlan::where('performance_id', $performance->id)->first();

        $response->assertOk();
        $response->assertJsonCount(1, 'results');
        $response->assertJsonFragment(['token' => $plan->token]);

        // Each row is labelled by its show and staging, with the submission date.
        $row = $response->json('results.0');
        $this->assertSame('Esitatud plaan — '.$performance->team->name, $row['title']);
        $this->assertSame(
            $performance->show_date->format('d.m.Y').' · esitatud '.$plan->submitted_at->format('d.m.Y'),
            $row['sub'],
        );
    }

    public function test_a_plan_without_a_performance_is_still_listed_in_lookup(): void
    {
        TechnicalPlan::factory()->for($this->user)->submitted()->create(['performance_id' => null]);

        $response = $this->postJson(route('technical-plan.lookup'));

        $response->assertOk();
        $this->assertSame('Nimeta plaan', $response->json('results.0.title'));
    }

    public function test_ai_review_requires_configuration(): void
    {
        config()->set('services.anthropic.key', null);

        // The reviewer must never be reached when the AI is not configured.
        $this->mock(TechnicalPlanReviewer::class)->shouldNotReceive('review');

        $response = $this->postJson(route('technical-plan.ai'), $this->validPayload());

        $response->assertUnprocessable();
        $response->assertJsonStructure(['message']);
    }

    public function test_ai_review_sees_a_scenes_sound_file_before_the_plan_is_saved(): void
    {
        Storage::fake('local');
        config()->set('services.anthropic.key', 'test-key');

        $handle = $this->soundHandle();

        // The reviewer is handed an unsaved plan, so the scene's handle is
        // still the staged one — it must survive into the reviewed payload.
        $this->mock(TechnicalPlanReviewer::class)
            ->shouldReceive('review')
            ->once()
            ->andReturnUsing(function (TechnicalPlan $plan) use ($handle): string {
                $scene = (new TechnicalPlanResource($plan))->toArray(request())['scenes'][0];

                $this->assertSame($handle, $scene['soundFile']['id']);
                $this->assertSame('muusika.mp3', $scene['soundFile']['name']);

                return 'Tagasiside';
            });

        $this->postJson(route('technical-plan.ai'), $this->validPayload([
            'scenes' => [['soundFile' => ['id' => $handle, 'name' => 'muusika.mp3', 'size' => 120]]],
        ]))->assertOk();
    }

    public function test_the_frontend_resource_paints_a_full_picture_of_a_saved_plan(): void
    {
        $plan = TechnicalPlan::factory()->submitted()->create();

        $data = (new TechnicalPlanResource($plan))->toArray(request());

        // Identity and status come straight off the model.
        $this->assertSame($plan->token, $data['token']);
        $this->assertSame($plan->status->value, $data['status']);

        // Meta is drawn from the performance and its team.
        $this->assertSame($plan->performance->team->name, $data['meta']['performer']);
        $this->assertSame($plan->performance->show_name, $data['meta']['showName']);

        // The full plan content is present…
        $this->assertArrayHasKey('sound', $data);
        $this->assertArrayHasKey('scenes', $data);
        $this->assertArrayHasKey('equipment', $data);
        $this->assertArrayHasKey('extra', $data);
        // …including the uploaded file handles the wizard rehydrates from.
        $this->assertArrayHasKey('files', $data['extra']);
    }

    public function test_an_attachment_can_be_uploaded_and_returns_a_handle(): void
    {
        Storage::fake('local');

        $response = $this->postJson(route('attachments.store'), [
            'file' => UploadedFile::fake()->create('plaan.pdf', 120, 'application/pdf'),
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['id', 'name', 'size', 'url', 'downloadUrl']);

        $media = Media::first();
        $this->assertSame(1, PendingUpload::count());
        $this->assertSame('local', $media->disk);
        $this->assertSame(route('attachments.show', $media->uuid), $response->json('url'));
        $this->assertSame(
            route('attachments.show', ['uuid' => $media->uuid, 'download' => 1]),
            $response->json('downloadUrl'),
        );
        Storage::disk('local')->assertExists($media->getPathRelativeToRoot());
    }

    public function test_an_attachment_can_be_streamed_by_uuid_and_is_logged(): void
    {
        Storage::fake('local');
        Log::spy();

        $handle = $this->uploadHandle();

        $response = $this->get(route('attachments.show', $handle));

        $response->assertOk();
        $response->assertHeader('content-type', Media::first()->mime_type);
        $response->assertHeader('x-content-type-options', 'nosniff');
        $this->assertStringStartsWith('inline', (string) $response->headers->get('content-disposition'));

        Log::shouldHaveReceived('info')
            ->once()
            ->withArgs(fn (string $message, array $context): bool => $message === 'Attachment downloaded'
                && $context['uuid'] === $handle
                && $context['disposition'] === 'inline');
    }

    public function test_an_attachment_can_be_forced_to_download_under_its_original_name(): void
    {
        Storage::fake('local');

        $handle = $this->uploadHandle();
        $media = Media::first();

        $response = $this->get(route('attachments.show', ['uuid' => $handle, 'download' => 1]));

        $response->assertOk();
        $response->assertHeader('content-type', $media->mime_type);
        $response->assertHeader('x-content-type-options', 'nosniff');

        $disposition = (string) $response->headers->get('content-disposition');
        $this->assertStringStartsWith('attachment', $disposition);
        $this->assertStringContainsString($media->file_name, $disposition);
    }

    public function test_streaming_an_unknown_uuid_returns_404(): void
    {
        $this->get(route('attachments.show', 'does-not-exist'))->assertNotFound();
    }

    public function test_uploading_requires_authentication(): void
    {
        Storage::fake('local');

        $this->app['auth']->forgetGuards();

        $response = $this->postJson(route('attachments.store'), [
            'file' => UploadedFile::fake()->create('plaan.pdf', 120, 'application/pdf'),
        ]);

        $response->assertUnauthorized();
        $this->assertSame(0, PendingUpload::count());
        $this->assertSame(0, Media::query()->count());
    }

    public function test_uploading_a_sound_file_requires_authentication(): void
    {
        Storage::fake('local');

        $this->app['auth']->forgetGuards();

        $response = $this->postJson(route('attachments.store'), [
            'file' => UploadedFile::fake()->create('muusika.mp3', 120, 'audio/mpeg'),
            'collection' => TechnicalPlan::SOUND_COLLECTION,
        ]);

        $response->assertUnauthorized();
        $this->assertSame(0, Media::query()->count());
    }

    public function test_discarding_a_staged_upload_requires_authentication(): void
    {
        Storage::fake('local');

        $handle = $this->soundHandle();

        $this->app['auth']->forgetGuards();

        $this->deleteJson(route('attachments.destroy', $handle))->assertUnauthorized();

        // The file is untouched — only its owner's session can discard it.
        $this->assertSame(1, Media::query()->count());
    }

    public function test_a_stored_attachment_stays_readable_without_an_account(): void
    {
        Storage::fake('local');

        // A plan shared by its public link must play its sound for a visitor
        // who has no account, so streaming is deliberately left open.
        $handle = $this->soundHandle();

        $this->app['auth']->forgetGuards();

        $this->get(route('attachments.show', $handle))->assertOk();
    }

    public function test_uploading_a_disallowed_extension_is_rejected(): void
    {
        Storage::fake('local');

        $response = $this->postJson(route('attachments.store'), [
            'file' => UploadedFile::fake()->create('script.exe', 10),
        ]);

        $response->assertUnprocessable();
        $this->assertSame(0, PendingUpload::count());
        $this->assertSame(0, Media::query()->count());
    }

    public function test_uploading_rejects_a_file_whose_content_mime_is_not_allowed(): void
    {
        Storage::fake('local');

        // Allowed extension (.pdf) but the real content is a PHP script.
        $response = $this->postJson(route('attachments.store'), [
            'file' => UploadedFile::fake()->create('invoice.pdf', 10, 'text/x-php'),
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('file');
        $this->assertSame(0, Media::query()->count());
    }

    public function test_uploading_allows_extensions_without_a_known_mime_type(): void
    {
        Storage::fake('local');

        // .qlc (QLC+ lighting file) has no Symfony MIME mapping; the extension
        // allowlist is its guard, so the content check must not reject it.
        $response = $this->postJson(route('attachments.store'), [
            'file' => UploadedFile::fake()->create('scene.qlc', 10),
        ]);

        $response->assertOk();
        $this->assertSame(1, Media::query()->count());
    }

    public function test_a_staged_upload_can_be_discarded(): void
    {
        Storage::fake('local');

        $handle = $this->uploadHandle();

        $this->deleteJson(route('attachments.destroy', $handle))->assertOk();

        $this->assertSame(0, PendingUpload::count());
        $this->assertSame(0, Media::query()->count());
    }

    public function test_submitting_moves_staged_uploads_onto_the_plan(): void
    {
        Storage::fake('local');

        $handle = $this->uploadHandle();

        $response = $this->postJson(route('technical-plan.store'), $this->validPayload([
            'submit' => true,
            'extra' => ['files' => [['id' => $handle, 'name' => 'plaan.pdf', 'size' => 120]]],
        ]));

        $response->assertOk();
        $response->assertJsonCount(1, 'files');

        $plan = TechnicalPlan::first();
        $this->assertCount(1, $plan->getMedia($plan->attachmentsCollection()));
        $this->assertSame(0, PendingUpload::count());
        $this->assertSame(1, Media::query()->count());
    }

    public function test_file_handles_carry_the_streaming_endpoints_wherever_they_are_serialised(): void
    {
        Storage::fake('local');

        $response = $this->postJson(route('technical-plan.store'), $this->validPayload([
            'extra' => ['files' => [['id' => $this->uploadHandle(), 'name' => 'plaan.pdf', 'size' => 120]]],
        ]));

        $handle = $response->json('files.0');

        $this->assertSame('plaan.pdf', $handle['name']);
        $this->assertSame(route('attachments.show', $handle['id']), $handle['url']);
        $this->assertSame(
            route('attachments.show', ['uuid' => $handle['id'], 'download' => 1]),
            $handle['downloadUrl'],
        );

        // Re-opening the plan hands back exactly the same handle.
        $fetched = $this->getJson(route('technical-plan.show', $response->json('token')))->json('extra.files.0');
        $this->assertSame($handle, $fetched);
    }

    public function test_resubmitting_without_a_file_detaches_it(): void
    {
        Storage::fake('local');

        $token = $this->postJson(route('technical-plan.store'), $this->validPayload([
            'extra' => ['files' => [['id' => $this->uploadHandle(), 'name' => 'plaan.pdf', 'size' => 120]]],
        ]))->json('token');

        $this->assertSame(1, Media::query()->count());

        $this->postJson(route('technical-plan.store'), $this->validPayload([
            'token' => $token,
            'extra' => ['files' => []],
        ]))->assertOk();

        $this->assertCount(0, TechnicalPlan::first()->getMedia((new TechnicalPlan)->attachmentsCollection()));
        $this->assertSame(0, Media::query()->count());
    }

    public function test_unknown_file_handles_are_ignored_on_submit(): void
    {
        Storage::fake('local');

        $this->postJson(route('technical-plan.store'), $this->validPayload([
            'extra' => ['files' => [['id' => 'does-not-exist', 'name' => 'ghost.pdf', 'size' => 10]]],
        ]))->assertOk();

        $this->assertCount(0, TechnicalPlan::first()->getMedia((new TechnicalPlan)->attachmentsCollection()));
    }

    public function test_copying_a_plan_duplicates_its_attachments_on_disk(): void
    {
        Storage::fake('local');

        // A submitted plan that owns one attachment.
        $token = $this->postJson(route('technical-plan.store'), $this->validPayload([
            'submit' => true,
            'extra' => ['files' => [['id' => $this->uploadHandle(), 'name' => 'plaan.pdf', 'size' => 120]]],
        ]))->json('token');

        $source = TechnicalPlan::where('token', $token)->first();
        $sourceMedia = $source->getMedia($source->attachmentsCollection())->first();

        $response = $this->postJson(route('technical-plan.copy', $source));

        $response->assertOk();
        $files = $response->json('extra.files');
        $this->assertCount(1, $files);

        // A fresh handle, staged on a PendingUpload, pointing at a distinct file.
        $this->assertNotSame((string) $sourceMedia->uuid, $files[0]['id']);
        $this->assertSame('plaan.pdf', $files[0]['name']);

        $copyMedia = Media::where('uuid', $files[0]['id'])->first();
        $this->assertInstanceOf(PendingUpload::class, $copyMedia->model);

        // Two independent files now exist on disk; the source keeps its own.
        $this->assertSame(2, Media::count());
        $this->assertNotSame($sourceMedia->getPathRelativeToRoot(), $copyMedia->getPathRelativeToRoot());
        Storage::disk('local')->assertExists($sourceMedia->getPathRelativeToRoot());
        Storage::disk('local')->assertExists($copyMedia->getPathRelativeToRoot());
    }

    public function test_a_copied_plans_attachments_move_onto_the_new_plan_on_submit(): void
    {
        Storage::fake('local');

        $sourceToken = $this->postJson(route('technical-plan.store'), $this->validPayload([
            'submit' => true,
            'extra' => ['files' => [['id' => $this->uploadHandle(), 'name' => 'plaan.pdf', 'size' => 120]]],
        ]))->json('token');

        $source = TechnicalPlan::where('token', $sourceToken)->first();

        // Duplicate the attachments to staging, as the wizard does when copying.
        $files = $this->postJson(route('technical-plan.copy', $source))->json('extra.files');

        // Submit a brand-new plan carrying the copied handles.
        $newToken = $this->postJson(route('technical-plan.store'), $this->validPayload([
            'submit' => true,
            'extra' => ['files' => $files],
        ]))->json('token');

        $this->assertNotSame($sourceToken, $newToken);

        $new = TechnicalPlan::where('token', $newToken)->first();
        $this->assertCount(1, $new->getMedia($new->attachmentsCollection()));
        // The source is untouched and still owns its own file.
        $this->assertCount(1, $source->refresh()->getMedia($source->attachmentsCollection()));
        // Two plans, two files, and no staging holders left behind.
        $this->assertSame(2, Media::count());
        $this->assertSame(0, PendingUpload::count());
    }

    public function test_copying_another_users_plan_is_forbidden(): void
    {
        $other = TechnicalPlan::factory()->submitted()->create();

        $this->postJson(route('technical-plan.copy', $other))->assertForbidden();
    }

    public function test_a_scene_sound_file_can_be_uploaded_and_moves_onto_the_plan(): void
    {
        Storage::fake('local');

        $handle = $this->soundHandle();

        $response = $this->postJson(route('technical-plan.store'), $this->validPayload([
            'scenes' => [
                ['soundFile' => ['id' => $handle, 'name' => 'muusika.mp3', 'size' => 120]],
            ],
        ]));

        $response->assertOk();

        $plan = TechnicalPlan::first();
        $sound = $plan->getMedia(TechnicalPlan::SOUND_COLLECTION);

        // The file lives in the plan's own `sound` collection, apart from the
        // plan's general attachments.
        $this->assertCount(1, $sound);
        $this->assertCount(0, $plan->attachments());
        $this->assertSame('muusika.mp3', $sound->first()->file_name);
        $this->assertSame(0, PendingUpload::count());

        // Moving the file re-keys it, so the scene now carries the new handle…
        $stored = $plan->scenes[0]['soundFile'];
        $this->assertSame((string) $sound->first()->uuid, $stored['id']);
        $this->assertSame('muusika.mp3', $stored['name']);

        // …and the client is handed that same handle back, with its links.
        $returned = $response->json('scenes.0.soundFile');
        $this->assertSame($stored['id'], $returned['id']);
        $this->assertSame(route('attachments.show', $stored['id']), $returned['url']);
        $this->assertSame(
            route('attachments.show', ['uuid' => $stored['id'], 'download' => 1]),
            $returned['downloadUrl'],
        );
    }

    public function test_a_scene_keeps_its_sound_file_when_the_plan_is_saved_again(): void
    {
        Storage::fake('local');

        $token = $this->postJson(route('technical-plan.store'), $this->validPayload([
            'scenes' => [['soundFile' => ['id' => $this->soundHandle()]]],
        ]))->json('token');

        $handle = TechnicalPlan::first()->scenes[0]['soundFile']['id'];

        // The wizard re-submits the handle it got back; the file must survive.
        $this->postJson(route('technical-plan.store'), $this->validPayload([
            'token' => $token,
            'submit' => true,
            'scenes' => [['soundFile' => ['id' => $handle]]],
        ]))->assertOk();

        $plan = TechnicalPlan::first();
        $this->assertCount(1, $plan->getMedia(TechnicalPlan::SOUND_COLLECTION));
        $this->assertSame($handle, $plan->scenes[0]['soundFile']['id']);
        $this->assertSame(1, Media::count());
    }

    public function test_dropping_a_scenes_sound_file_deletes_it(): void
    {
        Storage::fake('local');

        $token = $this->postJson(route('technical-plan.store'), $this->validPayload([
            'scenes' => [['soundFile' => ['id' => $this->soundHandle()]]],
        ]))->json('token');

        $this->postJson(route('technical-plan.store'), $this->validPayload([
            'token' => $token,
            'scenes' => [['soundFile' => null]],
        ]))->assertOk();

        $plan = TechnicalPlan::first();
        $this->assertCount(0, $plan->getMedia(TechnicalPlan::SOUND_COLLECTION));
        $this->assertNull($plan->scenes[0]['soundFile']);
        $this->assertSame(0, Media::count());
    }

    public function test_an_unknown_scene_sound_handle_leaves_the_scene_without_a_file(): void
    {
        Storage::fake('local');

        $this->postJson(route('technical-plan.store'), $this->validPayload([
            'scenes' => [['soundFile' => ['id' => 'does-not-exist', 'name' => 'ghost.mp3']]],
        ]))->assertOk();

        $plan = TechnicalPlan::first();
        $this->assertCount(0, $plan->getMedia(TechnicalPlan::SOUND_COLLECTION));
        $this->assertNull($plan->scenes[0]['soundFile']);
    }

    public function test_a_scene_cannot_have_both_a_sound_link_and_a_sound_file(): void
    {
        Storage::fake('local');

        $response = $this->postJson(route('technical-plan.store'), $this->validPayload([
            'scenes' => [[
                'soundUrl' => 'https://example.com/muusika.mp3',
                'soundFile' => ['id' => $this->soundHandle()],
            ]],
        ]));

        $response->assertUnprocessable();
        $this->assertArrayHasKey('scenes.0.soundFile', $response->json('errors'));
        $this->assertSame(0, TechnicalPlan::count());
    }

    public function test_a_sound_upload_only_accepts_sound_file_types(): void
    {
        Storage::fake('local');

        // A PDF is fine as a general attachment…
        $this->postJson(route('attachments.store'), [
            'file' => UploadedFile::fake()->create('plaan.pdf', 120, 'application/pdf'),
        ])->assertOk();

        // …but not as a scene's sound file.
        $response = $this->postJson(route('attachments.store'), [
            'file' => UploadedFile::fake()->create('plaan.pdf', 120, 'application/pdf'),
            'collection' => TechnicalPlan::SOUND_COLLECTION,
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('file');
        $this->assertSame(1, Media::count());
    }

    public function test_a_non_audio_upload_cannot_be_passed_off_as_a_scene_sound_file(): void
    {
        Storage::fake('local');

        // Uploaded as a plain attachment (no `collection`), so the upload
        // endpoint's audio allowlist never applied to it.
        $handle = $this->uploadHandle();

        $this->postJson(route('technical-plan.store'), $this->validPayload([
            'scenes' => [['soundFile' => ['id' => $handle, 'name' => 'plaan.pdf', 'size' => 120]]],
        ]))->assertOk();

        $plan = TechnicalPlan::first();

        // The handle is refused on the stored file's own type: nothing lands in
        // the sound collection and the scene is left without a file.
        $this->assertCount(0, $plan->getMedia(TechnicalPlan::SOUND_COLLECTION));
        $this->assertNull($plan->scenes[0]['soundFile']);
    }

    public function test_a_sound_upload_rejects_an_unknown_collection(): void
    {
        Storage::fake('local');

        $response = $this->postJson(route('attachments.store'), [
            'file' => UploadedFile::fake()->create('muusika.mp3', 120, 'audio/mpeg'),
            'collection' => 'technical-plan',
        ]);

        $response->assertUnprocessable();
        $this->assertSame(0, Media::count());
    }

    public function test_copying_a_plan_duplicates_its_scene_sound_files(): void
    {
        Storage::fake('local');

        $token = $this->postJson(route('technical-plan.store'), $this->validPayload([
            'submit' => true,
            'scenes' => [['soundFile' => ['id' => $this->soundHandle()]]],
        ]))->json('token');

        $source = TechnicalPlan::where('token', $token)->first();
        $sourceMedia = $source->getMedia(TechnicalPlan::SOUND_COLLECTION)->first();

        $copied = $this->postJson(route('technical-plan.copy', $source))->json('scenes.0.soundFile');

        // A fresh handle on a staged copy — the source keeps its own file.
        $this->assertNotSame((string) $sourceMedia->uuid, $copied['id']);
        $this->assertSame('muusika.mp3', $copied['name']);
        $this->assertInstanceOf(PendingUpload::class, Media::where('uuid', $copied['id'])->first()->model);

        // Submitting the copy moves the duplicate onto the new plan.
        $newToken = $this->postJson(route('technical-plan.store'), $this->validPayload([
            'submit' => true,
            'scenes' => [['soundFile' => $copied]],
        ]))->json('token');

        $new = TechnicalPlan::where('token', $newToken)->first();
        $this->assertCount(1, $new->getMedia(TechnicalPlan::SOUND_COLLECTION));
        $this->assertCount(1, $source->refresh()->getMedia(TechnicalPlan::SOUND_COLLECTION));
        $this->assertSame(2, Media::count());
        $this->assertSame(0, PendingUpload::count());
    }

    public function test_the_wizard_config_lists_the_sound_extensions(): void
    {
        $response = $this->get(route('technical-plan.index'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('TechnicalPlan')
            ->where('config.soundExtensions', ['mp3', 'wav', 'ogg'])
            ->etc());

        // Sound files are a subset of what may be uploaded in general.
        $config = $response->viewData('page')['props']['config'];
        $this->assertEmpty(array_diff($config['soundExtensions'], $config['allowedExtensions']));
    }

    /**
     * Upload a fake file and return the handle the wizard would send on submit.
     */
    private function uploadHandle(string $name = 'plaan.pdf'): string
    {
        return $this->postJson(route('attachments.store'), [
            'file' => UploadedFile::fake()->create($name, 120, 'application/pdf'),
        ])->json('id');
    }

    /**
     * Upload a fake sound file and return the handle for a scene's sound file.
     */
    private function soundHandle(string $name = 'muusika.mp3'): string
    {
        return $this->postJson(route('attachments.store'), [
            'file' => UploadedFile::fake()->create($name, 120, 'audio/mpeg'),
            'collection' => TechnicalPlan::SOUND_COLLECTION,
        ])->json('id');
    }
}
