<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderStatusTransitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property int|null $actor_user_id
 * @property OrderStatus|null $from_status
 * @property OrderStatus $to_status
 * @property Carbon $created_at
 */
#[Fillable([
    'order_id',
    'actor_user_id',
    'from_status',
    'to_status',
])]
class OrderStatusTransition extends Model
{
    /** @use HasFactory<OrderStatusTransitionFactory> */
    use HasFactory;

    /**
     * Transitions only record the accepted time; no updated timestamp.
     */
    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'actor_user_id' => 'integer',
            'from_status' => OrderStatus::class,
            'to_status' => OrderStatus::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * The order this transition belongs to.
     *
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
