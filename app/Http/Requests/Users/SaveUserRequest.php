<?php

namespace App\Http\Requests\Users;

use App\Concerns\ProfileValidationRules;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Correcting somebody else's account details. The same fields the owner of the
 * account edits under Seaded, held to the same rules — a technician fixing a
 * misspelt name must not be able to enter an address the sign-up form would
 * have refused.
 *
 * Who may be here at all is settled by the route's `can:` guard, so this asks
 * only what a correction has to look like.
 */
class SaveUserRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->profileRules($this->subject()->id);
    }

    /**
     * The account being edited — somebody else's, not the signed-in user's.
     */
    private function subject(): User
    {
        $user = $this->route('user');

        abort_if(! $user instanceof User, 404);

        return $user;
    }
}
