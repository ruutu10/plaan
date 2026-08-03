<?php

namespace Tests\Feature\Users;

use App\Enums\SignupSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('admin.users.index'))
            ->assertRedirect(route('login'));
    }

    public function test_guests_are_refused_by_the_api(): void
    {
        $this->getJson(route('api.users.index'))
            ->assertUnauthorized();
    }

    public function test_a_user_without_the_permission_is_refused_everywhere(): void
    {
        $user = User::factory()->create();
        $subject = User::factory()->create();

        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.users.edit', $subject))->assertForbidden();
        $this->actingAs($user)->getJson(route('api.users.index'))->assertForbidden();
        $this->actingAs($user)->getJson(route('api.users.show', $subject))->assertForbidden();

        $this->actingAs($user)
            ->patchJson(route('api.users.update', $subject), [
                'name' => 'Uus nimi',
                'email' => 'uus@naide.ee',
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->postJson(route('api.users.roles.store', $subject), ['role' => 'staff'])
            ->assertForbidden();

        $this->actingAs($user)
            ->deleteJson(route('api.users.roles.destroy', [$subject, 'staff']))
            ->assertForbidden();

        // Not even on their own account: the screens are the technicians'.
        $this->actingAs($user)->getJson(route('api.users.show', $user))->assertForbidden();
    }

    public function test_the_technician_role_carries_the_permission(): void
    {
        $this->assertTrue($this->technician()->can(User::MANAGE_PERMISSION));
    }

    public function test_the_staff_role_does_not_carry_the_permission(): void
    {
        $staff = User::factory()->create()->assignRole('staff');

        $this->assertFalse($staff->can(User::MANAGE_PERMISSION));
    }

    public function test_the_pages_are_shells_carrying_no_account_data(): void
    {
        $technician = $this->technician();
        $subject = User::factory()->create();

        $this->actingAs($technician)
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/Index')
                ->missing('users'));

        $this->actingAs($technician)
            ->get(route('admin.users.edit', $subject))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/users/Edit')
                ->where('userId', $subject->id)
                ->missing('user')
                ->missing('roles'));
    }

    public function test_the_listing_holds_every_account_sorted_by_name(): void
    {
        $technician = $this->technician();
        $technician->update(['name' => 'Mari']);

        User::factory()->create(['name' => 'anton']);
        User::factory()->create(['name' => 'Öölane']);

        $this->actingAs($technician)
            ->getJson(route('api.users.index'))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            // Sorted case-insensitively, so a lowercase name is not banished
            // to the end of the list.
            ->assertJsonPath('data.0.name', 'anton')
            ->assertJsonPath('data.1.name', 'Mari')
            ->assertJsonPath('data.2.name', 'Öölane');
    }

    public function test_a_listed_account_carries_its_roles_teams_and_origin(): void
    {
        $technician = $this->technician();

        $subject = User::factory()->create([
            'name' => 'Kaarel',
            'signup_source' => SignupSource::AuthentikSso,
        ]);
        $subject->assignRole('staff');
        $this->teamOf($subject);

        $response = $this->actingAs($technician)
            ->getJson(route('api.users.index'))
            ->assertOk();

        $row = collect($response->json('data'))->firstWhere('id', $subject->id);

        $this->assertSame('Kaarel', $row['name']);
        $this->assertSame($subject->email, $row['email']);
        $this->assertTrue($row['emailVerified']);
        $this->assertSame('authentik-sso', $row['signupSource']);
        $this->assertSame('Ruutu10 konto', $row['signupSourceLabel']);
        // The factory's own team, plus the one attached above.
        $this->assertSame(2, $row['teamCount']);
        $this->assertSame([['name' => 'staff', 'label' => 'Ruutu10 tiim']], $row['roles']);
    }

    public function test_the_listing_never_carries_a_secret(): void
    {
        $technician = $this->technician();

        User::factory()->withTwoFactor()->create();

        $response = $this->actingAs($technician)
            ->getJson(route('api.users.index'))
            ->assertOk();

        foreach ($response->json('data') as $row) {
            $this->assertArrayNotHasKey('password', $row);
            $this->assertArrayNotHasKey('two_factor_secret', $row);
            $this->assertArrayNotHasKey('remember_token', $row);
        }
    }

    public function test_an_account_is_read_with_every_grantable_role_and_what_the_reader_may_write(): void
    {
        $subject = User::factory()->unverified()->create(['name' => 'Kaarel']);
        $subject->assignRole('staff');

        $this->actingAs($this->technician())
            ->getJson(route('api.users.show', $subject))
            ->assertOk()
            ->assertJsonPath('data.name', 'Kaarel')
            ->assertJsonPath('data.emailVerified', false)
            ->assertJsonPath('data.roles.0.name', 'staff')
            // Every role in the house, alphabetically, held or not.
            ->assertJsonPath('roles.0.name', 'staff')
            ->assertJsonPath('roles.0.label', 'Ruutu10 tiim')
            ->assertJsonPath('roles.1.name', 'technician')
            ->assertJsonPath('roles.1.label', 'Tehnik')
            ->assertJsonPath('permissions.canUpdateRoles', true);
    }

    public function test_a_technician_reads_their_own_account_but_may_not_touch_its_roles(): void
    {
        $technician = $this->technician();

        $this->actingAs($technician)
            ->getJson(route('api.users.show', $technician))
            ->assertOk()
            // Dropping the role that opens these screens would shut the door
            // behind them, so their own roles are somebody else's to write.
            ->assertJsonPath('permissions.canUpdateRoles', false);
    }

    public function test_a_technician_can_correct_a_name_and_address(): void
    {
        $subject = User::factory()->create([
            'name' => 'Vana nimi',
            'email' => 'vana@naide.ee',
        ]);

        $this->actingAs($this->technician())
            ->patchJson(route('api.users.update', $subject), [
                'name' => 'Uus nimi',
                'email' => 'uus@naide.ee',
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Uus nimi')
            ->assertJsonPath('data.email', 'uus@naide.ee');

        $subject->refresh();

        $this->assertSame('Uus nimi', $subject->name);
        $this->assertSame('uus@naide.ee', $subject->email);
    }

    public function test_a_changed_address_has_to_be_proven_again(): void
    {
        $subject = User::factory()->create(['email' => 'vana@naide.ee']);

        $this->assertNotNull($subject->email_verified_at);

        $this->actingAs($this->technician())
            ->patchJson(route('api.users.update', $subject), [
                'name' => $subject->name,
                'email' => 'uus@naide.ee',
            ])
            ->assertOk()
            ->assertJsonPath('data.emailVerified', false);

        $this->assertNull($subject->fresh()->email_verified_at);
    }

    public function test_correcting_only_the_name_leaves_the_address_proven(): void
    {
        $subject = User::factory()->create(['name' => 'Vana nimi']);

        $this->actingAs($this->technician())
            ->patchJson(route('api.users.update', $subject), [
                'name' => 'Uus nimi',
                'email' => $subject->email,
            ])
            ->assertOk()
            ->assertJsonPath('data.emailVerified', true);

        $this->assertNotNull($subject->fresh()->email_verified_at);
    }

    public function test_an_account_needs_a_name_and_an_unclaimed_address(): void
    {
        $subject = User::factory()->create();
        $somebodyElse = User::factory()->create(['email' => 'juba@naide.ee']);

        $this->actingAs($this->technician())
            ->patchJson(route('api.users.update', $subject), ['name' => '', 'email' => 'not-an-address'])
            ->assertJsonValidationErrors(['name', 'email']);

        $this->actingAs($this->technician())
            ->patchJson(route('api.users.update', $subject), [
                'name' => 'Nimi',
                'email' => $somebodyElse->email,
            ])
            ->assertJsonValidationErrors('email');
    }

    public function test_an_account_keeping_its_own_address_is_not_refused_as_a_duplicate(): void
    {
        $subject = User::factory()->create();

        $this->actingAs($this->technician())
            ->patchJson(route('api.users.update', $subject), [
                'name' => 'Uus nimi',
                'email' => $subject->email,
            ])
            ->assertOk();
    }

    public function test_a_technician_can_grant_a_role(): void
    {
        $subject = User::factory()->create();

        $this->actingAs($this->technician())
            ->postJson(route('api.users.roles.store', $subject), ['role' => 'staff'])
            ->assertOk()
            ->assertJsonPath('data.id', $subject->id)
            ->assertJsonPath('data.roles.0.name', 'staff');

        $this->assertTrue($subject->fresh()->hasRole('staff'));
    }

    public function test_granting_a_role_twice_changes_nothing(): void
    {
        $subject = User::factory()->create();
        $subject->assignRole('staff');

        $this->actingAs($this->technician())
            ->postJson(route('api.users.roles.store', $subject), ['role' => 'staff'])
            ->assertOk()
            ->assertJsonCount(1, 'data.roles');

        $this->assertSame(1, $subject->fresh()->roles()->count());
    }

    public function test_a_role_nobody_created_is_refused(): void
    {
        $subject = User::factory()->create();

        $this->actingAs($this->technician())
            ->postJson(route('api.users.roles.store', $subject), ['role' => 'ülemtehnik'])
            ->assertJsonValidationErrors('role');

        $this->assertSame(0, $subject->fresh()->roles()->count());
    }

    public function test_a_technician_can_take_a_role_away(): void
    {
        $subject = User::factory()->create();
        $subject->assignRole('staff');

        $this->actingAs($this->technician())
            ->deleteJson(route('api.users.roles.destroy', [$subject, 'staff']))
            ->assertOk()
            ->assertJsonCount(0, 'data.roles');

        $this->assertFalse($subject->fresh()->hasRole('staff'));
    }

    public function test_taking_away_a_role_nobody_holds_changes_nothing(): void
    {
        $subject = User::factory()->create();

        $this->actingAs($this->technician())
            ->deleteJson(route('api.users.roles.destroy', [$subject, 'staff']))
            ->assertOk()
            ->assertJsonCount(0, 'data.roles');
    }

    public function test_granting_the_technician_role_hands_over_the_permission(): void
    {
        $subject = User::factory()->create();

        $this->actingAs($this->technician())
            ->postJson(route('api.users.roles.store', $subject), ['role' => 'technician'])
            ->assertOk();

        $this->assertTrue($subject->fresh()->can(User::MANAGE_PERMISSION));
    }

    public function test_nobody_writes_their_own_roles(): void
    {
        $technician = $this->technician();

        $this->actingAs($technician)
            ->deleteJson(route('api.users.roles.destroy', [$technician, 'technician']))
            ->assertForbidden();

        $this->actingAs($technician)
            ->postJson(route('api.users.roles.store', $technician), ['role' => 'staff'])
            ->assertForbidden();

        // The door is still open afterwards.
        $this->assertTrue($technician->fresh()->hasRole('technician'));
        $this->assertFalse($technician->fresh()->hasRole('staff'));
    }

    public function test_another_technician_can_take_the_role_away(): void
    {
        $subject = $this->technician();

        $this->actingAs($this->technician())
            ->deleteJson(route('api.users.roles.destroy', [$subject, 'technician']))
            ->assertOk();

        $this->assertFalse($subject->fresh()->can(User::MANAGE_PERMISSION));
    }

    public function test_a_role_that_does_not_exist_is_a_missing_page(): void
    {
        $subject = User::factory()->create();

        $this->actingAs($this->technician())
            ->deleteJson(route('api.users.roles.destroy', [$subject, 'ülemtehnik']))
            ->assertNotFound();
    }

    public function test_handing_out_rights_is_written_down(): void
    {
        Log::spy();

        $technician = $this->technician();
        $subject = User::factory()->create();

        $this->actingAs($technician)
            ->postJson(route('api.users.roles.store', $subject), ['role' => 'technician'])
            ->assertOk();

        Log::shouldHaveReceived('notice')
            ->withArgs(fn (string $message, array $context) => $message === 'Role granted from the account management screen'
                && $context['user_id'] === $subject->id
                && $context['role'] === 'technician'
                && $context['granted_by'] === $technician->id)
            ->once();
    }
}
