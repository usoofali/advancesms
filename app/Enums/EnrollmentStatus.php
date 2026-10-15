<?php

namespace App\Enums;

enum EnrollmentStatus: string
{
    case ACTIVE = 'active';
    case PROMOTED = 'promoted';
    case REPEATED = 'repeated';
    case WITHDRAWN = 'withdrawn';
    case GRADUATED = 'graduated';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::PROMOTED => 'Promoted',
            self::REPEATED => 'Repeated',
            self::WITHDRAWN => 'Withdrawn',
            self::GRADUATED => 'Graduated',
        };
    }
}
