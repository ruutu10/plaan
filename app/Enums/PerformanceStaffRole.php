<?php

namespace App\Enums;

use App\Concerns\HasValues;
use App\Services\PerformanceStaffSync;

/**
 * Who a person is to one performance — as the Planka import reads a card's cast
 * and crew (see {@see PerformanceStaffSync}), or as somebody puts them on the
 * night by hand. Most cases are the jobs a card can plainly name, offered by
 * hand as well, so both ways in describe a night alike.
 *
 * {@see self::Other} is the exception: a job outside that list, entered by hand
 * for somebody who should be on the night all the same. The import never reads
 * it — see {@see importable()} — since the card's other jobs (marketing, the
 * project lead) are left off the staff on purpose.
 */
enum PerformanceStaffRole: string
{
    use HasValues;

    case Performer = 'performer';
    case Host = 'host';
    case Technician = 'technician';
    case VideoOperator = 'video-operator';
    case Photographer = 'photographer';
    case TicketSeller = 'ticket-seller';
    case Bar = 'bar';
    case Other = 'other';

    /**
     * The roles the Planka import may read off a card: every one but
     * {@see self::Other}, which only a person puts somebody in.
     *
     * @return list<self>
     */
    public static function importable(): array
    {
        return [
            self::Performer,
            self::Host,
            self::Technician,
            self::VideoOperator,
            self::Photographer,
            self::TicketSeller,
            self::Bar,
        ];
    }

    /**
     * Whether the Planka import may read this role off a card — see
     * {@see importable()}.
     */
    public function isImportable(): bool
    {
        return in_array($this, self::importable(), strict: true);
    }

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
            self::Photographer => 'Fotograaf',
            self::TicketSeller => 'Piletimüüja',
            self::Bar => 'Baar',
            self::Other => 'Muu',
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
            self::Photographer => 4,
            self::TicketSeller => 5,
            self::Bar => 6,
            self::Other => 7,
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
            self::Photographer, self::TicketSeller, self::Bar, self::Other => null,
        };
    }
}
