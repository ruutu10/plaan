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
            self::Technician => 'Heli- ja valgustehnik',
            self::VideoOperator => 'Operaator/videoprodutsent',
            self::TicketSeller => 'Piletimüüja',
            self::Bar => 'Baar',
        };
    }
}
