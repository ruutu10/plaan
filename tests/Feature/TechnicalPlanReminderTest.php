<?php

namespace Tests\Feature;

use App\Enums\PerformanceStaffRole;
use App\Enums\TeamRole;
use App\Http\Controllers\PerformanceReminderController;
use App\Models\Format;
use App\Models\Performance;
use App\Models\Team;
use App\Models\User;
use App\Notifications\TechnicalPlanMissing;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Chasing performers by hand for a technical plan that has not been handed in
 * — see {@see PerformanceReminderController}.
 *
 * Nothing sends this on a schedule any more, so the things worth being sure of
 * are that only the people the crew picked are written to, that only members of
 * the group playing the night can be picked at all, and that each letter goes
 * to one person alone: the link it carries signs its holder in.
 */
class TechnicalPlanReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The link's lifetime is measured from here, so the tests say what they
        // mean rather than what today happens to be.
        CarbonImmutable::setTestNow('2026-09-01 12:00:00');

        config(['technical_plan.tech_email' => 'tehnik@ruutu10.ee']);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_it_chases_the_chosen_members_and_nobody_else(): void
    {
        Notification::fake();

        [$performance, $members] = $this->performanceWithGroup();

        $this->actingAs($this->technician())
            ->postJson($this->reminderUrl($performance), [
                'user_ids' => [$members[0]->id, $members[1]->id],
            ])
            ->assertOk()
            ->assertJson(['sent' => 2]);

        foreach ([$members[0], $members[1]] as $chased) {
            Notification::assertSentTo(
                $chased,
                TechnicalPlanMissing::class,
                fn (TechnicalPlanMissing $mail): bool => $mail->performance->is($performance),
            );
        }

        // The third member of the group was not picked, so nothing was sent to
        // them — and the crew's old copy is gone entirely.
        Notification::assertNotSentTo($members[2], TechnicalPlanMissing::class);
        Notification::assertSentTimes(TechnicalPlanMissing::class, 2);
        Notification::assertNotSentTo(new AnonymousNotifiable, TechnicalPlanMissing::class);
    }

    public function test_no_reminder_is_ever_addressed_to_more_than_one_person(): void
    {
        Notification::fake();

        [$performance, $members] = $this->performanceWithGroup();

        $this->actingAs($this->technician())
            ->postJson($this->reminderUrl($performance), [
                'user_ids' => [$members[0]->id, $members[1]->id],
            ])
            ->assertOk();

        // The whole point of a letter each: it carries a link that signs in
        // whoever follows it, so nobody may be copied or blind-copied in on it.
        Notification::assertSentTo(
            $members[0],
            TechnicalPlanMissing::class,
            function (TechnicalPlanMissing $notification) use ($members): bool {
                $mail = $notification->toMail($members[0]);

                $this->assertSame([], $mail->cc);
                $this->assertSame([], $mail->bcc);

                $body = (string) $mail->render();

                $this->assertStringContainsString($notification->planUrl, $body);
                $this->assertStringContainsString('Täida tehnikaplaan', $body);
                // Nobody else's address appears on it.
                $this->assertStringNotContainsString($members[1]->email, $body);

                return true;
            },
        );

        // And the two links are two links, not one shared between them.
        $this->assertNotSame(
            $this->sentLinkFor($members[0]),
            $this->sentLinkFor($members[1]),
        );
    }

    public function test_the_reminder_carries_a_link_that_opens_the_plan_for_that_night(): void
    {
        Notification::fake();

        [$performance, $members] = $this->performanceWithGroup();

        $this->actingAs($this->technician())
            ->postJson($this->reminderUrl($performance), ['user_ids' => [$members[0]->id]])
            ->assertOk();

        // Following it once signs the performer in and hands them on to the
        // wizard, told which night it is about and to skip the step that would
        // have been for choosing it.
        $landedOn = $this->get($this->sentLinkFor($members[0]))
            ->assertRedirect()
            ->headers->get('Location');

        $this->assertAuthenticatedAs($members[0]);
        $this->assertStringContainsString('performance='.$performance->id, (string) $landedOn);
        $this->assertStringContainsString('step=1', (string) $landedOn);

        $this->get($landedOn)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('TechnicalPlan')
                ->where('initialStep', 1)
                ->where('initialPerformance.performanceId', $performance->id)
                ->where('initialPerformance.formatName', 'Öine impro'));
    }

    public function test_somebody_outside_the_playing_group_cannot_be_chased(): void
    {
        Notification::fake();

        [$performance, $members] = $this->performanceWithGroup();
        $stranger = User::factory()->create();

        $this->actingAs($this->technician())
            ->postJson($this->reminderUrl($performance), [
                'user_ids' => [$members[0]->id, $stranger->id],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_ids.1');

        // Refused whole: the member named beside the stranger is not written to
        // either.
        Notification::assertNothingSent();
    }

    public function test_a_reminder_must_name_somebody(): void
    {
        Notification::fake();

        [$performance] = $this->performanceWithGroup();

        $this->actingAs($this->technician())
            ->postJson($this->reminderUrl($performance), ['user_ids' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('user_ids');

        Notification::assertNothingSent();
    }

    public function test_somebody_who_may_not_change_the_performance_may_not_chase_it(): void
    {
        Notification::fake();

        [$performance, $members] = $this->performanceWithGroup();

        $this->actingAs(User::factory()->create())
            ->postJson($this->reminderUrl($performance), ['user_ids' => [$members[0]->id]])
            ->assertForbidden();

        Notification::assertNothingSent();
    }

    public function test_a_member_of_the_playing_group_may_chase_their_own_night(): void
    {
        Notification::fake();

        [$performance, $members] = $this->performanceWithGroup();

        $this->actingAs($members[0])
            ->postJson($this->reminderUrl($performance), ['user_ids' => [$members[1]->id]])
            ->assertOk()
            ->assertJson(['sent' => 1]);

        Notification::assertSentTo($members[1], TechnicalPlanMissing::class);
    }

    public function test_guests_are_turned_away(): void
    {
        Notification::fake();

        [$performance, $members] = $this->performanceWithGroup();

        $this->postJson($this->reminderUrl($performance), ['user_ids' => [$members[0]->id]])
            ->assertUnauthorized();

        Notification::assertNothingSent();
    }

    public function test_the_details_endpoint_offers_the_playing_groups_members(): void
    {
        [$performance, $members] = $this->performanceWithGroup();

        $this->actingAs($this->technician())
            ->getJson(route('api.formats.performances.show', [$performance->format, $performance]))
            ->assertOk()
            // Alphabetical: Jaan, Mari, Tiit.
            ->assertJsonPath('reminderRecipients.0.id', $members[1]->id)
            ->assertJsonPath('reminderRecipients.0.name', $members[1]->name)
            ->assertJsonCount(3, 'reminderRecipients');
    }

    public function test_the_choice_names_people_without_giving_out_their_addresses(): void
    {
        [$performance, $members] = $this->performanceWithGroup();

        $offered = $this->actingAs($this->technician())
            ->getJson(route('api.formats.performances.show', [$performance->format, $performance]))
            ->assertOk()
            ->json('reminderRecipients');

        // Picking somebody is done by id; where the letter goes is the server's
        // business, so no address travels to the screen.
        foreach ($offered as $recipient) {
            $this->assertArrayNotHasKey('email', $recipient);
        }

        foreach ($members as $member) {
            $this->assertStringNotContainsString($member->email, json_encode($offered) ?: '');
        }
    }

    public function test_the_members_cast_as_performers_are_the_ones_marked(): void
    {
        [$performance, $members] = $this->performanceWithGroup();

        // The card names two of the three: one on stage, one behind the sound
        // desk. Only the one on stage owes a plan.
        $performance->staff()->attach($members[1], ['role' => PerformanceStaffRole::Performer->value]);
        $performance->staff()->attach($members[2], ['role' => PerformanceStaffRole::Technician->value]);

        $this->actingAs($this->technician())
            ->getJson(route('api.formats.performances.show', [$performance->format, $performance]))
            ->assertOk()
            // Alphabetical: Jaan, Mari, Tiit.
            ->assertJsonPath('reminderRecipients.0.staffedAsPerformer', true)
            ->assertJsonPath('reminderRecipients.1.staffedAsPerformer', false)
            ->assertJsonPath('reminderRecipients.2.staffedAsPerformer', false);
    }

    public function test_a_night_with_no_imported_staff_marks_nobody(): void
    {
        [$performance] = $this->performanceWithGroup();

        $this->actingAs($this->technician())
            ->getJson(route('api.formats.performances.show', [$performance->format, $performance]))
            ->assertOk()
            ->assertJsonPath('reminderRecipients.0.staffedAsPerformer', false)
            ->assertJsonPath('reminderRecipients.1.staffedAsPerformer', false)
            ->assertJsonPath('reminderRecipients.2.staffedAsPerformer', false);
    }

    public function test_somebody_cast_as_a_performer_outside_the_playing_group_is_not_offered(): void
    {
        [$performance] = $this->performanceWithGroup();

        // A guest on the card who is in no group of the house: the plan may not
        // be chased through them, so they are not among the choices at all.
        $guest = User::factory()->create();
        $performance->staff()->attach($guest, ['role' => PerformanceStaffRole::Performer->value]);

        $offered = $this->actingAs($this->technician())
            ->getJson(route('api.formats.performances.show', [$performance->format, $performance]))
            ->assertOk()
            ->assertJsonCount(3, 'reminderRecipients')
            ->json('reminderRecipients.*.id');

        // On the card — the staff list says so — but not among the people the
        // reminder may be sent to.
        $this->assertNotContains($guest->id, $offered);

        Notification::fake();

        $this->actingAs($this->technician())
            ->postJson($this->reminderUrl($performance), ['user_ids' => [$guest->id]])
            ->assertUnprocessable();

        Notification::assertNothingSent();
    }

    public function test_the_group_playing_an_act_is_offered_rather_than_the_formats_owner(): void
    {
        [$performance, $members] = $this->performanceWithGroup();

        // The evening is shared: this act is played by a group of its own, and
        // it is that group the reminder is offered to.
        $guests = Team::factory()->create();
        $guest = User::factory()->create();
        $guests->members()->attach($guest, ['role' => TeamRole::Owner->value]);

        $performance->update(['team_id' => $guests->id]);

        $this->actingAs($this->technician())
            ->getJson(route('api.formats.performances.show', [$performance->format, $performance]))
            ->assertOk()
            ->assertJsonCount(1, 'reminderRecipients')
            ->assertJsonPath('reminderRecipients.0.id', $guest->id);

        Notification::fake();

        // And the format owner's members can no longer be picked.
        $this->actingAs($this->technician())
            ->postJson($this->reminderUrl($performance), ['user_ids' => [$members[0]->id]])
            ->assertUnprocessable();

        Notification::assertNothingSent();
    }

    public function test_a_performance_no_group_plays_offers_nobody(): void
    {
        $performance = Performance::factory()->for(Format::factory()->create(['team_id' => null]))->create();

        $this->actingAs($this->technician())
            ->getJson(route('api.formats.performances.show', [$performance->format, $performance]))
            ->assertOk()
            ->assertJsonCount(0, 'reminderRecipients');

        Notification::fake();

        $this->actingAs($this->technician())
            ->postJson($this->reminderUrl($performance), ['user_ids' => [User::factory()->create()->id]])
            ->assertUnprocessable();

        Notification::assertNothingSent();
    }

    /**
     * A performance a week out, played by a group of three — the people a
     * reminder may be sent to. Named addresses, so a test can say whose letter
     * it is reading; alphabetical order is the order the screen offers them in.
     *
     * @return array{Performance, list<User>}
     */
    private function performanceWithGroup(): array
    {
        $team = Team::factory()->create();

        $members = [
            User::factory()->create(['name' => 'Mari Maasikas', 'email' => 'mari@naide.ee']),
            User::factory()->create(['name' => 'Jaan Kask', 'email' => 'jaan@naide.ee']),
            User::factory()->create(['name' => 'Tiit Tamm', 'email' => 'tiit@naide.ee']),
        ];

        foreach ($members as $index => $member) {
            $team->members()->attach($member, [
                'role' => $index === 0 ? TeamRole::Owner->value : TeamRole::Member->value,
            ]);
        }

        $format = Format::factory()->create(['team_id' => $team->id, 'name' => 'Öine impro']);

        $performance = Performance::factory()->for($format)->create([
            'date' => now()->addDays(6),
        ]);

        return [$performance, $members];
    }

    /**
     * Where a reminder for this performance is sent from.
     */
    private function reminderUrl(Performance $performance): string
    {
        return route('api.formats.performances.reminders.store', [
            $performance->format,
            $performance,
        ]);
    }

    /**
     * The magic link the given performer was actually mailed.
     */
    private function sentLinkFor(User $performer): string
    {
        $link = '';

        Notification::assertSentTo(
            $performer,
            TechnicalPlanMissing::class,
            function (TechnicalPlanMissing $mail) use (&$link): bool {
                $link = $mail->planUrl;

                return true;
            },
        );

        return $link;
    }
}
