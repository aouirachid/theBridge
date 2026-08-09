<?php

namespace App\Actions\Orders;

use App\Models\Order;
use App\Models\OrderStatusTransition;
use App\Support\Pricing\OrderTotalCalculator;

/**
 * The authorized private detail for one order.
 *
 * Only this detail page may decrypt the contact fields; the route's policy
 * gate is applied before this Action runs. The transition history uses the
 * generalized actor label so no internal actor identity is ever exposed.
 */
final class ShowOrderAction
{
    /**
     * @return array{
     *     reference: string,
     *     channel: string,
     *     status: string,
     *     crop: string,
     *     quantityKg: string,
     *     currency: string,
     *     unitPrice: string,
     *     total: string,
     *     deliveryZone: array{code: string, label: string},
     *     deliverySlot: array{startsAt: string, endsAt: string, serviceDate: string},
     *     confirmedAt: string|null,
     *     eligibleForNextCycle: bool,
     *     contact: array{
     *         customerName: string,
     *         businessName: string|null,
     *         phone: string,
     *         email: string|null,
     *         deliveryAddress: string|null,
     *         deliveryNote: string|null,
     *     },
     *     transitions: array<int, array{
     *         from: string|null,
     *         to: string,
     *         occurredAt: string,
     *         actorLabel: string,
     *     }>,
     * }
     */
    public function execute(Order $order): array
    {
        $order->load('transitions');

        return [
            'reference' => $order->public_id,
            'channel' => $order->channel->value,
            'status' => $order->status->value,
            'crop' => $order->crop_snapshot,
            'quantityKg' => OrderTotalCalculator::formatMinor($order->quantity_hundredths),
            'currency' => $order->currency,
            'unitPrice' => OrderTotalCalculator::formatPerKilogram($order->unit_price_minor),
            'total' => OrderTotalCalculator::formatTotal($order->total_minor),
            'deliveryZone' => [
                'code' => $order->delivery_zone->value,
                'label' => $order->delivery_zone->label(),
            ],
            'deliverySlot' => [
                'startsAt' => $order->slot_starts_at->toIso8601String(),
                'endsAt' => $order->slot_ends_at->toIso8601String(),
                'serviceDate' => $order->service_date->toDateString(),
            ],
            'confirmedAt' => $order->confirmed_at?->toIso8601String(),
            'eligibleForNextCycle' => $order->eligibleForNextCycle(),
            'contact' => [
                'customerName' => $order->customer_name,
                'businessName' => $order->business_name,
                'phone' => $order->phone,
                'email' => $order->email,
                'deliveryAddress' => $order->delivery_address,
                'deliveryNote' => $order->delivery_note,
            ],
            'transitions' => $order->transitions
                ->map(fn (OrderStatusTransition $transition): array => [
                    'from' => $transition->from_status?->value,
                    'to' => $transition->to_status->value,
                    'occurredAt' => $transition->created_at->toIso8601String(),
                    'actorLabel' => $transition->actor_user_id === null ? 'Guest' : 'Operations staff',
                ])
                ->values()
                ->all(),
        ];
    }
}
