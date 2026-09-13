<?php

namespace Tests\Feature;

use App\Enums\TechnicalPlanStatus;
use App\Events\TechnicalPlanSubmitted;
use App\Listeners\ReviewSubmittedPlanWithAi;
use App\Models\TechnicalPlan;
use App\Models\TechnicalPlanComment;
use App\Models\User;
use App\Notifications\TechnicalPlanCommented;
use App\Services\TechnicalPlanCriticalFindings;
use App\Services\TechnicalPlanReviewer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

/**
 * What happens to a plan the moment it is submitted: it is reviewed, that
 * review is sifted for the things that would stop the show, and anything found
 * is put back on the plan for the performer to fix.
 */
class TechnicalPlanAiReviewTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    private TechnicalPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.anthropic.key' => 'test-key',
            'technical_plan.tech_email' => 'tehnikud@ruutu10.ee',
        ]);

        Notification::fake();

        $this->author = User::factory()->create(['name' => 'Mari Esineja']);
        $this->plan = TechnicalPlan::factory()->submitted()->create([
            'user_id' => $this->author->id,
        ]);
    }

    public function test_a_freshly_submitted_plan_is_reviewed_and_its_show_stoppers_land_on_it(): void
    {
        $this->reviewerReturning('## Puudu või ebaselge

- Stseenil „Finaal" on muusika, aga faili pole.');

        $this->siftingInto([
            'Stseenil „Finaal" on kirjeldatud lõpumuusika, aga faili ega linki pole — lisa see.',
            'Vaheaja koht on plaanis kahes eri kohas — ütle, kumb kehtib.',
        ]);

        TechnicalPlanSubmitted::dispatch($this->plan, null);

        $comment = TechnicalPlanComment::query()->sole();

        $this->assertSame($this->plan->id, $comment->technical_plan_id);
        $this->assertNull($comment->user_id);
        $this->assertSame('AI tehnik (agent)', $comment->author_name);
        $this->assertSame('AI tehnik (agent)', $comment->authorName());
        $this->assertTrue($comment->from_technical_team);
        $this->assertStringContainsString('- Stseenil „Finaal" on kirjeldatud lõpumuusika', $comment->body);
        $this->assertStringContainsString('- Vaheaja koht on plaanis kahes eri kohas', $comment->body);
    }

    public function test_the_performer_is_mailed_about_what_the_agent_found(): void
    {
        $this->reviewerReturning('ülevaatus');
        $this->siftingInto(['Lisa „Finaali" helifail.']);

        TechnicalPlanSubmitted::dispatch($this->plan, null);

        Notification::assertSentTo($this->author, TechnicalPlanCommented::class);
    }

    public function test_a_plan_with_nothing_show_stopping_in_it_is_left_alone(): void
    {
        $this->reviewerReturning('Plaan on terviklik. Soovitused: täpsusta mikrofoni tüüpi.');
        $this->siftingInto([]);

        TechnicalPlanSubmitted::dispatch($this->plan, null);

        $this->assertDatabaseCount('technical_plan_comments', 0);

        // The plan itself still mails out as it always has; what must not
        // arrive is a remark about findings there were none of.
        Notification::assertNotSentTo($this->author, TechnicalPlanCommented::class);
    }

    public function test_a_plan_the_team_already_holds_is_not_reviewed_again(): void
    {
        $this->mock(TechnicalPlanReviewer::class)
            ->shouldNotReceive('reviewMarkdown');

        foreach (TechnicalPlanStatus::delivered() as $previousStatus) {
            TechnicalPlanSubmitted::dispatch($this->plan, $previousStatus);
        }

        $this->assertDatabaseCount('technical_plan_comments', 0);
    }

    public function test_a_house_without_an_ai_key_submits_plans_as_before(): void
    {
        config(['services.anthropic.key' => null]);

        $this->mock(TechnicalPlanReviewer::class)
            ->shouldNotReceive('reviewMarkdown');

        TechnicalPlanSubmitted::dispatch($this->plan, null);

        $this->assertDatabaseCount('technical_plan_comments', 0);
    }

    public function test_a_review_that_fails_leaves_the_submission_alone(): void
    {
        $this->mock(TechnicalPlanReviewer::class)
            ->shouldReceive('reviewMarkdown')
            ->andThrow(new RuntimeException('The API is down'));

        $this->mock(TechnicalPlanCriticalFindings::class)
            ->shouldNotReceive('findIn');

        TechnicalPlanSubmitted::dispatch($this->plan, null);

        $this->assertDatabaseCount('technical_plan_comments', 0);
    }

    public function test_an_empty_review_is_not_sifted(): void
    {
        $this->reviewerReturning('   ');

        $this->mock(TechnicalPlanCriticalFindings::class)
            ->shouldNotReceive('findIn');

        TechnicalPlanSubmitted::dispatch($this->plan, null);

        $this->assertDatabaseCount('technical_plan_comments', 0);
    }

    public function test_sifting_that_fails_leaves_the_submission_alone(): void
    {
        $this->reviewerReturning('ülevaatus');

        $this->mock(TechnicalPlanCriticalFindings::class)
            ->shouldReceive('findIn')
            ->andThrow(new RuntimeException('The API is down'));

        TechnicalPlanSubmitted::dispatch($this->plan, null);

        $this->assertDatabaseCount('technical_plan_comments', 0);
    }

    public function test_findings_longer_than_a_comment_may_run_are_cut_to_fit(): void
    {
        $this->reviewerReturning('ülevaatus');
        $this->siftingInto(array_fill(0, 40, str_repeat('a', 100)));

        TechnicalPlanSubmitted::dispatch($this->plan, null);

        $this->assertLessThanOrEqual(
            TechnicalPlanComment::MAX_LENGTH,
            mb_strlen(TechnicalPlanComment::query()->sole()->body),
        );
    }

    public function test_the_review_is_queued_rather_than_done_in_the_request(): void
    {
        $this->assertInstanceOf(ShouldQueue::class, new ReviewSubmittedPlanWithAi);
    }

    /**
     * Stand in for the first pass, which would otherwise call out to Anthropic.
     */
    private function reviewerReturning(string $markdown): void
    {
        $this->mock(TechnicalPlanReviewer::class)
            ->shouldReceive('reviewMarkdown')
            ->once()
            ->andReturn($markdown);
    }

    /**
     * Stand in for the second pass with the findings it should come back with.
     *
     * @param  list<string>  $findings
     */
    private function siftingInto(array $findings): void
    {
        $this->mock(TechnicalPlanCriticalFindings::class)
            ->shouldReceive('findIn')
            ->once()
            ->andReturn($findings);
    }
}
