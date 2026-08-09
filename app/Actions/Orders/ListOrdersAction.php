<?php

namespace App\Actions\Orders;

use App\Enums\DeliveryZone;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\ProductOffer;
use App\Support\Pricing\OrderTotalCalculator;
use Illuminate\Support\Arr;

/**
 * List and filter confirmed orders for operations, newest first.
 *
 * The index never selects or decrypts the encrypted contact fields; only the
 * allowlisted summary columns are read. Filter values are preserved on the
 * pagination links so the operator can move between pages without losing the
 * current search.
 */
final class ListOrdersAction
{
    /**
     * @param  array<string, string|int|null>  $filters
     * @return array{
     *     orders: array<string, mixed>,
     *     filters: array{
     *         channel: string|null,
     *         status: string|null,
     *         serviceDate: string|null,
     *         offer: string|null,
     *         deliveryZone: string|null,
     *     },
     *     filterOptions: array{
     *         channels: array<int, array{value: string, label: string}>,
     *         statuses: array<int, array{value: string, label: string}>,
     *         deliveryZones: array<int, array{value: string, label: string}>,
     *         offers: array<int, array{publicId: string, crop: string}>,
     *     },
     * }
     */
    public function execute(array $filters = []): array
    {
        $filters = $this->normalizedFilters($filters);
        $page = max(1, (int) ($filters['page'] ?? 1));

        $paginator = Order::query()
            ->when($filters['channel'] !== null, fn ($query) => $query->where('channel', $filters['channel']))
            ->when($filters['status'] !== null, fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['service_date'] !== null, fn ($query) => $query->whereDate('service_date', $filters['service_date']))
            ->when($filters['offer'] !== null, fn ($query) => $query->where('product_offer_id', $this->offerId($filters['offer'])))
            ->when($filters['delivery_zone'] !== null, fn ($query) => $query->where('delivery_zone', $filters['delivery_zone']))
            ->latest('id')
            ->paginate(25, [
                'id',
                'public_id',
                'channel',
                'status',
                'quantity_hundredths',
                'currency',
                'unit_price_minor',
                'total_minor',
                'crop_snapshot',
                'service_date',
                'delivery_zone',
                'confirmed_at',
            ], 'page', $page)
            ->appends(Arr::where(
                Arr::except($filters, ['page']),
                fn ($value): bool => $value !== null,
            ));

        $paginator->setCollection(
            $paginator->getCollection()->map(
                fn (Order $order): array => $this->summary($order),
            ),
        );

        return [
            'orders' => $paginator->toArray(),
            'filters' => [
                'channel' => $filters['channel'],
                'status' => $filters['status'],
                'serviceDate' => $filters['service_date'],
                'offer' => $filters['offer'],
                'deliveryZone' => $filters['delivery_zone'],
            ],
            'filterOptions' => [
                'channels' => array_map(
                    fn (OrderChannel $channel): array => ['value' => $channel->value, 'label' => $channel->label()],
                    OrderChannel::cases(),
                ),
                'statuses' => array_map(
                    fn (OrderStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                    OrderStatus::cases(),
                ),
                'deliveryZones' => array_map(
                    fn (DeliveryZone $zone): array => ['value' => $zone->value, 'label' => $zone->label()],
                    DeliveryZone::cases(),
                ),
                'offers' => ProductOffer::query()
                    ->latest('id')
                    ->limit(50)
                    ->get(['public_id', 'crop'])
                    ->map(fn (ProductOffer $offer): array => [
                        'publicId' => (string) $offer->public_id,
                        'crop' => $offer->crop,
                    ])
                    ->all(),
            ],
        ];
    }

    /**
     * @param  array<string, string|int|null>  $filters
     * @return array{
     *     channel: string|null,
     *     status: string|null,
     *     service_date: string|null,
     *     offer: string|null,
     *     delivery_zone: string|null,
     *     page: string|null,
     * }
     */
    private function normalizedFilters(array $filters): array
    {
        return [
            'channel' => $this->filterValue($filters, 'channel'),
            'status' => $this->filterValue($filters, 'status'),
            'service_date' => $this->filterValue($filters, 'service_date'),
            'offer' => $this->filterValue($filters, 'offer'),
            'delivery_zone' => $this->filterValue($filters, 'delivery_zone'),
            'page' => $this->filterValue($filters, 'page'),
        ];
    }

    /**
     * @param  array<string, string|int|null>  $filters
     */
    private function filterValue(array $filters, string $key): ?string
    {
        $value = $filters[$key] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    /**
     * @return array{
     *     reference: string,
     *     channel: string,
     *     crop: string,
     *     quantityKg: string,
     *     unitPrice: string,
     *     total: string,
     *     deliveryZone: array{code: string, label: string},
     *     serviceDate: string,
     *     status: string,
     *     eligibleForNextCycle: bool,
     *     confirmedAt: string|null,
     * }
     */
    private function summary(Order $order): array
    {
        return [
            'reference' => $order->public_id,
            'channel' => $order->channel->value,
            'crop' => $order->crop_snapshot,
            'quantityKg' => OrderTotalCalculator::formatMinor($order->quantity_hundredths),
            'unitPrice' => OrderTotalCalculator::formatPerKilogram($order->unit_price_minor),
            'total' => OrderTotalCalculator::formatTotal($order->total_minor),
            'deliveryZone' => [
                'code' => $order->delivery_zone->value,
                'label' => $order->delivery_zone->label(),
            ],
            'serviceDate' => $order->service_date->toDateString(),
            'status' => $order->status->value,
            'eligibleForNextCycle' => $order->eligibleForNextCycle(),
            'confirmedAt' => $order->confirmed_at?->toIso8601String(),
        ];
    }

    private function offerId(?string $publicId): ?int
    {
        $id = ProductOffer::query()->where('public_id', $publicId)->value('id');

        return $id === null ? null : (int) $id;
    }
}
