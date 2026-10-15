<?php

namespace App\Models;

use App\Enums\SecondaryStage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SchoolClass extends Model
{
    use HasFactory;

    protected $table = 'classes';

    protected $fillable = [
        'name',
        'level_order',
        'stage',
    ];

    protected function casts(): array
    {
        return [
            'level_order' => 'integer',
            'stage' => SecondaryStage::class,
        ];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class, 'class_id');
    }

    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'class_subjects', 'class_id', 'subject_id')
            ->withPivot('is_compulsory')
            ->withTimestamps();
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'class_id');
    }

    public function teacherSubjectAssignments(): HasMany
    {
        return $this->hasMany(TeacherSubjectAssignment::class, 'class_id');
    }
}
