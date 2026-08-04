<?php

namespace App\Enums;

/**
 * What the import found when it looked for the format a card names.
 */
enum ImportedFormatStatus
{
    /** The house already has this format, or the run has just made it. */
    case Existing;

    /** The house has never had this format, so the run made one. */
    case Created;

    /**
     * The only formats of this name were put aside here. A weekly job must not
     * undo somebody's deletion, so the card is left alone.
     */
    case Deleted;
}
