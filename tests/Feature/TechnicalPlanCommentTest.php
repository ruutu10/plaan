<?php

namespace Tests\Feature;

use App\Models\Format;
use App\Models\Performance;
use App\Models\TechnicalPlan;
use App\Models\TechnicalPlanComment;
use App\Models\User;
use App\Notifications\TechnicalPlanCommented;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The conversation on a plan's overview page: who may read it, who may write
 * in it, and who is told when somebody does.
 */
class TechnicalPlanCommentTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private TechnicalPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        config(['technical_plan.tech_email' => 'tehnikud@ruutu10.ee']);

        $this->author = User::factory()->create(['name' => 'Mari Esineja']);
        $this->plan = TechnicalPlan::factory()->submitted()->create([
            'user_id' => $this->author->id,
        ]);
    }

    public function test_a_guest_may_read_the_thread_but_is_not_offered_the_box(): void
    {
        TechnicalPlanComment::factory()->create([
            'technical_plan_id' => $this->plan->id,
            'user_id' => $this->author->id,
            'body' => 'Kas mikrofone on kindlasti neli?',
        ]);

        $this->getJson(route('technical-plan.comments.index', $this->plan))
            ->assertOk()
            ->assertJsonPath('canComment', false)
            ->assertJsonPath('results.0.body', 'Kas mikrofone on kindlasti neli?')
            ->assertJsonPath('results.0.authorName', 'Mari Esineja');
    }

    public function test_the_thread_never_carries_anybody_s_address(): void
    {
        TechnicalPlanComment::factory()->create([
            'technical_plan_id' => $this->plan->id,
            'user_id' => $this->author->id,
        ]);

        $this->getJson(route('technical-plan.comments.index', $this->plan))
            ->assertOk()
            ->assertDontSee($this->author->email);
    }

    public function test_the_thread_is_read_oldest_first(): void
    {
        foreach (['Esimene', 'Teine', 'Kolmas'] as $index => $body) {
            TechnicalPlanComment::factory()->create([
                'technical_plan_id' => $this->plan->id,
                'user_id' => $this->author->id,
                'body' => $body,
                'created_at' => now()->addMinutes($index),
            ]);
        }

        $this->getJson(route('technical-plan.comments.index', $this->plan))
            ->assertOk()
            ->assertJsonPath('results.0.body', 'Esimene')
            ->assertJsonPath('results.1.body', 'Teine')
            ->assertJsonPath('results.2.body', 'Kolmas');
    }

    public function test_a_thread_holds_only_its_own_plan_s_comments(): void
    {
        TechnicalPlanComment::factory()->create([
            'technical_plan_id' => $this->plan->id,
            'user_id' => $this->author->id,
            'body' => 'Selle plaani oma.',
        ]);

        TechnicalPlanComment::factory()->create([
            'body' => 'Hoopis teise plaani oma.',
        ]);

        $this->getJson(route('technical-plan.comments.index', $this->plan))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.body', 'Selle plaani oma.');
    }

    public function test_a_signed_in_reader_is_offered_the_box(): void
    {
        $this->actingAs($this->author)
            ->getJson(route('technical-plan.comments.index', $this->plan))
            ->assertOk()
            ->assertJsonPath('canComment', true);
    }

    public function test_a_guest_may_not_comment(): void
    {
        Notification::fake();

        $this->postJson(route('technical-plan.comments.store', $this->plan), [
            'body' => 'Tere',
        ])->assertUnauthorized();

        $this->assertDatabaseCount('technical_plan_comments', 0);
        Notification::assertNothingSent();
    }

    public function test_an_author_s_comment_is_stored_and_mailed_to_the_technical_team(): void
    {
        Notification::fake();

        $this->actingAs($this->author)
            ->postJson(route('technical-plan.comments.store', $this->plan), [
                'body' => 'Lisasime ühe stseeni juurde.',
            ])
            ->assertCreated()
            ->assertJsonPath('body', 'Lisasime ühe stseeni juurde.')
            ->assertJsonPath('authorName', 'Mari Esineja')
            ->assertJsonPath('fromTechnicalTeam', false);

        $comment = TechnicalPlanComment::sole();

        $this->assertSame($this->plan->id, $comment->technical_plan_id);
        $this->assertSame($this->author->id, $comment->user_id);
        $this->assertFalse($comment->from_technical_team);

        Notification::assertSentOnDemand(
            TechnicalPlanCommented::class,
            fn (TechnicalPlanCommented $notification, array $channels, object $notifiable): bool => $notifiable->routes['mail'] === 'tehnikud@ruutu10.ee'
                && $notification->comment->is($comment),
        );

        Notification::assertCount(1);
    }

    public function test_a_technician_s_comment_is_marked_as_theirs_and_mailed_to_the_author(): void
    {
        Notification::fake();

        $this->actingAs($this->technician())
            ->postJson(route('technical-plan.comments.store', $this->plan), [
                'body' => 'Suitsu sellel õhtul kasutada ei saa.',
            ])
            ->assertCreated()
            ->assertJsonPath('fromTechnicalTeam', true);

        $comment = TechnicalPlanComment::sole();

        $this->assertTrue($comment->from_technical_team);

        Notification::assertSentTo(
            $this->author,
            fn (TechnicalPlanCommented $notification): bool => $notification->comment->is($comment),
        );

        Notification::assertCount(1);
    }

    public function test_a_team_mate_may_comment_on_their_group_s_plan(): void
    {
        Notification::fake();

        $mate = User::factory()->create();
        $team = $this->teamOf($mate);
        $format = Format::factory()->create(['team_id' => $team->id]);
        $plan = TechnicalPlan::factory()->submitted()->create([
            'user_id' => $this->author->id,
            'performance_id' => Performance::factory()->create(['format_id' => $format->id]),
        ]);

        $this->actingAs($mate)
            ->postJson(route('technical-plan.comments.store', $plan), [
                'body' => 'Mina täidan selle lõpuni.',
            ])
            ->assertCreated()
            ->assertJsonPath('fromTechnicalTeam', false);

        // The performer's side spoke, so it is the crew that is told.
        Notification::assertSentOnDemand(TechnicalPlanCommented::class);
        Notification::assertCount(1);
    }

    public function test_a_technician_commenting_on_their_own_plan_mails_nobody(): void
    {
        Notification::fake();

        $technician = $this->technician();
        $plan = TechnicalPlan::factory()->submitted()->create(['user_id' => $technician->id]);

        $this->actingAs($technician)
            ->postJson(route('technical-plan.comments.store', $plan), [
                'body' => 'Märkus iseendale.',
            ])
            ->assertCreated();

        Notification::assertNothingSent();
    }

    public function test_a_crew_comment_on_an_authorless_plan_mails_nobody(): void
    {
        Notification::fake();

        $plan = TechnicalPlan::factory()->submitted()->create(['user_id' => null]);

        $this->actingAs($this->technician())
            ->postJson(route('technical-plan.comments.store', $plan), [
                'body' => 'Kelle oma see plaan on?',
            ])
            ->assertCreated();

        Notification::assertNothingSent();
        $this->assertDatabaseCount('technical_plan_comments', 1);
    }

    public function test_a_comment_written_from_the_technical_address_mails_nobody(): void
    {
        Notification::fake();

        $author = User::factory()->create(['email' => 'tehnikud@ruutu10.ee']);
        $plan = TechnicalPlan::factory()->submitted()->create(['user_id' => $author->id]);

        $this->actingAs($author)
            ->postJson(route('technical-plan.comments.store', $plan), [
                'body' => 'Kirjutan majale endale.',
            ])
            ->assertCreated();

        Notification::assertNothingSent();
    }

    public function test_a_comment_reaches_nobody_when_no_technical_contact_is_configured(): void
    {
        Notification::fake();
        config(['technical_plan.tech_email' => '']);

        $this->actingAs($this->author)
            ->postJson(route('technical-plan.comments.store', $this->plan), [
                'body' => 'Kas keegi loeb seda?',
            ])
            ->assertCreated();

        Notification::assertNothingSent();
        $this->assertDatabaseCount('technical_plan_comments', 1);
    }

    public function test_an_empty_comment_is_refused(): void
    {
        Notification::fake();

        $this->actingAs($this->author)
            ->postJson(route('technical-plan.comments.store', $this->plan), ['body' => '   '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');

        $this->assertDatabaseCount('technical_plan_comments', 0);
        Notification::assertNothingSent();
    }

    public function test_an_overlong_comment_is_refused(): void
    {
        $this->actingAs($this->author)
            ->postJson(route('technical-plan.comments.store', $this->plan), [
                'body' => str_repeat('a', TechnicalPlanComment::MAX_LENGTH + 1),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('body');

        $this->assertDatabaseCount('technical_plan_comments', 0);
    }

    public function test_a_plan_s_comments_go_with_the_plan(): void
    {
        TechnicalPlanComment::factory()->create([
            'technical_plan_id' => $this->plan->id,
            'user_id' => $this->author->id,
        ]);

        $this->plan->delete();

        $this->assertDatabaseCount('technical_plan_comments', 0);
    }

    public function test_the_mail_carries_the_remark_and_a_link_back_to_the_plan(): void
    {
        $comment = TechnicalPlanComment::factory()->fromTechnicalTeam()->create([
            'technical_plan_id' => $this->plan->id,
            'user_id' => $this->technician()->id,
            'body' => 'Palun täpsusta valgust teises stseenis.',
        ]);

        $mail = (new TechnicalPlanCommented($comment))->toMail($this->author);
        $rendered = $mail->render();

        $this->assertStringContainsString('Palun täpsusta valgust teises stseenis.', $rendered);
        $this->assertStringContainsString(route('technical-plan.public', $this->plan), $rendered);
        $this->assertStringContainsString('tehnikatiim', $rendered);
    }
}
