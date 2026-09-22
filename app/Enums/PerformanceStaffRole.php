<?php

namespace App\Enums;

use App\Concerns\HasValues;
use App\Services\PerformanceStaffSync;

/**
 * Who a person is to one performance, as the Planka import reads a card's cast
 * and crew. Nothing here is ever set by hand — see
 * {@see PerformanceStaffSync} — so the cases are only the jobs a
 * card can plainly name, not every role the house might ever have.
 */
enum PerformanceStaffRole: string
{
    use HasValues;

    case Performer = 'performer';
    case Host = 'host';
    case Technician = 'technician';
    case VideoOperator = 'video-operator';
    case TicketSeller = 'ticket-seller';
    case Bar = 'bar';

    /**
     * How the role is named on the staff list.
     */
    public function label(): string
    {
        return match ($this) {
            self::Performer => 'Esineja',
            self::Host => 'Õhtujuht',
            self::Technician => 'Heli- ja valgusmeister',
            self::VideoOperator => 'Operaator/videoprodutsent',
            self::TicketSeller => 'Piletimüüja',
            self::Bar => 'Baar',
        };
    }

    /**
     * Where the role sits when one person's jobs on a night are listed: the
     * stage first, then the side of it.
     *
     * Asked of the enum rather than sorted on the column, because `role` is a
     * database enum: MySQL orders it by the declaration and SQLite orders it
     * alphabetically, so the same two jobs would read one way in production and
     * the other in the tests.
     */
    public function listingOrder(): int
    {
        return match ($this) {
            self::Performer => 0,
            self::Host => 1,
            self::Technician => 2,
            self::VideoOperator => 3,
            self::TicketSeller => 4,
            self::Bar => 5,
        };
    }

    /**
     * The kind of person Jellyfin files this role under on the recording of a
     * night, or null for a role it has nobody for.
     *
     * Jellyfin's own list is fixed and short of a few things a theatre has: it
     * knows no compère and nobody selling tickets. A role it cannot name and
     * that is not on the recording anyway is left off the cast rather than
     * filed under something that reads wrong — the role's own {@see label()}
     * travels alongside as free text, so the ones that do go on are named the
     * way the house names them.
     */
    public function jellyfinPersonKind(): ?string
    {
        return match ($this) {
            self::Performer => 'Actor',
            self::Host => 'GuestStar',
            self::Technician => 'Engineer',
            self::VideoOperator => 'Producer',
            self::TicketSeller, self::Bar => null,
        };
    }
}
