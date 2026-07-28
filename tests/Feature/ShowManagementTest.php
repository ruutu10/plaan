<?php

namespace Tests\Feature;

use App\Enums\TeamRole;
use App\Models\Performance;
use App\Models\Show;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ShowManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('shows.index'))
            ->assertRedirect(route('login'));
    }

    public function test_guests_are_refused_by_the_api(): void
    {
        $this->getJson(route('api.shows.index'))
            ->assertUnauthorized();
    }

    public function test_the_pages_are_shells_carrying_no_show_data(): void
    {
        $user = User::factory()->create();
        $show = Show::factory()->create(['team_id' => $this->teamOf($user)->id]);

        $this->actingAs($user)
            ->get(route('shows.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('shows/Index')
                ->missing('shows'));

        $this->actingAs($user)
            ->get(route('shows.edit', $show))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('shows/Edit')
                ->where('showId', $show->id)
                ->missing('show'));
    }

    public function test_the_listing_holds_only_the_shows_of_the_users_own_teams(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user, 'Improteater Ruutu10');

        $own = Show::factory()->create(['team_id' => $team->id, 'name' => 'Hooaja avaetendus']);
        $somebodyElses = Show::factory()->create(['name' => 'Kellegi teise lavastus']);

        $response = $this->actingAs($user)
            ->getJson(route('api.shows.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('data.0.name', 'Hooaja avaetendus')
            ->assertJsonPath('data.0.teamName', 'Improteater Ruutu10')
            ->assertJsonPath('data.0.performanceCount', 0);

        $this->assertNotContains($somebodyElses->id, array_column($response->json('data'), 'id'));
    }

    public function test_the_listing_counts_the_performances_and_sorts_by_name(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);

        Show::factory()->create(['team_id' => $team->id, 'name' => 'Öine impro']);
        $first = Show::factory()->create(['team_id' => $team->id, 'name' => 'Avaetendus']);

        Performance::factory()->count(3)->create(['show_id' => $first->id]);

        $this->actingAs($user)
            ->getJson(route('api.shows.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Avaetendus')
            ->assertJsonPath('data.0.performanceCount', 3)
            ->assertJsonPath('data.1.name', 'Öine impro');
    }

    public function test_holders_of_the_edit_all_permission_see_every_show(): void
    {
        $somebodyElses = Show::factory()->create();
        $ownerless = Show::factory()->create(['team_id' => null]);

        $response = $this->actingAs($this->technician())
            ->getJson(route('api.shows.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->assertEqualsCanonicalizing(
            [$somebodyElses->id, $ownerless->id],
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_a_member_can_read_their_teams_show_with_the_teams_it_may_be_handed_to(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user, 'Jaanuar');

        $show = Show::factory()->create([
            'team_id' => $team->id,
            'name' => 'Talvefestival',
            'description' => 'Lühivorm festivali raames.',
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('api.shows.show', $show))
            ->assertOk()
            ->assertJsonPath('data.id', $show->id)
            ->assertJsonPath('data.name', 'Talvefestival')
            ->assertJsonPath('data.description', 'Lühivorm festivali raames.')
            ->assertJsonPath('data.teamId', $team->id)
            ->assertJsonPath('data.teamName', 'Jaanuar')
            // The user's own personal team plus the one attached above.
            ->assertJsonCount(2, 'teams');

        $this->assertContains('Jaanuar', array_column($response->json('teams'), 'name'));
    }

    public function test_another_teams_show_is_forbidden(): void
    {
        $user = User::factory()->create();
        $show = Show::factory()->create();

        $this->actingAs($user)
            ->getJson(route('api.shows.show', $show))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('shows.edit', $show))
            ->assertForbidden();
    }

    public function test_holders_of_the_edit_all_permission_may_read_any_show(): void
    {
        $show = Show::factory()->create();
        Team::factory()->count(2)->create();

        $this->actingAs($this->technician())
            ->getJson(route('api.shows.show', $show))
            ->assertOk()
            ->assertJsonPath('data.id', $show->id)
            // Every team in the house is offered as the owning group.
            ->assertJsonCount(Team::count(), 'teams');
    }

    public function test_a_member_can_enter_a_new_show_for_their_team(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user, 'Improgrupp Kolm');

        $this->actingAs($user)
            ->postJson(route('api.shows.store'), [
                'team_id' => $team->id,
                'name' => 'Kolm lugu',
                'description' => 'Kolmest põimuvast loost koosnev improetendus.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Kolm lugu')
            ->assertJsonPath('data.teamId', $team->id)
            ->assertJsonPath('data.teamName', 'Improgrupp Kolm');

        $this->assertDatabaseHas('shows', [
            'team_id' => $team->id,
            'name' => 'Kolm lugu',
            'description' => 'Kolmest põimuvast loost koosnev improetendus.',
        ]);
    }

    public function test_a_new_show_may_be_entered_without_a_description(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);

        $this->actingAs($user)
            ->postJson(route('api.shows.store'), [
                'team_id' => $team->id,
                'name' => 'Nimeta kirjelduseta',
            ])
            ->assertCreated()
            ->assertJsonPath('data.description', null);
    }

    public function test_a_new_show_cannot_be_filed_under_a_team_the_user_does_not_belong_to(): void
    {
        $strangers = Team::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson(route('api.shows.store'), [
                'team_id' => $strangers->id,
                'name' => 'Kaaperdatud',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('team_id');

        $this->assertDatabaseMissing('shows', ['name' => 'Kaaperdatud']);
    }

    public function test_a_new_show_needs_a_name_and_a_team(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('api.shows.store'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['team_id', 'name']);
    }

    public function test_guests_cannot_enter_a_show(): void
    {
        $this->postJson(route('api.shows.store'), [
            'team_id' => Team::factory()->create()->id,
            'name' => 'Kutsumata',
        ])->assertUnauthorized();

        $this->assertDatabaseMissing('shows', ['name' => 'Kutsumata']);
    }

    public function test_the_listing_offers_the_teams_a_new_show_may_be_filed_under(): void
    {
        $user = User::factory()->create();
        $this->teamOf($user, 'Must Kast');
        Team::factory()->create(['name' => 'Võõras tiim']);

        $response = $this->actingAs($user)
            ->getJson(route('api.shows.index'))
            ->assertOk()
            // The user's own personal team plus the one attached above.
            ->assertJsonCount(2, 'teams');

        $names = array_column($response->json('teams'), 'name');

        $this->assertContains('Must Kast', $names);
        $this->assertNotContains('Võõras tiim', $names);
    }

    public function test_a_member_can_update_their_teams_show(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);
        $otherTeam = $this->teamOf($user, 'Teine tiim');

        $show = Show::factory()->create([
            'team_id' => $team->id,
            'name' => 'Vana nimi',
            'description' => 'Vana kirjeldus.',
        ]);

        $this->actingAs($user)
            ->patchJson(route('api.shows.update', $show), [
                'team_id' => $otherTeam->id,
                'name' => 'Uus nimi',
                'description' => 'Uus kirjeldus.',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Uus nimi')
            ->assertJsonPath('data.teamName', 'Teine tiim');

        $this->assertDatabaseHas('shows', [
            'id' => $show->id,
            'team_id' => $otherTeam->id,
            'name' => 'Uus nimi',
            'description' => 'Uus kirjeldus.',
        ]);
    }

    public function test_the_description_may_be_cleared(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);
        $show = Show::factory()->create(['team_id' => $team->id]);

        $this->actingAs($user)
            ->patchJson(route('api.shows.update', $show), [
                'team_id' => $team->id,
                'name' => $show->name,
                'description' => null,
            ])
            ->assertOk();

        $this->assertNull($show->fresh()->description);
    }

    public function test_updating_another_teams_show_is_forbidden(): void
    {
        $show = Show::factory()->create(['name' => 'Puutumata']);

        $this->actingAs(User::factory()->create())
            ->patchJson(route('api.shows.update', $show), [
                'team_id' => $show->team_id,
                'name' => 'Kaaperdatud',
            ])
            ->assertForbidden();

        $this->assertSame('Puutumata', $show->fresh()->name);
    }

    public function test_a_show_cannot_be_handed_to_a_team_the_user_does_not_belong_to(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);
        $strangers = Team::factory()->create();

        $show = Show::factory()->create(['team_id' => $team->id]);

        $this->actingAs($user)
            ->patchJson(route('api.shows.update', $show), [
                'team_id' => $strangers->id,
                'name' => $show->name,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('team_id');

        $this->assertSame($team->id, $show->fresh()->team_id);
    }

    public function test_a_holder_of_the_edit_all_permission_may_hand_a_show_to_any_team(): void
    {
        $strangers = Team::factory()->create();
        $show = Show::factory()->create(['team_id' => null, 'name' => 'Peremeheta']);

        $this->actingAs($this->technician())
            ->patchJson(route('api.shows.update', $show), [
                'team_id' => $strangers->id,
                'name' => 'Peremeheta',
            ])
            ->assertOk();

        $this->assertSame($strangers->id, $show->fresh()->team_id);
    }

    public function test_the_name_is_required(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);
        $show = Show::factory()->create(['team_id' => $team->id]);

        $this->actingAs($user)
            ->patchJson(route('api.shows.update', $show), [
                'team_id' => $team->id,
                'name' => '',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_a_member_can_delete_their_teams_show(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);
        $show = Show::factory()->create(['team_id' => $team->id]);

        $this->actingAs($user)
            ->deleteJson(route('api.shows.destroy', $show))
            ->assertNoContent();

        // Put aside, not destroyed — and gone from the listing either way.
        $this->assertSoftDeleted($show);

        $this->actingAs($user)
            ->getJson(route('api.shows.index'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_deleting_a_show_takes_its_performances_with_it(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);
        $show = Show::factory()->create(['team_id' => $team->id]);

        $performances = Performance::factory()->count(2)->create(['show_id' => $show->id]);

        $this->actingAs($user)
            ->deleteJson(route('api.shows.destroy', $show))
            ->assertNoContent();

        // Nothing is left pointing at a show the rest of the app no longer sees.
        $performances->each(fn (Performance $performance) => $this->assertSoftDeleted($performance));
    }

    public function test_a_performance_already_deleted_on_its_own_is_not_disturbed(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);
        $show = Show::factory()->create(['team_id' => $team->id]);

        $earlier = Performance::factory()->trashed()->create(['show_id' => $show->id]);
        $deletedAt = $earlier->deleted_at;

        $this->actingAs($user)
            ->deleteJson(route('api.shows.destroy', $show))
            ->assertNoContent();

        $this->assertEquals($deletedAt, $earlier->fresh()->deleted_at);
    }

    public function test_deleting_another_teams_show_is_forbidden(): void
    {
        $show = Show::factory()->create();

        $this->actingAs(User::factory()->create())
            ->deleteJson(route('api.shows.destroy', $show))
            ->assertForbidden();

        $this->assertNotSoftDeleted($show);
    }

    public function test_a_technician_may_delete_any_show(): void
    {
        $show = Show::factory()->create();

        $this->actingAs($this->technician())
            ->deleteJson(route('api.shows.destroy', $show))
            ->assertNoContent();

        $this->assertSoftDeleted($show);
    }

    public function test_a_deleted_show_can_no_longer_be_reached(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);
        $show = Show::factory()->trashed()->create(['team_id' => $team->id]);

        $this->actingAs($user)
            ->getJson(route('api.shows.show', $show))
            ->assertNotFound();

        $this->actingAs($user)
            ->get(route('shows.edit', $show))
            ->assertNotFound();
    }

    /**
     * A user holding the technician role, which carries the edit-all permission.
     */
    private function technician(): User
    {
        return User::factory()->create()->assignRole('technician');
    }

    /**
     * Attach the user to a (new) team as its owner.
     */
    private function teamOf(User $user, ?string $name = null): Team
    {
        $team = Team::factory()->create($name ? ['name' => $name] : []);

        $team->members()->attach($user, ['role' => TeamRole::Owner->value]);

        return $team;
    }
}
