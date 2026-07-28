<?php

namespace Tests\Feature\Teams;

use App\Enums\TeamRole;
use App\Models\Show;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TeamAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('admin.teams.index'))
            ->assertRedirect(route('login'));
    }

    public function test_guests_are_refused_by_the_api(): void
    {
        $this->getJson(route('api.teams.index'))
            ->assertUnauthorized();
    }

    public function test_the_pages_are_shells_carrying_no_team_data(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);

        $this->actingAs($user)
            ->get(route('admin.teams.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/teams/Index')
                ->missing('members'));

        $this->actingAs($user)
            ->get(route('admin.teams.edit', $team))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/teams/Edit')
                ->where('teamId', $team->id)
                ->missing('team')
                ->missing('members'));
    }

    public function test_the_listing_holds_only_the_users_own_teams(): void
    {
        $user = User::factory()->create();
        $own = $this->teamOf($user, 'Improteater Ruutu10');
        $somebodyElses = Team::factory()->create();

        $response = $this->actingAs($user)
            ->getJson(route('api.teams.index'))
            ->assertOk()
            // The user's own personal team rides along; it is a team like any other.
            ->assertJsonCount(2, 'data');

        $rows = collect($response->json('data'))->keyBy('id');

        $this->assertEquals([
            'id' => $own->id,
            'name' => 'Improteater Ruutu10',
            'slug' => $own->slug,
            'isPersonal' => false,
            'memberCount' => 1,
            'showCount' => 0,
        ], $rows->get($own->id));

        $this->assertTrue($rows->get($user->personalTeam()->id)['isPersonal']);
        $this->assertNull($rows->get($somebodyElses->id));
    }

    public function test_the_listing_counts_the_members_and_shows_and_sorts_by_name(): void
    {
        $user = User::factory()->create(['name' => 'Öö']);

        $this->teamOf($user, 'Öine trupp');
        $first = $this->teamOf($user, 'Avatrupp');

        $first->members()->attach(User::factory()->create(), ['role' => TeamRole::Member->value]);
        Show::factory()->count(2)->create(['team_id' => $first->id]);

        $this->actingAs($user)
            ->getJson(route('api.teams.index'))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.name', 'Avatrupp')
            ->assertJsonPath('data.0.memberCount', 2)
            ->assertJsonPath('data.0.showCount', 2)
            ->assertJsonPath('data.1.name', 'Öine trupp')
            ->assertJsonPath('data.2.name', "Öö's Team");
    }

    public function test_the_listing_leaves_out_deleted_teams(): void
    {
        $technician = $this->technician();
        $team = $this->teamOf($technician);
        $gone = Team::factory()->trashed()->create();

        $response = $this->actingAs($technician)
            ->getJson(route('api.teams.index'))
            ->assertOk();

        $ids = array_column($response->json('data'), 'id');

        $this->assertContains($team->id, $ids);
        $this->assertNotContains($gone->id, $ids);
    }

    public function test_holders_of_the_edit_all_permission_see_every_team(): void
    {
        $technician = $this->technician();
        $own = $this->teamOf($technician);
        $somebodyElses = Team::factory()->create();

        $response = $this->actingAs($technician)
            ->getJson(route('api.teams.index'))
            ->assertOk();

        $ids = array_column($response->json('data'), 'id');

        $this->assertContains($own->id, $ids);
        $this->assertContains($somebodyElses->id, $ids);
    }

    public function test_a_member_can_read_their_team_with_its_members_and_the_roles_on_offer(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user, 'Jaanuar');

        $this->actingAs($user)
            ->getJson(route('api.teams.show', $team))
            ->assertOk()
            ->assertJsonPath('data.id', $team->id)
            ->assertJsonPath('data.name', 'Jaanuar')
            ->assertJsonPath('data.showCount', 0)
            ->assertJsonCount(1, 'data.members')
            ->assertJsonPath('data.members.0.id', $user->id)
            ->assertJsonPath('data.members.0.email', $user->email)
            ->assertJsonPath('data.members.0.role', 'owner')
            ->assertJsonPath('data.members.0.isOwner', true)
            ->assertJsonPath('roles.0.value', 'admin')
            ->assertJsonPath('roles.1.value', 'member');
    }

    public function test_the_team_says_what_its_reader_may_write(): void
    {
        $owner = User::factory()->create();
        $team = $this->teamOf($owner);

        // An owner holds every right in their own team.
        $this->actingAs($owner)
            ->getJson(route('api.teams.show', $team))
            ->assertOk()
            ->assertJsonPath('permissions.canUpdate', true)
            ->assertJsonPath('permissions.canAddMember', true)
            ->assertJsonPath('permissions.canUpdateMember', true)
            ->assertJsonPath('permissions.canRemoveMember', true);

        // A plain member reaches the team but writes nothing in it.
        $member = User::factory()->create();
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $this->actingAs($member)
            ->getJson(route('api.teams.show', $team))
            ->assertOk()
            ->assertJsonPath('permissions.canUpdate', false)
            ->assertJsonPath('permissions.canAddMember', false)
            ->assertJsonPath('permissions.canUpdateMember', false)
            ->assertJsonPath('permissions.canRemoveMember', false);

        // A technician holds every right in a team they do not belong to.
        $this->actingAs($this->technician())
            ->getJson(route('api.teams.show', $team))
            ->assertOk()
            ->assertJsonPath('permissions.canUpdate', true)
            ->assertJsonPath('permissions.canAddMember', true)
            ->assertJsonPath('permissions.canUpdateMember', true)
            ->assertJsonPath('permissions.canRemoveMember', true);
    }

    public function test_another_teams_details_are_forbidden(): void
    {
        $user = User::factory()->create();
        $somebodyElses = Team::factory()->create();

        $this->actingAs($user)
            ->getJson(route('api.teams.show', $somebodyElses))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('admin.teams.edit', $somebodyElses))
            ->assertForbidden();
    }

    public function test_holders_of_the_edit_all_permission_may_read_any_team(): void
    {
        $team = Team::factory()->create(['name' => 'Kellegi teise tiim']);

        $this->actingAs($this->technician())
            ->getJson(route('api.teams.show', $team))
            ->assertOk()
            ->assertJsonPath('data.name', 'Kellegi teise tiim');
    }

    public function test_a_new_team_is_entered_with_its_creator_as_owner(): void
    {
        $user = User::factory()->create();
        $home = $this->teamOf($user);
        $user->switchTeam($home);

        $this->actingAs($user)
            ->postJson(route('api.teams.store'), ['name' => 'Uus trupp'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Uus trupp')
            ->assertJsonPath('data.isPersonal', false)
            ->assertJsonPath('data.memberCount', 1);

        $team = Team::where('name', 'Uus trupp')->firstOrFail();

        $this->assertSame(TeamRole::Owner, $user->fresh()->teamRole($team));
        $this->assertSame('uus-trupp', $team->slug);

        // Entering a team elsewhere in the house does not move the user into it.
        $this->assertSame($home->id, $user->fresh()->current_team_id);
    }

    public function test_a_new_team_needs_a_name(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('api.teams.store'), ['name' => ''])
            ->assertJsonValidationErrors('name');
    }

    public function test_a_reserved_name_is_refused(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('api.teams.store'), ['name' => 'settings'])
            ->assertJsonValidationErrors('name');
    }

    public function test_an_owner_can_rename_their_team(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user, 'Vana nimi');

        $this->actingAs($user)
            ->patchJson(route('api.teams.update', $team), ['name' => 'Uus nimi'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Uus nimi')
            ->assertJsonPath('data.slug', 'uus-nimi');

        $this->assertSame('Uus nimi', $team->fresh()->name);
    }

    public function test_a_plain_member_cannot_rename_the_team(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user, 'Vana nimi', TeamRole::Member);

        $this->actingAs($user)
            ->patchJson(route('api.teams.update', $team), ['name' => 'Uus nimi'])
            ->assertForbidden();

        $this->assertSame('Vana nimi', $team->fresh()->name);
    }

    public function test_renaming_another_teams_team_is_forbidden(): void
    {
        $team = Team::factory()->create(['name' => 'Vana nimi']);

        $this->actingAs(User::factory()->create())
            ->patchJson(route('api.teams.update', $team), ['name' => 'Uus nimi'])
            ->assertForbidden();
    }

    public function test_a_technician_may_rename_any_team(): void
    {
        $team = Team::factory()->create(['name' => 'Vana nimi']);

        $this->actingAs($this->technician())
            ->patchJson(route('api.teams.update', $team), ['name' => 'Uus nimi'])
            ->assertOk();

        $this->assertSame('Uus nimi', $team->fresh()->name);
    }

    public function test_an_owner_can_delete_their_team(): void
    {
        $user = User::factory()->create();
        $personal = $user->personalTeam();
        $team = $this->teamOf($user);
        $user->switchTeam($team);

        $this->actingAs($user)
            ->deleteJson(route('api.teams.destroy', $team))
            ->assertNoContent();

        $this->assertSoftDeleted($team);
        $this->assertDatabaseMissing('team_members', ['team_id' => $team->id]);

        // Nobody is left standing in a team that is gone.
        $this->assertSame($personal->id, $user->fresh()->current_team_id);
    }

    public function test_deleting_a_team_leaves_its_shows_alone(): void
    {
        $team = Team::factory()->create();
        $show = Show::factory()->create(['team_id' => $team->id]);

        $this->actingAs($this->technician())
            ->deleteJson(route('api.teams.destroy', $team))
            ->assertNoContent();

        $this->assertNotSoftDeleted($show);
    }

    public function test_a_personal_team_cannot_be_deleted(): void
    {
        $team = Team::factory()->personal()->create();

        $this->actingAs($this->technician())
            ->deleteJson(route('api.teams.destroy', $team))
            ->assertForbidden();

        $this->assertNotSoftDeleted($team);
    }

    public function test_a_plain_member_cannot_delete_the_team(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user, role: TeamRole::Admin);

        $this->actingAs($user)
            ->deleteJson(route('api.teams.destroy', $team))
            ->assertForbidden();

        $this->assertNotSoftDeleted($team);
    }

    public function test_a_deleted_team_can_no_longer_be_reached(): void
    {
        $team = Team::factory()->trashed()->create();

        $this->actingAs($this->technician())
            ->getJson(route('api.teams.show', $team))
            ->assertNotFound();

        $this->actingAs($this->technician())
            ->get(route('admin.teams.edit', $team))
            ->assertNotFound();
    }

    public function test_an_existing_account_can_be_added_to_the_team(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);
        $newcomer = User::factory()->create(['name' => 'Uus Liige']);

        $this->actingAs($user)
            ->postJson(route('api.teams.members.store', $team), [
                'email' => $newcomer->email,
                'role' => TeamRole::Admin->value,
            ])
            ->assertCreated()
            ->assertJsonPath('data.id', $newcomer->id)
            ->assertJsonPath('data.name', 'Uus Liige')
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.isOwner', false);

        $this->assertSame(TeamRole::Admin, $newcomer->fresh()->teamRole($team));
    }

    public function test_an_unknown_address_is_refused(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);

        $this->actingAs($user)
            ->postJson(route('api.teams.members.store', $team), [
                'email' => 'keegi@näiteid.ee',
                'role' => TeamRole::Member->value,
            ])
            ->assertJsonValidationErrors('email');
    }

    public function test_somebody_already_in_the_team_is_not_added_twice(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);

        $this->actingAs($user)
            ->postJson(route('api.teams.members.store', $team), [
                'email' => $user->email,
                'role' => TeamRole::Member->value,
            ])
            ->assertJsonValidationErrors('email');

        $this->assertSame(1, $team->members()->count());
    }

    public function test_a_member_cannot_be_made_an_owner(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);
        $newcomer = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('api.teams.members.store', $team), [
                'email' => $newcomer->email,
                'role' => TeamRole::Owner->value,
            ])
            ->assertJsonValidationErrors('role');
    }

    public function test_adding_a_member_to_another_teams_team_is_forbidden(): void
    {
        $team = Team::factory()->create();
        $newcomer = User::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson(route('api.teams.members.store', $team), [
                'email' => $newcomer->email,
                'role' => TeamRole::Member->value,
            ])
            ->assertForbidden();
    }

    public function test_a_technician_may_add_a_member_to_any_team(): void
    {
        $team = Team::factory()->create();
        $newcomer = User::factory()->create();

        $this->actingAs($this->technician())
            ->postJson(route('api.teams.members.store', $team), [
                'email' => $newcomer->email,
                'role' => TeamRole::Member->value,
            ])
            ->assertCreated();

        $this->assertSame(TeamRole::Member, $newcomer->fresh()->teamRole($team));
    }

    public function test_a_members_role_can_be_changed(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);
        $member = User::factory()->create();
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $this->actingAs($user)
            ->patchJson(route('api.teams.members.update', [$team, $member]), [
                'role' => TeamRole::Admin->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.roleLabel', 'Admin');

        $this->assertSame(TeamRole::Admin, $member->fresh()->teamRole($team));
    }

    public function test_the_owners_role_cannot_be_changed(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);

        $this->actingAs($this->technician())
            ->patchJson(route('api.teams.members.update', [$team, $user]), [
                'role' => TeamRole::Member->value,
            ])
            ->assertForbidden();

        $this->assertSame(TeamRole::Owner, $user->fresh()->teamRole($team));
    }

    public function test_a_role_change_only_reaches_the_teams_own_members(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);
        $stranger = User::factory()->create();

        $this->actingAs($user)
            ->patchJson(route('api.teams.members.update', [$team, $stranger]), [
                'role' => TeamRole::Admin->value,
            ])
            ->assertNotFound();
    }

    public function test_a_member_can_be_removed_and_is_moved_home(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);

        $member = User::factory()->create();
        $personal = $member->personalTeam();
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->switchTeam($team);

        $this->actingAs($user)
            ->deleteJson(route('api.teams.members.destroy', [$team, $member]))
            ->assertNoContent();

        $this->assertDatabaseMissing('team_members', [
            'team_id' => $team->id,
            'user_id' => $member->id,
        ]);

        $this->assertSame($personal->id, $member->fresh()->current_team_id);
    }

    public function test_the_owner_cannot_be_removed(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);

        $this->actingAs($this->technician())
            ->deleteJson(route('api.teams.members.destroy', [$team, $user]))
            ->assertForbidden();

        $this->assertSame(TeamRole::Owner, $user->fresh()->teamRole($team));
    }

    public function test_removing_a_member_of_another_teams_team_is_forbidden(): void
    {
        $team = Team::factory()->create();
        $member = User::factory()->create();
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        $this->actingAs(User::factory()->create())
            ->deleteJson(route('api.teams.members.destroy', [$team, $member]))
            ->assertForbidden();

        $this->assertDatabaseHas('team_members', [
            'team_id' => $team->id,
            'user_id' => $member->id,
        ]);
    }

    /**
     * A user holding the technician role, which carries the edit-all permission.
     */
    private function technician(): User
    {
        return User::factory()->create()->assignRole('technician');
    }

    /**
     * Attach the user to a (new) team, owning it unless told otherwise.
     */
    private function teamOf(User $user, ?string $name = null, TeamRole $role = TeamRole::Owner): Team
    {
        $team = Team::factory()->create($name ? ['name' => $name] : []);

        $team->members()->attach($user, ['role' => $role->value]);

        return $team;
    }
}
