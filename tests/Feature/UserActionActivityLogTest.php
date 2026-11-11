<?php

namespace Tests\Feature;

use App\Actions\GrantStaffAccess;
use App\Data\ImportedStaffMember;
use App\Enums\PerformanceStaffRole;
use App\Enums\TeamRole;
use App\Events\PlankaReimportRequested;
use App\Models\Membership;
use App\Models\Performance;
use App\Models\PerformanceStaff;
use App\Models\Team;
use App\Models\TechnicalPlan;
use App\Models\TechnicalPlanComment;
use App\Models\User;
use App\Services\PerformanceStaffSync;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Passkeys\Events\PasskeyDeleted;
use Laravel\Passkeys\Events\PasskeyRegistered;
use Laravel\Passkeys\Passkey;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * The audit-trail entries written for what people do by hand that no model
 * event sees on its own: roles, staff, comments, account security, and the
 * Planka and copy buttons.
 */
class UserActionActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_granting_a_role_is_logged_once(): void
    {
        $technician = $this->technician();
        $subject = User::factory()->create();

        $this->actingAs($technician)->postJson(route('api.users.roles.store', $subject), ['role' => 'staff'])->assertOk();
        $this->actingAs($technician)->postJson(route('api.users.roles.store', $subject), ['role' => 'staff'])->assertOk();

        $activity = Activity::query()->forSubject($subject)->forEvent('role_granted')->sole();

        $this->assertTrue($technician->is($activity->causer));
        $this->assertSame('staff', $activity->getProperty('role'));
    }

    public function test_taking_a_role_away_is_logged_only_when_it_was_held(): void
    {
        $technician = $this->technician();
        $subject = User::factory()->create();
        $subject->assignRole('staff');

        $this->actingAs($technician)->deleteJson(route('api.users.roles.destroy', [$subject, 'staff']))->assertOk();
        $this->actingAs($technician)->deleteJson(route('api.users.roles.destroy', [$subject, 'staff']))->assertOk();

        $activity = Activity::query()->forSubject($subject)->forEvent('role_revoked')->sole();

        $this->assertTrue($technician->is($activity->causer));
        $this->assertSame('staff', $activity->getProperty('role'));
    }

    public function test_the_automatic_staff_grant_is_logged_once_as_the_systems(): void
    {
        $user = User::factory()->ofTheHouse()->create();
        $this->actingAs($user);

        app(GrantStaffAccess::class)->handle($user);
        app(GrantStaffAccess::class)->handle($user);

        $activity = Activity::query()->forSubject($user)->forEvent('role_granted')->sole();

        $this->assertNull($activity->causer);
        $this->assertSame(GrantStaffAccess::ROLE, $activity->getProperty('role'));
        $this->assertTrue($activity->getProperty('automatic'));
    }

    public function test_removing_a_member_from_team_settings_is_logged(): void
    {
        [$owner, $member, $team] = $this->teamWithMember();

        $this->actingAs($owner)->delete(route('teams.members.destroy', [$team, $member]))->assertRedirect();

        $this->assertMembershipDeletionLogged($owner, $member, $team);
    }

    public function test_leaving_a_team_is_logged(): void
    {
        [, $member, $team] = $this->teamWithMember();

        $this->actingAs($member)->delete(route('teams.leave', $team))->assertRedirect();

        $this->assertMembershipDeletionLogged($member, $member, $team);
    }

    public function test_adding_and_removing_staff_by_hand_is_logged_on_the_performance(): void
    {
        $technician = $this->technician();
        $performance = Performance::factory()->create();
        $guest = User::factory()->create(['name' => 'Külaline']);

        $this->actingAs($technician)
            ->postJson(route('api.formats.performances.staff.store', [$performance->format, $performance]), [
                'user_id' => $guest->id,
                'role' => PerformanceStaffRole::Performer->value,
            ])
            ->assertCreated();

        $staffing = $performance->staffings()->sole();

        $this->actingAs($technician)
            ->deleteJson(route('api.formats.performances.staff.destroy', [$performance->format, $performance, $staffing]))
            ->assertNoContent();

        foreach (['staff_added', 'staff_removed'] as $event) {
            $activity = Activity::query()->forSubject($performance)->forEvent($event)->sole();

            $this->assertTrue($technician->is($activity->causer));
            $this->assertSame($guest->id, $activity->getProperty('user_id'));
            $this->assertSame('performer', $activity->getProperty('role'));
            $this->assertStringContainsString('Külaline', $activity->description);
        }
    }

    public function test_a_planka_staff_sync_logs_who_it_added_and_took_off(): void
    {
        $performance = Performance::factory()->create();
        $kept = User::factory()->create(['name' => 'Kati Jääb']);
        $dropped = User::factory()->create(['name' => 'Peeter Läheb']);
        $arriving = User::factory()->create(['name' => 'Anu Tuleb']);

        $performance->staff()->attach($kept, ['role' => PerformanceStaffRole::Performer->value]);
        $performance->staff()->attach($dropped, ['role' => PerformanceStaffRole::Host->value]);

        app(PerformanceStaffSync::class)->sync($performance, [
            new ImportedStaffMember('Kati Jääb', PerformanceStaffRole::Performer),
            new ImportedStaffMember('Anu Tuleb', PerformanceStaffRole::Technician),
        ]);

        $activity = Activity::query()->forSubject($performance)->forEvent('staff_synced')->sole();

        $this->assertNull($activity->causer);
        $this->assertSame([['user_id' => $arriving->id, 'role' => 'technician']], $activity->getProperty('added'));
        $this->assertSame([['user_id' => $dropped->id, 'role' => 'host']], $activity->getProperty('removed'));
        $this->assertStringContainsString('Anu Tuleb', $activity->description);
        $this->assertStringContainsString('Peeter Läheb', $activity->description);
        $this->assertStringNotContainsString('Kati Jääb', $activity->description);
    }

    public function test_a_planka_staff_sync_that_changes_nothing_logs_nothing(): void
    {
        $performance = Performance::factory()->create();
        $performer = User::factory()->create(['name' => 'Kati Jääb']);
        $performance->staff()->attach($performer, ['role' => PerformanceStaffRole::Performer->value]);

        app(PerformanceStaffSync::class)->sync($performance, [
            new ImportedStaffMember('Kati Jääb', PerformanceStaffRole::Performer),
        ]);

        $this->assertFalse(Activity::query()->forEvent('staff_synced')->exists());
        $this->assertSame(1, PerformanceStaff::query()->where('performance_id', $performance->id)->count());
    }

    public function test_commenting_on_a_plan_is_logged(): void
    {
        $author = User::factory()->create();
        $plan = TechnicalPlan::factory()->submitted()->create(['user_id' => $author->id]);

        $this->actingAs($author)
            ->postJson(route('technical-plan.comments.store', $plan), ['body' => 'Kas mikrofone on neli?'])
            ->assertCreated();

        $activity = Activity::query()->forSubject($plan)->forEvent('commented')->sole();

        $this->assertTrue($author->is($activity->causer));
        $this->assertSame(TechnicalPlanComment::query()->sole()->id, $activity->getProperty('comment_id'));
    }

    public function test_deleting_a_comment_is_logged_with_what_it_said(): void
    {
        $author = User::factory()->create(['name' => 'Mari Esineja']);
        $plan = TechnicalPlan::factory()->submitted()->create(['user_id' => $author->id]);
        $comment = TechnicalPlanComment::factory()->create([
            'technical_plan_id' => $plan->id,
            'user_id' => $author->id,
            'body' => 'Tegelikult kolm.',
        ]);
        $technician = $this->technician();

        $this->actingAs($technician)
            ->deleteJson(route('technical-plan.comments.destroy', [$plan, $comment]))
            ->assertNoContent();

        $activity = Activity::query()->forSubject($plan)->forEvent('comment_deleted')->sole();

        $this->assertTrue($technician->is($activity->causer));
        $this->assertSame('Tegelikult kolm.', $activity->getProperty('body'));
        $this->assertSame($author->id, $activity->getProperty('author_id'));
        $this->assertStringContainsString('Mari Esineja', $activity->description);
    }

    public function test_asking_for_a_planka_reimport_is_logged_with_who_asked(): void
    {
        $technician = $this->technician();
        $performance = Performance::factory()->create(['planka_card_id' => 'card-1']);

        // Only the asking: the run itself is another listener's, on a queue.
        PlankaReimportRequested::dispatch($performance, 'card-1', $technician);

        $activity = Activity::query()->forSubject($performance)->forEvent('planka_reimport_requested')->sole();

        $this->assertTrue($technician->is($activity->causer));
        $this->assertSame('card-1', $activity->getProperty('card_id'));
    }

    public function test_copying_a_plan_is_logged_on_the_plan_copied_from(): void
    {
        $author = User::factory()->create();
        $plan = TechnicalPlan::factory()->submitted()->create(['user_id' => $author->id]);

        $this->actingAs($author)->postJson(route('technical-plan.copy', $plan))->assertOk();

        $activity = Activity::query()->forSubject($plan)->forEvent('copied')->sole();

        $this->assertTrue($author->is($activity->causer));
    }

    public function test_changing_ones_password_is_logged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123',
            ])
            ->assertSessionHasNoErrors();

        $activity = Activity::query()->forSubject($user)->forEvent('password_changed')->sole();

        $this->assertTrue($user->is($activity->causer));
    }

    public function test_turning_on_two_factor_authentication_is_logged(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('two-factor.enable'))
            ->assertSessionHasNoErrors();

        $this->assertTrue(Activity::query()->forSubject($user)->forEvent('two_factor_enabled')->exists());
    }

    public function test_every_account_security_change_is_logged_against_the_account(): void
    {
        $user = User::factory()->create();
        $passkey = (new Passkey)->forceFill(['id' => 7]);

        event(new PasswordReset($user));
        event(new TwoFactorAuthenticationConfirmed($user));
        event(new TwoFactorAuthenticationDisabled($user));
        event(new RecoveryCodesGenerated($user));
        event(new PasskeyRegistered($user, $passkey));
        event(new PasskeyDeleted($user, $passkey));

        foreach ([
            'password_reset',
            'two_factor_confirmed',
            'two_factor_disabled',
            'recovery_codes_regenerated',
            'passkey_added',
            'passkey_removed',
        ] as $event) {
            $activity = Activity::query()->forSubject($user)->forEvent($event)->sole();

            $this->assertTrue($user->is($activity->causer), "{$event} is not caused by the account holder.");
        }

        $this->assertSame(7, Activity::query()->forEvent('passkey_added')->sole()->getProperty('passkey_id'));
        $this->assertFalse(Activity::query()->forEvent('two_factor_enabled')->exists());
    }

    /**
     * @return array{0: User, 1: User, 2: Team}
     */
    private function teamWithMember(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = Team::factory()->create();

        $team->members()->attach($owner, ['role' => TeamRole::Owner->value]);
        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        return [$owner, $member, $team];
    }

    private function assertMembershipDeletionLogged(User $causer, User $member, Team $team): void
    {
        $activity = Activity::query()
            ->where('subject_type', (new Membership)->getMorphClass())
            ->forEvent('deleted')
            ->sole();

        $this->assertTrue($causer->is($activity->causer));
        $this->assertSame($member->id, $activity->attribute_changes->get('old')['user_id']);
        $this->assertSame($team->id, $activity->attribute_changes->get('old')['team_id']);
    }
}
