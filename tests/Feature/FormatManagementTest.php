<?php

namespace Tests\Feature;

use App\Enums\CreatedBy;
use App\Models\Format;
use App\Models\Performance;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FormatManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('formats.index'))
            ->assertRedirect(route('login'));
    }

    public function test_guests_are_refused_by_the_api(): void
    {
        $this->getJson(route('api.formats.index'))
            ->assertUnauthorized();
    }

    public function test_the_pages_are_shells_carrying_no_format_data(): void
    {
        $user = User::factory()->create();
        $format = Format::factory()->create(['team_id' => $this->teamOf($user)->id]);

        $this->actingAs($user)
            ->get(route('formats.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('formats/Index')
                ->missing('formats'));

        $this->actingAs($user)
            ->get(route('formats.edit', $format))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('formats/Edit')
                ->where('formatId', $format->id)
                ->missing('format'));
    }

    public function test_the_listing_holds_only_the_formats_of_the_users_own_teams(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user, 'Improteater Ruutu10');

        $own = Format::factory()->create(['team_id' => $team->id, 'name' => 'Hooaja avaetendus']);
        $somebodyElses = Format::factory()->create(['name' => 'Kellegi teise lavastus']);

        $response = $this->actingAs($user)
            ->getJson(route('api.formats.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('data.0.name', 'Hooaja avaetendus')
            ->assertJsonPath('data.0.teamName', 'Improteater Ruutu10')
            ->assertJsonPath('data.0.performanceCount', 0);

        $this->assertNotContains($somebodyElses->id, array_column($response->json('data'), 'id'));
    }

    public function test_the_listing_holds_the_evenings_the_users_group_only_plays_an_act_on(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user, 'Märtu10');

        $own = Format::factory()->create(['team_id' => $team->id, 'name' => 'Hooaja avaetendus']);

        // Somebody else's Õppelava, with one slot played by the user's group.
        $evening = Format::factory()->create(['name' => 'Õppelava']);
        Performance::factory()->for($evening)->performedBy($team, 'Märtu10')->create();

        $ids = array_column(
            $this->actingAs($user)->getJson(route('api.formats.index'))->assertOk()->json('data'),
            'canEdit',
            'id',
        );

        // Both are reachable, but only their own is theirs to correct.
        $this->assertSame([$own->id => true, $evening->id => false], $ids);
    }

    public function test_the_listing_counts_the_performances_and_sorts_by_name(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);

        Format::factory()->create(['team_id' => $team->id, 'name' => 'Öine impro']);
        $first = Format::factory()->create(['team_id' => $team->id, 'name' => 'Avaetendus']);

        Performance::factory()->count(3)->create(['format_id' => $first->id]);

        $this->actingAs($user)
            ->getJson(route('api.formats.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Avaetendus')
            ->assertJsonPath('data.0.performanceCount', 3)
            ->assertJsonPath('data.1.name', 'Öine impro');
    }

    public function test_holders_of_the_edit_all_permission_see_every_format(): void
    {
        $somebodyElses = Format::factory()->create();
        $ownerless = Format::factory()->create(['team_id' => null]);

        $response = $this->actingAs($this->technician())
            ->getJson(route('api.formats.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertEqualsCanonicalizing(
            [$somebodyElses->id, $ownerless->id],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_a_member_can_read_their_teams_format_with_the_teams_it_may_be_handed_to(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user, 'Jaanuar');

        $format = Format::factory()->create([
            'team_id' => $team->id,
            'name' => 'Talvefestival',
            'description' => 'Lühivorm festivali raames.',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('api.formats.show', $format))
            ->assertOk()
            ->assertJsonPath('data.id', $format->id)
            ->assertJsonPath('data.name', 'Talvefestival')
            ->assertJsonPath('data.description', 'Lühivorm festivali raames.')
            ->assertJsonPath('data.teamId', $team->id)
            ->assertJsonPath('data.teamName', 'Jaanuar')
            // The user's own personal team plus the one attached above.
            ->assertJsonCount(2, 'teams');

        $this->assertContains('Jaanuar', array_column($response->json('teams'), 'name'));
    }

    public function test_the_api_reports_where_a_format_came_from_and_when(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);

        $entered = Format::factory()->create([
            'team_id' => $team->id,
            'created_at' => '2026-07-15 06:30:00',
        ]);
        $imported = Format::factory()->plankaImported()->create(['team_id' => $team->id]);

        $this->actingAs($user)
            ->getJson(route('api.formats.show', $entered))
            ->assertOk()
            ->assertJsonPath('data.createdBy', 'manual')
            // On the venue's clock, like every other moment the screens are
            // handed: 06:30 UTC is half past nine in Tallinn.
            ->assertJsonPath('data.createdAt', '2026-07-15T09:30:00+03:00');

        $this->actingAs($user)
            ->getJson(route('api.formats.show', $imported))
            ->assertOk()
            ->assertJsonPath('data.createdBy', 'planka-import');
    }

    public function test_a_format_entered_by_hand_says_so(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);

        // The origin is the server's to decide: a client claiming the import
        // made this one is not believed.
        $this->actingAs($user)
            ->postJson(route('api.formats.store'), [
                'team_id' => $team->id,
                'name' => 'Käsitsi sisestatud',
                'created_by' => 'planka-import',
            ])
            ->assertCreated()
            ->assertJsonPath('data.createdBy', 'manual');

        $this->assertSame(CreatedBy::Manual, Format::sole()->created_by);
    }

    public function test_saving_an_imported_format_leaves_its_origin_alone(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);
        $format = Format::factory()->plankaImported()->create(['team_id' => $team->id]);

        $this->actingAs($user)
            ->patchJson(route('api.formats.update', $format), [
                'team_id' => $team->id,
                'name' => 'Uus nimi',
                'created_by' => 'manual',
            ])
            ->assertOk()
            ->assertJsonPath('data.createdBy', 'planka-import');

        $this->assertSame(CreatedBy::PlankaImport, $format->fresh()->created_by);
    }

    public function test_another_teams_format_is_forbidden(): void
    {
        $user = User::factory()->create();
        $format = Format::factory()->create();

        $this->actingAs($user)
            ->getJson(route('api.formats.show', $format))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('formats.edit', $format))
            ->assertForbidden();
    }

    public function test_holders_of_the_edit_all_permission_may_read_any_format(): void
    {
        $format = Format::factory()->create();
        Team::factory()->count(2)->create();

        $this->actingAs($this->technician())
            ->getJson(route('api.formats.show', $format))
            ->assertOk()
            ->assertJsonPath('data.id', $format->id)
            // Every team in the house is offered as the owning group.
            ->assertJsonCount(Team::count(), 'teams');
    }

    public function test_a_member_can_enter_a_new_format_for_their_team(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user, 'Improgrupp Kolm');

        $this->actingAs($user)
            ->postJson(route('api.formats.store'), [
                'team_id' => $team->id,
                'name' => 'Kolm lugu',
                'description' => 'Kolmest põimuvast loost koosnev improetendus.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Kolm lugu')
            ->assertJsonPath('data.teamId', $team->id)
            ->assertJsonPath('data.teamName', 'Improgrupp Kolm');

        $this->assertDatabaseHas('formats', [
            'team_id' => $team->id,
            'name' => 'Kolm lugu',
            'description' => 'Kolmest põimuvast loost koosnev improetendus.',
        ]);
    }

    public function test_a_new_format_may_be_entered_without_a_description(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);

        $this->actingAs($user)
            ->postJson(route('api.formats.store'), [
                'team_id' => $team->id,
                'name' => 'Nimeta kirjelduseta',
            ])
            ->assertCreated()
            ->assertJsonPath('data.description', null);
    }

    public function test_a_new_format_cannot_be_filed_under_a_team_the_user_does_not_belong_to(): void
    {
        $strangers = Team::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson(route('api.formats.store'), [
                'team_id' => $strangers->id,
                'name' => 'Kaaperdatud',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('team_id');

        $this->assertDatabaseMissing('formats', ['name' => 'Kaaperdatud']);
    }

    public function test_a_new_format_needs_a_name_and_a_team(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('api.formats.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['team_id', 'name']);
    }

    public function test_guests_cannot_enter_a_format(): void
    {
        $this->postJson(route('api.formats.store'), [
            'team_id' => Team::factory()->create()->id,
            'name' => 'Kutsumata',
        ])->assertUnauthorized();

        $this->assertDatabaseMissing('formats', ['name' => 'Kutsumata']);
    }

    public function test_the_listing_offers_the_teams_a_new_format_may_be_filed_under(): void
    {
        $user = User::factory()->create();
        $this->teamOf($user, 'Must Kast');
        Team::factory()->create(['name' => 'Võõras tiim']);

        $response = $this->actingAs($user)
            ->getJson(route('api.formats.index'))
            ->assertOk()
            // The user's own personal team plus the one attached above.
            ->assertJsonCount(2, 'teams');

        $names = array_column($response->json('teams'), 'name');

        $this->assertContains('Must Kast', $names);
        $this->assertNotContains('Võõras tiim', $names);
    }

    public function test_a_member_can_update_their_teams_format(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);
        $otherTeam = $this->teamOf($user, 'Teine tiim');

        $format = Format::factory()->create([
            'team_id' => $team->id,
            'name' => 'Vana nimi',
            'description' => 'Vana kirjeldus.',
        ]);

        $this->actingAs($user)
            ->patchJson(route('api.formats.update', $format), [
                'team_id' => $otherTeam->id,
                'name' => 'Uus nimi',
                'description' => 'Uus kirjeldus.',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Uus nimi')
            ->assertJsonPath('data.teamName', 'Teine tiim');

        $this->assertDatabaseHas('formats', [
            'id' => $format->id,
            'team_id' => $otherTeam->id,
            'name' => 'Uus nimi',
            'description' => 'Uus kirjeldus.',
        ]);
    }

    public function test_the_description_may_be_cleared(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);
        $format = Format::factory()->create(['team_id' => $team->id]);

        $this->actingAs($user)
            ->patchJson(route('api.formats.update', $format), [
                'team_id' => $team->id,
                'name' => $format->name,
                'description' => null,
            ])
            ->assertOk();

        $this->assertNull($format->fresh()->description);
    }

    public function test_updating_another_teams_format_is_forbidden(): void
    {
        $format = Format::factory()->create(['name' => 'Puutumata']);

        $this->actingAs(User::factory()->create())
            ->patchJson(route('api.formats.update', $format), [
                'team_id' => $format->team_id,
                'name' => 'Kaaperdatud',
            ])
            ->assertForbidden();

        $this->assertSame('Puutumata', $format->fresh()->name);
    }

    public function test_a_format_cannot_be_handed_to_a_team_the_user_does_not_belong_to(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);
        $strangers = Team::factory()->create();

        $format = Format::factory()->create(['team_id' => $team->id]);

        $this->actingAs($user)
            ->patchJson(route('api.formats.update', $format), [
                'team_id' => $strangers->id,
                'name' => $format->name,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('team_id');

        $this->assertSame($team->id, $format->fresh()->team_id);
    }

    public function test_a_holder_of_the_edit_all_permission_may_hand_a_format_to_any_team(): void
    {
        $strangers = Team::factory()->create();
        $format = Format::factory()->create(['team_id' => null, 'name' => 'Peremeheta']);

        $this->actingAs($this->technician())
            ->patchJson(route('api.formats.update', $format), [
                'team_id' => $strangers->id,
                'name' => 'Peremeheta',
            ])
            ->assertOk();

        $this->assertSame($strangers->id, $format->fresh()->team_id);
    }

    public function test_the_name_is_required(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);
        $format = Format::factory()->create(['team_id' => $team->id]);

        $this->actingAs($user)
            ->patchJson(route('api.formats.update', $format), [
                'team_id' => $team->id,
                'name' => '',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_a_member_can_delete_their_teams_format(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);
        $format = Format::factory()->create(['team_id' => $team->id]);

        $this->actingAs($user)
            ->deleteJson(route('api.formats.destroy', $format))
            ->assertNoContent();

        // Put aside, not destroyed — and gone from the listing either way.
        $this->assertSoftDeleted($format);

        $this->actingAs($user)
            ->getJson(route('api.formats.index'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_deleting_a_format_takes_its_performances_with_it(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);
        $format = Format::factory()->create(['team_id' => $team->id]);

        $performances = Performance::factory()->count(2)->create(['format_id' => $format->id]);

        $this->actingAs($user)
            ->deleteJson(route('api.formats.destroy', $format))
            ->assertNoContent();

        // Nothing is left pointing at a format the rest of the app no longer sees.
        $performances->each(fn (Performance $performance) => $this->assertSoftDeleted($performance));
    }

    public function test_a_performance_already_deleted_on_its_own_is_not_disturbed(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);
        $format = Format::factory()->create(['team_id' => $team->id]);

        $earlier = Performance::factory()->trashed()->create(['format_id' => $format->id]);
        $deletedAt = $earlier->deleted_at;

        $this->actingAs($user)
            ->deleteJson(route('api.formats.destroy', $format))
            ->assertNoContent();

        $this->assertEquals($deletedAt, $earlier->fresh()->deleted_at);
    }

    public function test_deleting_another_teams_format_is_forbidden(): void
    {
        $format = Format::factory()->create();

        $this->actingAs(User::factory()->create())
            ->deleteJson(route('api.formats.destroy', $format))
            ->assertForbidden();

        $this->assertNotSoftDeleted($format);
    }

    public function test_a_technician_may_delete_any_format(): void
    {
        $format = Format::factory()->create();

        $this->actingAs($this->technician())
            ->deleteJson(route('api.formats.destroy', $format))
            ->assertNoContent();

        $this->assertSoftDeleted($format);
    }

    public function test_a_deleted_format_can_no_longer_be_reached(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);
        $format = Format::factory()->trashed()->create(['team_id' => $team->id]);

        $this->actingAs($user)
            ->getJson(route('api.formats.show', $format))
            ->assertNotFound();

        $this->actingAs($user)
            ->get(route('formats.edit', $format))
            ->assertNotFound();
    }
}
