<?php

namespace App\Http\Requests;

use App\Models\TechnicalPlanComment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTechnicalPlanCommentRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.TechnicalPlanComment::MAX_LENGTH],
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
            'body.required' => 'Kommentaar ei saa olla tühi.',
            'body.max' => 'Kommentaar võib olla kuni '.TechnicalPlanComment::MAX_LENGTH.' tähemärki pikk.',
        ];
    }
}
