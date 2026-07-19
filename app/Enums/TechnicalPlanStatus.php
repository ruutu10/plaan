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
}
