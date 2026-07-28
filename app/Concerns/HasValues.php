<?php

namespace App\Concerns;

/**
 * For backed enums that also have to be handed to something expecting plain
 * strings — a database enum column, or a validation rule.
 */
trait HasValues
{
    /**
     * The enum's backing values, in the order the cases are declared.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
