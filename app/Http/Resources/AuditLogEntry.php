<?php

namespace App\Http\Resources;

use App\Concerns\LogsModelActivity;
use App\Models\Format;
use App\Models\Performance;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;

/**
 * One row of the audit-log feed: what happened, to what, and who did it —
 * see {@see LogsModelActivity} for how the trail is written.
 *
 * @property-read Activity $resource
 */
class AuditLogEntry extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /**
     * Transform the activity into a listable row.
     *
     * @return array{
     *     id: int,
     *     event: string|null,
     *     description: string,
     *     subjectType: string|null,
     *     subjectId: int|string|null,
     *     subjectLabel: string|null,
     *     causerName: string|null,
     *     createdAt: string|null,
     * }
     */
    public function toArray(Request $request): array
    {
        $activity = $this->resource;
        $causer = $activity->causer;

        return [
            'id' => $activity->id,
            'event' => $activity->event,
            'description' => $activity->description,
            // The subject may since have been deleted; the type still says
            // what kind of record this was about.
            'subjectType' => $activity->subject_type ? Str::headline(class_basename($activity->subject_type)) : null,
            'subjectId' => $activity->subject_id,
            'subjectLabel' => $this->subjectLabel($activity->subject),
            // Null reads as the system itself — see LogsModelActivity. Every
            // causer this application ever writes is a User, but the relation
            // is typed generically, so this is where that is made narrow.
            'causerName' => $causer instanceof User ? $causer->name : null,
            'createdAt' => $activity->created_at?->toIso8601String(),
        ];
    }

    /**
     * The name a subject reads by, for the record types worth naming in the
     * feed rather than left as a bare id. Null for a subject type that is not
     * one of these, or one since deleted — the row falls back to its id.
     */
    private function subjectLabel(?Model $subject): ?string
    {
        return match (true) {
            $subject instanceof Team, $subject instanceof User, $subject instanceof Format => $subject->name,
            // A shared evening's act carries its own name; the format's own
            // performance — the ordinary case — does not, and reads by its
            // format's name instead.
            $subject instanceof Performance => $subject->title ?? $subject->format->name,
            default => null,
        };
    }
}
