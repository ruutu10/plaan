<?php

namespace App\Rules;

use App\Models\TechnicalPlan;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Translation\PotentiallyTranslatedString;
use Symfony\Component\Mime\MimeTypes;

/**
 * Guards an uploaded file against the extension allowlist it is meant for, and
 * against its own content: a file's real, content-sniffed MIME type must be one
 * that an allowed extension can legitimately produce, so a script that has
 * merely been renamed (e.g. `shell.jpg`) is rejected on its content, not just
 * its name.
 *
 * Uploads destined for a specific collection can be held to a narrower list
 * than the general one — a scene's sound file accepts audio only.
 */
class AllowedAttachment implements ValidationRule
{
    /**
     * Collections whose uploads are narrowed, mapped to the config key holding
     * the extensions they accept.
     */
    private const NARROWED_COLLECTIONS = [
        TechnicalPlan::SOUND_COLLECTION => 'technical_plan.sound_extensions',
    ];

    public function __construct(private ?string $collection = null) {}

    /**
     * The collections an upload may declare itself destined for.
     *
     * @return array<int, string>
     */
    public static function collections(): array
    {
        return array_keys(self::NARROWED_COLLECTIONS);
    }

    /**
     * The lower-case extensions an upload for the given collection may have.
     * A collection's own list only ever narrows the general allowlist.
     *
     * @return array<int, string>
     */
    public static function extensionsFor(?string $collection = null): array
    {
        $allowed = array_map(
            fn (string $extension): string => strtolower($extension),
            (array) config('media-library.allowed_extensions', []),
        );

        $narrowing = self::NARROWED_COLLECTIONS[$collection] ?? null;

        if ($narrowing !== null) {
            $allowed = array_intersect($allowed, array_map(
                fn (string $extension): string => strtolower($extension),
                (array) config($narrowing, []),
            ));
        }

        return array_values($allowed);
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $allowed = self::extensionsFor($this->collection);
        $extension = strtolower($value->getClientOriginalExtension());

        if ($allowed !== [] && ! in_array($extension, $allowed, true)) {
            $fail('Seda failitüüpi ei saa üles laadida.');

            return;
        }

        $mimeTypes = MimeTypes::getDefault();

        // Allowed extensions Symfony has no MIME mapping for (such as `.qlc`)
        // cannot be content-checked and are left to the extension allowlist.
        if ($mimeTypes->getMimeTypes($extension) === []) {
            return;
        }

        $allowedMimeTypes = [];

        foreach ($allowed as $allowedExtension) {
            $allowedMimeTypes = array_merge($allowedMimeTypes, $mimeTypes->getMimeTypes($allowedExtension));
        }

        if (! in_array($value->getMimeType(), $allowedMimeTypes, true)) {
            $fail('Faili sisu ei vasta lubatud failitüübile.');
        }
    }
}
