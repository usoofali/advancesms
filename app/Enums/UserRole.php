<?php

namespace App\Enums;

enum UserRole: string
{
    case SUPERADMIN = 'superadmin';
    case ADMIN = 'admin';
    case EXAM_OFFICER = 'exam_officer';
    case TEACHER = 'teacher';
    case STUDENT = 'student';
    case GUARDIAN = 'guardian';

    public function label(): string
    {
        return match ($this) {
            self::SUPERADMIN => 'Super Administrator',
            self::ADMIN => 'Administrator',
            self::EXAM_OFFICER => 'Examination Officer',
            self::TEACHER => 'Teacher / Form Master',
            self::STUDENT => 'Student',
            self::GUARDIAN => 'Guardian / Parent',
        };
    }
}
