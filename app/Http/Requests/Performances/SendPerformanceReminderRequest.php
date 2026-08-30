<?php

namespace App\Http\Requests\Performances;

use App\Models\Performance;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SendPerformanceReminderRequest extends FormRequest
{
    /**
     * Chasing the performers of a night is a right of whoever may change that
     * night — its own group and the crew, the same people the edit and delete
     * buttons beside it are offered to.
     */
    public function authorize(): bool
    {
        return Gate::allows('update', $this->performance());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Who may be written to is not the client's to decide: the addresses come
     * off the group playing this performance, and an id from anywhere else is
     * refused however the request was put together.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => [
                'integer',
                Rule::in($this->candidates()->modelKeys()),
            ],
        ];
    }

    /**
     * The chosen performers, as models ready to be written to.
     *
     * @return Collection<int, User>
     */
    public function performers(): Collection
    {
        /** @var list<int> $chosen */
        $chosen = $this->validated('user_ids');

        return $this->candidates()->whereIn('id', $chosen);
    }

    /**
     * The performance the reminder is about.
     */
    public function performance(): Performance
    {
        /** @var Performance $performance */
        $performance = $this->route('performance');

        return $performance;
    }

    /**
     * Everybody who could be chased about this night: the members of the group
     * playing it — the performance's own when the evening is shared, the
     * format's otherwise.
     *
     * @return Collection<int, User>
     */
    private function candidates(): Collection
    {
        /** @var Collection<int, User> */
        return $this->performance()->performedBy()->members ?? new Collection;
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'user_ids.required' => __('Vali vähemalt üks saaja.'),
            'user_ids.min' => __('Vali vähemalt üks saaja.'),
            'user_ids.*.in' => __('Meeldetuletust saab saata ainult etendust mängiva tiimi liikmetele.'),
        ];
    }
}
