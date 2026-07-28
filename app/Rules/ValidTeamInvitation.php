<?php

namespace App\Rules;

use App\Models\TeamInvitation;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Log;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidTeamInvitation implements ValidationRule
{
    public function __construct(protected ?User $user)
    {
        //
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof TeamInvitation || ! $this->user instanceof User) {
            $this->refuse($fail, 'unknown_invitation', null);

            return;
        }

        if ($value->isAccepted()) {
            $this->refuse($fail, 'already_accepted', $value, __('This invitation has already been accepted.'));

            return;
        }

        if ($value->isExpired()) {
            $this->refuse($fail, 'expired', $value, __('This invitation has expired.'));

            return;
        }

        if (strtolower($value->email) !== strtolower($this->user->email)) {
            // Somebody is holding an invitation code addressed to another
            // person — a forwarded mail as often as anything worse, but the
            // shape is the same either way.
            $this->refuse($fail, 'wrong_recipient', $value);
        }
    }

    /**
     * Turn an invitation down, recording why. A user stuck on "this invitation
     * is not valid" cannot say which of the four reasons they hit; this can.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    protected function refuse(Closure $fail, string $reason, ?TeamInvitation $invitation, ?string $message = null): void
    {
        Log::warning('Team invitation refused', [
            'reason' => $reason,
            'invitation_id' => $invitation?->id,
            'team_id' => $invitation?->team_id,
            'user_id' => $this->user?->id,
        ]);

        $fail($message ?? __('This invitation was sent to a different email address.'));
    }
}
