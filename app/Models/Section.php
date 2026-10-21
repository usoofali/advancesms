<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $class_id
 * @property string $name
 * @property int $capacity
 * @property SchoolClass|null $schoolClass
 * @property-read string $full_name
 */
class Section extends Model
{
    use HasFactory;
    use LogsActivity;

    public function getActivityLogName(): string
    {
        return 'academic';
    }

    protected $fillable = [
        'class_id',
        'name',
        'capacity',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'full_name',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'section_id');
    }

    /**
     * @return HasMany<TeacherSubjectAssignment, $this>
     */
    public function teacherSubjectAssignments(): HasMany
    {
        return $this->hasMany(TeacherSubjectAssignment::class, 'section_id');
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->schoolClass ? "{$this->schoolClass->name} - {$this->name}" : $this->name
        );
    }

    /**
     * Get the current active enrollment count for a specific academic session.
     */
    public function activeEnrollmentCount(int $academicSessionId): int
    {
        return $this->enrollments()
            ->where('academic_session_id', $academicSessionId)
            ->where('status', EnrollmentStatus::ACTIVE)
            ->count();
    }

    /**
     * Check if the section has reached its maximum student capacity for a session.
     */
    public function isFullForSession(int $academicSessionId): bool
    {
        return $this->activeEnrollmentCount($academicSessionId) >= $this->capacity;
    }

    /**
     * Check if section can be safely deleted without cascade integrity violations.
     */
    public function canBeDeleted(): bool
    {
        return $this->enrollments()->doesntExist()
            && $this->teacherSubjectAssignments()->doesntExist();
    }
}
