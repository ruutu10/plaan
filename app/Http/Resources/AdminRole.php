<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Lang;
use Spatie\Permission\Models\Role;

/**
 * One role as the account screens show it: the slug it is held by, and the name
 * it is called in the interface. Both travel — the slug is what a grant is
 * written with, the label is what nobody outside the code should have to read.
 *
 * @property-read Role $resource
 */
class AdminRole extends JsonResource
{
    /**
     * Transform the role into a grantable option.
     *
     * @return array{name: string, label: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->resource->name,
            'label' => self::label($this->resource->name),
        ];
    }

    /**
     * The role's Estonian display name, falling back to the raw slug for a role
     * created since the translations were last written.
     */
    public static function label(string $name): string
    {
        return Lang::has("roles.$name", 'et')
            ? __("roles.$name", locale: 'et')
            : $name;
    }
}
