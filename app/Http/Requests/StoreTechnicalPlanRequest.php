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

            'meta' => ['required', 'array'],
            'meta.performanceId' => ['nullable', 'integer', 'exists:performances,id'],
            'meta.performer' => ['nullable', 'string', 'max:255'],
            'meta.showName' => ['nullable', 'string', 'max:255'],
            'meta.showDate' => ['nullable', 'date'],
            'meta.duration' => ['nullable', 'integer', 'min:1', 'max:240'],
            'meta.description' => ['nullable', 'string', 'max:5000'],

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
}
