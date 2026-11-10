<?php

namespace Tests\Feature;

use App\Enums\PerformanceStaffRole;
use App\Http\Controllers\PerformanceStaffController;
use App\Models\Format;
use App\Models\Performance;
use App\Models\PerformanceStaff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Staff put on a performance by hand — {@see PerformanceStaffController} — and
 * how they stand beside the staff the Planka import reads off the card.
 */
class PerformanceStaffManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_of_the_formats_team_may_add_staff_by_hand(): void
    {
        [$user, $format, $performance] = $this->performanceOfOwnTeam();
        $guest = User::factory()->create(['name' => 'Külaline']);

        $this->actingAs($user)
            ->postJson(route('api.formats.performances.staff.store', [$format, $performance]), [
                'user_id' => $guest->id,
                'role' => PerformanceStaffRole::Performer->value,
            ])
            ->assertCreated()
            ->assertJsonPath('data.id', $guest->id)
            ->assertJsonPath('data.name', 'Külaline')
            ->assertJsonPath('data.role', 'performer')
            ->assertJsonPath('data.roleLabel', 'Esineja')
            ->assertJsonPath('data.manuallyAdded', true);

        $staffing = $performance->staffings()->sole();

        $this->assertSame($guest->id, $staffing->user_id);
        $this->assertSame(PerformanceStaffRole::Performer, $staffing->role);
        $this->assertTrue($staffing->manually_added);
    }

    public function test_a_member_of_the_performing_team_may_add_staff_by_hand(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);
        $performance = Performance::factory()->create(['team_id' => $team->id]);

        $this->actingAs($user)
            ->postJson(route('api.formats.performances.staff.store', [$performance->format, $performance]), [
                'user_id' => User::factory()->create()->id,
                'role' => PerformanceStaffRole::Technician->value,
            ])
            ->assertCreated();
    }

    public function test_a_technician_may_add_staff_to_any_performance(): void
    {
        $performance = Performance::factory()->create();

        $this->actingAs($this->technician())
            ->postJson(route('api.formats.performances.staff.store', [$performance->format, $performance]), [
                'user_id' => User::factory()->create()->id,
                'role' => PerformanceStaffRole::VideoOperator->value,
            ])
            ->assertCreated();
    }

    public function test_a_stranger_to_the_performance_may_not_add_staff(): void
    {
        $performance = Performance::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson(route('api.formats.performances.staff.store', [$performance->format, $performance]), [
                'user_id' => User::factory()->create()->id,
                'role' => PerformanceStaffRole::Performer->value,
            ])
            ->assertForbidden();

        $this->assertSame(0, $performance->staffings()->count());
    }

    public function test_guests_may_not_add_staff(): void
    {
        $performance = Performance::factory()->create();

        $this->postJson(route('api.formats.performances.staff.store', [$performance->format, $performance]), [
            'user_id' => User::factory()->create()->id,
            'role' => PerformanceStaffRole::Performer->value,
        ])->assertUnauthorized();
    }

    public function test_a_performance_cannot_be_staffed_through_another_formats_url(): void
    {
        [$user, $format] = $this->performanceOfOwnTeam();
        $strangersPerformance = Performance::factory()->create();

        $this->actingAs($user)
            ->postJson(route('api.formats.performances.staff.store', [$format, $strangersPerformance]), [
                'user_id' => User::factory()->create()->id,
                'role' => PerformanceStaffRole::Performer->value,
            ])
            ->assertNotFound();
    }

    public function test_the_user_and_role_are_required_and_must_exist(): void
    {
        [$user, $format, $performance] = $this->performanceOfOwnTeam();

        $this->actingAs($user)
            ->postJson(route('api.formats.performances.staff.store', [$format, $performance]), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id', 'role']);

        $this->actingAs($user)
            ->postJson(route('api.formats.performances.staff.store', [$format, $performance]), [
                'user_id' => 999999,
                'role' => 'dramaturg',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id', 'role']);
    }

    public function test_a_job_already_held_is_refused(): void
    {
        [$user, $format, $performance] = $this->performanceOfOwnTeam();
        $arne = User::factory()->create();
        // Read off the card by the import.
        $performance->staff()->attach($arne, ['role' => PerformanceStaffRole::Technician->value]);

        $this->actingAs($user)
            ->postJson(route('api.formats.performances.staff.store', [$format, $performance]), [
                'user_id' => $arne->id,
                'role' => PerformanceStaffRole::Technician->value,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id']);

        $this->assertSame(1, $performance->staffings()->count());
    }

    public function test_the_same_person_may_hold_another_job_on_the_night(): void
    {
        [$user, $format, $performance] = $this->performanceOfOwnTeam();
        $arne = User::factory()->create();
        $performance->staff()->attach($arne, ['role' => PerformanceStaffRole::Technician->value]);

        $this->actingAs($user)
            ->postJson(route('api.formats.performances.staff.store', [$format, $performance]), [
                'user_id' => $arne->id,
                'role' => PerformanceStaffRole::VideoOperator->value,
            ])
            ->assertCreated();

        $this->assertSame(2, $performance->staffings()->count());
    }

    public function test_the_same_job_on_another_night_does_not_count_as_held(): void
    {
        [$user, $format, $performance] = $this->performanceOfOwnTeam();
        $arne = User::factory()->create();
        Performance::factory()->create()->staff()->attach($arne, ['role' => PerformanceStaffRole::Technician->value]);

        $this->actingAs($user)
            ->postJson(route('api.formats.performances.staff.store', [$format, $performance]), [
                'user_id' => $arne->id,
                'role' => PerformanceStaffRole::Technician->value,
            ])
            ->assertCreated();
    }

    public function test_a_manually_added_job_may_be_removed(): void
    {
        [$user, $format, $performance] = $this->performanceOfOwnTeam();
        $staffing = $this->manuallyStaff($performance, User::factory()->create(), PerformanceStaffRole::Performer);

        $this->actingAs($user)
            ->deleteJson(route('api.formats.performances.staff.destroy', [$format, $performance, $staffing]))
            ->assertNoContent();

        $this->assertModelMissing($staffing);
    }

    public function test_an_imported_job_may_not_be_removed(): void
    {
        [$user, $format, $performance] = $this->performanceOfOwnTeam();
        $performance->staff()->attach(User::factory()->create(), ['role' => PerformanceStaffRole::Host->value]);
        $staffing = $performance->staffings()->sole();

        $this->actingAs($user)
            ->deleteJson(route('api.formats.performances.staff.destroy', [$format, $performance, $staffing]))
            ->assertForbidden();

        $this->assertModelExists($staffing);
    }

    public function test_a_stranger_may_not_remove_a_manually_added_job(): void
    {
        $performance = Performance::factory()->create();
        $staffing = $this->manuallyStaff($performance, User::factory()->create(), PerformanceStaffRole::Performer);

        $this->actingAs(User::factory()->create())
            ->deleteJson(route('api.formats.performances.staff.destroy', [$performance->format, $performance, $staffing]))
            ->assertForbidden();

        $this->assertModelExists($staffing);
    }

    public function test_another_performances_job_cannot_be_removed_through_this_one(): void
    {
        [$user, $format, $performance] = $this->performanceOfOwnTeam();
        $elsewhere = $this->manuallyStaff(
            Performance::factory()->for($format)->create(),
            User::factory()->create(),
            PerformanceStaffRole::Performer,
        );

        $this->actingAs($user)
            ->deleteJson(route('api.formats.performances.staff.destroy', [$format, $performance, $elsewhere]))
            ->assertNotFound();

        $this->assertModelExists($elsewhere);
    }

    public function test_the_details_api_says_which_staff_were_added_by_hand(): void
    {
        [$user, $format, $performance] = $this->performanceOfOwnTeam();
        $imported = User::factory()->create(['name' => 'Arne']);
        $manual = User::factory()->create(['name' => 'Berit']);
        $performance->staff()->attach($imported, ['role' => PerformanceStaffRole::Host->value]);
        $staffing = $this->manuallyStaff($performance, $manual, PerformanceStaffRole::Performer);

        $staff = collect($this->actingAs($user)
            ->getJson(route('api.formats.performances.show', [$format, $performance]))
            ->assertOk()
            ->json('data.staff'))
            ->keyBy('name');

        $this->assertFalse($staff['Arne']['manuallyAdded']);
        $this->assertTrue($staff['Berit']['manuallyAdded']);
        $this->assertSame($staffing->id, $staff['Berit']['staffingId']);
    }

    public function test_the_details_api_offers_roles_but_never_the_list_of_accounts(): void
    {
        [$user, $format, $performance] = $this->performanceOfOwnTeam();
        User::factory()->create(['name' => 'Külaline']);

        $response = $this->actingAs($user)
            ->getJson(route('api.formats.performances.show', [$format, $performance]))
            ->assertOk()
            ->assertJsonMissingPath('staffCandidates');

        $this->assertSame(
            ['performer', 'host', 'technician', 'video-operator', 'ticket-seller', 'bar'],
            collect($response->json('staffRoles'))->pluck('value')->all(),
        );
    }

    public function test_manually_added_staff_are_read_like_imported_staff(): void
    {
        $performance = Performance::factory()->create();
        $performer = User::factory()->create();
        $host = User::factory()->create();
        $technician = User::factory()->create();
        $this->manuallyStaff($performance, $performer, PerformanceStaffRole::Performer);
        $this->manuallyStaff($performance, $host, PerformanceStaffRole::Host);
        $this->manuallyStaff($performance, $technician, PerformanceStaffRole::Technician);

        // Who a submitted plan is copied to, who a recording is copied to, who
        // the plan is written to, and who may be reminded about it.
        $this->assertSame([$performer->id], $performance->performers()->pluck('users.id')->all());
        $this->assertEqualsCanonicalizing([$performer->id, $host->id], $performance->onStage()->pluck('users.id')->all());
        $this->assertSame([$technician->id], $performance->technicians()->pluck('users.id')->all());
        $this->assertEqualsCanonicalizing(
            [$performer->id, $host->id, $technician->id],
            $performance->reminderCandidates()->modelKeys(),
        );
    }

    public function test_a_manually_added_performer_may_download_the_recording(): void
    {
        config()->set('services.jellyfin.url', 'https://jellyfin.test');
        config()->set('services.jellyfin.api_key', 'test-key');

        $performance = Performance::factory()->create();
        $performer = User::factory()->create();

        $this->assertFalse(Gate::forUser($performer)->allows('downloadRecording', $performance));

        $this->manuallyStaff($performance, $performer, PerformanceStaffRole::Performer);

        $this->assertTrue(Gate::forUser($performer)->allows('downloadRecording', $performance));
    }

    /**
     * A user, a format their own team owns, and a performance of it.
     *
     * @return array{User, Format, Performance}
     */
    private function performanceOfOwnTeam(): array
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);
        $format = Format::factory()->create(['team_id' => $team->id]);

        return [$user, $format, Performance::factory()->for($format)->create()];
    }

    /**
     * Put somebody on the performance by hand, as the staff list's dialog does.
     */
    private function manuallyStaff(Performance $performance, User $user, PerformanceStaffRole $role): PerformanceStaff
    {
        /** @var PerformanceStaff $staffing */
        $staffing = $performance->staffings()->create([
            'user_id' => $user->id,
            'role' => $role,
            'manually_added' => true,
        ]);

        return $staffing;
    }
}
