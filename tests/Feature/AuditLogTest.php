<?php

namespace Tests\Feature;

use App\Http\Controllers\AuditLogController;
use App\Models\Format;
use App\Models\Performance;
use App\Models\Team;
use App\Models\TechnicalPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('admin.audit-log.index'))
            ->assertRedirect(route('login'));
    }

    public function test_a_user_without_the_permission_is_refused(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.audit-log.index'))
            ->assertForbidden();
    }

    public function test_a_technician_sees_the_feed_newest_first(): void
    {
        $technician = $this->technician();
        $team = Team::factory()->create();
        $this->actingAs($technician);

        // Both filed under the same team, so nothing but the two formats
        // themselves is created after the technician is set up — the feed's
        // two newest entries are exactly these, newer one first.
        $older = Format::factory()->create(['team_id' => $team->id, 'name' => 'Vanem lavastus']);
        $newer = Format::factory()->create(['team_id' => $team->id, 'name' => 'Uuem lavastus']);

        $this->get(route('admin.audit-log.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/audit-log/Index')
                ->where('entries.0.subjectId', $newer->id)
                ->where('entries.0.subjectType', 'Format')
                ->where('entries.0.event', 'created')
                ->where('entries.0.causerName', $technician->name)
                ->where('entries.1.subjectId', $older->id)
            );
    }

    public function test_the_feed_labels_teams_users_and_performances_by_name(): void
    {
        $technician = $this->technician();
        $this->actingAs($technician);

        $team = Team::factory()->create(['name' => 'Ruutu10']);
        // A new account brings its own team and membership along; both are
        // just as real an entry as the ones this test is actually about.
        $member = User::factory()->create(['name' => 'Mart Naide']);
        $format = Format::factory()->create(['team_id' => $team->id, 'name' => 'Suveetendus']);

        // A shared evening's act, named on its own — and the format's own,
        // ordinary performance, which carries no title of its own.
        $namedAct = Performance::factory()->create(['format_id' => $format->id, 'title' => 'Teise trupi etteaste']);
        $ordinary = Performance::factory()->create(['format_id' => $format->id, 'title' => null]);

        $this->get(route('admin.audit-log.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('entries.0.subjectId', $ordinary->id)
                ->where('entries.0.subjectType', 'Performance')
                ->where('entries.0.subjectLabel', 'Suveetendus')
                ->where('entries.1.subjectId', $namedAct->id)
                ->where('entries.1.subjectLabel', 'Teise trupi etteaste')
                ->where('entries.2.subjectId', $format->id)
                ->where('entries.2.subjectType', 'Format')
                ->where('entries.2.subjectLabel', 'Suveetendus')
                ->where('entries.5.subjectId', $member->id)
                ->where('entries.5.subjectType', 'User')
                ->where('entries.5.subjectLabel', 'Mart Naide')
                ->where('entries.6.subjectId', $team->id)
                ->where('entries.6.subjectType', 'Team')
                ->where('entries.6.subjectLabel', 'Ruutu10')
            );
    }

    public function test_a_plan_is_labelled_by_the_date_and_name_of_its_night(): void
    {
        $technician = $this->technician();
        $this->actingAs($technician);

        $format = Format::factory()->create(['name' => 'Suveetendus']);
        $performance = Performance::factory()->create([
            'format_id' => $format->id,
            'title' => null,
            'date' => Performance::momentFrom('2026-09-01', '19:00'),
        ]);
        $plan = TechnicalPlan::factory()->create(['performance_id' => $performance->id]);

        $this->get(route('admin.audit-log.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('entries.0.subjectId', $plan->id)
                ->where('entries.0.subjectType', 'Technical Plan')
                ->where('entries.0.subjectLabel', '01.09.2026 · Suveetendus')
            );
    }

    public function test_every_named_record_is_linked_to_its_own_screen(): void
    {
        $technician = $this->technician();
        $this->actingAs($technician);

        $team = Team::factory()->create();
        $member = User::factory()->create();
        $format = Format::factory()->create(['team_id' => $team->id]);
        $performance = Performance::factory()->create(['format_id' => $format->id]);
        $plan = TechnicalPlan::factory()->create([
            'performance_id' => $performance->id,
            'user_id' => $member->id,
        ]);

        $response = $this->get(route('admin.audit-log.index'))->assertOk();

        $this->assertSame(
            route('admin.teams.edit', $team),
            $this->entryUrl($response->viewData('page')['props']['entries'], 'Team', $team->id),
        );
        $this->assertSame(
            route('admin.users.edit', $member),
            $this->entryUrl($response->viewData('page')['props']['entries'], 'User', $member->id),
        );
        $this->assertSame(
            route('formats.edit', $format),
            $this->entryUrl($response->viewData('page')['props']['entries'], 'Format', $format->id),
        );
        $this->assertSame(
            route('formats.performances.show', [$format->id, $performance->id]),
            $this->entryUrl($response->viewData('page')['props']['entries'], 'Performance', $performance->id),
        );
        $this->assertSame(
            route('technical-plans.show', $plan),
            $this->entryUrl($response->viewData('page')['props']['entries'], 'Technical Plan', $plan->id),
        );
    }

    public function test_a_reader_who_may_not_open_a_record_is_offered_no_link(): void
    {
        // The trail's own permission on its own: reading the log is not itself
        // a right to open the records it names.
        $reader = User::factory()->create();
        $reader->givePermissionTo(AuditLogController::VIEW_PERMISSION);

        Format::factory()->create();

        $this->actingAs($reader)
            ->get(route('admin.audit-log.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('entries.0.subjectType', 'Format')
                ->where('entries.0.subjectUrl', null)
            );
    }

    /**
     * Where the feed says a given record may be opened. Found by what the entry
     * is about rather than by its place in the feed: creating any one of these
     * records writes entries of its own along the way.
     *
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function entryUrl(array $entries, string $subjectType, int $subjectId): ?string
    {
        $entry = collect($entries)->firstWhere(
            fn (array $entry): bool => $entry['subjectType'] === $subjectType && $entry['subjectId'] === $subjectId,
        );

        $this->assertNotNull($entry, "No {$subjectType} entry for #{$subjectId} in the feed.");

        return $entry['subjectUrl'];
    }

    public function test_an_entry_with_nobody_signed_in_reports_no_causer(): void
    {
        $technician = $this->technician();

        // No actingAs(): the format is created the way a console import runs.
        Format::factory()->create();

        $this->actingAs($technician)
            ->get(route('admin.audit-log.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('entries.0.causerName', null)
            );
    }
}
