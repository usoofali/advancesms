<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grade extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'grade_point',
        'mark_from',
        'mark_to',
        'remark',
    ];

    protected function casts(): array
    {
        return [
            'grade_point' => 'decimal:2',
            'mark_from' => 'decimal:2',
            'mark_to' => 'decimal:2',
        ];
    }

    /**
     * @return HasMany<StudentMark, $this>
     */
    public function studentMarks(): HasMany
    {
        return $this->hasMany(StudentMark::class);
    }

    /**
     * Find matching grade record for a given numeric score.
     */
    public static function forScore(float $score): ?self
    {
        $rounded = round($score, 2);

        $grade = static::where('mark_from', '<=', $rounded)
            ->where('mark_to', '>=', $rounded)
            ->first();

        if ($grade) {
            return $grade;
        }

        return static::where('mark_from', '<=', $rounded)
            ->orderByDesc('mark_from')
            ->first() ?? static::orderBy('mark_from')->first();
    }
}
