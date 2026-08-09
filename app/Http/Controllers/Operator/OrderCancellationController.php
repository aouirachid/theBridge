<?php

namespace App\Http\Controllers\Operator;

use App\Actions\Orders\CancelOrderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\CancelOrderRequest;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OrderCancellationController extends Controller
{
    public function __construct(private CancelOrderAction $cancelOrder) {}

    /**
     * Cancel one confirmed order on behalf of an authorized operator.
     */
    public function store(CancelOrderRequest $request, Order $order): RedirectResponse
    {
        $this->cancelOrder->execute($order, (int) $request->user()->getAuthIdentifier());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Order cancelled.')]);

        return to_route('operator.orders.show', $order);
    }
}
