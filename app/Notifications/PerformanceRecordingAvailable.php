<?php

namespace App\Notifications;

use App\Http\Resources\PerformanceDetails;
use App\Listeners\NotifyRecordingAvailable;
use App\Models\PerformanceRecording;
use App\Models\User;
use App\Services\JellyfinClient;
use App\Services\RecordingFileDetails;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The word to whoever wrote a night's technical plan that the video of it is
 * up — sent the first time somebody says where the recording is, and only
 * then. Who gets it, and that they only get it once, is
 * {@see NotifyRecordingAvailable}'s to settle.
 */
class PerformanceRecordingAvailable extends Notification implements AboutRecord, ShouldQueue
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
     * The recording the letter announces.
     */
    public function auditSubject(): ?Model
    {
        return $this->recording;
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
        $downloadUrl = $this->downloadUrl();

        $mail = (new MailMessage)
            ->subject('Etenduse salvestus · '.$this->label())
            ->view('emails.performance-recording-available', [
                'formatName' => $performance?->displayName(),
                'details' => PerformanceDetails::make($performance)->resolve(),
                // The item as the library holds it, so a link that was pasted
                // in an odd shape still opens the right episode. What was
                // typed is the fallback. Only the house can open the library,
                // so nobody else is shown the way in.
                'recordingUrl' => $notifiable instanceof User && $notifiable->isHouseStaff()
                    ? JellyfinClient::itemUrl($this->recording->item_id) ?? $this->recording->url
                    : null,
                'downloadUrl' => $downloadUrl,
                // Read when the letter is sent rather than when it was queued;
                // a library that does not answer leaves it out.
                'recordingFile' => $downloadUrl === null ? null : app(RecordingFileDetails::class)->for($this->recording),
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
     * Where the video can be downloaded with a login here, when it can be.
     */
    private function downloadUrl(): ?string
    {
        $performance = $this->recording->performance;

        if ($performance === null || $this->recording->item_id === null || ! JellyfinClient::isConfigured()) {
            return null;
        }

        return route('performances.recording.download', $performance);
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
