<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exam extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_session_id',
        'academic_term_id',
        'name',
        'max_ca1',
        'max_ca2',
        'max_ca3',
        'max_exam',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'max_ca1' => 'decimal:2',
            'max_ca2' => 'decimal:2',
            'max_ca3' => 'decimal:2',
            'max_exam' => 'decimal:2',
            'is_published' => 'boolean',
        ];
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function studentMarks(): HasMany
    {
        return $this->hasMany(StudentMark::class);
    }

    protected function maxTotal(): Attribute
    {
        return Attribute::make(
            get: fn () => (float) $this->max_ca1 + (float) $this->max_ca2 + (float) $this->max_ca3 + (float) $this->max_exam
        );
    }
}
