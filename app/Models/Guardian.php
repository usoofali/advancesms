<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Guardian extends Model
{
    use HasFactory;
    use LogsActivity;

    public function getActivityLogName(): string
    {
        return 'people';
    }

    protected $fillable = [
        'user_id',
        'name',
        'relationship',
        'phone',
        'email',
        'address',
        'occupation',
    ];

    /**
     * Mutator to guarantee uppercase guardian name.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value !== null ? mb_strtoupper(trim(preg_replace('/\s+/', ' ', $value))) : null,
        );
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Student, $this>
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /**
     * Check if the guardian can safely be deleted.
     */
    public function canBeDeleted(): bool
    {
        return $this->students()->count() === 0;
    }
}
