<?php

namespace App\Http\Requests\Performances;

use App\Models\Performance;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SavePerformanceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request. The same rules
     * cover adding a performance and correcting one, so which right is asked for
     * turns on whether the route names a performance at all.
     */
    public function authorize(): bool
    {
        $performance = $this->route('performance');

        return $performance instanceof Performance
            ? Gate::allows('update', $performance)
            : Gate::allows('create', [Performance::class, $this->route('show')]);
    }

    /**
     * A cleared duration field arrives as an empty string from a plain HTML
     * client, which is a performance without a duration rather than a bad integer.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('duration') === '') {
            $this->merge(['duration' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The show is not among them: a performance belongs to the show in the URL and
     * is never moved to another by saving it.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            // Minutes. A performance may be timed loosely or not at all, but a full
            // day of it is a typo rather than a plan.
            'duration' => ['nullable', 'integer', 'min:1', 'max:1440'],
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
            'date.required' => __('Etenduse kuupäev on kohustuslik.'),
            'date.date_format' => __('Etenduse kuupäev pole korrektne.'),
            'duration.max' => __('Etenduse kestus saab olla kuni 1440 minutit.'),
        ];
    }
}
