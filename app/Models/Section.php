<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $class_id
 * @property string $name
 * @property int $capacity
 * @property SchoolClass|null $schoolClass
 */
class Section extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_id',
        'name',
        'capacity',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
        ];
    }

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'class_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'section_id');
    }

    public function teacherSubjectAssignments(): HasMany
    {
        return $this->hasMany(TeacherSubjectAssignment::class, 'section_id');
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->schoolClass ? "{$this->schoolClass->name} - {$this->name}" : $this->name
        );
    }
}
