<?php

namespace Tests\Feature;

use App\Models\ClaudeReasoningLog;
use App\Models\Performance;
use App\Models\Show;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reading back what the AI made of a Planka card: who may, who may not, and how
 * the management screens are told which records have an account to show.
 */
class ClaudeReasoningLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_technician_reads_what_the_ai_made_of_the_card(): void
    {
        $log = ClaudeReasoningLog::factory()->create([
            'card_id' => 'card-1',
            'card_name' => 'Õppelava 9.10',
            'notes' => ['Kuupäev real "Toimumise kuupäev: 9.10.2025".'],
        ]);

        $this->actingAs($this->technician())
            ->getJson(route('api.claude-logs.show', $log))
            ->assertOk()
            ->assertJsonPath('data.cardId', 'card-1')
            ->assertJsonPath('data.cardName', 'Õppelava 9.10')
            ->assertJsonPath('data.notes', ['Kuupäev real "Toimumise kuupäev: 9.10.2025".'])
            ->assertJsonPath('data.id', $log->id);
    }

    public function test_the_reading_links_back_to_the_card_it_argues_about(): void
    {
        config()->set('services.planka.url', 'https://planka.test/');

        $log = ClaudeReasoningLog::factory()->create(['card_id' => 'card-1']);

        $this->actingAs($this->technician())
            ->getJson(route('api.claude-logs.show', $log))
            ->assertOk()
            ->assertJsonPath('data.cardUrl', 'https://planka.test/cards/card-1');
    }

    public function test_a_reading_of_no_card_links_nowhere(): void
    {
        config()->set('services.planka.url', 'https://planka.test');

        $log = ClaudeReasoningLog::factory()->create(['card_id' => null]);

        $this->actingAs($this->technician())
            ->getJson(route('api.claude-logs.show', $log))
            ->assertOk()
            ->assertJsonPath('data.cardUrl', null);
    }

    public function test_the_house_staff_read_it_too(): void
    {
        $log = ClaudeReasoningLog::factory()->create();
        $staff = User::factory()->create()->assignRole('staff');

        $this->actingAs($staff)
            ->getJson(route('api.claude-logs.show', $log))
            ->assertOk();
    }

    public function test_a_group_of_the_house_is_refused(): void
    {
        $log = ClaudeReasoningLog::factory()->create();

        // Even the group whose own show the reading created: the notes are a
        // debugging aid, not something a show says about itself.
        $user = User::factory()->create();
        $show = Show::factory()->create(['team_id' => $this->teamOf($user)->id]);
        $log->link($show);

        $this->actingAs($user)
            ->getJson(route('api.claude-logs.show', $log))
            ->assertForbidden();
    }

    public function test_reading_it_requires_signing_in(): void
    {
        $log = ClaudeReasoningLog::factory()->create();

        $this->getJson(route('api.claude-logs.show', $log))
            ->assertUnauthorized();
    }

    public function test_the_listings_point_a_permitted_user_at_the_reading(): void
    {
        $technician = $this->technician();

        $show = Show::factory()->create(['team_id' => $this->teamOf($technician)->id]);
        $performance = Performance::factory()->create(['show_id' => $show->id]);

        $log = ClaudeReasoningLog::factory()->create();
        $log->link($show);
        $log->link($performance);

        $this->actingAs($technician)
            ->getJson(route('api.shows.index'))
            ->assertOk()
            ->assertJsonPath('data.0.reasoningLogId', $log->id);

        $this->actingAs($technician)
            ->getJson(route('api.shows.performances.index', $show))
            ->assertOk()
            ->assertJsonPath('data.0.reasoningLogId', $log->id);
    }

    public function test_a_user_without_the_permission_is_not_told_a_reading_exists(): void
    {
        $user = User::factory()->create();

        $show = Show::factory()->create(['team_id' => $this->teamOf($user)->id]);
        $performance = Performance::factory()->create(['show_id' => $show->id]);

        $log = ClaudeReasoningLog::factory()->create();
        $log->link($show);
        $log->link($performance);

        // Null rather than absent: the screens hide the button on the field
        // itself, so there is nothing to press and nothing to be refused.
        $this->actingAs($user)
            ->getJson(route('api.shows.index'))
            ->assertOk()
            ->assertJsonPath('data.0.reasoningLogId', null);

        $this->actingAs($user)
            ->getJson(route('api.shows.performances.index', $show))
            ->assertOk()
            ->assertJsonPath('data.0.reasoningLogId', null);
    }

    public function test_a_record_entered_by_hand_has_no_reading(): void
    {
        $technician = $this->technician();
        $show = Show::factory()->create(['team_id' => $this->teamOf($technician)->id]);

        $this->actingAs($technician)
            ->getJson(route('api.shows.index'))
            ->assertOk()
            ->assertJsonPath('data.0.reasoningLogId', null);
    }

    public function test_a_record_is_never_left_with_two_accounts_of_itself(): void
    {
        $show = Show::factory()->create();
        $log = ClaudeReasoningLog::factory()->create();

        // The same card read twice links the same show once.
        $log->link($show);
        $log->link($show);

        $this->assertDatabaseCount('claude_reasoning_log_subjects', 1);
        $this->assertSame($log->id, $show->fresh()?->reasoningLog()?->id);
    }
}
