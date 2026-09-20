<?php

namespace App\Enums;

use App\Concerns\HasValues;

/**
 * Where a performance stands on the house's books.
 *
 * A draft is what the Planka import registers: a claim read off a card written
 * for people rather than for us, whose date may be wrong or whose evening may
 * not be happening at all. It waits until the technical team has looked it over
 * and made it upcoming, which is the standing every performance entered by hand
 * starts from. An archived performance has been played — see
 * App\Console\Commands\ArchivePerformances, which moves them there weekly.
 */
enum PerformanceStatus: string
{
    use HasValues;

    case Draft = 'draft';
    case Upcoming = 'upcoming';
    case Archived = 'archived';

    /**
     * The statuses of a performance the house has vouched for: a night somebody
     * has confirmed, whether it is still to come or already behind us. What
     * this excludes is the one thing that matters — a draft nobody has reviewed
     * is not a performance a technical plan may be filed under.
     *
     * @return array<int, self>
     */
    public static function vouchedFor(): array
    {
        return [self::Upcoming, self::Archived];
    }
}
