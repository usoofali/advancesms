<?php

namespace App\Models;

use App\Enums\EducationalStage;
use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property int $level_order
 * @property string $stage
 * @property-read string $stage_label
 * @property-read Collection<int, Section> $sections
 * @property-read Collection<int, Subject> $subjects
 * @property-read Collection<int, Enrollment> $enrollments
 * @property-read Collection<int, TeacherSubjectAssignment> $teacherSubjectAssignments
 */
class SchoolClass extends Model
{
    use HasFactory;
    use LogsActivity;

    public function getActivityLogName(): string
    {
        return 'academic';
    }

    protected $table = 'classes';

    protected $fillable = [
        'name',
        'level_order',
        'stage',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'stage_label',
    ];

    protected function casts(): array
    {
        return [
            'level_order' => 'integer',
            'stage' => 'string',
        ];
    }

    protected function stageLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => EducationalStage::formatLabel((string) $this->stage)
        );
    }

    /**
     * @return HasMany<Section, $this>
     */
    public function sections(): HasMany
    {
        return $this->hasMany(Section::class, 'class_id');
    }

    /**
     * @return BelongsToMany<Subject, $this>
     */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(Subject::class, 'class_subjects', 'class_id', 'subject_id')
            ->withPivot('is_compulsory')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'class_id');
    }

    /**
     * @return HasMany<TeacherSubjectAssignment, $this>
     */
    public function teacherSubjectAssignments(): HasMany
    {
        return $this->hasMany(TeacherSubjectAssignment::class, 'class_id');
    }

    /**
     * Check if class can be safely deleted without cascade integrity violations.
     */
    public function canBeDeleted(): bool
    {
        return $this->enrollments()->doesntExist()
            && $this->teacherSubjectAssignments()->doesntExist()
            && $this->subjects()->doesntExist();
    }

    /**
     * Re-sequence all classes sequentially starting from 1 with zero gaps or duplicate numbers.
     */
    public static function resequence(): void
    {
        $classes = static::query()->orderBy('level_order')->orderBy('id')->get();
        foreach ($classes as $index => $cls) {
            $expectedOrder = $index + 1;
            if ($cls->level_order !== $expectedOrder) {
                $cls->updateQuietly(['level_order' => $expectedOrder]);
            }
        }
    }
}
