<?php

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderStatusTransition;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->operations = User::factory()->operationsOperator()->create();
    $this->sourcingOnly = User::factory()->operator()->create();
    $this->member = User::factory()->create();
});

it('redirects guests away from the order index', function () {
    $this->get(route('operator.orders.index'))
        ->assertRedirect(route('login'));
});

it('redirects guests away from the order detail', function () {
    $order = Order::factory()->create();

    $this->get(route('operator.orders.show', $order))
        ->assertRedirect(route('login'));
});

it('redirects guests away from the cancellation route without writing', function () {
    $order = Order::factory()->create();

    $this->post(route('operator.orders.cancellations.store', $order))
        ->assertRedirect(route('login'));

    expect($order->refresh()->status)->toBe(OrderStatus::Confirmed);
});

it('mounts the email-verification middleware on the operator routes', function () {
    foreach (['index', 'show', 'cancellations.store'] as $name) {
        $middleware = Route::getRoutes()
            ->getByName('operator.orders.'.$name)
            ->gatherMiddleware();

        expect($middleware)->toContain('verified');
    }
});

it('forbids ordinary members from the order index', function () {
    $this->actingAs($this->member)
        ->get(route('operator.orders.index'))
        ->assertForbidden();
});

it('forbids ordinary members from the order detail', function () {
    $order = Order::factory()->create();

    $this->actingAs($this->member)
        ->get(route('operator.orders.show', $order))
        ->assertForbidden();
});

it('forbids ordinary members from cancelling an order without writing', function () {
    $order = Order::factory()->create();

    $this->actingAs($this->member)
        ->post(route('operator.orders.cancellations.store', $order))
        ->assertForbidden();

    expect($order->refresh()->status)->toBe(OrderStatus::Confirmed);
});

it('does not reuse the sourcing permission for order access', function () {
    $order = Order::factory()->create();

    $this->actingAs($this->sourcingOnly)
        ->get(route('operator.orders.index'))
        ->assertForbidden();

    $this->actingAs($this->sourcingOnly)
        ->get(route('operator.orders.show', $order))
        ->assertForbidden();

    $this->actingAs($this->sourcingOnly)
        ->post(route('operator.orders.cancellations.store', $order))
        ->assertForbidden();

    expect($order->refresh()->status)->toBe(OrderStatus::Confirmed);
});

it('lists the newest orders for an operations user with filter options', function () {
    Order::factory()->count(3)->create();

    $this->actingAs($this->operations)
        ->get(route('operator.orders.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/orders/index')
            ->has('orders.data', 3)
            ->has('filters.channel')
            ->has('filterOptions.channels', 2)
            ->has('filterOptions.statuses', 7)
            ->has('filterOptions.deliveryZones', 3)
            ->has('filterOptions.offers', 3));
});

it('paginates the order list twenty-five rows at a time newest first', function () {
    Order::factory()->count(30)->create();

    $this->actingAs($this->operations)
        ->get(route('operator.orders.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('orders.total', 30)
            ->where('orders.per_page', 25)
            ->where('orders.last_page', 2)
            ->has('orders.data', 25));

    $this->actingAs($this->operations)
        ->get(route('operator.orders.index', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('orders.current_page', 2)
            ->has('orders.data', 5));
});

it('filters the order index by status with the filter retained', function () {
    Order::factory()->create(['status' => OrderStatus::Confirmed]);
    Order::factory()->create(['status' => OrderStatus::Grouped]);

    $this->actingAs($this->operations)
        ->get(route('operator.orders.index', ['status' => 'grouped']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.status', 'grouped')
            ->where('filters.status', 'grouped'));
});

it('rejects an invalid channel filter', function () {
    $this->actingAs($this->operations)
        ->get(route('operator.orders.index', ['channel' => 'bogus']))
        ->assertSessionHasErrors('channel');
});

it('rejects an invalid status filter', function () {
    $this->actingAs($this->operations)
        ->get(route('operator.orders.index', ['status' => 'not-a-status']))
        ->assertSessionHasErrors('status');
});

it('rejects an invalid service date filter', function () {
    $this->actingAs($this->operations)
        ->get(route('operator.orders.index', ['service_date' => 'not-a-date']))
        ->assertSessionHasErrors('service_date');
});

it('rejects an invalid offer filter', function () {
    $this->actingAs($this->operations)
        ->get(route('operator.orders.index', ['offer' => 'not-a-uuid']))
        ->assertSessionHasErrors('offer');
});

it('rejects an invalid delivery zone filter', function () {
    $this->actingAs($this->operations)
        ->get(route('operator.orders.index', ['delivery_zone' => 'bogus']))
        ->assertSessionHasErrors('delivery_zone');
});

it('rejects a non-positive page filter', function () {
    $this->actingAs($this->operations)
        ->get(route('operator.orders.index', ['page' => 0]))
        ->assertSessionHasErrors('page');
});

it('never exposes private contact details in the index', function () {
    $order = Order::factory()->create([
        'customer_name' => 'Leila Bennani',
        'phone' => '+212 661 234 567',
        'email' => 'leila@example.com',
        'business_name' => 'Atlas Gifts SARL',
        'delivery_address' => 'Rue des Oliviers 45, Casablanca',
        'delivery_note' => 'Ring the doorbell twice',
    ]);

    $this->actingAs($this->operations)
        ->get(route('operator.orders.index'))
        ->assertOk()
        ->assertDontSee('Leila Bennani')
        ->assertDontSee('+212 661 234 567')
        ->assertDontSee('leila@example.com')
        ->assertDontSee('Atlas Gifts SARL')
        ->assertDontSee('Rue des Oliviers')
        ->assertDontSee('Ring the doorbell')
        ->assertDontSee('submission_hash')
        ->assertDontSee('product_offer_id');
});

it('shows the private detail with contact and history for an operations user', function () {
    $order = Order::factory()->create([
        'customer_name' => 'Leila Bennani',
        'phone' => '+212 661 234 567',
        'email' => 'leila@example.com',
        'business_name' => 'Atlas Gifts SARL',
        'delivery_address' => 'Rue des Oliviers 45, Casablanca',
        'delivery_note' => 'Ring the doorbell twice',
    ]);
    $order->transitions()->save(OrderStatusTransition::factory()->initial()->make());
    $order->transitions()->save(OrderStatusTransition::factory()->toConfirmed()->make());

    $this->actingAs($this->operations)
        ->get(route('operator.orders.show', $order))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('operator/orders/show')
            ->where('order.reference', $order->public_id)
            ->where('order.status', 'confirmed')
            ->where('order.contact.customerName', 'Leila Bennani')
            ->where('order.contact.phone', '+212 661 234 567')
            ->where('order.transitions.0.from', null)
            ->where('order.transitions.0.to', 'pending')
            ->where('order.transitions.1.to', 'confirmed')
            ->where('order.transitions.1.actorLabel', 'Guest')
            ->where('can.cancel', true));
});

it('exposes cancellation capability only for confirmed orders', function () {
    $confirmed = Order::factory()->create();
    $grouped = Order::factory()->create(['status' => OrderStatus::Grouped]);

    expect($this->operations->can('cancel', $confirmed))->toBeTrue();
    expect($this->operations->can('cancel', $grouped))->toBeFalse();
    expect($this->member->can('cancel', $confirmed))->toBeFalse();

    $this->actingAs($this->operations)
        ->get(route('operator.orders.show', $grouped))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('can.cancel', false));
});

it('returns not-found for an unknown order public id', function () {
    $this->actingAs($this->operations)
        ->get(route('operator.orders.show', '00000000-0000-0000-0000-000000000000'))
        ->assertNotFound();
});

it('returns not-found when cancelling an unknown order', function () {
    $this->actingAs($this->operations)
        ->post(route('operator.orders.cancellations.store', '00000000-0000-0000-0000-000000000000'))
        ->assertNotFound();
});

it('cancels a confirmed order as an operations user with a toast redirect', function () {
    $order = Order::factory()->create();
    $order->transitions()->save(OrderStatusTransition::factory()->initial()->make());
    $order->transitions()->save(OrderStatusTransition::factory()->toConfirmed()->make());

    $this->actingAs($this->operations)
        ->post(route('operator.orders.cancellations.store', $order))
        ->assertRedirectToRoute('operator.orders.show', $order)
        ->assertSessionHas('inertia.flash_data.toast');

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Cancelled);

    $transitions = $order->transitions()->orderBy('id')->get();

    expect($transitions)->toHaveCount(3);
    expect($transitions->last()->from_status)->toBe(OrderStatus::Confirmed);
    expect($transitions->last()->to_status)->toBe(OrderStatus::Cancelled);
    expect($transitions->last()->actor_user_id)->toBe($this->operations->id);
});

it('repeats a cancellation idempotently without appending another transition', function () {
    $order = Order::factory()->create();
    $order->transitions()->save(OrderStatusTransition::factory()->initial()->make());
    $order->transitions()->save(OrderStatusTransition::factory()->toConfirmed()->make());

    $this->actingAs($this->operations)
        ->post(route('operator.orders.cancellations.store', $order))
        ->assertRedirectToRoute('operator.orders.show', $order);

    $order->refresh();

    $this->actingAs($this->operations)
        ->post(route('operator.orders.cancellations.store', $order))
        ->assertRedirectToRoute('operator.orders.show', $order);

    $order->refresh();

    expect($order->status)->toBe(OrderStatus::Cancelled);
    expect($order->transitions()->count())->toBe(3);
});

it('returns to the order page with an error toast for an invalid cancellation state', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Grouped]);

    $this->actingAs($this->operations)
        ->post(route('operator.orders.cancellations.store', $order))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast', fn ($toast) => $toast['type'] === 'error');

    expect($order->refresh()->status)->toBe(OrderStatus::Grouped);
    expect($order->transitions()->count())->toBe(0);
});

it('rejects cancellation from every non-cancellable active lifecycle state', function (OrderStatus $status) {
    $order = Order::factory()->create(['status' => $status]);

    $this->actingAs($this->operations)
        ->post(route('operator.orders.cancellations.store', $order))
        ->assertRedirect()
        ->assertSessionHas('inertia.flash_data.toast', fn ($toast) => $toast['type'] === 'error');

    expect($order->refresh()->status)->toBe($status);
    expect($order->transitions()->count())->toBe(0);
})->with([
    'pending' => OrderStatus::Pending,
    'grouped' => OrderStatus::Grouped,
    'allocated' => OrderStatus::Allocated,
    'dispatched' => OrderStatus::Dispatched,
    'delivered' => OrderStatus::Delivered,
]);

it('rejects a non-empty body on the cancellation route without writing', function () {
    $order = Order::factory()->create();

    $this->actingAs($this->operations)
        ->post(route('operator.orders.cancellations.store', $order), ['status' => 'cancelled'])
        ->assertSessionHasErrors();

    expect($order->refresh()->status)->toBe(OrderStatus::Confirmed);
});

it('applies the operator throttle to order requests', function () {
    RateLimiter::shouldReceive('limiter')
        ->with('operator-orders')
        ->andReturn(fn ($request) => Limit::perMinute(60)->by('test-operator'));

    RateLimiter::shouldReceive('tooManyAttempts')->andReturn(true);

    RateLimiter::shouldReceive('availableIn')->andReturn(60);

    $this->actingAs($this->operations)
        ->get(route('operator.orders.index'))
        ->assertStatus(429);
});
