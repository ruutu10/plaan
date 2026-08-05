<?php

namespace App\Http\Requests\Teams;

use App\Enums\TeamRole;
use App\Http\Controllers\Teams\TeamAdminMemberController;
use App\Models\Team;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Putting a person straight into a team, which is the management screen's way
 * around the invitation flow. If no account exists for the address yet, one
 * is provisioned the same way an unrecognised magic-link address is — see
 * {@see TeamAdminMemberController::store()}.
 */
class AddTeamMemberRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('addMember', $this->team());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'role' => ['required', 'string', Rule::in(array_column(TeamRole::assignable(), 'value'))],
        ];
    }

    /**
     * Configure the validator instance. An address with no account yet is
     * fine — one is provisioned when the request is handled — so this only
     * has to catch an address that is already in the team.
     *
     * @return array<int, Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('email')) {
                    return;
                }

                $user = $this->member();

                if ($user && $this->team()->members()->whereKey($user->id)->exists()) {
                    $validator->errors()->add('email', __('See kasutaja on juba tiimi liige.'));
                }
            },
        ];
    }

    /**
     * The existing account at this address, if there is one already.
     */
    public function member(): ?User
    {
        return once(fn (): ?User => User::where('email', strtolower($this->string('email')->trim()->value()))->first());
    }

    /**
     * The team the member is being added to.
     */
    private function team(): Team
    {
        $team = $this->route('team');

        abort_if(! $team instanceof Team, 404);

        return $team;
    }
}
