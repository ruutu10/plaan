<?php

namespace Tests\Feature\Users;

use App\Enums\SignupSource;
use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ImportUsersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Nothing this command does may reach anybody's inbox.
        Notification::fake();
    }

    /**
     * Write a CSV the command can be pointed at.
     */
    private function csv(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'user-import').'.csv';

        file_put_contents($path, $contents);

        return $path;
    }

    public function test_it_creates_an_account_for_every_row(): void
    {
        $path = $this->csv("Mari Maasikas,mari@example.com\nJaan Tamm,jaan@example.com\n");

        $this->artisan('user:import', ['path' => $path])->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'name' => 'Mari Maasikas',
            'email' => 'mari@example.com',
            'signup_source' => SignupSource::CsvImport->value,
        ]);

        $this->assertDatabaseHas('users', [
            'name' => 'Jaan Tamm',
            'email' => 'jaan@example.com',
        ]);

        $this->assertSame(2, User::count());
    }

    public function test_imported_accounts_are_verified_and_get_an_unknown_password(): void
    {
        $path = $this->csv("Mari Maasikas,mari@example.com\n");

        $this->artisan('user:import', ['path' => $path])->assertSuccessful();

        $user = User::firstWhere('email', 'mari@example.com');

        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertNotEmpty($user->password);
        $this->assertFalse(Hash::check('', $user->password));
        $this->assertFalse(Hash::check($user->email, $user->password));
    }

    public function test_it_notifies_nobody(): void
    {
        $path = $this->csv("Mari Maasikas,mari@example.com\n");

        $this->artisan('user:import', ['path' => $path])->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_it_seats_imported_accounts_in_the_named_team(): void
    {
        $team = Team::factory()->create(['name' => 'Improteater']);

        $path = $this->csv("Mari Maasikas,mari@example.com\n");

        $this->artisan('user:import', ['path' => $path, '--team' => $team->slug])
            ->assertSuccessful();

        $user = User::firstWhere('email', 'mari@example.com');

        $this->assertTrue($user->belongsToTeam($team));
        $this->assertSame(TeamRole::Member, $user->teamRole($team));

        // An imported account has no team of its own, so the one it was put in
        // is where it lands.
        $this->assertSame($team->id, $user->current_team_id);
    }

    public function test_accounts_join_no_team_when_none_is_named(): void
    {
        Team::factory()->create();

        $path = $this->csv("Mari Maasikas,mari@example.com\n");

        $this->artisan('user:import', ['path' => $path])->assertSuccessful();

        $user = User::firstWhere('email', 'mari@example.com');

        $this->assertCount(0, $user->teams);
        $this->assertNull($user->current_team_id);
    }

    public function test_an_unknown_team_slug_stops_the_run_before_anything_is_created(): void
    {
        $path = $this->csv("Mari Maasikas,mari@example.com\n");

        $this->artisan('user:import', ['path' => $path, '--team' => 'no-such-team'])
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_an_address_that_already_has_an_account_is_passed_over(): void
    {
        $existing = User::factory()->create([
            'name' => 'Mari M.',
            'email' => 'mari@example.com',
        ]);

        $team = Team::factory()->create();

        $path = $this->csv("Mari Maasikas,mari@example.com\nJaan Tamm,jaan@example.com\n");

        $this->artisan('user:import', ['path' => $path, '--team' => $team->slug])
            ->assertSuccessful();

        $existing->refresh();

        // Untouched: not renamed, and not put into the team either.
        $this->assertSame('Mari M.', $existing->name);
        $this->assertFalse($existing->belongsToTeam($team));

        $this->assertDatabaseHas('users', ['email' => 'jaan@example.com']);
        $this->assertSame(1, User::where('email', 'mari@example.com')->count());
    }

    public function test_an_address_is_matched_without_regard_to_case(): void
    {
        User::factory()->create(['email' => 'mari@example.com']);

        $path = $this->csv("Mari Maasikas,MARI@Example.com\n");

        $this->artisan('user:import', ['path' => $path])->assertSuccessful();

        $this->assertSame(1, User::where('email', 'mari@example.com')->count());
    }

    public function test_the_same_address_twice_in_one_file_makes_one_account(): void
    {
        $path = $this->csv("Mari Maasikas,mari@example.com\nMari Maasikas,mari@example.com\n");

        $this->artisan('user:import', ['path' => $path])->assertSuccessful();

        $this->assertSame(1, User::where('email', 'mari@example.com')->count());
    }

    public function test_an_unreadable_row_costs_only_that_row(): void
    {
        $path = $this->csv(implode("\n", [
            'Mari Maasikas,mari@example.com',
            'Jaan Tamm,not-an-address',
            ',orphan@example.com',
            'Nimeta',
            'Kati Kask,kati@example.com',
            '',
        ]));

        $this->artisan('user:import', ['path' => $path])->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'mari@example.com']);
        $this->assertDatabaseHas('users', ['email' => 'kati@example.com']);
        $this->assertDatabaseMissing('users', ['email' => 'orphan@example.com']);
        $this->assertSame(2, User::count());
    }

    public function test_it_grants_the_named_role_to_imported_accounts(): void
    {
        $path = $this->csv("Mari Maasikas,mari@example.com\nJaan Tamm,jaan@example.com\n");

        $this->artisan('user:import', ['path' => $path, '--role' => 'staff'])
            ->assertSuccessful();

        $this->assertTrue(User::firstWhere('email', 'mari@example.com')->hasRole('staff'));
        $this->assertTrue(User::firstWhere('email', 'jaan@example.com')->hasRole('staff'));
    }

    public function test_it_grants_the_named_role_to_an_account_that_already_exists(): void
    {
        $existing = User::factory()->create(['email' => 'mari@example.com']);

        $this->assertFalse($existing->hasRole('staff'));

        $path = $this->csv("Mari Maasikas,mari@example.com\n");

        $this->artisan('user:import', ['path' => $path, '--role' => 'staff'])
            ->assertSuccessful();

        $this->assertTrue($existing->fresh()->hasRole('staff'));

        // Still passed over as an import: no second account, and nothing else
        // about the one it has was rewritten.
        $this->assertSame(1, User::where('email', 'mari@example.com')->count());
        $this->assertSame($existing->name, $existing->fresh()->name);
    }

    public function test_granting_a_role_an_account_already_holds_changes_nothing(): void
    {
        $existing = User::factory()->create(['email' => 'mari@example.com']);
        $existing->assignRole('staff');

        $path = $this->csv("Mari Maasikas,mari@example.com\n");

        $this->artisan('user:import', ['path' => $path, '--role' => 'staff'])
            ->assertSuccessful();

        $this->assertTrue($existing->fresh()->hasRole('staff'));
        $this->assertCount(1, $existing->fresh()->roles);
    }

    public function test_no_role_is_granted_when_none_is_named(): void
    {
        $existing = User::factory()->create(['email' => 'mari@example.com']);

        $path = $this->csv("Mari Maasikas,mari@example.com\nJaan Tamm,jaan@example.com\n");

        $this->artisan('user:import', ['path' => $path])->assertSuccessful();

        $this->assertCount(0, $existing->fresh()->roles);
        $this->assertCount(0, User::firstWhere('email', 'jaan@example.com')->roles);
    }

    public function test_an_unknown_role_stops_the_run_before_anything_is_created(): void
    {
        $path = $this->csv("Mari Maasikas,mari@example.com\n");

        $this->artisan('user:import', ['path' => $path, '--role' => 'no-such-role'])
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_the_role_is_granted_alongside_the_team(): void
    {
        $team = Team::factory()->create();

        $path = $this->csv("Mari Maasikas,mari@example.com\n");

        $this->artisan('user:import', [
            'path' => $path,
            '--team' => $team->slug,
            '--role' => 'technician',
        ])->assertSuccessful();

        $user = User::firstWhere('email', 'mari@example.com');

        $this->assertTrue($user->hasRole('technician'));
        $this->assertSame(TeamRole::Member, $user->teamRole($team));
    }

    public function test_a_header_row_is_not_taken_for_an_account(): void
    {
        $path = $this->csv("name,email\nMari Maasikas,mari@example.com\n");

        $this->artisan('user:import', ['path' => $path])->assertSuccessful();

        $this->assertSame(1, User::count());
        $this->assertDatabaseHas('users', ['email' => 'mari@example.com']);
    }

    public function test_a_missing_file_fails_without_creating_anything(): void
    {
        $this->artisan('user:import', ['path' => '/no/such/file.csv'])->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
