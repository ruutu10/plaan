<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreTechnicalPlanRequest extends FormRequest
{
    /**
     * Always return validation errors as JSON — these endpoints are consumed
     * by the wizard's XHR client, not by an Inertia form.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Andmete valideerimine ebaõnnestus.',
            'errors' => $validator->errors(),
        ], 422));
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['nullable', 'string', 'exists:technical_plans,token'],
            'submit' => ['boolean'],

            // The night is the only thing the wizard's first block contributes:
            // the show, the group, the date and the running time are read off
            // the performance rather than taken from the client, so whatever
            // else `meta` arrives with is ignored. A performer whose evening is
            // not on the books picks the stand-in performance — see
            // App\Models\Performance::placeholder().
            'meta' => ['required', 'array'],
            'meta.performanceId' => ['required', 'integer', 'exists:performances,id'],

            'sound' => ['required', 'array'],
            'sound.micsMode' => ['nullable', 'string', 'max:20'],
            'sound.micsDetail' => ['nullable', 'string', 'max:2000'],
            'sound.musicianMode' => ['nullable', 'string', 'max:20'],
            'sound.musicianDetail' => ['nullable', 'string', 'max:2000'],

            'scenes' => ['required', 'array', 'min:1'],
            'scenes.*.id' => ['nullable', 'string', 'max:40'],
            'scenes.*.name' => ['nullable', 'string', 'max:255'],
            'scenes.*.light' => ['nullable', 'string', 'max:2000'],
            'scenes.*.soundUrl' => ['nullable', 'string', 'max:2000'],
            'scenes.*.soundFile' => ['nullable', 'array'],
            'scenes.*.soundFile.id' => ['required_with:scenes.*.soundFile', 'string', 'max:64'],
            'scenes.*.soundFile.name' => ['nullable', 'string', 'max:255'],
            'scenes.*.soundFile.size' => ['nullable', 'integer', 'min:0'],
            'scenes.*.sound' => ['nullable', 'string', 'max:2000'],
            'scenes.*.notes' => ['nullable', 'string', 'max:2000'],

            'equipment' => ['required', 'array'],
            'equipment.items' => ['array'],
            'equipment.items.*.id' => ['nullable', 'string', 'max:40'],
            'equipment.items.*.name' => ['nullable', 'string', 'max:255'],
            'equipment.items.*.use' => ['nullable', 'string', 'max:1000'],
            'equipment.smoke' => ['nullable', 'string', 'max:20'],
            'equipment.suggestions' => ['nullable', 'string', 'max:20'],
            'equipment.suggestNote' => ['nullable', 'string', 'max:2000'],

            'extra' => ['required', 'array'],
            'extra.notes' => ['nullable', 'string', 'max:10000'],
            'extra.files' => ['array'],
            'extra.files.*.id' => ['required', 'string', 'max:64'],
            'extra.files.*.name' => ['nullable', 'string', 'max:255'],
            'extra.files.*.size' => ['nullable', 'integer', 'min:0'],
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
            'meta.performanceId.required' => 'Vali etendus, mille kohta plaan käib.',
            'meta.performanceId.exists' => 'Valitud etendust ei leitud. Vali etendus uuesti.',
        ];
    }

    /**
     * A scene's sound is either linked or uploaded, never both — the wizard
     * offers the two as a choice, and the stored plan must reflect that.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach ((array) $this->input('scenes', []) as $index => $scene) {
                    if (filled($scene['soundUrl'] ?? null) && filled($scene['soundFile']['id'] ?? null)) {
                        $validator->errors()->add(
                            "scenes.{$index}.soundFile",
                            'Stseenil saab olla kas helifaili link või üleslaaditud fail, mitte mõlemad.',
                        );
                    }
                }
            },
        ];
    }
}
