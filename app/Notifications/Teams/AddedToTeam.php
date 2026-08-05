<?php

namespace App\Notifications\Teams;

use App\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AddedToTeam extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Team $team, public string $loginUrl)
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
            ->subject(__("You've been added to :teamName", ['teamName' => $this->team->name]))
            ->line(__('You have been added to the :teamName team.', ['teamName' => $this->team->name]))
            ->line(__('Log in with the button below to verify your account and get started.'))
            ->action(__('Log in'), $this->loginUrl);
    }
}
