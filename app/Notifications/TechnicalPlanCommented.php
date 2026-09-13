<?php

namespace App\Notifications;

use App\Models\TechnicalPlanComment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Word to the other side of a plan's conversation that something was said —
 * sent to the plan's author when the crew writes, and to the technical team's
 * address when the performer's side does. Carries the remark itself, so the
 * recipient can tell from the letter whether it needs them, and a link back to
 * the plan, where it is answered.
 */
class TechnicalPlanCommented extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public TechnicalPlanComment $comment) {}

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
        $plan = $this->comment->plan;
        $performance = $plan->performance;

        return (new MailMessage)
            ->subject('Uus kommentaar tehnikaplaanile · '.$this->planLabel())
            ->view('emails.technical-plan-commented', [
                'formatName' => $performance?->displayName() ?? $plan->token,
                'performer' => $performance?->performerName(),
                'startsAt' => $performance?->startsAt(),
                'authorName' => $this->comment->user->name,
                'fromTechnicalTeam' => $this->comment->from_technical_team,
                'body' => $this->comment->body,
                'publicUrl' => route('technical-plan.public', $plan),
                'techEmail' => (string) config('technical_plan.tech_email'),
            ]);
    }

    /**
     * How the plan is named in the subject line: the format and the night it is
     * for, or — for a plan filled in ahead of any registered performance — its
     * own key. The same rule {@see TechnicalPlanReceived} names it by.
     */
    private function planLabel(): string
    {
        $performance = $this->comment->plan->performance;

        $parts = array_values(array_filter([
            $performance?->format->name,
            $performance?->startsAt()->format('d.m.Y'),
        ]));

        return $parts === [] ? $this->comment->plan->token : implode(' · ', $parts);
    }
}
