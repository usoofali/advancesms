<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Database\Factories\FrontendContactMessageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property string $subject
 * @property string $message
 * @property string $status
 * @property string|null $admin_notes
 * @property Carbon|null $replied_at
 * @property int|null $replied_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $repliedByUser
 */
class FrontendContactMessage extends Model
{
    /** @use HasFactory<FrontendContactMessageFactory> */
    use HasFactory;

    use LogsActivity;

    public function getActivityLogName(): string
    {
        return 'inquiries';
    }

    protected $table = 'frontend_contact_messages';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'status',
        'admin_notes',
        'replied_at',
        'replied_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'replied_at' => 'datetime',
        ];
    }

    /**
     * Scope query for unread messages.
     *
     * @param  Builder<FrontendContactMessage>  $query
     */
    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('status', 'unread');
    }

    /**
     * Relationship to the user who replied.
     *
     * @return BelongsTo<User, $this>
     */
    public function repliedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by');
    }
}
