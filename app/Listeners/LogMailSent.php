<?php

namespace App\Listeners;

use App\Notifications\AboutRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\SentMessage;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Str;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Keep an audit-trail entry for every letter the application mails out: what
 * it was, and who it went to — openly and blindly.
 *
 * Read off the message as it actually left rather than off the notification,
 * so the entry says what the recipient saw. Every letter is queued, so by the
 * time it goes out nobody is signed in: the sender is always the system, and
 * whoever set it off has an entry of their own for that.
 */
class LogMailSent
{
    public function handle(NotificationSent $event): void
    {
        // A notifiable with no address to send to comes back with nothing
        // sent, and there is nothing to keep a trail of.
        if ($event->channel !== 'mail' || ! $event->response instanceof SentMessage) {
            return;
        }

        $message = $event->response->getOriginalMessage();

        if (! $message instanceof Email) {
            return;
        }

        $to = $this->addresses($message->getTo());
        $blindCopies = $this->addresses($message->getBcc());
        $subject = $this->subjectOf($event);

        $entry = activity()
            ->event('mail_sent')
            ->withProperties([
                'notification' => class_basename($event->notification),
                'subject' => $message->getSubject(),
                'to' => $to,
                'bcc' => $blindCopies,
            ]);

        if ($subject !== null) {
            $entry->performedOn($subject);
        }

        $entry->log(sprintf(
            'E-mail "%s" sent to %s%s',
            $message->getSubject(),
            implode(', ', $to),
            $blindCopies === [] ? '' : sprintf(
                ' and %d blind %s',
                count($blindCopies),
                Str::plural('copy', count($blindCopies)),
            ),
        ));
    }

    /**
     * The record the letter is filed under: what it is about, or — for one
     * about nothing but its recipient, like a sign-in link — the recipient's
     * own account. A letter to a bare address files under nothing.
     */
    private function subjectOf(NotificationSent $event): ?Model
    {
        if ($event->notification instanceof AboutRecord) {
            return $event->notification->auditSubject();
        }

        return $event->notifiable instanceof Model ? $event->notifiable : null;
    }

    /**
     * @param  array<int, Address>  $addresses
     * @return array<int, string>
     */
    private function addresses(array $addresses): array
    {
        return array_map(fn (Address $address): string => $address->getAddress(), $addresses);
    }
}
