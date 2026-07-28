<?php

namespace App\Http\Requests\Teams;

use App\Enums\TeamRole;
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
 * around the invitation flow: the account has to exist already, and it is only
 * ever added once.
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
     * Configure the validator instance. Both checks read the same account, so
     * they are made here rather than as two rules that each fetch it.
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

                if (! $user) {
                    $validator->errors()->add('email', __('Selle e-postiga kasutajat pole. Kutsu ta tiimi kutsega.'));

                    return;
                }

                if ($this->team()->members()->whereKey($user->id)->exists()) {
                    $validator->errors()->add('email', __('See kasutaja on juba tiimi liige.'));
                }
            },
        ];
    }

    /**
     * The account being added, if there is one with the given address.
     */
    public function member(): ?User
    {
        return once(fn (): ?User => User::where('email', $this->string('email')->trim()->value())->first());
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
