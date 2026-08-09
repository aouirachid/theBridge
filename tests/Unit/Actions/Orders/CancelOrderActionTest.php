<?php

use App\Actions\Orders\CancelOrderAction;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Exceptions\Ordering\OrderConflictException;
use App\Models\OfferDeliverySlot;
use App\Models\Order;
use App\Models\OrderStatusTransition;
use App\Models\ProductOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function cancelOrderOffer(array $offerOverrides = []): ProductOffer
{
    $offer = ProductOffer::factory()
        ->published()
        ->withStandardCosts()
        ->create($offerOverrides);

    $offer->deliverySlots()->create([
        'starts_at' => now()->addDays(2)->startOfDay()->addHours(2),
        'ends_at' => now()->addDays(2)->startOfDay()->addHours(4),
    ]);

    return $offer;
}

function cancelSlot(ProductOffer $offer): OfferDeliverySlot
{
    return $offer->deliverySlots()->oldest()->firstOrFail();
}

function cancelOrder(Order $order, int $actorUserId): array
{
    return app(CancelOrderAction::class)->execute($order, $actorUserId);
}

it('cancels a confirmed order once and preserves its snapshots', function () {
    $operator = User::factory()->operationsOperator()->create();
    $order = Order::factory()->create([
        'customer_name' => 'Amina Benali',
        'quantity_hundredths' => 500,
    ]);
    OrderStatusTransition::factory()->initial()->create(['order_id' => $order->id]);
    OrderStatusTransition::factory()->toConfirmed()->create(['order_id' => $order->id]);

    $snapshot = $order->only([
        'unit_price_minor',
        'total_minor',
        'offer_public_id_snapshot',
        'crop_snapshot',
        'service_date',
        'quantity_hundredths',
    ]);

    $this->freezeTime();
    $result = cancelOrder($order, $operator->id);

    expect($result)->toHaveKeys(['reference', 'status']);
    expect($result['reference'])->toBe($order->public_id);
    expect($result['status'])->toBe('cancelled');

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Cancelled);
    expect($order->only([
        'unit_price_minor',
        'total_minor',
        'offer_public_id_snapshot',
        'crop_snapshot',
        'service_date',
        'quantity_hundredths',
    ]))->toEqual($snapshot);
    expect($order->confirmed_at)->not->toBeNull();

    $transitions = $order->transitions()->orderBy('id')->get();

    expect(count($transitions))->toBe(3);
    expect($transitions[2]->from_status)->toBe(OrderStatus::Confirmed);
    expect($transitions[2]->to_status)->toBe(OrderStatus::Cancelled);
    expect($transitions[2]->actor_user_id)->toBe($operator->id);
    expect($transitions[2]->created_at->eq(now()->startOfSecond()))->toBeTrue();
});

it('releases the cancelled quantity exactly once', function () {
    $operator = User::factory()->operationsOperator()->create();
    $offer = cancelOrderOffer();
    $slot = cancelSlot($offer);

    $b2c = Order::factory()->forOffer($offer, $slot, ['quantity_hundredths' => 500])->create();
    Order::factory()->forOffer($offer, $slot, [
        'channel' => OrderChannel::B2b->value,
        'quantity_hundredths' => 4000,
        'business_name' => 'Atlas Coop',
        'delivery_address' => null,
        'delivery_note' => null,
    ])->create();

    $reserved = fn (): int => Order::query()
        ->where('product_offer_id', $offer->id)
        ->where('status', '!=', OrderStatus::Cancelled)
        ->sum('quantity_hundredths');

    expect($reserved())->toBe(4500);

    cancelOrder($b2c, $operator->id);

    expect($reserved())->toBe(4000);
});

it('returns the already-cancelled order on an identical repeat', function () {
    $operator = User::factory()->operationsOperator()->create();
    $order = Order::factory()->create();
    OrderStatusTransition::factory()->initial()->create(['order_id' => $order->id]);
    OrderStatusTransition::factory()->toConfirmed()->create(['order_id' => $order->id]);

    $first = cancelOrder($order, $operator->id);
    $second = cancelOrder($order, $operator->id);

    expect($second['reference'])->toBe($first['reference']);
    expect($second['status'])->toBe('cancelled');
    expect(OrderStatusTransition::query()->where('order_id', $order->id)->count())->toBe(3);
});

it('commits a single transition when the same cancellation runs twice in a transaction', function () {
    $operator = User::factory()->operationsOperator()->create();
    $order = Order::factory()->create();
    OrderStatusTransition::factory()->initial()->create(['order_id' => $order->id]);
    OrderStatusTransition::factory()->toConfirmed()->create(['order_id' => $order->id]);

    $baseline = DB::transactionLevel();
    DB::beginTransaction();

    try {
        cancelOrder($order, $operator->id);
        cancelOrder($order, $operator->id);
    } finally {
        DB::commit();
    }

    expect(OrderStatusTransition::query()->where('order_id', $order->id)->count())->toBe(3);
    expect(DB::transactionLevel())->toBe($baseline);
});

it('rejects cancellation from {status} without mutating the snapshot', function (string $status) {
    $operator = User::factory()->operationsOperator()->create();
    $order = Order::factory()->create(['status' => $status]);

    $snapshot = $order->only([
        'status',
        'unit_price_minor',
        'total_minor',
        'offer_public_id_snapshot',
        'crop_snapshot',
        'service_date',
        'quantity_hundredths',
    ]);

    expect(fn () => cancelOrder($order, $operator->id))
        ->toThrow(
            fn (OrderConflictException $exception) => $exception->getCode() === OrderConflictException::INVALID_STATE,
        );

    $order->refresh();

    expect($order->only([
        'status',
        'unit_price_minor',
        'total_minor',
        'offer_public_id_snapshot',
        'crop_snapshot',
        'service_date',
        'quantity_hundredths',
    ]))->toEqual($snapshot);
    expect(OrderStatusTransition::query()->where('order_id', $order->id)->count())->toBe(0);
})->with(['pending', 'grouped', 'allocated', 'dispatched', 'delivered']);
