<?php

namespace App\Http\Requests\Performances;

use App\Enums\PerformanceStaffRole;
use App\Models\Performance;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StorePerformanceStaffRequest extends FormRequest
{
    /**
     * Putting somebody on a night is a right of whoever may change that night —
     * the groups it belongs to and the crew, the same people the edit button
     * beside the staff list is offered to.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->performance());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * One person holds one job on one night once: a job the card already gives
     * them, or one somebody already added by hand, is refused rather than
     * written twice.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id'),
                Rule::unique('performance_staff', 'user_id')
                    ->where(fn (Builder $query) => $query
                        ->where('performance_id', $this->performance()->id)
                        ->where('role', $this->input('role'))),
            ],
            'role' => ['required', Rule::enum(PerformanceStaffRole::class)],
        ];
    }

    /**
     * The performance being staffed.
     */
    public function performance(): Performance
    {
        /** @var Performance $performance */
        $performance = $this->route('performance');

        return $performance;
    }

    /**
     * The chosen job.
     */
    public function role(): PerformanceStaffRole
    {
        return PerformanceStaffRole::from((string) $this->validated('role'));
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_id.required' => __('Vali kasutaja.'),
            'user_id.exists' => __('Sellist kasutajat ei ole.'),
            'user_id.unique' => __('See kasutaja on selles rollis juba meeskonnas.'),
            'role.required' => __('Vali roll.'),
            'role.enum' => __('Sellist rolli ei ole.'),
        ];
    }
}
