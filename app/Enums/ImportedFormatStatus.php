<?php

namespace App\Enums;

/**
 * What the import found when it looked for the show a card names.
 */
enum ImportedShowStatus
{
    /** The house already has this show, or the run has just made it. */
    case Existing;

    /** The house has never had this show, so the run made one. */
    case Created;

    /**
     * The only shows of this name were put aside here. A weekly job must not
     * undo somebody's deletion, so the card is left alone.
     */
    case Deleted;
}
