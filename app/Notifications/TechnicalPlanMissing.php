<?php

namespace App\Notifications;

use App\Actions\BuildTechnicalPlanInvite;
use App\Models\Performance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The nudge that a night is coming up and the technical team still has no plan
 * for it, sent by hand from the performance's own page to the performers the
 * crew picks.
 *
 * One recipient per message, always. The link it carries signs its holder in as
 * them (see {@see BuildTechnicalPlanInvite}), which makes it a
 * credential, and a credential is not something two people share — so no copy
 * of this letter is ever addressed, copied or blind-copied to anybody else.
 */
class TechnicalPlanMissing extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $planUrl  the recipient's own magic link, which nobody else may be given
     */
    public function __construct(
        public Performance $performance,
        public string $planUrl,
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
        $format = $this->performance->format;

        return (new MailMessage)
            ->subject($this->subject())
            ->view('emails.technical-plan-missing', [
                'formatName' => $this->performance->title === null
                    ? $format->name
                    : $format->name.' — '.$this->performance->title,
                'performer' => $this->performance->performerName() ?? '',
                'startsAt' => $this->performance->startsAt(),
                'duration' => $this->performance->duration,
                // Named only when the night is played somewhere other than the
                // house's own room; see the template.
                'location' => $this->performance->location,
                'planUrl' => $this->planUrl,
                'techEmail' => (string) config('technical_plan.tech_email'),
            ]);
    }

    /**
     * The subject line: what is missing, and which night it is missing for.
     */
    private function subject(): string
    {
        return 'Tehnikaplaan puudu · '
            .$this->performance->format->name
            .' · '
            .$this->performance->startsAt()->format('d.m.Y');
    }
}
