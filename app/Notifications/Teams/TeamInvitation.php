<?php

namespace App\Notifications\Teams;

use App\Models\TeamInvitation as TeamInvitationModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param  string  $loginUrl  a magic link that signs the invited address in
     *                            directly and lands on the dashboard, where the
     *                            pending invitation waits to be accepted or
     *                            declined — see App\Listeners\SendTeamInvitation
     */
    public function __construct(public TeamInvitationModel $invitation, public string $loginUrl)
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
        $team = $this->invitation->team;
        $inviter = $this->invitation->inviter;

        return (new MailMessage)
            ->subject('Kutse liituda tiimiga '.$team->name)
            ->line('Plaan on Ruutu10 improteatri tehnikaplaneerimise süsteem, kus esinevad trupid kirjeldavad oma etenduste valgus- ja helivajadused ning tehnikatiim kogub need plaanid kokku ja juhib nende järgi õhtut.')
            ->line($inviter->name.' kutsus sind liituma Plaan tiimiga '.$team->name.'.')
            ->line('Logi sisse ja mine oma töölauale, et kutse vastu võtta või tagasi lükata.')
            ->action('Logi sisse', $this->loginUrl);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'team_id' => $this->invitation->team_id,
            'team_name' => $this->invitation->team->name,
            'role' => $this->invitation->role->value,
        ];
    }
}
