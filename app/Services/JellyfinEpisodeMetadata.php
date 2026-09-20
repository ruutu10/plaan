<?php

namespace App\Services;

use App\Enums\PerformanceStaffRole;
use App\Enums\TechnicalPlanStatus;
use App\Models\Performance;
use App\Models\PerformanceStaff;
use App\Models\TechnicalPlan;

/**
 * This app's account of a night, in the shape Jellyfin files an episode by.
 *
 * Only the keys the house actually owns are returned — the library keeps
 * plenty this knows nothing about, and {@see JellyfinClient::applyToItem()}
 * lays these over the rest rather than replacing it.
 *
 * Nothing here is locked on the Jellyfin side: the episode stays as editable
 * as any other, which also means a library refresh asked to replace all
 * metadata will throw this away. That is a deliberate trade — locking an item
 * stops Jellyfin fetching artwork for it too.
 */
class JellyfinEpisodeMetadata
{
    /**
     * A fixed tag on everything this app has written to, so the whole of it can
     * be found again from inside Jellyfin.
     */
    public const MARKER_TAG = 'plaan';

    /**
     * The month a season turns over on: a night in September opens a new one,
     * a night in June closes the one before it.
     */
    private const SEASON_STARTS_IN = 9;

    /**
     * What this app has to say about the night the given performance is.
     *
     * @return array<string, mixed>
     */
    public function for(Performance $performance): array
    {
        return [
            'Name' => $performance->displayName(),
            // Jellyfin ignores a plain SortName and reads this one instead. The
            // date rather than the name, so a season's episodes sit in the
            // order they were played however they are titled.
            'ForcedSortName' => $performance->startDate(),
            'Overview' => $this->overview($performance),
            // Venue-local midnight, stated as UTC: a client showing the true
            // moment of an early evening would put some nights on the day
            // before, and the date is the only part of it anybody reads.
            'PremiereDate' => $performance->startDate().'T00:00:00.0000000Z',
            'ProductionYear' => $performance->startsAt()->year,
            'Studios' => $this->studios($performance),
            'People' => $this->people($performance),
            'Tags' => $this->tags($performance),
            'ProviderIds' => $this->providerIds($performance),
        ];
    }

    /**
     * Who played it, as the one studio behind the episode. Empty for a night no
     * group is named on.
     *
     * @return array<int, array{Name: string}>
     */
    private function studios(Performance $performance): array
    {
        $performer = $performance->performerName();

        return blank($performer) ? [] : [['Name' => $performer]];
    }

    /**
     * The cast and crew of the night, as the Planka card named them.
     *
     * A role Jellyfin has nobody for is left off rather than filed under
     * something that reads wrong — see
     * {@see PerformanceStaffRole::jellyfinPersonKind()}. The role's Estonian
     * name travels as free text, which is what the library shows beside the
     * person.
     *
     * @return array<int, array{Name: string, Role: string, Type: string}>
     */
    private function people(Performance $performance): array
    {
        $people = [];

        foreach ($performance->staff as $member) {
            // The role is on the pivot row, so this only ever reads staff
            // fetched through Performance::staff() — as PerformanceStaffMember
            // does, and for the same reason.
            /** @var PerformanceStaff $staffing */
            $staffing = $member->getRelation('pivot');

            $kind = $staffing->role->jellyfinPersonKind();

            if ($kind === null) {
                continue;
            }

            $people[] = [
                'Name' => $member->name,
                'Role' => $staffing->role->label(),
                'Type' => $kind,
            ];
        }

        return $people;
    }

    /**
     * What the night was, in prose, for the description under the video.
     *
     * Plain text: Jellyfin's clients render an overview without markup, so the
     * two links sit on lines of their own rather than hiding behind words. The
     * whole of it is rewritten on every push — anything typed into Jellyfin by
     * hand is lost — which is what a one-way sync means.
     */
    private function overview(Performance $performance): string
    {
        $plaanUrl = route('formats.performances.show', [
            $performance->format_id,
            $performance->getKey(),
        ]);

        $lines = [
            $performance->format->description,
            '',
            $this->line('Esineja', $performance->performerName()),
            $this->line('Toimumiskoht', $performance->location),
            $this->line('Hooaeg', $this->season($performance)),
            '',
            $this->line('plaan', $plaanUrl),
            $this->line('Planka', PlankaClient::cardUrl($performance->planka_card_id)),
        ];

        // A format with nothing written about it and a night off the board
        // still want a readable paragraph rather than a run of blank lines.
        $lines = array_values(array_filter($lines, fn (?string $line): bool => $line !== null));

        return trim(implode("\n", $lines));
    }

    /**
     * One "label: value" line, or null when there is no value to state.
     */
    private function line(string $label, ?string $value): ?string
    {
        return blank($value) ? null : $label.': '.$value;
    }

    /**
     * What the episode is findable by, beyond the series it sits in.
     *
     * Deliberately a short list: a tag written on a *folder* — a series or a
     * season — is handed down to every child, so anyone widening this beyond
     * the single episode {@see JellyfinClient::applyToItem()} allows needs to
     * think about that first.
     *
     * @return array<int, string>
     */
    private function tags(Performance $performance): array
    {
        $tags = [
            self::MARKER_TAG,
            $performance->format->name,
            $performance->performerName(),
            $performance->location,
            $this->season($performance),
        ];

        foreach ($this->technicalTags($performance) as $tag) {
            $tags[] = $tag;
        }

        return array_values(array_unique(array_filter(
            $tags,
            fn (?string $tag): bool => filled($tag),
        )));
    }

    /**
     * What the technical plan says the night involved, for the handful of
     * things somebody might go looking for a recording of: live music, haze,
     * an interval.
     *
     * Read off the plan the night was actually played from — the most recent
     * one the crew were handed. A night nobody filed a plan for says nothing
     * rather than guessing.
     *
     * @return array<int, string>
     */
    private function technicalTags(Performance $performance): array
    {
        $plan = $performance->technicalPlans()
            ->whereIn('status', TechnicalPlanStatus::reusable())
            ->orderByDesc('id')
            ->first();

        if (! $plan instanceof TechnicalPlan) {
            return [];
        }

        $tags = [];

        if (($plan->sound['musicianMode'] ?? null) === 'yes') {
            $tags[] = 'elav muusika';
        }

        if (($plan->equipment['smoke'] ?? null) === 'yes') {
            $tags[] = 'suits';
        }

        // An interval is a scene entry carrying minutes rather than a scene —
        // see StoreTechnicalPlanRequest. One is enough to say the night has one.
        foreach ($plan->scenes as $scene) {
            if (filled($scene['intermission'] ?? null)) {
                $tags[] = 'vaheaeg';

                break;
            }
        }

        return $tags;
    }

    /**
     * The season a night belongs to, as the house counts them: September opens
     * one and it runs to the following summer, so a night in January is still
     * the autumn's season.
     */
    private function season(Performance $performance): string
    {
        $startsAt = $performance->startsAt();

        $opening = $startsAt->month >= self::SEASON_STARTS_IN
            ? $startsAt->year
            : $startsAt->year - 1;

        return $opening.'/'.($opening + 1);
    }

    /**
     * Where this episode's night is written down, for anything reading the
     * library rather than reading this app. Jellyfin keeps ids it knows no
     * provider for without complaint.
     *
     * @return array<string, string>
     */
    private function providerIds(Performance $performance): array
    {
        $ids = ['plaan' => (string) $performance->getKey()];

        if (filled($performance->planka_card_id)) {
            $ids['planka'] = (string) $performance->planka_card_id;
        }

        return $ids;
    }
}
