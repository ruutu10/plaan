<?php

namespace App\Http\Resources;

use App\Models\Performance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The night a mail is about, as the "Etendus" table every mail carries reads
 * it: every value already rendered to the string the reader sees, by the same
 * rules {@see PlanDocument} renders a plan by. One shape for every letter, so
 * the table is drawn once — `resources/views/components/emails/performance-details.blade.php`
 * — and a mail can only add rows of its own, never leave a common one out.
 *
 * @property-read Performance|null $resource
 */
class PerformanceDetails extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /**
     * What to call a night that is not there — the plan's own key, for a plan
     * filled in ahead of any registered performance or whose performance has
     * since been put aside.
     */
    private string $fallbackName = '';

    public function withFallbackName(?string $name): static
    {
        $this->fallbackName = (string) $name;

        return $this;
    }

    /**
     * The venue is the one value left unrendered: a night in the house's own
     * room names none, and the table leaves the row out rather than show a
     * blank one.
     *
     * @return array{formatName: string, performer: string, technicians: string, date: string, startTime: string, duration: string, location: string|null}
     */
    public function toArray(Request $request): array
    {
        $performance = $this->resource;

        return [
            'formatName' => PlanDocument::dash($performance?->displayName() ?? $this->fallbackName),
            'performer' => PlanDocument::dash($performance?->performerName()),
            'technicians' => PlanDocument::techniciansLine($performance?->technicianNames() ?? []),
            'date' => PlanDocument::dash($performance?->startsAt()->format('d.m.Y')),
            'startTime' => PlanDocument::dash($performance?->startTime()),
            'duration' => PlanDocument::duration($performance?->duration),
            'location' => $performance?->location,
        ];
    }
}
