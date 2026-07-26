<?php

namespace App\Enums;

enum SignupSource: string
{
    case AnonymousPlan = 'anonymous-plan';
    case SignupForm = 'signup-form';

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
