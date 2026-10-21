<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $academic_session_id
 * @property string $name
 * @property int $term_number
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property bool $is_current
 * @property-read AcademicSession $academicSession
 */
class AcademicTerm extends Model
{
    use HasFactory;
    use LogsActivity;

    public function getActivityLogName(): string
    {
        return 'academic';
    }

    protected $fillable = [
        'academic_session_id',
        'name',
        'term_number',
        'start_date',
        'end_date',
        'is_current',
    ];

    protected function casts(): array
    {
        return [
            'term_number' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_current' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<AcademicSession, $this>
     */
    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    public function teacherSubjectAssignments(): HasMany
    {
        return $this->hasMany(TeacherSubjectAssignment::class);
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query->where('is_current', true);
    }

    public static function current(): ?self
    {
        return static::with('academicSession')->where('is_current', true)->first();
    }

    public function makeCurrent(): void
    {
        static::where('is_current', true)->update(['is_current' => false]);
        $this->update(['is_current' => true]);

        if (! $this->academicSession->is_current) {
            $this->academicSession->makeCurrent();
        }
    }

    public function canBeDeleted(): bool
    {
        return $this->exams()->doesntExist()
            && $this->teacherSubjectAssignments()->doesntExist();
    }
}
