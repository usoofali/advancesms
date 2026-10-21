<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Database\Factories\FrontendSliderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $title
 * @property string|null $subtitle
 * @property string|null $badge
 * @property string|null $primary_button_text
 * @property string|null $primary_button_url
 * @property string|null $secondary_button_text
 * @property string|null $secondary_button_url
 * @property string|null $image_path
 * @property int $sort_order
 * @property bool $is_active
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read string|null $image_url
 */
class FrontendSlider extends Model
{
    /** @use HasFactory<FrontendSliderFactory> */
    use HasFactory;

    use LogsActivity;

    public function getActivityLogName(): string
    {
        return 'sliders';
    }

    protected $table = 'frontend_sliders';

    protected $fillable = [
        'title',
        'subtitle',
        'badge',
        'primary_button_text',
        'primary_button_url',
        'secondary_button_text',
        'secondary_button_url',
        'image_path',
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
     * Scope query to only active sliders.
     *
     * @param  Builder<FrontendSlider>  $query
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope query ordered by sort_order ascending.
     *
     * @param  Builder<FrontendSlider>  $query
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('id', 'asc');
    }

    /**
     * Publicly accessible URL for slide background image.
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->image_path ? Storage::disk('public')->url($this->image_path) : null,
        );
    }
}
