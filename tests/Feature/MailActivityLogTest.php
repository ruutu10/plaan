<?php

namespace Tests\Feature;

use App\Models\Performance;
use App\Models\TechnicalPlan;
use App\Models\User;
use App\Notifications\MagicLoginLink;
use App\Notifications\TechnicalPlanSubmitted;
use App\Notifications\TechnicianMissing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Every letter the application mails out leaves an audit-trail entry — see
 * App\Listeners\LogMailSent.
 */
class MailActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_letter_about_nothing_but_its_recipient_is_filed_under_the_recipient(): void
    {
        $user = User::factory()->create(['email' => 'mari@example.com']);

        $user->notify(new MagicLoginLink('https://example.com/login'));

        $activity = Activity::query()->forSubject($user)->forEvent('mail_sent')->sole();

        $this->assertNull($activity->causer);
        $this->assertSame('MagicLoginLink', $activity->getProperty('notification'));
        $this->assertSame('Sinu sisselogimislink · Tehnikaplaan', $activity->getProperty('subject'));
        $this->assertSame(['mari@example.com'], $activity->getProperty('to'));
        $this->assertSame([], $activity->getProperty('bcc'));
        $this->assertSame(
            'E-mail "Sinu sisselogimislink · Tehnikaplaan" sent to mari@example.com',
            $activity->description,
        );
    }

    public function test_a_letter_about_a_record_is_filed_under_that_record_with_its_blind_copies(): void
    {
        $author = User::factory()->create(['email' => 'autor@example.com']);
        $plan = TechnicalPlan::factory()->create(['user_id' => $author->id]);

        $author->notify(new TechnicalPlanSubmitted($plan, ['a@example.com', 'b@example.com']));

        $activity = Activity::query()->forSubject($plan)->forEvent('mail_sent')->sole();

        $this->assertSame(['autor@example.com'], $activity->getProperty('to'));
        $this->assertSame(['a@example.com', 'b@example.com'], $activity->getProperty('bcc'));
        $this->assertStringContainsString('sent to autor@example.com and 2 blind copies', $activity->description);
        $this->assertSame(0, Activity::query()->forSubject($author)->forEvent('mail_sent')->count());
    }

    public function test_a_letter_to_a_bare_address_is_logged_without_a_subject(): void
    {
        $performances = Performance::factory()->count(2)->create();

        Notification::route('mail', 'tehnika@example.com')->notify(new TechnicianMissing($performances));

        $activity = Activity::query()->forEvent('mail_sent')->sole();

        $this->assertNull($activity->subject_type);
        $this->assertSame(['tehnika@example.com'], $activity->getProperty('to'));
    }

    public function test_a_letter_with_nowhere_to_go_is_not_logged(): void
    {
        (new AnonymousNotifiable)->notify(new TechnicianMissing(Performance::factory()->count(1)->create()));

        $this->assertSame(0, Activity::query()->forEvent('mail_sent')->count());
    }

    public function test_a_faked_letter_is_not_logged(): void
    {
        Notification::fake();

        User::factory()->create()->notify(new MagicLoginLink('https://example.com/login'));

        $this->assertSame(0, Activity::query()->forEvent('mail_sent')->count());
    }
}
