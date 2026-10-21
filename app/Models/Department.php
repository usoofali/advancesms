<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property-read Collection<int, Teacher> $teachers
 * @property-read Collection<int, Subject> $subjects
 */
class Department extends Model
{
    use HasFactory;
    use LogsActivity;

    public function getActivityLogName(): string
    {
        return 'academic';
    }

    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * @return HasMany<Teacher, $this>
     */
    public function teachers(): HasMany
    {
        return $this->hasMany(Teacher::class);
    }

    /**
     * @return HasMany<Subject, $this>
     */
    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    /**
     * Check if department can be safely deleted without cascade integrity violations.
     */
    public function canBeDeleted(): bool
    {
        return $this->teachers()->doesntExist()
            && $this->subjects()->doesntExist();
    }
}
