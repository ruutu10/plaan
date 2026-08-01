<?php

namespace Tests\Feature;

use App\Models\ClaudeReasoningLog;
use App\Models\Performance;
use App\Models\Show;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Reading back what the AI made of the cards a record was imported from: who
 * may, who may not, and how the management screens are told there is something
 * to show.
 */
class ClaudeReasoningLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_technician_reads_what_the_ai_made_of_the_card(): void
    {
        $show = Show::factory()->create();

        $log = ClaudeReasoningLog::factory()->create([
            'card_id' => 'card-1',
            'card_name' => 'Õppelava 9.10',
            'notes' => ['Kuupäev real "Toimumise kuupäev: 9.10.2025".'],
        ]);
        $log->link($show);

        $this->actingAs($this->technician())
            ->getJson(route('api.shows.claude-logs', $show))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $log->id)
            ->assertJsonPath('data.0.cardId', 'card-1')
            ->assertJsonPath('data.0.cardName', 'Õppelava 9.10')
            ->assertJsonPath('data.0.notes', ['Kuupäev real "Toimumise kuupäev: 9.10.2025".']);
    }

    public function test_a_show_built_card_by_card_shows_every_reading_newest_first(): void
    {
        // The Õppelava case: one show, made by one card and given a night by
        // the next. Reading only the first would explain an evening months ago
        // and say nothing about the one that looks wrong.
        $show = Show::factory()->create();

        $october = ClaudeReasoningLog::factory()->create([
            'card_name' => 'Õppelava 9.10',
            'created_at' => '2025-09-01 10:00:00',
        ]);
        $november = ClaudeReasoningLog::factory()->create([
            'card_name' => 'TLN õppelava 15.11',
            'created_at' => '2025-11-01 10:00:00',
        ]);

        $october->link($show);
        $november->link($show);

        $this->actingAs($this->technician())
            ->getJson(route('api.shows.claude-logs', $show))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.cardName', 'TLN õppelava 15.11')
            ->assertJsonPath('data.1.cardName', 'Õppelava 9.10');
    }

    public function test_a_performance_is_answered_in_the_same_shape(): void
    {
        $show = Show::factory()->create();
        $performance = Performance::factory()->create(['show_id' => $show->id]);

        $log = ClaudeReasoningLog::factory()->create(['card_name' => 'Õppelava 9.10']);
        $log->link($performance);

        $this->actingAs($this->technician())
            ->getJson(route('api.shows.performances.claude-logs', [$show, $performance]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.cardName', 'Õppelava 9.10');
    }

    public function test_a_record_nobody_imported_has_nothing_to_read(): void
    {
        $show = Show::factory()->create();

        $this->actingAs($this->technician())
            ->getJson(route('api.shows.claude-logs', $show))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_the_reading_links_back_to_the_card_it_argues_about(): void
    {
        config()->set('services.planka.url', 'https://planka.test/');

        $show = Show::factory()->create();
        ClaudeReasoningLog::factory()->create(['card_id' => 'card-1'])->link($show);

        $this->actingAs($this->technician())
            ->getJson(route('api.shows.claude-logs', $show))
            ->assertOk()
            ->assertJsonPath('data.0.cardUrl', 'https://planka.test/cards/card-1');
    }

    public function test_a_reading_of_no_card_links_nowhere(): void
    {
        config()->set('services.planka.url', 'https://planka.test');

        $show = Show::factory()->create();
        ClaudeReasoningLog::factory()->create(['card_id' => null])->link($show);

        $this->actingAs($this->technician())
            ->getJson(route('api.shows.claude-logs', $show))
            ->assertOk()
            ->assertJsonPath('data.0.cardUrl', null);
    }

    public function test_the_house_staff_read_it_too(): void
    {
        $show = Show::factory()->create();
        ClaudeReasoningLog::factory()->create()->link($show);

        $staff = User::factory()->create()->assignRole('staff');

        $this->actingAs($staff)
            ->getJson(route('api.shows.claude-logs', $show))
            ->assertOk();
    }

    public function test_a_group_of_the_house_is_refused(): void
    {
        // Even the group whose own show the reading created: the notes are a
        // debugging aid, not something a show says about itself.
        $user = User::factory()->create();
        $show = Show::factory()->create(['team_id' => $this->teamOf($user)->id]);

        ClaudeReasoningLog::factory()->create()->link($show);

        $this->actingAs($user)
            ->getJson(route('api.shows.claude-logs', $show))
            ->assertForbidden();
    }

    public function test_reading_it_requires_signing_in(): void
    {
        $show = Show::factory()->create();

        $this->getJson(route('api.shows.claude-logs', $show))
            ->assertUnauthorized();
    }

    public function test_the_listings_say_how_much_there_is_to_read(): void
    {
        $technician = $this->technician();

        $show = Show::factory()->create(['team_id' => $this->teamOf($technician)->id]);
        $performance = Performance::factory()->create(['show_id' => $show->id]);

        $first = ClaudeReasoningLog::factory()->create();
        $second = ClaudeReasoningLog::factory()->create();

        $first->link($show);
        $second->link($show);
        $first->link($performance);

        $this->actingAs($technician)
            ->getJson(route('api.shows.index'))
            ->assertOk()
            ->assertJsonPath('data.0.reasoningLogCount', 2);

        $this->actingAs($technician)
            ->getJson(route('api.shows.performances.index', $show))
            ->assertOk()
            ->assertJsonPath('data.0.reasoningLogCount', 1);
    }

    public function test_a_user_without_the_permission_is_not_told_a_reading_exists(): void
    {
        $user = User::factory()->create();

        $show = Show::factory()->create(['team_id' => $this->teamOf($user)->id]);
        $performance = Performance::factory()->create(['show_id' => $show->id]);

        $log = ClaudeReasoningLog::factory()->create();
        $log->link($show);
        $log->link($performance);

        // Zero rather than absent: the screens hide the button on the count
        // itself, so there is nothing to press and nothing to be refused.
        $this->actingAs($user)
            ->getJson(route('api.shows.index'))
            ->assertOk()
            ->assertJsonPath('data.0.reasoningLogCount', 0);

        $this->actingAs($user)
            ->getJson(route('api.shows.performances.index', $show))
            ->assertOk()
            ->assertJsonPath('data.0.reasoningLogCount', 0);
    }

    public function test_a_record_entered_by_hand_has_no_reading(): void
    {
        $technician = $this->technician();
        $show = Show::factory()->create(['team_id' => $this->teamOf($technician)->id]);

        $this->actingAs($technician)
            ->getJson(route('api.shows.index'))
            ->assertOk()
            ->assertJsonPath('data.0.reasoningLogCount', 0);
    }

    public function test_the_same_reading_is_never_attached_to_a_record_twice(): void
    {
        $show = Show::factory()->create();
        $log = ClaudeReasoningLog::factory()->create();

        $log->link($show);
        $log->link($show);

        $this->assertDatabaseCount('claude_reasoning_log_subjects', 1);
        $this->assertSame([$log->id], $show->fresh()?->reasoningLogs->pluck('id')->all());
    }
}
