<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\Ordering\OrderConflictException;
use App\Models\OfferDeliverySlot;
use App\Models\Order;
use App\Models\ProductOffer;
use App\Support\Pricing\OrderTotalCalculator;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Confirm one B2C or B2B order in a single transaction.
 *
 * The submission token never touches storage: only a SHA-256 hash is kept.
 * An existing hash is compared against the normalized defining payload and, on
 * a full match, returns the identical confirmation instead of writing a second
 * row. All other failures throw safe public conflicts that reserve nothing.
 */
final class CreateOrderAction
{
    /**
     * @param  array{
     *     submission_token: string,
     *     channel: string,
     *     quantity_kg: string,
     *     delivery_slot_public_id: string,
     *     delivery_zone: string,
     *     customer_name: string,
     *     phone: string,
     *     email?: string|null,
     *     business_name?: string|null,
     *     delivery_address?: string|null,
     *     delivery_note?: string|null,
     * }  $input
     * @return array<string, mixed>
     */
    public function execute(string $offerPublicId, array $input, ?CarbonInterface $now = null): array
    {
        $now = $now ?? now();

        $normalized = $this->normalize($offerPublicId, $input);
        $hash = hash('sha256', $normalized['submission_token']);

        return DB::transaction(function () use ($hash, $normalized, $now): array {
            $existing = Order::query()
                ->where('submission_hash', $hash)
                ->first();

            if ($existing !== null) {
                return $this->confirmationOrMismatch($existing, $normalized);
            }

            $offer = ProductOffer::query()
                ->where('public_id', $normalized['offer_public_id'])
                ->lockForUpdate()
                ->first();

            $this->requireOrderableOffer($offer, $now);

            $existing = Order::query()
                ->where('submission_hash', $hash)
                ->first();

            if ($existing !== null) {
                return $this->confirmationOrMismatch($existing, $normalized);
            }

            $slot = $offer->deliverySlots()
                ->where('public_id', $normalized['delivery_slot_public_id'])
                ->lockForUpdate()
                ->first();

            if (! $this->slotIsEligible($slot, $offer, $now)) {
                throw OrderConflictException::slotUnavailable();
            }

            $quantityHundredths = OrderTotalCalculator::parseQuantityKg($normalized['quantity_kg']);
            $this->assertRemainingQuantity($offer, $quantityHundredths);

            $unitPriceMinor = (int) $offer->final_price_minor;

            $order = Order::query()->create([
                'submission_hash' => $hash,
                'product_offer_id' => $offer->id,
                'offer_delivery_slot_id' => $slot->id,
                'channel' => $normalized['channel'],
                'status' => OrderStatus::Pending,
                'quantity_hundredths' => $quantityHundredths,
                'currency' => 'MAD',
                'unit_price_minor' => $unitPriceMinor,
                'total_minor' => OrderTotalCalculator::totalMinor($unitPriceMinor, $quantityHundredths),
                'offer_public_id_snapshot' => $offer->public_id,
                'crop_snapshot' => $offer->crop,
                'service_date' => $slot->starts_at->copy()->setTimezone('Africa/Casablanca')->toDateString(),
                'slot_starts_at' => $slot->starts_at,
                'slot_ends_at' => $slot->ends_at,
                'delivery_zone' => $normalized['delivery_zone'],
                'customer_name' => $normalized['customer_name'],
                'business_name' => $normalized['business_name'],
                'phone' => $normalized['phone'],
                'email' => $normalized['email'],
                'delivery_address' => $normalized['delivery_address'],
                'delivery_note' => $normalized['delivery_note'],
            ]);

            $this->recordTransition($order, null, OrderStatus::Pending, $now);

            $order->forceFill([
                'status' => OrderStatus::Confirmed,
                'confirmed_at' => $now,
            ])->save();

            $this->recordTransition($order, OrderStatus::Pending, OrderStatus::Confirmed, $now);

            return $this->confirmation($order->fresh());
        }, attempts: 3);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function normalize(string $offerPublicId, array $input): array
    {
        return [
            'offer_public_id' => $offerPublicId,
            'submission_token' => trim((string) ($input['submission_token'] ?? '')),
            'channel' => (string) $input['channel'],
            'quantity_kg' => trim((string) $input['quantity_kg']),
            'delivery_slot_public_id' => (string) $input['delivery_slot_public_id'],
            'delivery_zone' => (string) $input['delivery_zone'],
            'customer_name' => trim((string) $input['customer_name']),
            'phone' => $this->normalizePhone((string) $input['phone']),
            'email' => $this->normalizeOptional($input['email'] ?? null),
            'business_name' => $this->normalizeOptional($input['business_name'] ?? null),
            'delivery_address' => $this->normalizeOptional($input['delivery_address'] ?? null),
            'delivery_note' => $this->normalizeOptional($input['delivery_note'] ?? null),
        ];
    }

    private function normalizePhone(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (str_starts_with($digits, '212') && strlen($digits) === 12) {
            return '0'.substr($digits, 3);
        }

        return $digits;
    }

    private function normalizeOptional(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return trim((string) $value);
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    private function confirmationOrMismatch(Order $order, array $normalized): array
    {
        if (! $this->matches($order, $normalized)) {
            throw OrderConflictException::submissionMismatch();
        }

        return $this->confirmation($order);
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    private function matches(Order $order, array $normalized): bool
    {
        return (string) $order->offer_public_id_snapshot === $normalized['offer_public_id']
            && $order->channel->value === $normalized['channel']
            && $order->quantity_hundredths === OrderTotalCalculator::parseQuantityKg($normalized['quantity_kg'])
            && (string) $order->deliverySlot->public_id === $normalized['delivery_slot_public_id']
            && $order->delivery_zone->value === $normalized['delivery_zone']
            && $order->customer_name === $normalized['customer_name']
            && $order->business_name === $normalized['business_name']
            && $order->phone === $normalized['phone']
            && $order->email === $normalized['email']
            && $order->delivery_address === $normalized['delivery_address']
            && $order->delivery_note === $normalized['delivery_note'];
    }

    /**
     * @return array<string, mixed>
     */
    private function confirmation(Order $order): array
    {
        return [
            'reference' => $order->public_id,
            'channel' => $order->channel->value,
            'crop' => $order->crop_snapshot,
            'quantityKg' => OrderTotalCalculator::formatMinor($order->quantity_hundredths),
            'deliveryZone' => [
                'code' => $order->delivery_zone->value,
                'label' => $order->delivery_zone->label(),
            ],
            'deliverySlot' => [
                'startsAt' => $order->slot_starts_at->toIso8601String(),
                'endsAt' => $order->slot_ends_at->toIso8601String(),
                'serviceDate' => $order->service_date->toDateString(),
            ],
            'unitPrice' => [
                'minor' => $order->unit_price_minor,
                'formatted' => OrderTotalCalculator::formatPerKilogram($order->unit_price_minor),
            ],
            'total' => [
                'minor' => $order->total_minor,
                'formatted' => OrderTotalCalculator::formatTotal($order->total_minor),
            ],
            'currency' => $order->currency,
            'status' => $order->status->value,
            'confirmedAt' => $order->confirmed_at->toIso8601String(),
            'maskedPhone' => $order->maskedPhone(),
            'eligibleForNextCycle' => $order->eligibleForNextCycle(),
        ];
    }

    private function recordTransition(Order $order, ?OrderStatus $from, OrderStatus $to, CarbonInterface $now): void
    {
        $transition = $order->transitions()->make([
            'from_status' => $from,
            'to_status' => $to,
        ]);

        $transition->created_at = $now;
        $transition->save();
    }

    private function requireOrderableOffer(?ProductOffer $offer, CarbonInterface $now): void
    {
        if ($offer === null || ! $offer->isPublished() || $offer->isSuperseded()) {
            throw new NotFoundHttpException('The offer could not be found.');
        }

        if ($offer->availability_ends_at->lte($now)) {
            throw OrderConflictException::offerUnavailable();
        }
    }

    private function slotIsEligible(?OfferDeliverySlot $slot, ProductOffer $offer, CarbonInterface $now): bool
    {
        if ($slot === null) {
            return false;
        }

        if (! $slot->starts_at->gt($now)) {
            return false;
        }

        return $slot->starts_at->gte($offer->availability_starts_at)
            && $slot->ends_at->lte($offer->availability_ends_at);
    }

    private function assertRemainingQuantity(ProductOffer $offer, int $quantityHundredths): void
    {
        $reserved = Order::query()
            ->where('product_offer_id', $offer->id)
            ->where('status', '!=', OrderStatus::Cancelled)
            ->sum('quantity_hundredths');

        $available = OrderTotalCalculator::parseQuantityKg($offer->available_quantity_kg);

        if ($quantityHundredths > $available - $reserved) {
            throw OrderConflictException::quantityUnavailable();
        }
    }
}
