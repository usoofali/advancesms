<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentMark extends Model
{
    use HasFactory;

    protected $fillable = [
        'enrollment_id',
        'subject_id',
        'exam_id',
        'ca1',
        'ca2',
        'ca3',
        'exam_score',
        'grade_id',
        'teacher_comment',
    ];

    protected function casts(): array
    {
        return [
            'ca1' => 'decimal:2',
            'ca2' => 'decimal:2',
            'ca3' => 'decimal:2',
            'exam_score' => 'decimal:2',
            'total_score' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $mark) {
            $computedTotal = (float) ($mark->ca1 ?? 0)
                + (float) ($mark->ca2 ?? 0)
                + (float) ($mark->ca3 ?? 0)
                + (float) ($mark->exam_score ?? 0);

            // Auto-assign grade if not explicitly set
            if (! $mark->grade_id) {
                $matchedGrade = Grade::forScore($computedTotal);
                if ($matchedGrade) {
                    $mark->grade_id = $matchedGrade->id;
                }
            }
        });
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }
}
