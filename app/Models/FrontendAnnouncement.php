<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Database\Factories\FrontendAnnouncementFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string $category
 * @property string|null $summary
 * @property string $content
 * @property string|null $cover_image_path
 * @property string|null $attachment_path
 * @property string|null $attachment_name
 * @property bool $is_pinned
 * @property bool $is_published
 * @property Carbon|null $published_at
 * @property int|null $author_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read string|null $cover_image_url
 * @property-read string|null $attachment_url
 * @property-read User|null $author
 */
class FrontendAnnouncement extends Model
{
    /** @use HasFactory<FrontendAnnouncementFactory> */
    use HasFactory;

    use LogsActivity;

    public function getActivityLogName(): string
    {
        return 'announcements';
    }

    protected $table = 'frontend_announcements';

    protected $fillable = [
        'title',
        'slug',
        'category',
        'summary',
        'content',
        'cover_image_path',
        'attachment_path',
        'attachment_name',
        'is_pinned',
        'is_published',
        'published_at',
        'author_id',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'cover_image_url',
        'attachment_url',
    ];

    protected static function booted(): void
    {
        static::creating(function (FrontendAnnouncement $announcement) {
            if (empty($announcement->slug)) {
                $baseSlug = Str::slug($announcement->title);
                $slug = $baseSlug;
                $counter = 1;
                while (static::where('slug', $slug)->exists()) {
                    $slug = $baseSlug.'-'.$counter++;
                }
                $announcement->slug = $slug;
            }

            if ($announcement->is_published && ! $announcement->published_at) {
                $announcement->published_at = now();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Scope query to only published announcements.
     *
     * @param  Builder<FrontendAnnouncement>  $query
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->where(function (Builder $q) {
                $q->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    /**
     * Scope query to pinned announcements first.
     *
     * @param  Builder<FrontendAnnouncement>  $query
     */
    public function scopePinned(Builder $query): Builder
    {
        return $query->where('is_pinned', true);
    }

    /**
     * Publicly accessible URL for cover image.
     */
    protected function coverImageUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->cover_image_path ? Storage::disk('public')->url($this->cover_image_path) : null,
        );
    }

    /**
     * Publicly accessible URL for document attachment.
     */
    protected function attachmentUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->attachment_path ? Storage::disk('public')->url($this->attachment_path) : null,
        );
    }

    /**
     * User who authored the announcement.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
