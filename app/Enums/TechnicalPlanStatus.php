<?php

namespace App\Enums;

enum TechnicalPlanStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Received = 'received';
    case Archived = 'archived';

    /**
     * Get the Estonian display label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Mustand',
            self::Submitted => 'Esitatud',
            self::Received => 'Tehnik kätte saanud',
            self::Archived => 'Arhiveeritud',
        };
    }

    /**
     * Get the list of backing values for the database enum column.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * The statuses of a plan that has reached the technical team. A draft is
     * still the performer's own; an archived plan is done with. Everything in
     * between is a plan the crew holds, and is what the dashboard counts and
     * what a new plan may be filled in from.
     *
     * @return array<int, self>
     */
    public static function delivered(): array
    {
        return [self::Submitted, self::Received];
    }
}
