<?php

namespace App\Http\Requests\Users;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SearchUsersRequest extends FormRequest
{
    /**
     * The fewest characters a search may be made with. Short enough to find
     * anybody by a fragment of their name, long enough that the answers stay
     * few, so the house's accounts cannot be read out a letter at a time.
     */
    public const MIN_LENGTH = 4;

    /**
     * See {@see UserPolicy::search()}.
     */
    public function authorize(): bool
    {
        return Gate::allows('search', User::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:'.self::MIN_LENGTH, 'max:100'],
        ];
    }

    /**
     * The search as the words it is made of, each of which a name must
     * contain.
     *
     * @return list<string>
     */
    public function terms(): array
    {
        $terms = preg_split('/\s+/u', trim((string) $this->validated('q')), flags: PREG_SPLIT_NO_EMPTY);

        return $terms === false ? [] : $terms;
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'q.required' => __('Sisesta otsingusõna.'),
            'q.min' => __('Sisesta vähemalt :min tähemärki.'),
        ];
    }
}
