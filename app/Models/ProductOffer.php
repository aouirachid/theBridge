<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ProductOfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int|null $created_by_user_id
 * @property int|null $replaces_product_offer_id
 * @property string $crop
 * @property string $origin
 * @property string $available_quantity_kg
 * @property Carbon $availability_starts_at
 * @property Carbon $availability_ends_at
 * @property int $farmer_payment_minor
 * @property int $platform_margin_minor
 * @property int $final_price_minor
 * @property int $farmer_share_bps
 * @property Carbon|null $published_at
 * @property Carbon|null $superseded_at
 * @property Carbon|null $withdrawn_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'public_id',
    'created_by_user_id',
    'replaces_product_offer_id',
    'crop',
    'origin',
    'available_quantity_kg',
    'availability_starts_at',
    'availability_ends_at',
    'farmer_payment_minor',
    'platform_margin_minor',
    'final_price_minor',
    'farmer_share_bps',
    'published_at',
    'superseded_at',
    'withdrawn_at',
])]
class ProductOffer extends Model
{
    /** @use HasFactory<ProductOfferFactory> */
    use HasFactory, HasUuids;

    /**
     * The UUID column used for public route binding.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'available_quantity_kg' => 'decimal:2',
            'availability_starts_at' => 'datetime',
            'availability_ends_at' => 'datetime',
            'farmer_payment_minor' => 'integer',
            'platform_margin_minor' => 'integer',
            'final_price_minor' => 'integer',
            'farmer_share_bps' => 'integer',
            'published_at' => 'datetime',
            'superseded_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }

    /**
     * The private actor who created this offer.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * The published offer this draft replaces.
     *
     * @return BelongsTo<ProductOffer, $this>
     */
    public function replaces(): BelongsTo
    {
        return $this->belongsTo(ProductOffer::class, 'replaces_product_offer_id');
    }

    /**
     * The published replacement created from this offer.
     *
     * @return HasOne<ProductOffer, $this>
     */
    public function replacement(): HasOne
    {
        return $this->hasOne(ProductOffer::class, 'replaces_product_offer_id');
    }

    /**
     * The cost components in stable public display order.
     *
     * @return HasMany<OfferCostComponent, $this>
     */
    public function costs(): HasMany
    {
        return $this->hasMany(OfferCostComponent::class)
            ->orderBy('position')
            ->orderBy('id');
    }

    /**
     * The benchmark observations for this offer, newest first.
     *
     * @return HasMany<BenchmarkComparison, $this>
     */
    public function benchmarkComparisons(): HasMany
    {
        return $this->hasMany(BenchmarkComparison::class)
            ->orderByDesc('observed_at')
            ->orderByDesc('id');
    }

    public function isDraft(): bool
    {
        return $this->published_at === null;
    }

    public function isPublished(): bool
    {
        return $this->published_at !== null && $this->withdrawn_at === null;
    }

    public function isSuperseded(): bool
    {
        return $this->superseded_at !== null && $this->withdrawn_at === null;
    }

    public function isWithdrawn(): bool
    {
        return $this->withdrawn_at !== null;
    }

    /**
     * Derive the lifecycle status label from timestamps.
     */
    public function status(): string
    {
        if ($this->isWithdrawn()) {
            return 'withdrawn';
        }

        if ($this->superseded_at !== null) {
            return 'superseded';
        }

        if ($this->published_at !== null) {
            return 'published';
        }

        return 'draft';
    }

    /**
     * Whether the offer may be shown publicly at the given time.
     */
    public function isPubliclyVisible(CarbonInterface $now): bool
    {
        if ($this->published_at === null || $this->withdrawn_at !== null) {
            return false;
        }

        if ($this->superseded_at === null) {
            return true;
        }

        return $this->superseded_at->gte($now->subDays(30)->startOfSecond());
    }
}
