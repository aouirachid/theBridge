<?php

namespace App\Models;

use Database\Factories\OfferCostComponentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $product_offer_id
 * @property string|null $standard_code
 * @property string $name
 * @property string $normalized_name
 * @property int $amount_minor
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'product_offer_id',
    'standard_code',
    'name',
    'normalized_name',
    'amount_minor',
    'position',
])]
class OfferCostComponent extends Model
{
    /** @use HasFactory<OfferCostComponentFactory> */
    use HasFactory;

    public const CODE_COLLECTION = 'collection';

    public const CODE_QUALITY_CONTROL = 'quality_control';

    public const CODE_HUB_HANDLING_STORAGE = 'hub_handling_storage';

    public const CODE_DELIVERY_ALLOCATION = 'delivery_allocation';

    public const STANDARD_LABELS = [
        self::CODE_COLLECTION => 'Collection',
        self::CODE_QUALITY_CONTROL => 'Quality control',
        self::CODE_HUB_HANDLING_STORAGE => 'Hub handling and storage',
        self::CODE_DELIVERY_ALLOCATION => 'Delivery allocation',
    ];

    public const STANDARD_POSITIONS = [
        self::CODE_COLLECTION => 10,
        self::CODE_QUALITY_CONTROL => 20,
        self::CODE_HUB_HANDLING_STORAGE => 30,
        self::CODE_DELIVERY_ALLOCATION => 40,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'position' => 'integer',
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
     * Trim, whitespace-collapse, and lowercase a name for uniqueness checks.
     */
    public static function normalizeName(string $name): string
    {
        return Str::of($name)->lower()->squish()->toString();
    }

    /**
     * Whether a normalized name collides with a standard category name or code.
     */
    public static function isStandardName(string $normalizedName): bool
    {
        $standard = collect(self::STANDARD_LABELS)
            ->map(fn (string $label): string => self::normalizeName($label))
            ->values()
            ->all();

        return in_array($normalizedName, $standard, true)
            || in_array($normalizedName, array_keys(self::STANDARD_LABELS), true);
    }
}
