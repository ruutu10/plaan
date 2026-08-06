<?php

namespace Tests\Feature;

use App\Models\ClaudeReasoningLog;
use App\Models\Format;
use App\Models\Performance;
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
        $format = Format::factory()->create();

        $log = ClaudeReasoningLog::factory()->create([
            'card_id' => 'card-1',
            'card_name' => 'Õppelava 9.10',
            'notes' => ['Kuupäev real "Toimumise kuupäev: 9.10.2025".'],
            'raw_response' => ['formats' => [], 'reasoningNotes' => ['Kuupäev real "Toimumise kuupäev: 9.10.2025".']],
        ]);
        $log->link($format);

        $this->actingAs($this->technician())
            ->getJson(route('api.formats.claude-logs', $format))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $log->id)
            ->assertJsonPath('data.0.cardId', 'card-1')
            ->assertJsonPath('data.0.cardName', 'Õppelava 9.10')
            ->assertJsonPath('data.0.notes', ['Kuupäev real "Toimumise kuupäev: 9.10.2025".'])
            ->assertJsonPath('data.0.rawResponse', ['formats' => [], 'reasoningNotes' => ['Kuupäev real "Toimumise kuupäev: 9.10.2025".']]);
    }

    public function test_a_format_built_card_by_card_shows_every_reading_newest_first(): void
    {
        // The Õppelava case: one format, made by one card and given a night by
        // the next. Reading only the first would explain an evening months ago
        // and say nothing about the one that looks wrong.
        $format = Format::factory()->create();

        $october = ClaudeReasoningLog::factory()->create([
            'card_name' => 'Õppelava 9.10',
            'created_at' => '2025-09-01 10:00:00',
        ]);
        $november = ClaudeReasoningLog::factory()->create([
            'card_name' => 'TLN õppelava 15.11',
            'created_at' => '2025-11-01 10:00:00',
        ]);

        $october->link($format);
        $november->link($format);

        $this->actingAs($this->technician())
            ->getJson(route('api.formats.claude-logs', $format))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.cardName', 'TLN õppelava 15.11')
            ->assertJsonPath('data.1.cardName', 'Õppelava 9.10');
    }

    public function test_a_performance_is_answered_in_the_same_shape(): void
    {
        $format = Format::factory()->create();
        $performance = Performance::factory()->create(['format_id' => $format->id]);

        $log = ClaudeReasoningLog::factory()->create(['card_name' => 'Õppelava 9.10']);
        $log->link($performance);

        $this->actingAs($this->technician())
            ->getJson(route('api.formats.performances.claude-logs', [$format, $performance]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.cardName', 'Õppelava 9.10');
    }

    public function test_a_record_nobody_imported_has_nothing_to_read(): void
    {
        $format = Format::factory()->create();

        $this->actingAs($this->technician())
            ->getJson(route('api.formats.claude-logs', $format))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_the_reading_links_back_to_the_card_it_argues_about(): void
    {
        config()->set('services.planka.url', 'https://planka.test/');

        $format = Format::factory()->create();
        ClaudeReasoningLog::factory()->create(['card_id' => 'card-1'])->link($format);

        $this->actingAs($this->technician())
            ->getJson(route('api.formats.claude-logs', $format))
            ->assertOk()
            ->assertJsonPath('data.0.cardUrl', 'https://planka.test/cards/card-1');
    }

    public function test_a_reading_of_no_card_links_nowhere(): void
    {
        config()->set('services.planka.url', 'https://planka.test');

        $format = Format::factory()->create();
        ClaudeReasoningLog::factory()->create(['card_id' => null])->link($format);

        $this->actingAs($this->technician())
            ->getJson(route('api.formats.claude-logs', $format))
            ->assertOk()
            ->assertJsonPath('data.0.cardUrl', null);
    }

    public function test_the_house_staff_read_it_too(): void
    {
        $format = Format::factory()->create();
        ClaudeReasoningLog::factory()->create()->link($format);

        $staff = User::factory()->create()->assignRole('staff');

        $this->actingAs($staff)
            ->getJson(route('api.formats.claude-logs', $format))
            ->assertOk();
    }

    public function test_a_group_of_the_house_is_refused(): void
    {
        // Even the group whose own format the reading created: the notes are a
        // debugging aid, not something a format says about itself.
        $user = User::factory()->create();
        $format = Format::factory()->create(['team_id' => $this->teamOf($user)->id]);

        ClaudeReasoningLog::factory()->create()->link($format);

        $this->actingAs($user)
            ->getJson(route('api.formats.claude-logs', $format))
            ->assertForbidden();
    }

    public function test_reading_it_requires_signing_in(): void
    {
        $format = Format::factory()->create();

        $this->getJson(route('api.formats.claude-logs', $format))
            ->assertUnauthorized();
    }

    public function test_the_listings_say_how_much_there_is_to_read(): void
    {
        $technician = $this->technician();

        $format = Format::factory()->create(['team_id' => $this->teamOf($technician)->id]);
        $performance = Performance::factory()->create(['format_id' => $format->id]);

        $first = ClaudeReasoningLog::factory()->create();
        $second = ClaudeReasoningLog::factory()->create();

        $first->link($format);
        $second->link($format);
        $first->link($performance);

        $this->actingAs($technician)
            ->getJson(route('api.formats.index'))
            ->assertOk()
            ->assertJsonPath('data.0.reasoningLogCount', 2);

        $this->actingAs($technician)
            ->getJson(route('api.formats.performances.index', $format))
            ->assertOk()
            ->assertJsonPath('data.0.reasoningLogCount', 1);
    }

    public function test_a_user_without_the_permission_is_not_told_a_reading_exists(): void
    {
        $user = User::factory()->create();

        $format = Format::factory()->create(['team_id' => $this->teamOf($user)->id]);
        $performance = Performance::factory()->create(['format_id' => $format->id]);

        $log = ClaudeReasoningLog::factory()->create();
        $log->link($format);
        $log->link($performance);

        // Zero rather than absent: the screens hide the button on the count
        // itself, so there is nothing to press and nothing to be refused.
        $this->actingAs($user)
            ->getJson(route('api.formats.index'))
            ->assertOk()
            ->assertJsonPath('data.0.reasoningLogCount', 0);

        $this->actingAs($user)
            ->getJson(route('api.formats.performances.index', $format))
            ->assertOk()
            ->assertJsonPath('data.0.reasoningLogCount', 0);
    }

    public function test_a_record_entered_by_hand_has_no_reading(): void
    {
        $technician = $this->technician();
        $format = Format::factory()->create(['team_id' => $this->teamOf($technician)->id]);

        $this->actingAs($technician)
            ->getJson(route('api.formats.index'))
            ->assertOk()
            ->assertJsonPath('data.0.reasoningLogCount', 0);
    }

    public function test_the_same_reading_is_never_attached_to_a_record_twice(): void
    {
        $format = Format::factory()->create();
        $log = ClaudeReasoningLog::factory()->create();

        $log->link($format);
        $log->link($format);

        $this->assertDatabaseCount('claude_reasoning_log_subjects', 1);
        $this->assertSame([$log->id], $format->fresh()?->reasoningLogs->pluck('id')->all());
    }
}
