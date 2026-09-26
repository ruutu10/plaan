<?php

namespace App\Concerns;

use App\Models\User;

/**
 * For form requests that take an e-mail address: the address is normalised the
 * way {@see User} stores it before the rules run, so a uniqueness check
 * compares like with like instead of passing an address that differs from a
 * stored one only in case.
 */
trait NormalizesEmailInput
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => User::normalizeEmail($this->input('email'))]);
        }
    }
}
