<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTechnicalPlanPerformanceRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * A night the house has not vouched for is refused: the picker leaves the
     * imported drafts out — their date may be wrong or the evening may not be
     * happening — and a plan must not be filed under one by naming its id. A
     * performance since put aside is no target either.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'performance_id' => [
                'required',
                'integer',
                Rule::exists('performances', 'id')
                    // The draft flag is compared inside a callback so the
                    // boolean reaches the database as a boolean. Handing it to
                    // where() instead folds the rule into its string form,
                    // where false becomes an empty string that only MySQL is
                    // lenient enough to read back as a zero.
                    ->where(fn (Builder $query) => $query->where('is_draft', false))
                    ->whereNull('deleted_at'),
            ],
        ];
    }
}
