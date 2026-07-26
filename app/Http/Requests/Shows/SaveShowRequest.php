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
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('show'));
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
            'team_id.in' => __('Lavastuse saab anda ainult trupile, kuhu sa ise kuulud.'),
        ];
    }
}
