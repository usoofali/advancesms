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

    public function studentMarks(): HasMany
    {
        return $this->hasMany(StudentMark::class);
    }

    /**
     * Find matching grade record for a given numeric score.
     */
    public static function forScore(float $score): ?self
    {
        return static::where('mark_from', '<=', $score)
            ->where('mark_to', '>=', $score)
            ->first();
    }
}
