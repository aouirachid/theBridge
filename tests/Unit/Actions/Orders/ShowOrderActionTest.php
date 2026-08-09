<?php

use App\Actions\Orders\ShowOrderAction;
use App\Enums\OrderChannel;
use App\Models\Order;
use App\Models\OrderStatusTransition;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('returns the allowlisted price, delivery, contact, and history props', function () {
    $order = Order::factory()->create([
        'customer_name' => 'Amina Benali',
        'phone' => '+212612345678',
        'email' => 'amina@example.com',
        'business_name' => null,
        'delivery_address' => '12 Rue des Orangers, Casablanca',
        'delivery_note' => 'Ring the bell',
        'quantity_hundredths' => 500,
    ]);

    OrderStatusTransition::factory()->initial()->create(['order_id' => $order->id]);
    OrderStatusTransition::factory()->toConfirmed()->create(['order_id' => $order->id]);

    $result = app(ShowOrderAction::class)->execute($order);

    expect(array_keys($result))->toBe([
        'reference',
        'channel',
        'status',
        'crop',
        'quantityKg',
        'currency',
        'unitPrice',
        'total',
        'deliveryZone',
        'deliverySlot',
        'confirmedAt',
        'eligibleForNextCycle',
        'contact',
        'transitions',
    ]);

    expect($result['reference'])->toBe($order->public_id);
    expect($result['channel'])->toBe('b2c');
    expect($result['status'])->toBe('confirmed');
    expect($result['crop'])->toBe('Tomatoes');
    expect($result['quantityKg'])->toBe('5.00');
    expect($result['currency'])->toBe('MAD');
    expect($result['unitPrice'])->toBe('5.50 MAD/kg');
    expect($result['total'])->toBe('27.50 MAD');
    expect($result['deliveryZone'])->toBe([
        'code' => 'casablanca_centre',
        'label' => 'Casablanca Centre',
    ]);
    expect($result['deliverySlot'])->toHaveKeys(['startsAt', 'endsAt', 'serviceDate']);
    expect($result['deliverySlot']['serviceDate'])->toBe($order->service_date->toDateString());
    expect($result['confirmedAt'])->toBe($order->confirmed_at->toIso8601String());
    expect($result['eligibleForNextCycle'])->toBeTrue();

    expect(array_keys($result['contact']))->toBe([
        'customerName',
        'businessName',
        'phone',
        'email',
        'deliveryAddress',
        'deliveryNote',
    ]);
    expect($result['contact'])->toBe([
        'customerName' => 'Amina Benali',
        'businessName' => null,
        'phone' => '+212612345678',
        'email' => 'amina@example.com',
        'deliveryAddress' => '12 Rue des Orangers, Casablanca',
        'deliveryNote' => 'Ring the bell',
    ]);

    expect(count($result['transitions']))->toBe(2);
    expect($result['transitions'][0]['to'])->toBe('pending');
    expect($result['transitions'][0]['actorLabel'])->toBe('Guest');
    expect($result['transitions'][1]['to'])->toBe('confirmed');
});

it('labels guest and operations staff actors and decrypts B2B contact', function () {
    $operator = User::factory()->operationsOperator()->create();

    $order = Order::factory()->create([
        'channel' => OrderChannel::B2b->value,
        'customer_name' => 'Youssef Idrissi',
        'phone' => '+212655443322',
        'email' => 'contact@atlas.example',
        'business_name' => 'Atlas Coop',
        'delivery_address' => null,
        'delivery_note' => null,
    ]);

    OrderStatusTransition::factory()->initial()->create(['order_id' => $order->id]);
    OrderStatusTransition::factory()->toConfirmed()->create(['order_id' => $order->id]);
    OrderStatusTransition::factory()->toCancelled($operator->id)->create(['order_id' => $order->id]);

    $result = app(ShowOrderAction::class)->execute($order);

    expect($result['channel'])->toBe('b2b');
    expect($result['contact'])->toBe([
        'customerName' => 'Youssef Idrissi',
        'businessName' => 'Atlas Coop',
        'phone' => '+212655443322',
        'email' => 'contact@atlas.example',
        'deliveryAddress' => null,
        'deliveryNote' => null,
    ]);

    expect(count($result['transitions']))->toBe(3);
    expect($result['transitions'][0]['actorLabel'])->toBe('Guest');
    expect($result['transitions'][1]['actorLabel'])->toBe('Guest');
    expect($result['transitions'][2]['actorLabel'])->toBe('Operations staff');
});

it('orders transitions oldest first', function () {
    $order = Order::factory()->create();

    $initial = OrderStatusTransition::factory()->initial()->create(['order_id' => $order->id]);
    $initial->forceFill(['created_at' => now()->subDays(2)->startOfSecond()])->save();

    $confirmed = OrderStatusTransition::factory()->toConfirmed()->create(['order_id' => $order->id]);
    $confirmed->forceFill(['created_at' => now()->subDay()->startOfSecond()])->save();

    $result = app(ShowOrderAction::class)->execute($order);

    expect(count($result['transitions']))->toBe(2);
    expect($result['transitions'][0]['from'])->toBeNull();
    expect($result['transitions'][0]['to'])->toBe('pending');
    expect($result['transitions'][0]['occurredAt'])->toBe($initial->created_at->toIso8601String());
    expect($result['transitions'][1]['from'])->toBe('pending');
    expect($result['transitions'][1]['to'])->toBe('confirmed');
    expect($result['transitions'][1]['occurredAt'])->toBe($confirmed->created_at->toIso8601String());
});

it('never exposes internal actor identifiers or internal order keys', function () {
    $operator = User::factory()->operationsOperator()->create();

    $order = Order::factory()->create([
        'customer_name' => 'Zineb Confidential',
        'phone' => '+212699887766',
    ]);

    OrderStatusTransition::factory()->initial()->create(['order_id' => $order->id]);
    OrderStatusTransition::factory()->toConfirmed()->create(['order_id' => $order->id]);
    OrderStatusTransition::factory()->toCancelled($operator->id)->create(['order_id' => $order->id]);

    $result = app(ShowOrderAction::class)->execute($order);

    $encoded = json_encode($result);

    expect($encoded)->not->toContain('actor_user_id');
    expect($encoded)->not->toContain('actorUserId');
    expect($encoded)->not->toContain('submission_hash');
    expect($encoded)->not->toContain('product_offer_id');
    expect($encoded)->not->toContain('offer_delivery_slot_id');

    foreach ($result['transitions'] as $transition) {
        expect(array_keys($transition))->toBe(['from', 'to', 'occurredAt', 'actorLabel']);
    }
});
