<?php

namespace App\Http\Resources;

use App\Models\Performance as PerformanceModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One night offered to a picker, as a bare value and the name it reads by: the
 * shape a select on the frontend takes, the same as a status option's.
 *
 * @property-read PerformanceModel $resource
 */
class SelectablePerformance extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /**
     * Transform the performance into a selectable option.
     *
     * @return array{value: int, label: string}
     */
    public function toArray(Request $request): array
    {
        $performance = $this->resource;

        return [
            'value' => $performance->id,
            'label' => $this->label($performance),
        ];
    }

    /**
     * When the night is played and what is played on it — "01.08.2026 ·
     * Festival 2026" — which is what tells two runs of the same format apart in
     * a list. The stand-in performance is a filing drawer for the plans whose
     * evening is not on the books rather than a night anybody plays, and its
     * date sits years out, so it reads by its name alone. Compare
     * {@see AuditLogEntry}, which names a plan's night the same way.
     */
    private function label(PerformanceModel $performance): string
    {
        return $performance->isPlaceholder()
            ? $performance->displayName()
            : $performance->startsAt()->format('d.m.Y').' · '.$performance->displayName();
    }
}
