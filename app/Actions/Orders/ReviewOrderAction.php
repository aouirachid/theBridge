<?php

namespace App\Actions\Orders;

use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Exceptions\Ordering\OrderConflictException;
use App\Models\OfferDeliverySlot;
use App\Models\Order;
use App\Models\ProductOffer;
use App\Support\Pricing\OrderTotalCalculator;
use Carbon\CarbonInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Produce the bounded, privacy-safe review for one order attempt.
 *
 * A review never writes and never reserves anything. The visibility decision and
 * the slot eligibility share one captured clock value, and only allowlisted
 * fields leave this action.
 */
final class ReviewOrderAction
{
    /**
     * @param  array{channel: string, quantity_kg: string, delivery_slot_public_id: string}  $input
     * @return array{
     *     channel: string,
     *     crop: string,
     *     quantityKg: string,
     *     deliverySlot: array{publicId: string, startsAt: string, endsAt: string, serviceDate: string},
     *     unitPrice: array{minor: int, formatted: string},
     *     total: array{minor: int, formatted: string},
     * }
     */
    public function execute(string $offerPublicId, array $input, ?CarbonInterface $now = null): array
    {
        $now = $now ?? now();

        $offer = ProductOffer::query()
            ->where('public_id', $offerPublicId)
            ->first();

        $this->requireOrderableOffer($offer, $now);

        $slot = $offer->deliverySlots()
            ->where('public_id', $input['delivery_slot_public_id'])
            ->first();

        if (! $this->slotIsEligible($slot, $offer, $now)) {
            throw OrderConflictException::slotUnavailable();
        }

        $quantityHundredths = OrderTotalCalculator::parseQuantityKg($input['quantity_kg']);

        $this->assertRemainingQuantity($offer, $quantityHundredths);

        $channel = OrderChannel::from($input['channel']);
        $unitPriceMinor = (int) $offer->final_price_minor;

        return [
            'channel' => $channel->value,
            'crop' => $offer->crop,
            'quantityKg' => OrderTotalCalculator::formatMinor($quantityHundredths),
            'deliverySlot' => [
                'publicId' => (string) $slot->public_id,
                'startsAt' => $slot->starts_at->toIso8601String(),
                'endsAt' => $slot->ends_at->toIso8601String(),
                'serviceDate' => $slot->starts_at->copy()->setTimezone('Africa/Casablanca')->toDateString(),
            ],
            'unitPrice' => [
                'minor' => $unitPriceMinor,
                'formatted' => OrderTotalCalculator::formatPerKilogram($unitPriceMinor),
            ],
            'total' => [
                'minor' => OrderTotalCalculator::totalMinor($unitPriceMinor, $quantityHundredths),
                'formatted' => OrderTotalCalculator::formatTotal(
                    OrderTotalCalculator::totalMinor($unitPriceMinor, $quantityHundredths),
                ),
            ],
        ];
    }

    /**
     * Enforce the current-public-publication and availability-window rules.
     *
     * Offers that are missing, private, withdrawn, or superseded resolve to the
     * same generic not-found outcome. A still-published offer whose availability
     * window has ended is no longer orderable and becomes a safe conflict.
     */
    private function requireOrderableOffer(?ProductOffer $offer, CarbonInterface $now): void
    {
        if ($offer === null || ! $offer->isPublished() || $offer->isSuperseded()) {
            throw new NotFoundHttpException('The offer could not be found.');
        }

        if ($offer->availability_ends_at->lte($now)) {
            throw OrderConflictException::offerUnavailable();
        }
    }

    /**
     * A slot is eligible when it belongs to the current offer, has not started,
     * and stays inside the offer's availability window.
     */
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
