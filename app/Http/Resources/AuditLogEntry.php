<?php

namespace App\Http\Resources;

use App\Concerns\LogsModelActivity;
use App\Data\RecordLinks;
use App\Models\Format;
use App\Models\Performance;
use App\Models\Team;
use App\Models\TechnicalPlan;
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
     *     subjectUrl: string|null,
     *     causerName: string|null,
     *     createdAt: string|null,
     * }
     */
    public function toArray(Request $request): array
    {
        $activity = $this->resource;
        $causer = $activity->causer;
        $subject = $activity->subject;

        return [
            'id' => $activity->id,
            'event' => $activity->event,
            'description' => $activity->description,
            // The subject may since have been deleted; the type still says
            // what kind of record this was about.
            'subjectType' => $activity->subject_type ? Str::headline(class_basename($activity->subject_type)) : null,
            'subjectId' => $activity->subject_id,
            'subjectLabel' => $this->subjectLabel($subject),
            'subjectUrl' => $this->subjectUrl($subject, $request->user()),
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
            $subject instanceof Performance => $this->performanceName($subject),
            // Nothing on a plan itself says what it is about, so it reads by
            // the night it was written for. A plan whose performance has since
            // been put aside falls back to its id like any other lost subject.
            $subject instanceof TechnicalPlan => $subject->performance
                ? $this->nightLabel($subject->performance)
                : null,
            default => null,
        };
    }

    /**
     * A performance's own name: a shared evening's act carries one; the
     * format's own performance — the ordinary case — does not, and reads by its
     * format's name instead.
     */
    private function performanceName(Performance $performance): string
    {
        return $performance->title ?? $performance->format->name;
    }

    /**
     * The night a plan was written for: when it is played, and what is played.
     * The stand-in performance is a filing drawer for the plans whose evening is
     * not on the books rather than a night anybody plays, and its date sits
     * years out — see {@see Performance::placeholder()} — so it is left off.
     */
    private function nightLabel(Performance $performance): string
    {
        $name = $this->performanceName($performance);

        return $performance->isPlaceholder()
            ? $name
            : $performance->startsAt()->format('d.m.Y').' · '.$name;
    }

    /**
     * Where this row's record may be opened, or null when there is no screen
     * for it — a membership, an invitation, a subject since deleted.
     *
     * A link is only offered to a reader who holds the house-wide right over
     * that kind of record, so one that would land on a refusal is left as plain
     * text instead. The audit trail is the technicians' own screen and they hold
     * every one of these, but the trail's own permission is grantable on its
     * own, and reading the log is not itself a right to open what it names.
     * Settled on the permissions alone, so a two-hundred-row feed asks the
     * database nothing extra — compare {@see RecordLinks}, which
     * answers the same question a row at a time for readers without them.
     */
    private function subjectUrl(?Model $subject, ?User $viewer): ?string
    {
        if ($viewer === null) {
            return null;
        }

        return match (true) {
            $subject instanceof User => $viewer->can(User::MANAGE_PERMISSION)
                ? route('admin.users.edit', $subject)
                : null,
            $subject instanceof Team => $viewer->can(Team::EDIT_ALL_PERMISSION)
                ? route('admin.teams.edit', $subject)
                : null,
            // The stand-in format and its performance are filing drawers rather
            // than anything a reader clicking their name is after.
            $subject instanceof Format => $viewer->can(Format::EDIT_ALL_PERMISSION) && ! $subject->isPlaceholder()
                ? route('formats.edit', $subject)
                : null,
            // A performance is opened through the format it belongs to; it has
            // no page of its own.
            $subject instanceof Performance => $viewer->can(Performance::EDIT_ALL_PERMISSION) && ! $subject->isPlaceholder()
                ? route('formats.performances.show', [$subject->format_id, $subject->getKey()])
                : null,
            $subject instanceof TechnicalPlan => $viewer->can(TechnicalPlan::VIEW_ALL_PERMISSION)
                ? route('technical-plans.show', $subject)
                : null,
            default => null,
        };
    }
}
