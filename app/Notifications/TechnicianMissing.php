<?php

namespace App\Notifications;

use App\Console\Commands\RemindAboutMissingTechnicians;
use App\Models\Performance;
use App\Services\PlankaClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

/**
 * The technical team's daily digest of the upcoming nights nobody has signed
 * on to run sound and light for.
 *
 * One mail names every performance still missing a technician rather than one
 * mail per performance — see {@see RemindAboutMissingTechnicians},
 * which sends this again, list and all, for as long as any gap remains.
 */
class TechnicianMissing extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  Collection<int, Performance>  $performances  ordered soonest first
     */
    public function __construct(public Collection $performances)
    {
        //
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject())
            ->view('emails.technician-missing', [
                'performances' => $this->performances
                    ->map(fn (Performance $performance): array => [
                        'label' => $performance->title === null
                            ? $performance->format->name
                            : $performance->format->name.' — '.$performance->title,
                        'startsAt' => $performance->startsAt(),
                        'cardUrl' => PlankaClient::cardUrl($performance->planka_card_id),
                    ])
                    ->all(),
            ]);
    }

    /**
     * The subject line: how many nights are still open, so the tally is
     * readable from the inbox without opening the mail.
     */
    private function subject(): string
    {
        $count = $this->performances->count();

        return $count === 1
            ? 'Tehnik puudu · 1 etendus'
            : sprintf('Tehnik puudu · %d etendust', $count);
    }
}
