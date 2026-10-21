<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Database\Factories\FrontendGalleryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $title
 * @property string $category
 * @property string $image_path
 * @property string|null $caption
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read string|null $image_url
 */
class FrontendGallery extends Model
{
    /** @use HasFactory<FrontendGalleryFactory> */
    use HasFactory;

    use LogsActivity;

    public function getActivityLogName(): string
    {
        return 'galleries';
    }

    protected $table = 'frontend_galleries';

    protected $fillable = [
        'title',
        'category',
        'image_path',
        'caption',
        'sort_order',
        'is_active',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'image_url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope query to active gallery photos.
     *
     * @param  Builder<FrontendGallery>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope query ordered by sort_order ascending.
     *
     * @param  Builder<FrontendGallery>  $query
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('id', 'asc');
    }

    /**
     * Publicly accessible URL for gallery photo.
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->image_path ? Storage::disk('public')->url($this->image_path) : null,
        );
    }
}
