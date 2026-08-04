<?php

namespace App\Notifications;

use App\Models\Performance;
use App\Models\TechnicalPlan;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The word back to a plan's own author that the technical team has picked it
 * up — sent the moment a plan moves from submitted to received, so the
 * performer knows their plan reached someone rather than sitting unread. The
 * technical team is copied on their own letter, so the mail also stands as
 * the crew's own record of who was told.
 */
class TechnicalPlanReceived extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public TechnicalPlan $plan, public User $confirmedBy)
    {
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
        $performance = $this->plan->performance;
        $techEmail = (string) config('technical_plan.tech_email');
        $recipientEmail = $notifiable instanceof User ? $notifiable->email : null;

        $mail = (new MailMessage)
            ->subject('Tehnikaplaan kätte saadud · '.$this->planLabel())
            ->view('emails.technical-plan-received', [
                'formatName' => $this->formatName($performance),
                'performer' => $performance?->performerName(),
                'startsAt' => $performance?->startsAt(),
                'statusLabel' => $this->plan->status->label(),
                'confirmedByName' => $this->confirmedBy->name,
                'publicUrl' => route('technical-plan.public', $this->plan),
                'techEmail' => $techEmail,
            ]);

        // A copy for the crew's own record, unless they are the ones being
        // told — the tech contact writing to themselves is not a CC.
        if ($techEmail !== '' && $techEmail !== $recipientEmail) {
            $mail->cc($techEmail);
        }

        return $mail;
    }

    /**
     * How the plan is named in the subject line: the format it belongs to, or —
     * for a plan filled in ahead of any registered performance — its key.
     */
    private function planLabel(): string
    {
        $performance = $this->plan->performance;

        $parts = array_values(array_filter([
            $performance?->format->name,
            $performance?->startsAt()->format('d.m.Y'),
        ]));

        return $parts === [] ? $this->plan->token : implode(' · ', $parts);
    }

    /**
     * The format's name, with the performance's own title appended when it has
     * one — e.g. a guest act's name on a night shared with others.
     */
    private function formatName(?Performance $performance): string
    {
        if ($performance === null) {
            return $this->plan->token;
        }

        return $performance->title === null
            ? $performance->format->name
            : $performance->format->name.' — '.$performance->title;
    }
}
