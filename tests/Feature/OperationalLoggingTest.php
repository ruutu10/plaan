<?php

namespace Tests\Feature;

use App\Actions\Teams\DeleteTeam;
use App\Enums\TeamRole;
use App\Models\PendingUpload;
use App\Models\Performance;
use App\Models\Show;
use App\Models\Team;
use App\Models\TechnicalPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The application's activity has to be readable from the log alone — who signed
 * in, what was written, what was refused and what a scheduled job did. These
 * tests hold the entries that carry that, so an edit that quietly drops one
 * fails here rather than the next time somebody goes looking in production.
 *
 * Each case asserts the level as well as the payload: the level is what a log
 * search filters on, so demoting a refusal to `debug` is a real regression.
 */
class OperationalLoggingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Assert one entry was written at the given level, with a message and a
     * context matching the given predicate.
     *
     * @param  callable(array<string, mixed>): bool  $matches
     */
    private function assertLogged(string $level, string $message, callable $matches): void
    {
        Log::shouldHaveReceived($level)
            ->withArgs(fn (string $loggedMessage, array $context): bool => $loggedMessage === $message
                && $matches($context))
            ->once();
    }

    private function teamOf(User $user, TeamRole $role = TeamRole::Owner): Team
    {
        $team = Team::factory()->create();

        $team->members()->attach($user, ['role' => $role->value]);

        return $team;
    }

    public function test_a_sign_in_is_logged_with_its_method_and_origin(): void
    {
        $user = User::factory()->create();
        $this->teamOf($user);

        Log::spy();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertLogged('info', 'User signed in', fn (array $context): bool => $context['user_id'] === $user->id
            && $context['method'] === 'password'
            && array_key_exists('ip', $context));
    }

    public function test_a_registration_is_logged(): void
    {
        Log::spy();

        $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::where('email', 'test@example.com')->firstOrFail();

        $this->assertLogged('info', 'User registered', fn (array $context): bool => $context['user_id'] === $user->id
            && $context['signup_source'] === 'signup-form');
    }

    public function test_a_password_change_is_logged_at_notice(): void
    {
        $user = User::factory()->create();
        $this->teamOf($user);

        Log::spy();

        $this->actingAs($user)->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $this->assertLogged(
            'notice',
            'Password changed from the settings screen',
            fn (array $context): bool => $context['user_id'] === $user->id,
        );
    }

    public function test_a_role_change_records_both_ends_of_the_change(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $this->teamOf($owner);

        $team->members()->attach($member, ['role' => TeamRole::Member->value]);

        Log::spy();

        $this->actingAs($owner)->patch(route('teams.members.update', [$team, $member]), [
            'role' => TeamRole::Admin->value,
        ]);

        $this->assertLogged('notice', 'Team member role changed', fn (array $context): bool => $context['team_id'] === $team->id
            && $context['member_id'] === $member->id
            && $context['from_role'] === TeamRole::Member->value
            && $context['to_role'] === TeamRole::Admin->value
            && $context['changed_by'] === $owner->id);
    }

    public function test_a_refused_team_route_is_logged_as_a_warning(): void
    {
        $outsider = User::factory()->create();
        $this->teamOf($outsider);

        $team = Team::factory()->create();

        Log::spy();

        $this->actingAs($outsider)
            ->get(route('teams.edit', $team))
            ->assertForbidden();

        $this->assertLogged('warning', 'Refused access to a team route', fn (array $context): bool => $context['user_id'] === $outsider->id
            && $context['team_id'] === $team->id
            && $context['reason'] === 'not_a_member');
    }

    public function test_deleting_a_show_records_how_many_performances_went_with_it(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);
        $show = Show::factory()->create(['team_id' => $team->id]);

        Performance::factory()->count(3)->create(['show_id' => $show->id]);

        Log::spy();

        $this->actingAs($user)
            ->deleteJson(route('api.shows.destroy', $show))
            ->assertNoContent();

        $this->assertLogged('notice', 'Show deleted', fn (array $context): bool => $context['show_id'] === $show->id
            && $context['team_id'] === $team->id
            && $context['user_id'] === $user->id
            && $context['performances_deleted'] === 3);
    }

    public function test_deleting_a_team_records_what_it_took_with_it(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $team = $this->teamOf($owner);

        $team->members()->attach($member, ['role' => TeamRole::Member->value]);
        $member->forceFill(['current_team_id' => $team->id])->save();

        Log::spy();

        app(DeleteTeam::class)->handle($team);

        $this->assertLogged('notice', 'Team deleted', fn (array $context): bool => $context['team_id'] === $team->id
            && $context['memberships_cleared'] === 2
            && $context['members_moved'] === 1);
    }

    public function test_a_member_left_without_a_team_is_warned_about(): void
    {
        $member = User::factory()->create();

        // The factory hands every user a personal team to fall back on; this
        // member has to have nowhere else to go for the warning to be reached.
        $member->teamMemberships()->delete();

        $team = $this->teamOf($member);

        $member->forceFill(['current_team_id' => $team->id])->save();

        Log::spy();

        app(DeleteTeam::class)->handle($team);

        // Their only team was the one just deleted, so there is nowhere to send
        // them and every screen they load is now team-less.
        $this->assertLogged(
            'warning',
            'Member left without a current team after their team was deleted',
            fn (array $context): bool => $context['user_id'] === $member->id
                && $context['from_team_id'] === $team->id,
        );
    }

    public function test_submitting_a_plan_is_logged_with_what_was_sent(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $this->teamOf($user);

        Log::spy();

        $this->actingAs($user)
            ->postJson(route('technical-plan.store'), $this->planPayload())
            ->assertOk();

        $plan = TechnicalPlan::query()->firstOrFail();

        $this->assertLogged('info', 'Technical plan submitted', fn (array $context): bool => $context['plan_id'] === $plan->id
            && $context['user_id'] === $user->id
            && $context['status'] === 'submitted'
            && $context['created'] === true
            && $context['scenes'] === 1);
    }

    public function test_a_rejected_upload_is_logged_as_a_warning(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $this->teamOf($user);

        Log::spy();

        $this->actingAs($user)
            ->postJson(route('attachments.store'), [
                'file' => UploadedFile::fake()->create('kood.exe', 10, 'application/x-msdownload'),
            ])
            ->assertStatus(422);

        $this->assertLogged('warning', 'Upload rejected', fn (array $context): bool => $context['file_name'] === 'kood.exe'
            && $context['user_id'] === $user->id
            && $context['errors'] !== []);
    }

    public function test_an_attachment_handle_that_resolves_to_nothing_is_logged(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $this->teamOf($user);

        Log::spy();

        // A handle the client believes in but the server has no media for: the
        // file is silently dropped, so the log is the only sign of it.
        $this->actingAs($user)
            ->postJson(route('technical-plan.store'), $this->planPayload([
                'extra' => ['files' => [['id' => 'a-handle-that-never-existed', 'name' => 'kava.pdf', 'size' => 10]]],
            ]))
            ->assertOk();

        $this->assertLogged(
            'warning',
            'Submitted attachment handles resolved to nothing',
            fn (array $context): bool => $context['handles'] === ['a-handle-that-never-existed']
                && $context['model_type'] === TechnicalPlan::class,
        );
    }

    public function test_the_prune_command_reports_what_it_removed(): void
    {
        Storage::fake('local');

        $upload = PendingUpload::create();
        $upload->addMedia(UploadedFile::fake()->create('plaan.pdf', 20, 'application/pdf'))
            ->toMediaCollection($upload->attachmentsCollection());
        $upload->forceFill(['created_at' => now()->subHours(96)])->save();

        Log::spy();

        $this->artisan('attachments:prune-stale')->assertSuccessful();

        $this->assertLogged('info', 'Pruned stale staged uploads', fn (array $context): bool => $context['pruned'] === 1
            && $context['older_than_hours'] === 72);
    }

    /**
     * A submitted plan, shaped as the wizard posts it.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function planPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'token' => null,
            'submit' => true,
            'meta' => [
                'performanceId' => null,
                'performer' => 'Märold',
                'showName' => 'Festival 2026',
                'showDate' => '2026-08-01',
                'duration' => 25,
                'description' => 'Improetendus kolmes osas.',
            ],
            'sound' => [
                'micsMode' => 'yes',
                'micsDetail' => '2 käsimikrofoni',
                'musicianMode' => 'no',
                'musicianDetail' => '',
            ],
            'scenes' => [
                ['id' => 'stseen-1', 'name' => 'Lavale tulek', 'light' => 'Soe üldvalgus', 'soundUrl' => '', 'soundFile' => null, 'sound' => '', 'notes' => ''],
            ],
            'equipment' => [
                'items' => [
                    ['id' => 'e1', 'name' => 'Suitsumasin', 'use' => 'Lavaletuleku ajal'],
                ],
                'smoke' => 'yes',
                'suggestions' => 'yes',
                'suggestNote' => '',
            ],
            'extra' => [
                'notes' => 'Palun jälgida ajakava.',
                'files' => [],
            ],
        ], $overrides);
    }
}
