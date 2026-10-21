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
 * @property int $enrollment_id
 * @property Carbon $attendance_date
 * @property AttendanceStatus $status
 * @property int|null $recorded_by
 * @property string|null $remark
 */
class DailyAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'enrollment_id',
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
     * @return BelongsTo<Enrollment, $this>
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->where('attendance_date', $date);
    }

    public function scopePresent(Builder $query): Builder
    {
        return $query->where('status', AttendanceStatus::PRESENT);
    }

    public function scopeAbsent(Builder $query): Builder
    {
        return $query->where('status', AttendanceStatus::ABSENT);
    }
}
