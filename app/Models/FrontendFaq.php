<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Database\Factories\FrontendFaqFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $category
 * @property string $question
 * @property string $answer
 * @property int $sort_order
 * @property bool $is_published
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class FrontendFaq extends Model
{
    /** @use HasFactory<FrontendFaqFactory> */
    use HasFactory;

    use LogsActivity;

    public function getActivityLogName(): string
    {
        return 'faqs';
    }

    protected $table = 'frontend_faqs';

    protected $fillable = [
        'category',
        'question',
        'answer',
        'sort_order',
        'is_published',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    /**
     * Scope query to published FAQs.
     *
     * @param  Builder<FrontendFaq>  $query
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * Scope query ordered by sort_order ascending.
     *
     * @param  Builder<FrontendFaq>  $query
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('id', 'asc');
    }
}
