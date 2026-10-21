<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $teacher_id
 * @property int $academic_session_id
 * @property int|null $term_id
 * @property Carbon $attendance_date
 * @property AttendanceStatus $status
 * @property int|null $recorded_by
 * @property string|null $remark
 * @property-read Teacher $teacher
 * @property-read AcademicSession $academicSession
 * @property-read AcademicTerm|null $term
 * @property-read User|null $recordedBy
 */
class TeacherAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'academic_session_id',
        'term_id',
        'attendance_date',
        'status',
        'recorded_by',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'status' => AttendanceStatus::class,
        ];
    }

    public function setAttendanceDateAttribute(mixed $value): void
    {
        $this->attributes['attendance_date'] = Carbon::parse($value)->toDateString();
    }

    /**
     * @return BelongsTo<Teacher, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * @return BelongsTo<AcademicSession, $this>
     */
    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    /**
     * @return BelongsTo<AcademicTerm, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class, 'term_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * Scope query to a specific date.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeOnDate(Builder $query, string $date): Builder
    {
        return $query->where('attendance_date', Carbon::parse($date)->toDateString());
    }

    /**
     * Scope query to a specific date range.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeBetweenDates(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('attendance_date', [$startDate, $endDate]);
    }
}
