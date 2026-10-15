<?php

namespace App\Enums;

enum SecondaryStage: string
{
    case JUNIOR_SECONDARY = 'junior_secondary';
    case SENIOR_SECONDARY = 'senior_secondary';

    public function label(): string
    {
        return match ($this) {
            self::JUNIOR_SECONDARY => 'Junior Secondary (JSS)',
            self::SENIOR_SECONDARY => 'Senior Secondary (SS)',
        };
    }
}
