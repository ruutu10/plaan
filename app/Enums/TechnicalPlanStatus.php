<?php

namespace App\Enums;

use App\Concerns\HasValues;

enum TechnicalPlanStatus: string
{
    use HasValues;

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
            self::Received => 'Tehniku kinnitatud',
            self::Archived => 'Arhiveeritud',
        };
    }

    /**
     * The statuses of a plan the technical team holds right now. A draft is
     * still the performer's own; an archived plan's night has been played.
     * Everything in between is a plan the crew is working from, and is what
     * the dashboard counts and what the performers are chased for.
     *
     * @return array<int, self>
     */
    public static function delivered(): array
    {
        return [self::Submitted, self::Received];
    }

    /**
     * The statuses of a plan somebody other than its author may open and fill
     * a new plan in from: what the crew holds, plus what has since been
     * archived — a plan for a format that has been played is exactly the one
     * worth copying for its next run.
     *
     * @return array<int, self>
     */
    public static function reusable(): array
    {
        return [...self::delivered(), self::Archived];
    }

    /**
     * Every status as a value/label pair, for a picker offering all of them.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status): array => ['value' => $status->value, 'label' => $status->label()],
            self::cases(),
        );
    }
}
