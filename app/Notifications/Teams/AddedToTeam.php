<?php

namespace App\Notifications\Teams;

use App\Models\Team;
use App\Models\User;
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
    public function __construct(public Team $team, public string $loginUrl, public User $addedBy)
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
            ->line(__('Plaan is the technical planning system of the Ruutu10 improv theatre, where performing troupes describe the light and sound needs of their shows, and the technical team gathers those plans and runs the night by them.'))
            ->line(__(':adderName added you to the :teamName team.', [
                'adderName' => $this->addedBy->name,
                'teamName' => $this->team->name,
            ]))
            ->line(__('Log in with the button below to verify your account and get started.'))
            ->action(__('Log in'), $this->loginUrl);
    }
}
