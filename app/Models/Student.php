<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Student extends Model
{
    use HasFactory;

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function currentEnrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class)->latestOfMany();
    }

    public function studentMarks(): HasManyThrough
    {
        return $this->hasManyThrough(StudentMark::class, Enrollment::class);
    }

    public function attendances(): HasManyThrough
    {
        return $this->hasManyThrough(DailyAttendance::class, Enrollment::class);
    }
}
