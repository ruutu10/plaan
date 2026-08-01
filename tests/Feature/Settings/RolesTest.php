<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_roles_page_is_displayed_for_a_user_without_extra_roles(): void
    {
        $user = User::factory()->create();
        $team = $user->currentTeam;

        $this->actingAs($user)
            ->get(route('roles.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Roles')
                ->where('roles', [])
                ->where('permissions', [])
                ->has('teams', 1)
                ->where('teams.0.id', $team->id)
                ->where('teams.0.role', 'owner')
            );
    }

    public function test_roles_page_shows_assigned_role_and_its_permissions(): void
    {
        $user = User::factory()->create()->assignRole('technician');

        $this->actingAs($user)
            ->get(route('roles.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Roles')
                ->where('roles', [__('roles.technician', locale: 'et')])
                ->where('permissions', [
                    __('permissions.technical_plans.view_all', locale: 'et'),
                    __('permissions.shows.edit_all', locale: 'et'),
                    __('permissions.performances.edit_all', locale: 'et'),
                    __('permissions.teams.edit_all', locale: 'et'),
                    __('permissions.claude.view_log', locale: 'et'),
                ])
            );
    }

    public function test_roles_page_translates_staff_role(): void
    {
        $user = User::factory()->create()->assignRole('staff');

        $this->actingAs($user)
            ->get(route('roles.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Roles')
                ->where('roles', [__('roles.staff', locale: 'et')])
                ->where('permissions', [
                    __('permissions.technical_plans.view_all', locale: 'et'),
                    __('permissions.claude.view_log', locale: 'et'),
                ])
            );
    }

    public function test_roles_page_falls_back_to_raw_slug_for_untranslated_role_or_permission(): void
    {
        Role::create(['name' => 'custom_role'])
            ->givePermissionTo(Permission::create(['name' => 'custom.permission']));

        $user = User::factory()->create()->assignRole('custom_role');

        $this->actingAs($user)
            ->get(route('roles.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/Roles')
                ->where('roles', ['custom_role'])
                ->where('permissions', ['custom.permission'])
            );
    }

    public function test_roles_page_requires_authentication(): void
    {
        $this->get(route('roles.show'))
            ->assertRedirect(route('login'));
    }
}
