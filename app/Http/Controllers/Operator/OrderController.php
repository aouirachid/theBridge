<?php

namespace App\Http\Controllers\Operator;

use App\Actions\Orders\ListOrdersAction;
use App\Actions\Orders\ShowOrderAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Operator\ListOrdersRequest;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    /**
     * List and filter the latest orders for operations.
     */
    public function index(ListOrdersRequest $request): Response
    {
        $data = app(ListOrdersAction::class)->execute($request->validated());

        return Inertia::render('operator/orders/index', $data);
    }

    /**
     * Show the authorized private detail for one order.
     */
    public function show(Request $request, Order $order): Response
    {
        $this->authorize('view', $order);

        return Inertia::render('operator/orders/show', [
            'order' => app(ShowOrderAction::class)->execute($order),
            'can' => [
                'cancel' => $request->user()->can('cancel', $order),
            ],
        ]);
    }
}
