<?php

namespace App\Notifications;

use App\Listeners\NotifyRecordingAvailable;
use App\Models\PerformanceRecording;
use App\Services\JellyfinClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The word to whoever wrote a night's technical plan that the video of it is
 * up — sent the first time somebody says where the recording is, and only
 * then. Who gets it, and that they only get it once, is
 * {@see NotifyRecordingAvailable}'s to settle.
 */
class PerformanceRecordingAvailable extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param  array<int, string>  $blindCopies  The people who were on stage that
     *                                           night, who are told a video of
     *                                           them exists without being shown
     *                                           each other's addresses. Empty
     *                                           unless this is the one letter
     *                                           carrying them — see
     *                                           {@see NotifyRecordingAvailable}.
     */
    public function __construct(
        public PerformanceRecording $recording,
        public array $blindCopies = [],
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
        $performance = $this->recording->performance;

        $mail = (new MailMessage)
            ->subject('Etenduse salvestus on olemas · '.$this->label())
            ->view('emails.performance-recording-available', [
                'formatName' => $performance?->displayName(),
                'performer' => $performance?->performerName(),
                'startsAt' => $performance?->startsAt(),
                'location' => $performance?->location,
                // The item as the library holds it, so a link that was pasted
                // in an odd shape still opens the right episode. What was
                // typed is the fallback, never nothing.
                'recordingUrl' => JellyfinClient::itemUrl($this->recording->item_id)
                    ?? $this->recording->url,
                'techEmail' => (string) config('technical_plan.tech_email'),
            ]);

        // Blind rather than copied openly: the people on stage are told a video
        // of them exists without the letter handing every one of their
        // addresses to everybody else on it.
        if ($this->blindCopies !== []) {
            $mail->bcc($this->blindCopies);
        }

        return $mail;
    }

    /**
     * How the night is named in the subject line: what was played and when.
     */
    private function label(): string
    {
        $performance = $this->recording->performance;

        $parts = array_values(array_filter([
            $performance?->format->name,
            $performance?->startsAt()->format('d.m.Y'),
        ]));

        return $parts === [] ? (string) $this->recording->id : implode(' · ', $parts);
    }
}
