<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An account as a name search lists it: enough to pick the right person, and
 * nothing more. The address stays the server's business.
 *
 * @property-read User $resource
 */
class UserSearchResult extends JsonResource
{
    /**
     * Transform the account into a search answer.
     *
     * @return array{id: int, name: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
        ];
    }
}
