<?php

namespace Tests\Feature;

use App\Enums\TechnicalPlanStatus;
use App\Models\Performance;
use App\Models\TechnicalPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
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
                ['id' => 's1', 'name' => 'Lavale tulek', 'light' => 'Soe üldvalgus', 'soundUrl' => '', 'sound' => '', 'transition' => 'Blackout', 'notes' => ''],
            ],
            'equipment' => [
                'items' => [
                    ['id' => 'e1', 'name' => 'Suitsumasin', 'use' => 'Lavaletuleku ajal'],
                ],
                'smoke' => 'minimal',
                'suggestions' => 'yes',
                'suggestNote' => '',
            ],
            'extra' => [
                'notes' => 'Palun jälgida ajakava.',
                'files' => [
                    ['name' => 'plaan.pdf', 'size' => 12345],
                ],
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
            ->has('config.techEmail'));
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

        $this->assertDatabaseHas('users', ['email' => 'mart@naide.ee']);
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
}
