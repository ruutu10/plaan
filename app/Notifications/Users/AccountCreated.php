<?php

namespace App\Notifications\Users;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountCreated extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public string $loginUrl, public User $createdBy)
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
     * Get the mail representation of the notification. Written in Estonian
     * outright rather than through the translation files, so the mail does
     * not depend on the locale it happens to be rendered under.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tere tulemast Plaan\'i')
            ->line('Plaan on Ruutu10 improteatri tehnikaplaneerimise süsteem, kus esinevad trupid kirjeldavad oma etenduste valgus- ja helivajadused. Tehnikatiim kogub need plaanid kokku ja juhib nende järgi õhtut.')
            ->line("{$this->createdBy->name} lisas sind Plaan kasutajaks.")
            ->action('Ava Plaan', $this->loginUrl);
    }
}
