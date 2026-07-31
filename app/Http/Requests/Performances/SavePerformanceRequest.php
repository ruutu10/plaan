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
     * A cleared duration or start time arrives as an empty string from a plain
     * HTML client. Neither is a bad value: one is a performance nobody has
     * timed, the other one nobody has given an hour, and both are handled
     * further down as an absence.
     */
    protected function prepareForValidation(): void
    {
        foreach (['duration', 'start_time'] as $field) {
            if ($this->input($field) === '') {
                $this->merge([$field => null]);
            }
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
            // Curtain-up on the venue's clock, as a 24-hour "19:00". Left out,
            // the performance takes the house's usual hour — see
            // Performance::momentFrom().
            'start_time' => ['nullable', 'date_format:H:i'],
            // Minutes. A performance may be timed loosely or not at all, but a full
            // day of it is a typo rather than a plan.
            'duration' => ['nullable', 'integer', 'min:1', 'max:1440'],
            // Whether the performance is still waiting to be reviewed. Sent only
            // by the screens that offer the toggle; left out, the performance
            // keeps the standing it had — a new one is vouched for by the adding.
            'is_draft' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The validated input as the model takes it: the date and the start time
     * are two fields on the form but one stored moment, so they are folded
     * together here rather than in each controller action.
     *
     * `is_draft` is left exactly as it arrived — present or absent — so a save
     * that says nothing about it goes on saying nothing.
     *
     * @return array<string, mixed>
     */
    public function performanceAttributes(): array
    {
        $data = $this->validated();

        $data['date'] = Performance::momentFrom($data['date'], $data['start_time'] ?? null);

        unset($data['start_time']);

        return $data;
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
            'start_time.date_format' => __('Etenduse algusaeg pole korrektne.'),
            'duration.max' => __('Etenduse kestus saab olla kuni 1440 minutit.'),
        ];
    }
}
