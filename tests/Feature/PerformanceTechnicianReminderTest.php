<?php

namespace Tests\Feature;

use App\Enums\PerformanceStaffRole;
use App\Models\Format;
use App\Models\Performance;
use App\Models\User;
use App\Notifications\TechnicianMissing;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Chasing the technical team about performances nobody has signed on to run
 * sound and light for.
 *
 * Unlike the technical-plan reminders, this one is not a one-shot per
 * performance: it is a daily digest of whatever the gap looks like today, so
 * the thing worth being sure of is that it keeps naming a performance every
 * day the gap lasts and stops the moment it does not.
 */
class PerformanceTechnicianReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-09-01 12:00:00');

        config(['technical_plan.tech_email' => 'tehnik@ruutu10.ee']);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_a_performance_inside_the_window_without_a_technician_is_chased(): void
    {
        Notification::fake();

        $performance = $this->performanceIn('6 days');

        $this->artisan('performances:remind-missing-technicians')->assertSuccessful();

        Notification::assertSentOnDemand(
            TechnicianMissing::class,
            fn (TechnicianMissing $mail, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'tehnik@ruutu10.ee'
                && $mail->performances->contains(fn (Performance $p) => $p->is($performance)),
        );
    }

    public function test_a_performance_with_a_technician_already_signed_on_is_left_alone(): void
    {
        Notification::fake();

        $performance = $this->performanceIn('6 days');
        $performance->staff()->attach(
            User::factory()->create(),
            ['role' => PerformanceStaffRole::Technician->value],
        );

        $this->artisan('performances:remind-missing-technicians')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_a_performance_staffed_only_by_a_non_technician_role_is_still_chased(): void
    {
        Notification::fake();

        $performance = $this->performanceIn('6 days');
        $performance->staff()->attach(
            User::factory()->create(),
            ['role' => PerformanceStaffRole::Host->value],
        );

        $this->artisan('performances:remind-missing-technicians')->assertSuccessful();

        Notification::assertSentOnDemand(TechnicianMissing::class);
    }

    public function test_a_performance_outside_the_lead_window_waits(): void
    {
        Notification::fake();

        $this->performanceIn('10 days');

        $this->artisan('performances:remind-missing-technicians')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_a_draft_performance_is_not_chased(): void
    {
        Notification::fake();

        Performance::factory()->draft()->create([
            'date' => now()->add('6 days')->subMinute(),
        ]);

        $this->artisan('performances:remind-missing-technicians')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_a_performance_already_being_played_is_not_chased(): void
    {
        Notification::fake();

        $this->performanceIn('-1 hour');

        $this->artisan('performances:remind-missing-technicians')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_the_digest_names_every_performance_still_open_in_one_mail(): void
    {
        Notification::fake();

        $first = $this->performanceIn('6 days');
        $second = $this->performanceIn('2 days');

        $this->artisan('performances:remind-missing-technicians')->assertSuccessful();

        Notification::assertSentOnDemand(
            TechnicianMissing::class,
            fn (TechnicianMissing $mail): bool => $mail->performances->count() === 2
                && $mail->performances->contains(fn (Performance $p) => $p->is($first))
                && $mail->performances->contains(fn (Performance $p) => $p->is($second)),
        );

        // One digest, not one letter per performance.
        Notification::assertSentTimes(TechnicianMissing::class, 1);
    }

    public function test_it_keeps_chasing_the_same_gap_every_day_it_runs(): void
    {
        Notification::fake();

        $this->performanceIn('6 days');

        $this->artisan('performances:remind-missing-technicians')->assertSuccessful();
        $this->artisan('performances:remind-missing-technicians')->assertSuccessful();

        // Unlike the technical-plan reminders, there is nothing recorded to
        // stop this one repeating — the gap is still there both times.
        Notification::assertSentTimes(TechnicianMissing::class, 2);
    }

    public function test_a_dry_run_mails_nobody(): void
    {
        Notification::fake();

        $this->performanceIn('6 days');

        $this->artisan('performances:remind-missing-technicians', ['--dry-run' => true])
            ->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_the_reminder_can_be_switched_off_entirely(): void
    {
        Notification::fake();

        config(['performance.technician_reminders.enabled' => false]);

        $this->performanceIn('6 days');

        $this->artisan('performances:remind-missing-technicians')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_without_a_technical_contact_nobody_is_mailed_but_the_run_still_succeeds(): void
    {
        Notification::fake();
        Log::spy();

        config(['technical_plan.tech_email' => '']);

        $this->performanceIn('6 days');

        $this->artisan('performances:remind-missing-technicians')->assertSuccessful();

        Notification::assertNothingSent();

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message): bool => $message === 'Missing-technician reminder run found performances to chase, but no technical contact is configured');
    }

    public function test_the_lead_window_can_be_configured(): void
    {
        Notification::fake();

        config(['performance.technician_reminders.lead_days' => 14]);

        $performance = $this->performanceIn('10 days');

        $this->artisan('performances:remind-missing-technicians')->assertSuccessful();

        Notification::assertSentOnDemand(
            TechnicianMissing::class,
            fn (TechnicianMissing $mail): bool => $mail->performances->contains(fn (Performance $p) => $p->is($performance)),
        );
    }

    /**
     * A vouched-for performance the given interval away, with no technician
     * staffed.
     */
    private function performanceIn(string $interval): Performance
    {
        return Performance::factory()
            ->for(Format::factory())
            ->create([
                'date' => now()->add($interval)->subMinute(),
            ]);
    }
}
