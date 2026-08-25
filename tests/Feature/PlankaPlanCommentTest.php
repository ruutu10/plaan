<?php

namespace Tests\Feature;

use App\Enums\TechnicalPlanStatus;
use App\Events\TechnicalPlanStatusChanged;
use App\Models\Performance;
use App\Models\Team;
use App\Models\TechnicalPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * A technician confirming a plan should leave a word on the night's Planka
 * card, and should leave exactly one however many times the plan travels
 * through that status — which the card's own comments, not a column here, are
 * asked about.
 *
 * The status change is made the way the controller makes it and the event
 * fired by hand rather than driven through the route: only production writes
 * to the board, and an app told it is production enforces the CSRF token a
 * test request has no way to carry. That the route dispatches this event at
 * all is TechnicalPlanAdminTest's business, and the one case that does run
 * through the route here is the one that must stay silent anyway.
 */
class PlankaPlanCommentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        // Only production talks to the house's real board.
        $this->app->detectEnvironment(fn (): string => 'production');

        config()->set('services.planka.url', 'https://planka.test');
        config()->set('services.planka.list_ids', 'list-1');
        config()->set('services.planka.token', 'test-token');
    }

    /**
     * Fake a card's comment endpoint, holding the given comment texts.
     *
     * @param  list<string>  $texts
     */
    private function fakeCardComments(string $cardId, array $texts = []): void
    {
        Http::preventStrayRequests();

        Http::fake([
            "planka.test/api/cards/{$cardId}/comments" => Http::response([
                'items' => array_map(
                    fn (string $text, int $index): array => ['id' => 'comment-'.$index, 'text' => $text],
                    $texts,
                    array_keys($texts),
                ),
                'included' => ['users' => []],
            ]),
        ]);
    }

    /**
     * A submitted plan for a night that was imported from the board.
     */
    private function planOnCard(string $cardId = 'card-1'): TechnicalPlan
    {
        return TechnicalPlan::factory()->submitted()->create([
            'performance_id' => Performance::factory()->create(['planka_card_id' => $cardId]),
        ]);
    }

    /**
     * Move a plan to the given status the way the controller does it, and tell
     * the listeners about it.
     */
    private function move(TechnicalPlan $plan, TechnicalPlanStatus $to, ?User $by = null): User
    {
        $from = $plan->status;
        $by ??= $this->technician();

        $plan->update(['status' => $to]);

        TechnicalPlanStatusChanged::dispatch($plan, $from, $to, $by);

        return $by;
    }

    private function confirm(TechnicalPlan $plan, ?User $by = null): User
    {
        return $this->move($plan, TechnicalPlanStatus::Received, $by);
    }

    /**
     * What the card is told for a plan written by Mari and confirmed by Toomas.
     */
    private function expectedComment(TechnicalPlan $plan): string
    {
        return 'Tehnikaplaan on esitatud (esitas Mari Esineja) ja tehniku (Tehnik Toomas) poolt kinnitatud.'
            .' Plaani link: '.route('technical-plan.public', $plan);
    }

    public function test_confirming_a_plan_names_both_people_and_links_the_plan(): void
    {
        $this->fakeCardComments('card-1');

        $plan = $this->planOnCard();
        $plan->user->update(['name' => 'Mari Esineja']);

        $technician = User::factory()->create(['name' => 'Tehnik Toomas'])->assignRole('technician');

        $this->confirm($plan->refresh(), $technician);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://planka.test/api/cards/card-1/comments'
            && $request->hasHeader('X-Api-Key', 'test-token')
            && $request['text'] === $this->expectedComment($plan));
    }

    public function test_a_plan_with_no_author_is_announced_under_its_performer(): void
    {
        $this->fakeCardComments('card-1');

        $team = Team::factory()->create(['name' => 'Märtu10']);
        $plan = TechnicalPlan::factory()->submitted()->create([
            'user_id' => null,
            'performance_id' => Performance::factory()->performedBy($team)->create(['planka_card_id' => 'card-1']),
        ]);

        $this->confirm($plan);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_contains((string) $request['text'], '(esitas Märtu10)'));
    }

    public function test_a_plan_already_announced_on_its_card_is_not_announced_twice(): void
    {
        $plan = $this->planOnCard();

        $this->fakeCardComments('card-1', [$this->expectedComment($plan)]);

        $this->confirm($plan);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET');
        Http::assertNotSent(fn (Request $request): bool => $request->method() === 'POST');
    }

    public function test_confirming_a_plan_a_second_time_leaves_one_comment(): void
    {
        // A board that keeps what it is told, so the second confirmation reads
        // back what the first one wrote rather than a canned answer.
        $comments = [];

        Http::preventStrayRequests();

        Http::fake(function (Request $request) use (&$comments) {
            if ($request->method() === 'POST') {
                $comments[] = (string) $request['text'];

                return Http::response(['item' => ['id' => 'comment-1', 'text' => end($comments)]], 201);
            }

            return Http::response([
                'items' => array_map(fn (string $text): array => ['text' => $text], $comments),
            ]);
        });

        $plan = $this->planOnCard();

        $this->confirm($plan);

        $this->move($plan, TechnicalPlanStatus::Submitted);
        $this->confirm($plan);

        $this->assertCount(1, $comments);
    }

    public function test_a_card_carrying_another_plans_link_still_gets_this_ones(): void
    {
        $other = $this->planOnCard('card-2');

        $this->fakeCardComments('card-1', [$this->expectedComment($other)]);

        $plan = $this->planOnCard();

        $this->confirm($plan);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && str_contains((string) $request['text'], route('technical-plan.public', $plan)));
    }

    public function test_a_performance_without_a_planka_card_is_left_alone(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $plan = TechnicalPlan::factory()->submitted()->create([
            'performance_id' => Performance::factory()->create(['planka_card_id' => null]),
        ]);

        $this->confirm($plan);

        Http::assertNothingSent();
    }

    public function test_other_status_changes_say_nothing_on_the_board(): void
    {
        Http::preventStrayRequests();
        Http::fake();

        $plan = TechnicalPlan::factory()->create([
            'status' => TechnicalPlanStatus::Received,
            'performance_id' => Performance::factory()->create(['planka_card_id' => 'card-1']),
        ]);

        $this->move($plan, TechnicalPlanStatus::Archived);

        Http::assertNothingSent();
    }

    /**
     * The one case driven through the route, since a test app that is not
     * production is one the route still answers: nothing outside production
     * reaches the board, and confirming a plan there works all the same.
     */
    public function test_nothing_is_sent_outside_production(): void
    {
        $this->app->detectEnvironment(fn (): string => 'testing');

        Http::preventStrayRequests();
        Http::fake();

        $plan = $this->planOnCard();

        $this->actingAs($this->technician())
            ->patch(route('technical-plans.update-status', $plan), ['status' => TechnicalPlanStatus::Received->value])
            ->assertRedirect(route('technical-plans.show', $plan));

        Http::assertNothingSent();
        $this->assertSame(TechnicalPlanStatus::Received, $plan->refresh()->status);
    }

    public function test_nothing_is_sent_when_planka_is_not_configured(): void
    {
        config()->set('services.planka.token', '');

        Http::preventStrayRequests();
        Http::fake();

        $this->confirm($this->planOnCard());

        Http::assertNothingSent();
    }

    public function test_a_planka_outage_does_not_hold_up_the_status_change(): void
    {
        Log::spy();

        Http::preventStrayRequests();
        Http::fake(['planka.test/*' => Http::response(['message' => 'nope'], 500)]);

        $plan = $this->planOnCard();

        $this->confirm($plan);

        $this->assertSame(TechnicalPlanStatus::Received, $plan->refresh()->status);

        Log::shouldHaveReceived('error')->withArgs(
            fn (string $message, array $context): bool => $message === 'Could not comment a confirmed plan on its Planka card'
                && $context['card_id'] === 'card-1',
        )->once();
    }
}
