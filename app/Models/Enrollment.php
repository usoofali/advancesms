<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'academic_session_id',
        'class_id',
        'section_id',
        'roll_number',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => EnrollmentStatus::class,
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function marks(): HasMany
    {
        return $this->hasMany(StudentMark::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(DailyAttendance::class);
    }
}
