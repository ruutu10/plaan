<?php

namespace App\Enums;

use App\Concerns\HasValues;
use App\Console\Commands\ImportPlankaPerformances;

/**
 * What put a format or a performance on the books: somebody typing it in, or the
 * weekly reading of the Planka board. The records look the same once they are
 * there, and the difference matters when one of them is wrong — a date nobody
 * chose was read off a card, and the card is where to go and look.
 */
enum CreatedBy: string
{
    use HasValues;

    /** Entered by hand, on the management screens. */
    case Manual = 'manual';

    /** Registered by {@see ImportPlankaPerformances}. */
    case PlankaImport = 'planka-import';
}
