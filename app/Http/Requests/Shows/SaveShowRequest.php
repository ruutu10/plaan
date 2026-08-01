<?php

namespace App\Http\Requests\Shows;

use App\Models\Show;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveShowRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request. The same rules
     * cover entering a show and correcting one, so which right is asked for
     * turns on whether the route names a show at all.
     */
    public function authorize(): bool
    {
        $show = $this->route('show');

        return $show instanceof Show
            ? Gate::allows('update', $show)
            : Gate::allows('create', Show::class);
    }

    /**
     * A cleared card reference arrives as an empty string from the form, and
     * means the show is not on the board at all.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('planka_card_id') === '') {
            $this->merge(['planka_card_id' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The owning group is held to the ones the user may assign: handing a show
     * to a group they do not belong to would give somebody else's group a show
     * and lock the editor out of what they just saved.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'team_id' => ['required', 'integer', Rule::in(Show::assignableTeams($this->user())->modelKeys())],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            // The card on the Planka board this show was announced on. Filled
            // by the import; typed in by hand for a show that was not.
            'planka_card_id' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'team_id.in' => __('Lavastuse saab anda ainult tiimile, kuhu sa ise kuulud.'),
        ];
    }
}
