<?php

namespace App\Rules;

use App\Services\JellyfinClient;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * A recording link has to name an item, because the item is the whole point of
 * it: the address is stored as a link for a person to follow, but what the push
 * writes to is the item that address names.
 *
 * The reading itself is {@see JellyfinClient::itemIdFromUrl()}'s, which knows
 * every shape Jellyfin's interface writes an address in. This is only where
 * "it named nothing" is said out loud, in the language the form is in.
 */
class JellyfinItemUrl implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        if (JellyfinClient::itemIdFromUrl($value) === null) {
            $fail(__('Jellyfini link pole korrektne. Kopeeri video aadress Jellyfini veebiliidesest.'));
        }
    }
}
