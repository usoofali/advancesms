<?php

namespace App\Models;

use App\Enums\EnrollmentStatus;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Student extends Model
{
    use HasFactory;
    use LogsActivity;

    public function getActivityLogName(): string
    {
        return 'people';
    }

    protected $fillable = [
        'user_id',
        'guardian_id',
        'admission_number',
        'admission_date',
        'date_of_birth',
        'state_of_origin',
        'lga',
    ];

    protected function casts(): array
    {
        return [
            'admission_date' => 'date',
            'date_of_birth' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Guardian, $this>
     */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * @return HasOne<Enrollment, $this>
     */
    public function currentEnrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class)->latestOfMany();
    }

    /**
     * @return HasOne<Enrollment, $this>
     */
    public function graduatingEnrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class)
            ->where('status', EnrollmentStatus::GRADUATED)
            ->latestOfMany();
    }

    /**
     * Check if student is graduated / alumnus.
     */
    public function isGraduated(): bool
    {
        return $this->currentEnrollment?->status === EnrollmentStatus::GRADUATED;
    }

    /**
     * @return HasManyThrough<StudentMark, Enrollment, $this>
     */
    public function studentMarks(): HasManyThrough
    {
        return $this->hasManyThrough(StudentMark::class, Enrollment::class);
    }

    /**
     * @return HasManyThrough<DailyAttendance, Enrollment, $this>
     */
    public function attendances(): HasManyThrough
    {
        return $this->hasManyThrough(DailyAttendance::class, Enrollment::class);
    }

    /**
     * Check if the student can safely be deleted.
     */
    public function canBeDeleted(): bool
    {
        return $this->studentMarks()->count() === 0 && $this->attendances()->count() === 0;
    }

    /**
     * Sanitize an admission number for credential generation.
     * Removes slashes, spaces, and characters not acceptable for usernames and email local parts.
     * e.g., 'YUM-SA/001' -> 'gsss-gsa001', 'GSSS/0001' -> 'gsss0001'.
     */
    public static function sanitizeAdmissionNumber(string $admissionNumber): string
    {
        $noSlashesOrSpaces = str_replace(['/', '\\', ' '], '', $admissionNumber);
        $clean = strtolower(preg_replace('/[^a-zA-Z0-9._-]/', '', $noSlashesOrSpaces) ?? '');

        return $clean ?: 'student';
    }

    /**
     * Generate a default student email from their admission number.
     * Pattern: {sanitized_admission}@sms.com (e.g. gsss-gsa001@sms.com)
     */
    public static function generateStudentEmail(string $admissionNumber, ?int $ignoreUserId = null): string
    {
        $base = static::sanitizeAdmissionNumber($admissionNumber);
        $candidate = "{$base}@sms.com";
        $counter = 1;

        while (User::query()->where('email', $candidate)->when($ignoreUserId, fn ($q) => $q->where('id', '!=', $ignoreUserId))->exists()) {
            $candidate = "{$base}{$counter}@sms.com";
            $counter++;
        }

        return $candidate;
    }

    /**
     * Generate a default student username from their admission number.
     * e.g., 'YUM-SA/001' -> 'gsss-gsa001'
     */
    public static function generateStudentUsername(string $admissionNumber, ?int $ignoreUserId = null): string
    {
        $base = static::sanitizeAdmissionNumber($admissionNumber);
        $candidate = $base;
        $counter = 1;

        while (User::query()->where('username', $candidate)->when($ignoreUserId, fn ($q) => $q->where('id', '!=', $ignoreUserId))->exists()) {
            $candidate = "{$base}_{$counter}";
            $counter++;
        }

        return $candidate;
    }

    /**
     * Generate the next sequential admission number for the school.
     * Pattern: {school_code}/{0001} or {school_code}-0001 (based on school setting).
     */
    public static function generateNextAdmissionNumber(?string $schoolCode = null): string
    {
        if (empty($schoolCode)) {
            $setting = SchoolSetting::query()->first();
            $schoolCode = $setting && ! empty($setting->school_code)
                ? trim($setting->school_code)
                : 'GSSS';
        }

        $lastStudent = static::query()->orderByDesc('id')->first();
        $sequence = 1;

        if ($lastStudent !== null && preg_match('/(\d+)$/', $lastStudent->admission_number, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        } else {
            $sequence = static::query()->count() + 1;
        }

        do {
            $candidate = sprintf('%s/%04d', $schoolCode, $sequence);
            $exists = static::query()->where('admission_number', $candidate)->exists();
            if ($exists) {
                $sequence++;
            }
        } while ($exists);

        return $candidate;
    }
}
