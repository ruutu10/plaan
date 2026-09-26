<?php

namespace App\Http\Requests\Users;

use App\Concerns\NormalizesEmailInput;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating an account by hand. Only the address is asked for: the name is
 * taken from it the same way every other provisioned account's is, and the
 * owner corrects it themselves once they are in.
 *
 * Who may be here at all is settled by the route's `can:` guard, so this asks
 * only what a new account has to look like.
 */
class CreateUserRequest extends FormRequest
{
    use NormalizesEmailInput;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            // Left out, the welcome is sent: an account nobody is told about
            // is the exception, not the rule.
            'sendWelcome' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Whether the newcomer is to be mailed their one-time link in.
     */
    public function sendsWelcome(): bool
    {
        return $this->boolean('sendWelcome', true);
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => 'Sisesta e-posti aadress.',
            'email.email' => 'Sisesta kehtiv e-posti aadress.',
            'email.unique' => 'Selle aadressiga konto on juba olemas.',
        ];
    }
}
