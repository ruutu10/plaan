<?php

namespace App\Http\Requests\Formats;

use App\Models\Format;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveFormatRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request. The same rules
     * cover entering a format and correcting one, so which right is asked for
     * turns on whether the route names a format at all.
     */
    public function authorize(): bool
    {
        $format = $this->route('format');

        return $format instanceof Format
            ? Gate::allows('update', $format)
            : Gate::allows('create', Format::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The owning group is held to the ones the user may assign: handing a format
     * to a group they do not belong to would give somebody else's group a format
     * and lock the editor out of what they just saved.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'team_id' => ['required', 'integer', Rule::in(Format::assignableTeams($this->user())->modelKeys())],
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
            'team_id.in' => __('Formaadi saab anda ainult tiimile, kuhu sa ise kuulud.'),
        ];
    }
}
