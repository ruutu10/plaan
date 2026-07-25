<?php

namespace App\Notifications;

use App\Http\Resources\TechnicalPlan as TechnicalPlanResource;
use App\Models\TechnicalPlan;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The plan a performer has just submitted, mailed out in full — to the
 * performer as their own copy, and to the technical team as the plan they will
 * play the show from. Both get the same document; only the opening line and
 * the sharing link's framing differ.
 */
class TechnicalPlanSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public TechnicalPlan $plan)
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
        return (new MailMessage)
            ->subject('Tehnikaplaan · '.$this->planLabel())
            ->view('emails.technical-plan-submitted', [
                'plan' => TechnicalPlanResource::make($this->plan)->resolve(),
                'publicUrl' => route('technical-plan.public', $this->plan),
                'contactEmail' => $this->plan->user?->email,
                'isAuthor' => $notifiable instanceof User && $notifiable->is($this->plan->user),
            ]);
    }

    /**
     * How the plan is named in the subject line: the show it belongs to, or —
     * for a plan filled in ahead of any registered performance — its key.
     */
    private function planLabel(): string
    {
        $performance = $this->plan->performance;

        $parts = array_values(array_filter([
            $performance?->show_name,
            $performance?->show_date?->format('d.m.Y'),
        ]));

        return $parts === [] ? $this->plan->token : implode(' · ', $parts);
    }
}
