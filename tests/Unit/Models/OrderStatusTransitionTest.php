<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusTransition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('casts from and to statuses to backed enums', function () {
    $transition = OrderStatusTransition::factory()->toConfirmed()->create();

    expect($transition->from_status)->toBe(OrderStatus::Pending);
    expect($transition->to_status)->toBe(OrderStatus::Confirmed);
});

it('stores only the accepted time without an updated timestamp', function () {
    $transition = OrderStatusTransition::factory()->initial()->create();

    $attributes = $transition->toArray();

    expect($attributes)->toHaveKeys(['order_id', 'actor_user_id', 'from_status', 'to_status', 'created_at']);
    expect($attributes)->not->toHaveKey('updated_at');
});

it('records a null from status only for the initial pending row', function () {
    $transition = OrderStatusTransition::factory()->initial()->create();

    expect($transition->from_status)->toBeNull();
    expect($transition->to_status)->toBe(OrderStatus::Pending);
    expect($transition->actor_user_id)->toBeNull();
});

it('records the operations actor on a cancellation transition', function () {
    $operator = User::factory()->operationsOperator()->create();
    $transition = OrderStatusTransition::factory()->toCancelled($operator->id)->create();

    expect($transition->actor_user_id)->toBe($operator->id);
    expect($transition->from_status)->toBe(OrderStatus::Confirmed);
    expect($transition->to_status)->toBe(OrderStatus::Cancelled);
});

it('belongs to its parent order', function () {
    $order = Order::factory()->create();
    $transition = OrderStatusTransition::factory()->for($order)->create();

    expect($transition->order->id)->toBe($order->id);
});
