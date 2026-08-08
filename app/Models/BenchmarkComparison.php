<?php

namespace App\Models;

use Database\Factories\BenchmarkComparisonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_offer_id
 * @property int|null $recorded_by_user_id
 * @property int|null $published_by_user_id
 * @property int|null $supersedes_comparison_id
 * @property int $benchmark_price_minor
 * @property string $market_name
 * @property string $source_type
 * @property string $source_reference
 * @property Carbon $observed_at
 * @property bool $is_demo
 * @property int|null $saving_minor
 * @property int|null $saving_percentage_bps
 * @property Carbon|null $published_at
 * @property Carbon|null $superseded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'product_offer_id',
    'recorded_by_user_id',
    'published_by_user_id',
    'supersedes_comparison_id',
    'benchmark_price_minor',
    'market_name',
    'source_type',
    'source_reference',
    'observed_at',
    'is_demo',
    'saving_minor',
    'saving_percentage_bps',
    'published_at',
    'superseded_at',
])]
class BenchmarkComparison extends Model
{
    /** @use HasFactory<BenchmarkComparisonFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'benchmark_price_minor' => 'integer',
            'observed_at' => 'datetime',
            'is_demo' => 'boolean',
            'saving_minor' => 'integer',
            'saving_percentage_bps' => 'integer',
            'published_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ProductOffer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(ProductOffer::class, 'product_offer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }

    /**
     * @return BelongsTo<BenchmarkComparison, $this>
     */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(BenchmarkComparison::class, 'supersedes_comparison_id');
    }

    public function isRecorded(): bool
    {
        return $this->published_at === null;
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->superseded_at === null;
    }

    public function isSuperseded(): bool
    {
        return $this->superseded_at !== null;
    }
}
