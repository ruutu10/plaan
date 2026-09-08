<?php

namespace App\Http\Requests;

use App\Http\Resources\TechnicalPlan as TechnicalPlanResource;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreTechnicalPlanRequest extends FormRequest
{
    /**
     * How many cues one scene may carry. A scene wanting more than this is
     * really two scenes, and the technician has to read the list at the desk.
     */
    public const MAX_SOUNDS_PER_SCENE = 10;

    /**
     * How long a cue's link may be. Shared with the wizard through
     * {@see TechnicalPlanController::wizardConfig()}, so the field stops the
     * performer at the same place the rules below would.
     */
    public const MAX_SOUND_URL_LENGTH = 2000;

    /**
     * The longest interval a plan may name, in minutes.
     */
    public const MAX_INTERMISSION_MINUTES = 60;

    /**
     * The longest one part of a show may run, in minutes. The house's own cap
     * on a performance's running time, so no part can outlast the evening it
     * belongs to.
     */
    public const MAX_ACT_MINUTES = 240;

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
        // A "jah" on either sound question is only half an answer: the
        // technician needs to know which microphones, or which instrument.
        // A plan still being written may carry it undescribed — the wizard
        // saves drafts and hands out share links mid-write — so the detail is
        // only insisted on when the plan is handed over.
        $whenSubmitting = fn (string $question): array => $this->boolean('submit')
            ? ["required_if:{$question},yes"]
            : [];

        return [
            'token' => ['nullable', 'string', 'exists:technical_plans,token'],
            'submit' => ['boolean'],

            // The night is the only thing the wizard's first block contributes:
            // the format, the group, the date and the running time are read off
            // the performance rather than taken from the client, so whatever
            // else `meta` arrives with is ignored. A performer whose evening is
            // not on the books picks the stand-in performance — see
            // App\Models\Performance::placeholder().
            'meta' => ['required', 'array'],
            'meta.performanceId' => ['required', 'integer', 'exists:performances,id'],

            'sound' => ['required', 'array'],
            'sound.micsMode' => ['nullable', 'string', 'max:20'],
            'sound.micsDetail' => ['nullable', ...$whenSubmitting('sound.micsMode'), 'string', 'max:2000'],
            'sound.musicianMode' => ['nullable', 'string', 'max:20'],
            'sound.musicianDetail' => ['nullable', ...$whenSubmitting('sound.musicianMode'), 'string', 'max:2000'],

            'scenes' => ['required', 'array', 'min:1'],
            'scenes.*.id' => ['nullable', 'string', 'max:40'],
            'scenes.*.name' => ['nullable', 'string', 'max:255'],
            'scenes.*.light' => ['nullable', 'string', 'max:2000'],
            'scenes.*.sounds' => ['nullable', 'array', 'max:'.self::MAX_SOUNDS_PER_SCENE],
            'scenes.*.sounds.*.id' => ['nullable', 'string', 'max:40'],
            // Held to a real http(s) address, not merely to being a string: a
            // cue's link is rendered as an `href` in the mail, on the printout
            // and in the technician's view, so a `javascript:` or `data:` URL
            // would be somebody else's code running under whoever opened it.
            'scenes.*.sounds.*.url' => ['nullable', 'string', 'max:'.self::MAX_SOUND_URL_LENGTH, 'url:http,https'],
            'scenes.*.sounds.*.file' => ['nullable', 'array'],
            'scenes.*.sounds.*.file.id' => ['required_with:scenes.*.sounds.*.file', 'string', 'max:64'],
            'scenes.*.sounds.*.file.name' => ['nullable', 'string', 'max:255'],
            'scenes.*.sounds.*.file.size' => ['nullable', 'integer', 'min:0'],
            'scenes.*.sound' => ['nullable', 'string', 'max:2000'],
            'scenes.*.notes' => ['nullable', 'string', 'max:2000'],
            // A scene entry carrying minutes is the interval between two halves
            // of the show rather than a scene; every other entry leaves it null.
            'scenes.*.intermission' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_INTERMISSION_MINUTES],
            // How long the part of the show that interval ends runs. Only an
            // interval carries it; the part closing the show is worked out from
            // the running time rather than named.
            'scenes.*.actMinutes' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_ACT_MINUTES],

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
            'sound.micsDetail.required_if' => 'Kirjelda mikrofonide kogust ja paigutust laval.',
            'sound.musicianDetail.required_if' => 'Kirjelda instrumenti ja muusiku paigutust laval.',
            'scenes.*.sounds.*.url.url' => 'Heli link peab olema täielik http:// või https:// aadress.',
            'scenes.*.intermission.min' => 'Vaheaeg peab kestma vähemalt ühe minuti.',
            'scenes.*.intermission.max' => 'Vaheaeg saab kesta kuni '.self::MAX_INTERMISSION_MINUTES.' minutit.',
            'scenes.*.actMinutes.min' => 'Etenduse osa peab kestma vähemalt ühe minuti.',
            'scenes.*.actMinutes.max' => 'Etenduse osa saab kesta kuni '.self::MAX_ACT_MINUTES.' minutit.',
        ];
    }

    /**
     * A scene carries as many sounds as it needs, but each one of them is
     * either a link or an uploaded file — the wizard offers the two as a
     * choice, and the stored plan must reflect that. An entry that is neither
     * is an empty row nobody meant to add.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $scenes = (array) $this->input('scenes', []);

                // `scenes.min:1` counts entries, and an interval is one of
                // them: a plan made only of breaks describes no show at all.
                $intervals = array_filter(
                    $scenes,
                    fn ($scene): bool => is_array($scene)
                        && TechnicalPlanResource::intermission($scene['intermission'] ?? null) > 0,
                );

                if ($scenes !== [] && count($intervals) === count($scenes)) {
                    $validator->errors()->add('scenes', 'Plaanis peab olema vähemalt üks stseen.');
                }

                foreach ($scenes as $index => $scene) {
                    foreach ((array) ($scene['sounds'] ?? []) as $position => $sound) {
                        $hasUrl = filled($sound['url'] ?? null);
                        $hasFile = filled($sound['file']['id'] ?? null);

                        if ($hasUrl === $hasFile) {
                            $validator->errors()->add(
                                "scenes.{$index}.sounds.{$position}",
                                $hasUrl
                                    ? 'Helil saab olla kas link või üleslaaditud fail, mitte mõlemad.'
                                    : 'Igal helil peab olema kas link või üleslaaditud fail.',
                            );
                        }
                    }
                }
            },
        ];
    }
}
