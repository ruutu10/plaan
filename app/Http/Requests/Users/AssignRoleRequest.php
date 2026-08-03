<?php

namespace App\Http\Requests\Users;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

/**
 * Granting a role. Which roles exist is read from the table rather than listed
 * here: they are created by migrations, and a name this request has never heard
 * of is a typo, not a new right.
 */
class AssignRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('updateRoles', $this->subject());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::exists(Role::class, 'name')],
        ];
    }

    /**
     * The account the role is being granted to.
     */
    private function subject(): User
    {
        $user = $this->route('user');

        abort_if(! $user instanceof User, 404);

        return $user;
    }
}
