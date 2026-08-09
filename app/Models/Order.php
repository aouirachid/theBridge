<?php

namespace App\Models;

use App\Enums\DeliveryZone;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property string $submission_hash
 * @property int $product_offer_id
 * @property int $offer_delivery_slot_id
 * @property OrderChannel $channel
 * @property OrderStatus $status
 * @property int $quantity_hundredths
 * @property string $currency
 * @property int $unit_price_minor
 * @property int $total_minor
 * @property string $offer_public_id_snapshot
 * @property string $crop_snapshot
 * @property Carbon $service_date
 * @property Carbon $slot_starts_at
 * @property Carbon $slot_ends_at
 * @property DeliveryZone $delivery_zone
 * @property string $customer_name
 * @property string|null $business_name
 * @property string $phone
 * @property string|null $email
 * @property string|null $delivery_address
 * @property string|null $delivery_note
 * @property Carbon $confirmed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'public_id',
    'submission_hash',
    'product_offer_id',
    'offer_delivery_slot_id',
    'channel',
    'status',
    'quantity_hundredths',
    'currency',
    'unit_price_minor',
    'total_minor',
    'offer_public_id_snapshot',
    'crop_snapshot',
    'service_date',
    'slot_starts_at',
    'slot_ends_at',
    'delivery_zone',
    'customer_name',
    'business_name',
    'phone',
    'email',
    'delivery_address',
    'delivery_note',
    'confirmed_at',
])]
#[Hidden([
    'id',
    'submission_hash',
    'product_offer_id',
    'offer_delivery_slot_id',
    'customer_name',
    'business_name',
    'phone',
    'email',
    'delivery_address',
    'delivery_note',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
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
            'channel' => OrderChannel::class,
            'status' => OrderStatus::class,
            'quantity_hundredths' => 'integer',
            'unit_price_minor' => 'integer',
            'total_minor' => 'integer',
            'service_date' => 'date',
            'slot_starts_at' => 'datetime',
            'slot_ends_at' => 'datetime',
            'delivery_zone' => DeliveryZone::class,
            'customer_name' => 'encrypted',
            'business_name' => 'encrypted',
            'phone' => 'encrypted',
            'email' => 'encrypted',
            'delivery_address' => 'encrypted',
            'delivery_note' => 'encrypted',
            'confirmed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * The source publication this order confirms.
     *
     * @return BelongsTo<ProductOffer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(ProductOffer::class, 'product_offer_id');
    }

    /**
     * The selected delivery slot this order confirms.
     *
     * @return BelongsTo<OfferDeliverySlot, $this>
     */
    public function deliverySlot(): BelongsTo
    {
        return $this->belongsTo(OfferDeliverySlot::class, 'offer_delivery_slot_id');
    }

    /**
     * The append-only lifecycle history, oldest first.
     *
     * @return HasMany<OrderStatusTransition, $this>
     */
    public function transitions(): HasMany
    {
        return $this->hasMany(OrderStatusTransition::class)
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * Whether this order may be consolidated into the next delivery cycle.
     */
    public function eligibleForNextCycle(): bool
    {
        return $this->status === OrderStatus::Confirmed;
    }

    /**
     * A contact-safe phone fragment for confirmations.
     */
    public function maskedPhone(): string
    {
        $phone = (string) $this->phone;

        return str_repeat('*', max(0, strlen($phone) - 2)).substr($phone, -2);
    }
}
