<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $student_id
 * @property int $academic_session_id
 * @property int|null $previous_enrollment_id
 * @property int $class_id
 * @property int $section_id
 * @property string|null $roll_number
 * @property EnrollmentStatus $status
 */
class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'previous_enrollment_id',
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

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * The previous enrollment record of this student (e.g. prior grade/session before promotion).
     *
     * @return BelongsTo<self, $this>
     */
    public function previousEnrollment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_enrollment_id');
    }

    /**
     * The subsequent enrollment records branched from this enrollment.
     *
     * @return HasMany<self, $this>
     */
    public function nextEnrollments(): HasMany
    {
        return $this->hasMany(self::class, 'previous_enrollment_id');
    }

    /**
     * @return BelongsTo<AcademicSession, $this>
     */
    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * @return BelongsTo<Section, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    /**
     * @return HasMany<StudentMark, $this>
     */
    public function marks(): HasMany
    {
        return $this->hasMany(StudentMark::class);
    }

    /**
     * @return HasMany<DailyAttendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(DailyAttendance::class);
    }

    /**
     * Check if the enrollment record can safely be removed.
     */
    public function canBeDeleted(): bool
    {
        return $this->marks()->count() === 0 && $this->attendances()->count() === 0;
    }
}
