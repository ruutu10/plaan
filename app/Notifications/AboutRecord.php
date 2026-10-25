<?php

namespace App\Notifications;

use App\Listeners\LogMailSent;
use Illuminate\Database\Eloquent\Model;

/**
 * A notification written about one record in particular — a plan, a night, a
 * team — which its audit-trail entry is filed under, so the letter shows up
 * beside everything else that happened to that record. One that is not
 * about anything but its recipient is filed under the recipient instead — see
 * {@see LogMailSent}.
 */
interface AboutRecord
{
    /**
     * The record this letter is about, or null when it has since gone.
     */
    public function auditSubject(): ?Model;
}
