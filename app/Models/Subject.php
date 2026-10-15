<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id',
        'code',
        'name',
        'is_elective',
    ];

    protected function casts(): array
    {
        return [
            'is_elective' => 'boolean',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(SchoolClass::class, 'class_subjects', 'subject_id', 'class_id')
            ->withPivot('is_compulsory')
            ->withTimestamps();
    }

    public function teacherSubjectAssignments(): HasMany
    {
        return $this->hasMany(TeacherSubjectAssignment::class);
    }

    public function studentMarks(): HasMany
    {
        return $this->hasMany(StudentMark::class);
    }
}
