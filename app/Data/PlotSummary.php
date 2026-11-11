<?php

namespace App\Data;

use App\Services\PerformancePlotSummariser;

/**
 * What the model made of a recording's subtitles: a summary and the themes it
 * names, or the reason it could not read one out of them. See
 * {@see PerformancePlotSummariser}.
 */
final readonly class PlotSummary
{
    /**
     * @param  list<string>  $tags  Thematic tags, lowercase and unique.
     */
    public function __construct(
        public ?string $summary,
        public ?string $reason = null,
        public array $tags = [],
    ) {}

    /**
     * Whether the subtitles held a plot worth writing up.
     */
    public function isUsable(): bool
    {
        return filled($this->summary);
    }
}
