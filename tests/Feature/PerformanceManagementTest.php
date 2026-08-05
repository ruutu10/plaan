<?php

namespace Tests\Feature;

use App\Enums\CreatedBy;
use App\Enums\TeamRole;
use App\Models\Format;
use App\Models\Performance;
use App\Models\Team;
use App\Models\TechnicalPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_refused(): void
    {
        $format = Format::factory()->create();

        $this->getJson(route('api.formats.performances.index', $format))
            ->assertUnauthorized();
    }

    public function test_a_member_can_list_the_performances_of_their_teams_format(): void
    {
        [$user, $format] = $this->formatOfOwnTeam();

        $later = Performance::factory()->create(['format_id' => $format->id, 'date' => '2026-09-01']);
        $sooner = Performance::factory()->create(['format_id' => $format->id, 'date' => '2026-08-01', 'duration' => 75]);

        $this->actingAs($user)
            ->getJson(route('api.formats.performances.index', $format))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            // Soonest first.
            ->assertJsonPath('data.0.id', $sooner->id)
            ->assertJsonPath('data.0.date', '2026-08-01')
            ->assertJsonPath('data.0.duration', 75)
            ->assertJsonPath('data.0.technicalPlanCount', 0)
            // The format rides along without an extra query per row — see
            // PerformanceController::index()'s own setRelation() call.
            ->assertJsonPath('data.0.formatId', $format->id)
            ->assertJsonPath('data.0.formatName', $format->name)
            ->assertJsonPath('data.1.id', $later->id);
    }

    public function test_a_format_without_performances_lists_none(): void
    {
        [$user, $format] = $this->formatOfOwnTeam();

        $this->actingAs($user)
            ->getJson(route('api.formats.performances.index', $format))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_the_performances_of_another_teams_format_are_forbidden(): void
    {
        $format = Format::factory()->create();

        $this->actingAs(User::factory()->create())
            ->getJson(route('api.formats.performances.index', $format))
            ->assertForbidden();
    }

    public function test_a_member_can_add_a_performance(): void
    {
        [$user, $format] = $this->formatOfOwnTeam();

        $this->actingAs($user)
            ->postJson(route('api.formats.performances.store', $format), [
                'date' => '2026-08-14',
                'duration' => 90,
            ])
            ->assertCreated()
            ->assertJsonPath('data.date', '2026-08-14')
            ->assertJsonPath('data.duration', 90)
            ->assertJsonPath('data.technicalPlanCount', 0);

        $performance = Performance::sole();

        $this->assertSame($format->id, $performance->format_id);
        $this->assertSame('2026-08-14', $performance->date->toDateString());
        $this->assertSame(90, $performance->duration);
    }

    public function test_a_performance_keeps_the_hour_it_was_given(): void
    {
        [$user, $format] = $this->formatOfOwnTeam();

        $this->actingAs($user)
            ->postJson(route('api.formats.performances.store', $format), [
                'date' => '2026-08-14',
                'start_time' => '20:30',
            ])
            ->assertCreated()
            ->assertJsonPath('data.date', '2026-08-14')
            ->assertJsonPath('data.startTime', '20:30');

        // Half past eight on an August evening in Tallinn is 17:30 UTC, which
        // is what a column shared with every other timestamp in the app holds.
        $this->assertSame('2026-08-14 17:30:00', Performance::sole()->date->utc()->toDateTimeString());
    }

    public function test_a_performance_without_an_hour_takes_the_houses_usual_one(): void
    {
        [$user, $format] = $this->formatOfOwnTeam();

        $this->actingAs($user)
            ->postJson(route('api.formats.performances.store', $format), ['date' => '2026-08-14'])
            ->assertCreated()
            ->assertJsonPath('data.startTime', '19:00');

        $this->actingAs($user)
            ->postJson(route('api.formats.performances.store', $format), [
                'date' => '2026-08-15',
                'start_time' => '',
            ])
            ->assertCreated()
            ->assertJsonPath('data.startTime', '19:00');
    }

    public function test_a_late_night_performance_stays_on_the_night_it_is_played(): void
    {
        [$user, $format] = $this->formatOfOwnTeam();

        $this->actingAs($user)
            ->postJson(route('api.formats.performances.store', $format), [
                'date' => '2026-08-14',
                'start_time' => '00:30',
            ])
            ->assertCreated()
            // Half past midnight Tallinn time is still the 13th in UTC. The
            // house means the 14th, and that is what it is given back as.
            ->assertJsonPath('data.date', '2026-08-14')
            ->assertJsonPath('data.startTime', '00:30');

        $this->assertSame('2026-08-13 21:30:00', Performance::sole()->date->utc()->toDateTimeString());
    }

    public function test_the_venues_winter_clock_is_a_different_offset_from_its_summer_one(): void
    {
        [$user, $format] = $this->formatOfOwnTeam();

        // Two hours ahead of UTC in January, three in August: a start time
        // stored as a fixed offset would be an hour out for half the season.
        $this->actingAs($user)
            ->postJson(route('api.formats.performances.store', $format), [
                'date' => '2027-01-14',
                'start_time' => '19:00',
            ])
            ->assertCreated()
            ->assertJsonPath('data.startTime', '19:00');

        $this->assertSame('2027-01-14 17:00:00', Performance::sole()->date->utc()->toDateTimeString());
    }

    public function test_an_hour_that_is_not_a_time_of_day_is_refused(): void
    {
        [$user, $format] = $this->formatOfOwnTeam();

        $this->actingAs($user)
            ->postJson(route('api.formats.performances.store', $format), [
                'date' => '2026-08-14',
                'start_time' => 'kell seitse',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('start_time');

        $this->assertDatabaseCount('performances', 0);
    }

    public function test_a_member_can_move_a_performances_start_time(): void
    {
        [$user, $format] = $this->formatOfOwnTeam();

        $performance = Performance::factory()->startingAt('2026-08-01', '19:00')->create(['format_id' => $format->id]);

        $this->actingAs($user)
            ->patchJson(route('api.formats.performances.update', [$format, $performance]), [
                'date' => '2026-08-01',
                'start_time' => '21:15',
            ])
            ->assertOk()
            ->assertJsonPath('data.startTime', '21:15');

        $this->assertSame('21:15', $performance->fresh()->startTime());
    }

    public function test_a_performance_may_be_added_without_a_duration(): void
    {
        [$user, $format] = $this->formatOfOwnTeam();

        $this->actingAs($user)
            ->postJson(route('api.formats.performances.store', $format), ['date' => '2026-08-14'])
            ->assertCreated()
            ->assertJsonPath('data.duration', null);
    }

    public function test_a_blank_duration_counts_as_no_duration(): void
    {
        [$user, $format] = $this->formatOfOwnTeam();

        $this->actingAs($user)
            ->postJson(route('api.formats.performances.store', $format), [
                'date' => '2026-08-14',
                'duration' => '',
            ])
            ->assertCreated()
            ->assertJsonPath('data.duration', null);
    }

    public function test_adding_a_performance_to_another_teams_format_is_forbidden(): void
    {
        $format = Format::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson(route('api.formats.performances.store', $format), ['date' => '2026-08-14'])
            ->assertForbidden();

        $this->assertDatabaseCount('performances', 0);
    }

    public function test_a_performance_needs_a_valid_date_and_a_sane_duration(): void
    {
        [$user, $format] = $this->formatOfOwnTeam();

        $this->actingAs($user)
            ->postJson(route('api.formats.performances.store', $format), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('date');

        $this->actingAs($user)
            ->postJson(route('api.formats.performances.store', $format), [
                'date' => '14.08.2026',
                'duration' => 5000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date', 'duration']);
    }

    public function test_a_member_can_update_a_performance(): void
    {
        [$user, $format] = $this->formatOfOwnTeam();
        $performance = Performance::factory()->create(['format_id' => $format->id, 'date' => '2026-08-01']);

        $this->actingAs($user)
            ->patchJson(route('api.formats.performances.update', [$format, $performance]), [
                'date' => '2026-08-02',
                'duration' => 120,
            ])
            ->assertOk()
            ->assertJsonPath('data.date', '2026-08-02')
            ->assertJsonPath('data.duration', 120);

        $performance->refresh();

        $this->assertSame('2026-08-02', $performance->date->toDateString());
        $this->assertSame(120, $performance->duration);
    }

    public function test_the_planka_card_can_be_written_down_by_hand(): void
    {
        config()->set('services.planka.url', 'https://planka.test/');

        [$user, $format] = $this->formatOfOwnTeam();
        $performance = Performance::factory()->create(['format_id' => $format->id]);

        $this->actingAs($user)
            ->patchJson(route('api.formats.performances.update', [$format, $performance]), [
                'date' => $performance->startDate(),
                'planka_card_id' => '1516073411733063234',
            ])
            ->assertOk()
            ->assertJsonPath('data.plankaCardId', '1516073411733063234')
            ->assertJsonPath('data.plankaCardUrl', 'https://planka.test/cards/1516073411733063234');

        $this->assertSame('1516073411733063234', $performance->fresh()?->planka_card_id);
    }

    public function test_the_planka_card_may_be_cleared(): void
    {
        [$user, $format] = $this->formatOfOwnTeam();
        $performance = Performance::factory()->create([
            'format_id' => $format->id,
            'planka_card_id' => '1516073411733063234',
        ]);

        $this->actingAs($user)
            ->patchJson(route('api.formats.performances.update', [$format, $performance]), [
                'date' => $performance->startDate(),
                'planka_card_id' => '',
            ])
            ->assertOk()
            ->assertJsonPath('data.plankaCardId', null)
            ->assertJsonPath('data.plankaCardUrl', null);

        $this->assertNull($performance->fresh()?->planka_card_id);
    }

    public function test_the_api_reports_where_a_performance_came_from_and_when(): void
    {
        [$user, $format] = $this->formatOfOwnTeam();

        Performance::factory()->create([
            'format_id' => $format->id,
            'date' => '2026-08-01',
            'created_at' => '2026-07-15 06:30:00',
        ]);
        Performance::factory()->plankaImported()->create([
            'format_id' => $format->id,
            'date' => '2026-08-02',
            'created_at' => '2026-07-16 06:30:00',
        ]);

        $this->actingAs($user)
            ->getJson(route('api.formats.performances.index', $format))
            ->assertOk()
            ->assertJsonPath('data.0.createdBy', 'manual')
            // On the venue's clock, like every other moment the screens are
            // handed: 06:30 UTC is half past nine in Tallinn.
            ->assertJsonPath('data.0.createdAt', '2026-07-15T09:30:00+03:00')
            ->assertJsonPath('data.1.createdBy', 'planka-import')
            ->assertJsonPath('data.1.createdAt', '2026-07-16T09:30:00+03:00');
    }

    public function test_a_performance_added_by_hand_says_so(): void
    {
        [$user, $format] = $this->formatOfOwnTeam();

        // The origin is the server's to decide: a client claiming the import
        // made this one is not believed.
        $this->actingAs($user)
            ->postJson(route('api.formats.performances.store', $format), [
                'date' => '2026-08-14',
                'created_by' => 'planka-import',
            ])
            ->assertCreated()
            ->assertJsonPath('data.createdBy', 'manual');

        $this->assertSame(CreatedBy::Manual, Performance::sole()->created_by);
    }

    public function test_saving_an_imported_performance_leaves_its_origin_alone(): void
    {
        [$user, $format] = $this->formatOfOwnTeam();
        $performance = Performance::factory()->plankaImported()->create([
            'format_id' => $format->id,
            'date' => '2026-08-01',
        ]);

        // Reviewing an imported performance does not turn it into one somebody
        // entered: the card is still where its date came from.
        $this->actingAs($user)
            ->patchJson(route('api.formats.performances.update', [$format, $performance]), [
                'date' => '2026-08-01',
                'is_draft' => false,
                'created_by' => 'manual',
            ])
            ->assertOk()
            ->assertJsonPath('data.createdBy', 'planka-import');

        $this->assertSame(CreatedBy::PlankaImport, $performance->fresh()->created_by);
    }

    public function test_the_listing_says_which_performances_wait_to_be_reviewed(): void
    {
        [$user, $format] = $this->formatOfOwnTeam();

        Performance::factory()->draft()->create(['format_id' => $format->id, 'date' => '2026-08-01']);
        Performance::factory()->create(['format_id' => $format->id, 'date' => '2026-08-02']);

        $this->actingAs($user)
            ->getJson(route('api.formats.performances.index', $format))
            ->assertOk()
            ->assertJsonPath('data.0.isDraft', true)
            ->assertJsonPath('data.1.isDraft', false);
    }

    public function test_a_performance_added_by_hand_is_not_a_draft(): void
    {
        [$user, $format] = $this->formatOfOwnTeam();

        // Adding it here is the review, so there is nothing left to vouch for.
        $this->actingAs($user)
            ->postJson(route('api.formats.performances.store', $format), ['date' => '2026-08-14'])
            ->assertCreated()
            ->assertJsonPath('data.isDraft', false);

        $this->assertFalse(Performance::sole()->is_draft);
    }

    public function test_a_member_can_clear_a_performance_that_waited_to_be_reviewed(): void
    {
        [$user, $format] = $this->formatOfOwnTeam();
        $performance = Performance::factory()->draft()->create(['format_id' => $format->id, 'date' => '2026-08-01']);

        $this->actingAs($user)
            ->patchJson(route('api.formats.performances.update', [$format, $performance]), [
                'date' => '2026-08-01',
                'is_draft' => false,
            ])
            ->assertOk()
            ->assertJsonPath('data.isDraft', false);

        $this->assertFalse($performance->fresh()->is_draft);
    }

    public function test_a_member_can_put_a_performance_back_to_waiting(): void
    {
        [$user, $format] = $this->formatOfOwnTeam();
        $performance = Performance::factory()->create(['format_id' => $format->id, 'date' => '2026-08-01']);

        $this->actingAs($user)
            ->patchJson(route('api.formats.performances.update', [$format, $performance]), [
                'date' => '2026-08-01',
                'is_draft' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.isDraft', true);

        $this->assertTrue($performance->fresh()->is_draft);
    }

    public function test_a_saved_performance_keeps_its_standing_when_the_field_is_left_out(): void
    {
        [$user, $format] = $this->formatOfOwnTeam();
        $performance = Performance::factory()->draft()->create(['format_id' => $format->id, 'date' => '2026-08-01']);

        // A client that does not offer the toggle must not clear the flag by
        // saving the date alone.
        $this->actingAs($user)
            ->patchJson(route('api.formats.performances.update', [$format, $performance]), [
                'date' => '2026-08-02',
            ])
            ->assertOk()
            ->assertJsonPath('data.isDraft', true);

        $this->assertTrue($performance->fresh()->is_draft);
    }

    public function test_the_draft_flag_must_be_a_boolean(): void
    {
        [$user, $format] = $this->formatOfOwnTeam();

        $this->actingAs($user)
            ->postJson(route('api.formats.performances.store', $format), [
                'date' => '2026-08-14',
                'is_draft' => 'vahest',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('is_draft');
    }

    public function test_updating_another_teams_performance_is_forbidden(): void
    {
        $performance = Performance::factory()->create(['date' => '2026-08-01']);

        $this->actingAs(User::factory()->create())
            ->patchJson(route('api.formats.performances.update', [$performance->format, $performance]), [
                'date' => '2026-09-09',
            ])
            ->assertForbidden();

        $this->assertSame('2026-08-01', $performance->fresh()->date->toDateString());
    }

    public function test_a_member_can_delete_a_performance(): void
    {
        [$user, $format] = $this->formatOfOwnTeam();
        $performance = Performance::factory()->create(['format_id' => $format->id]);

        $this->actingAs($user)
            ->deleteJson(route('api.formats.performances.destroy', [$format, $performance]))
            ->assertNoContent();

        // Put aside, not destroyed — and gone from the listing either way.
        $this->assertSoftDeleted($performance);

        $this->actingAs($user)
            ->getJson(route('api.formats.performances.index', $format))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_a_deleted_performance_keeps_the_technical_plans_written_for_it(): void
    {
        [$user, $format] = $this->formatOfOwnTeam();
        $performance = Performance::factory()->create(['format_id' => $format->id]);
        $plan = TechnicalPlan::factory()->create(['performance_id' => $performance->id]);

        $this->actingAs($user)
            ->deleteJson(route('api.formats.performances.destroy', [$format, $performance]))
            ->assertNoContent();

        // The plan survives, and so does its trail back to the performance: the
        // performance is only hidden, so restoring it would join the two up again.
        $this->assertDatabaseHas('technical_plans', [
            'id' => $plan->id,
            'performance_id' => $performance->id,
        ]);

        // Until then the plan reads as one without a performance, because the
        // relation does not reach through a soft delete.
        $this->assertNull($plan->fresh()->performance);
    }

    public function test_a_deleted_performance_can_no_longer_be_reached(): void
    {
        [$user, $format] = $this->formatOfOwnTeam();
        $performance = Performance::factory()->trashed()->create(['format_id' => $format->id]);

        $this->actingAs($user)
            ->patchJson(route('api.formats.performances.update', [$format, $performance]), [
                'date' => '2026-09-09',
            ])
            ->assertNotFound();
    }

    public function test_the_number_of_plans_is_listed_so_the_screen_can_warn(): void
    {
        [$user, $format] = $this->formatOfOwnTeam();
        $performance = Performance::factory()->create(['format_id' => $format->id]);
        TechnicalPlan::factory()->count(2)->create(['performance_id' => $performance->id]);

        $this->actingAs($user)
            ->getJson(route('api.formats.performances.index', $format))
            ->assertOk()
            ->assertJsonPath('data.0.technicalPlanCount', 2);
    }

    public function test_deleting_another_teams_performance_is_forbidden(): void
    {
        $performance = Performance::factory()->create();

        $this->actingAs(User::factory()->create())
            ->deleteJson(route('api.formats.performances.destroy', [$performance->format, $performance]))
            ->assertForbidden();

        $this->assertDatabaseHas('performances', ['id' => $performance->id]);
    }

    public function test_a_performance_cannot_be_reached_through_another_formats_url(): void
    {
        [$user, $format] = $this->formatOfOwnTeam();
        $strangersPerformance = Performance::factory()->create();

        // The user may manage $format's performances, but this one is not among them.
        $this->actingAs($user)
            ->patchJson(route('api.formats.performances.update', [$format, $strangersPerformance]), [
                'date' => '2026-09-09',
            ])
            ->assertNotFound();
    }

    public function test_a_technician_may_manage_the_performances_of_any_format(): void
    {
        $technician = User::factory()->create()->assignRole('technician');
        $format = Format::factory()->create();
        $performance = Performance::factory()->create(['format_id' => $format->id]);

        $this->actingAs($technician)
            ->getJson(route('api.formats.performances.index', $format))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($technician)
            ->postJson(route('api.formats.performances.store', $format), ['date' => '2026-12-01'])
            ->assertCreated();

        $this->actingAs($technician)
            ->patchJson(route('api.formats.performances.update', [$format, $performance]), [
                'date' => '2026-12-02',
            ])
            ->assertOk();

        $this->actingAs($technician)
            ->deleteJson(route('api.formats.performances.destroy', [$format, $performance]))
            ->assertNoContent();
    }

    public function test_the_edit_all_permission_stands_on_its_own(): void
    {
        $user = User::factory()->create()->givePermissionTo(Performance::EDIT_ALL_PERMISSION);
        $format = Format::factory()->create();

        // The performances of anybody's format: yes.
        $this->actingAs($user)
            ->postJson(route('api.formats.performances.store', $format), ['date' => '2026-12-01'])
            ->assertCreated();

        // The format itself: no — that is what formats.edit_all is for.
        $this->actingAs($user)
            ->getJson(route('api.formats.show', $format))
            ->assertForbidden();
    }

    public function test_a_performance_can_name_the_act_and_the_group_playing_it(): void
    {
        [$user, $format] = $this->formatOfOwnTeam();
        $guest = $format->team;

        $this->actingAs($user)
            ->postJson(route('api.formats.performances.store', $format), [
                'date' => '2026-10-09',
                'title' => 'Märtu10',
                'team_id' => $guest->id,
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Märtu10')
            ->assertJsonPath('data.teamId', $guest->id)
            ->assertJsonPath('data.teamName', $guest->name);

        $performance = Performance::sole();

        $this->assertSame('Märtu10', $performance->title);
        $this->assertSame($guest->id, $performance->team_id);
    }

    public function test_an_act_without_a_name_or_a_group_of_its_own_is_the_formats(): void
    {
        [$user, $format] = $this->formatOfOwnTeam();

        $this->actingAs($user)
            ->postJson(route('api.formats.performances.store', $format), [
                'date' => '2026-10-09',
                'title' => '',
                'team_id' => '',
            ])
            ->assertCreated()
            ->assertJsonPath('data.title', null)
            ->assertJsonPath('data.teamId', null);

        // The format's own group answers for it.
        $this->assertSame($format->team->name, Performance::sole()->performerName());
    }

    public function test_a_performance_cannot_be_handed_to_a_group_the_user_is_not_in(): void
    {
        [$user, $format] = $this->formatOfOwnTeam();
        $stranger = Team::factory()->create();

        $this->actingAs($user)
            ->postJson(route('api.formats.performances.store', $format), [
                'date' => '2026-10-09',
                'team_id' => $stranger->id,
            ])
            ->assertJsonValidationErrors('team_id');
    }

    public function test_an_act_name_longer_than_the_column_is_refused(): void
    {
        [$user, $format] = $this->formatOfOwnTeam();

        $this->actingAs($user)
            ->postJson(route('api.formats.performances.store', $format), [
                'date' => '2026-10-09',
                'title' => str_repeat('a', 256),
            ])
            ->assertJsonValidationErrors('title');
    }

    public function test_a_group_playing_an_act_may_correct_it_on_somebody_elses_evening(): void
    {
        [$guest, $ownFormat] = $this->formatOfOwnTeam();
        $guestTeam = $ownFormat->team;

        // Somebody else's Õppelava, with one slot played by the guest.
        $evening = Format::factory()->create();
        $slot = Performance::factory()->for($evening)
            ->performedBy($guestTeam, 'Märtu10')
            ->create(['date' => '2026-10-09 17:00:00']);

        $this->actingAs($guest)
            ->getJson(route('api.formats.performances.index', $evening))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($guest)
            ->patchJson(route('api.formats.performances.update', [$evening, $slot]), [
                'date' => '2026-10-10',
                'duration' => 25,
            ])
            ->assertOk();

        $this->assertSame(25, $slot->refresh()->duration);
    }

    public function test_a_group_playing_an_act_may_not_touch_the_format_around_it(): void
    {
        [$guest, $ownFormat] = $this->formatOfOwnTeam();

        $evening = Format::factory()->create(['name' => 'Õppelava']);
        Performance::factory()->for($evening)->performedBy($ownFormat->team, 'Märtu10')->create();

        // The evening's page opens, so the guest can reach its own slot…
        $this->actingAs($guest)
            ->getJson(route('api.formats.show', $evening))
            ->assertOk()
            ->assertJsonPath('data.canEdit', false);

        // …but the format's own details are not theirs to rewrite, and neither is
        // putting another performance of their own on the bill.
        $this->actingAs($guest)
            ->patchJson(route('api.formats.update', $evening), [
                'team_id' => $ownFormat->team_id,
                'name' => 'Meie oma',
            ])
            ->assertForbidden();

        $this->actingAs($guest)
            ->postJson(route('api.formats.performances.store', $evening), ['date' => '2026-11-01'])
            ->assertForbidden();

        $this->assertSame('Õppelava', $evening->refresh()->name);
    }

    /**
     * A user with a team of their own, and a format that team owns.
     *
     * @return array{0: User, 1: Format}
     */
    private function formatOfOwnTeam(): array
    {
        $user = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

        return [$user, Format::factory()->create(['team_id' => $team->id])];
    }
}
