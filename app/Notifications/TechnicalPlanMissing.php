<?php

namespace App\Notifications;

use App\Enums\ReminderSchedule;
use App\Models\Performance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The nudge that a night is coming up and the technical team still has no plan
 * for it.
 *
 * Both {@see ReminderSchedule} moments send this same letter — the second is
 * not a sterner one, it is the same one arriving when there is no longer time
 * to forget about it — so only the line saying how long is left differs.
 *
 * It goes to two kinds of reader. Each performer gets their own copy carrying
 * their own login link, which is a credential and is why no two performers, and
 * certainly nobody else, ever share a message. The technical team gets a copy
 * of its own — the same facts, the roster of who was chased, and a plain link
 * that signs nobody in.
 */
class TechnicalPlanMissing extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string|null  $planUrl  the recipient's own magic link (see App\Actions\BuildTechnicalPlanInvite), or null for the crew's copy, which must never carry one
     * @param  list<array{name: string, email: string}>  $chased  who was written to; listed in the crew's copy only
     */
    public function __construct(
        public Performance $performance,
        public ReminderSchedule $schedule,
        public ?string $planUrl = null,
        public array $chased = [],
    ) {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $show = $this->performance->show;

        return (new MailMessage)
            ->subject($this->subject())
            ->view('emails.technical-plan-missing', [
                'showName' => $show->name,
                'performer' => $show->team->name ?? '',
                'startsAt' => $this->performance->startsAt(),
                'noticeLabel' => $this->schedule->noticeLabel(),
                'duration' => $this->performance->duration,
                // The performer's copy opens the wizard signed in; the crew's
                // points at the overview and asks them to sign in as
                // themselves.
                'planUrl' => $this->planUrl ?? route('technical-plans.index'),
                'isPerformer' => $this->isForPerformer(),
                'chased' => $this->chased,
                'techEmail' => (string) config('technical_plan.tech_email'),
            ]);
    }

    /**
     * Whether this is a performer's copy — the one being chased — as against
     * the technical team's.
     */
    public function isForPerformer(): bool
    {
        return $this->planUrl !== null;
    }

    /**
     * The subject line. The crew's copy is filed rather than acted on, so it
     * says what it is about; the performer's asks them for something.
     */
    private function subject(): string
    {
        $label = $this->performance->show->name.' · '.$this->performance->startsAt()->format('d.m.Y');

        return $this->isForPerformer()
            ? 'Tehnikaplaan puudu · '.$label
            : 'Tehnikaplaani ootel · '.$label;
    }
}
