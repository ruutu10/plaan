<?php

namespace Tests\Feature;

use App\Enums\SignupSource;
use App\Enums\TechnicalPlanStatus;
use App\Models\PendingUpload;
use App\Models\Performance;
use App\Models\TechnicalPlan;
use App\Models\User;
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
                'contactEmail' => 'mart@naide.ee',
            ],
            'sound' => [
                'micsMode' => 'yes',
                'micsDetail' => '2 käsimikrofoni',
                'musicianMode' => 'no',
                'musicianDetail' => '',
                'musicMode' => 'none',
                'musicList' => '',
            ],
            'scenes' => [
                ['id' => 's1', 'name' => 'Lavale tulek', 'light' => 'Soe üldvalgus', 'soundUrl' => '', 'sound' => '', 'notes' => ''],
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
        $response->assertJsonStructure(['token', 'status', 'publicUrl']);
        $this->assertStringStartsWith('R10-', $response->json('token'));
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

    public function test_storing_creates_a_contact_user_when_none_exists(): void
    {
        $this->assertDatabaseMissing('users', ['email' => 'mart@naide.ee']);

        $this->postJson(route('technical-plan.store'), $this->validPayload())->assertOk();

        $this->assertDatabaseHas('users', [
            'email' => 'mart@naide.ee',
            'signup_source' => SignupSource::AnonymousPlan->value,
        ]);
        $this->assertSame(1, User::where('email', 'mart@naide.ee')->count());
    }

    public function test_storing_links_an_existing_contact_user(): void
    {
        $user = User::factory()->create(['email' => 'mart@naide.ee']);

        $this->postJson(route('technical-plan.store'), $this->validPayload())->assertOk();

        $this->assertSame(1, User::where('email', 'mart@naide.ee')->count());
        $this->assertSame($user->id, TechnicalPlan::first()->user_id);
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

    public function test_the_contact_email_is_validated(): void
    {
        $response = $this->postJson(route('technical-plan.store'), $this->validPayload([
            'meta' => ['contactEmail' => 'not-an-email'],
        ]));

        $response->assertUnprocessable();
        $this->assertArrayHasKey('meta.contactEmail', $response->json('errors'));
        $this->assertSame(0, TechnicalPlan::count());
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

    public function test_lookup_returns_submitted_plans_for_an_email(): void
    {
        $sina = User::factory()->create(['email' => 'ando@ruutu10.ee']);

        $performance = Performance::factory()->create(['show_name' => 'Esitatud plaan']);
        TechnicalPlan::factory()->for($sina)->for($performance)->submitted()->create();
        TechnicalPlan::factory()->for($sina)->create();
        TechnicalPlan::factory()->submitted()->create();

        $response = $this->postJson(route('technical-plan.lookup'), ['email' => 'ando@ruutu10.ee']);

        $response->assertOk();
        $response->assertJsonCount(1, 'results');
        $response->assertJsonFragment([
            'token' => TechnicalPlan::where('performance_id', $performance->id)->first()->token,
        ]);
    }

    public function test_ai_review_requires_configuration(): void
    {
        config()->set('services.anthropic.key', null);

        $response = $this->postJson(route('technical-plan.ai'), $this->validPayload());

        $response->assertUnprocessable();
        $response->assertJsonStructure(['message']);
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

    /**
     * Upload a fake file and return the handle the wizard would send on submit.
     */
    private function uploadHandle(string $name = 'plaan.pdf'): string
    {
        return $this->postJson(route('attachments.store'), [
            'file' => UploadedFile::fake()->create($name, 120, 'application/pdf'),
        ])->json('id');
    }
}
