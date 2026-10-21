<?php

namespace App\Enums;

enum EducationalStage: string
{
    case KINDERGARTEN = 'kindergarten';
    case NURSERY = 'nursery';
    case PRIMARY = 'primary';
    case JUNIOR_SECONDARY = 'junior_secondary';
    case SENIOR_SECONDARY = 'senior_secondary';

    public function label(): string
    {
        return match ($this) {
            self::KINDERGARTEN => 'Kindergarten / Pre-School',
            self::NURSERY => 'Nursery / Creche',
            self::PRIMARY => 'Primary School',
            self::JUNIOR_SECONDARY => 'Junior Secondary (JSS)',
            self::SENIOR_SECONDARY => 'Senior Secondary (SS)',
        };
    }

    /**
     * Get human-friendly label for any stage value (standard or custom).
     */
    public static function formatLabel(string $stage): string
    {
        $matched = self::tryFrom($stage);
        if ($matched) {
            return $matched->label();
        }

        return ucwords(str_replace(['_', '-'], ' ', $stage));
    }
}
