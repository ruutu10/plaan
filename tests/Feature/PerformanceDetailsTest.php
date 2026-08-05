<?php

namespace Tests\Feature;

use App\Enums\PerformanceStaffRole;
use App\Http\Controllers\PerformanceController;
use App\Http\Controllers\PerformancePageController;
use App\Models\Format;
use App\Models\Performance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A single performance's own details screen — the page shell
 * ({@see PerformancePageController}) and the JSON it
 * reads from ({@see PerformanceController::show()}),
 * including the imported staff the format's own listing does not carry.
 */
class PerformanceDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $performance = Performance::factory()->create();

        $this->get(route('formats.performances.show', [$performance->format, $performance]))
            ->assertRedirect(route('login'));
    }

    public function test_a_stranger_to_the_format_is_forbidden(): void
    {
        $performance = Performance::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get(route('formats.performances.show', [$performance->format, $performance]))
            ->assertForbidden();
    }

    public function test_a_member_of_the_formats_own_team_may_open_it(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);
        $format = Format::factory()->create(['team_id' => $team->id]);
        $performance = Performance::factory()->for($format)->create();

        $this->actingAs($user)
            ->get(route('formats.performances.show', [$format, $performance]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('formats/performances/Show')
                ->where('formatId', $format->id)
                ->where('performanceId', $performance->id));
    }

    public function test_a_performance_cannot_be_reached_through_another_formats_url(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);
        $format = Format::factory()->create(['team_id' => $team->id]);
        $strangersPerformance = Performance::factory()->create();

        $this->actingAs($user)
            ->get(route('formats.performances.show', [$format, $strangersPerformance]))
            ->assertNotFound();
    }

    public function test_a_technician_may_open_any_performance(): void
    {
        $performance = Performance::factory()->create();

        $this->actingAs($this->technician())
            ->get(route('formats.performances.show', [$performance->format, $performance]))
            ->assertOk();
    }

    public function test_the_json_api_reports_the_format_and_the_imported_staff(): void
    {
        config()->set('mail.verified_email_domains', ['ruutu10.ee']);
        $user = User::factory()->create();
        $team = $this->teamOf($user);
        $format = Format::factory()->create(['team_id' => $team->id, 'name' => 'Trupp 1']);
        $performance = Performance::factory()->for($format)->create();

        $host = User::factory()->create(['name' => 'Arne', 'email' => 'arne@ruutu10.ee']);
        $performance->staff()->attach($host, ['role' => PerformanceStaffRole::Host->value]);

        $this->actingAs($user)
            ->getJson(route('api.formats.performances.show', [$format, $performance]))
            ->assertOk()
            ->assertJsonPath('data.formatId', $format->id)
            ->assertJsonPath('data.formatName', 'Trupp 1')
            ->assertJsonCount(1, 'data.staff')
            ->assertJsonPath('data.staff.0.id', $host->id)
            ->assertJsonPath('data.staff.0.name', 'Arne')
            ->assertJsonPath('data.staff.0.role', 'host')
            ->assertJsonPath('data.staff.0.roleLabel', 'Õhtujuht');
    }

    public function test_a_performance_with_no_imported_staff_reports_an_empty_list(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);
        $format = Format::factory()->create(['team_id' => $team->id]);
        $performance = Performance::factory()->for($format)->create();

        $this->actingAs($user)
            ->getJson(route('api.formats.performances.show', [$format, $performance]))
            ->assertOk()
            ->assertJsonCount(0, 'data.staff');
    }

    public function test_the_json_api_offers_the_groups_a_performance_may_be_handed_to(): void
    {
        $user = User::factory()->create();
        $team = $this->teamOf($user);
        $format = Format::factory()->create(['team_id' => $team->id]);
        $performance = Performance::factory()->for($format)->create();

        // The user's UserFactory-provisioned team of one rides along too, so
        // the assignable group is asserted for its presence, not its position.
        $response = $this->actingAs($user)
            ->getJson(route('api.formats.performances.show', [$format, $performance]))
            ->assertOk();

        $this->assertContains($team->id, collect($response->json('teams'))->pluck('id')->all());
    }

    public function test_a_group_playing_an_act_may_open_its_own_slot_on_somebody_elses_evening(): void
    {
        $guest = User::factory()->create();
        $guestTeam = $this->teamOf($guest);

        $evening = Format::factory()->create();
        $slot = Performance::factory()->for($evening)->performedBy($guestTeam, 'Märtu10')->create();

        $this->actingAs($guest)
            ->get(route('formats.performances.show', [$evening, $slot]))
            ->assertOk();
    }
}
