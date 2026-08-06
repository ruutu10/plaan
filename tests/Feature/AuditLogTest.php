<?php

namespace Tests\Feature;

use App\Models\Format;
use App\Models\Performance;
use App\Models\Team;
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
