<?php

namespace App\Actions\Orders;

use App\Enums\OrderStatus;
use App\Exceptions\Ordering\OrderConflictException;
use App\Models\Order;
use App\Models\ProductOffer;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Cancel one confirmed order on behalf of an authorized operator.
 *
 * The order row and its parent offer are locked inside one transaction so a
 * concurrent confirmation cannot overbook after the release. Only a current
 * `confirmed` order can be cancelled; an already-cancelled order is returned
 * unchanged (idempotent), and every other state throws an invalid-state
 * conflict without mutating the commercial snapshot.
 */
final class CancelOrderAction
{
    /**
     * @return array{
     *     reference: string,
     *     status: string,
     * }
     */
    public function execute(Order $order, int $actorUserId, ?CarbonInterface $now = null): array
    {
        $now = $now ?? now();

        return DB::transaction(function () use ($order, $actorUserId, $now): array {
            $lockedOrder = Order::query()
                ->whereKey($order->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrder->status === OrderStatus::Cancelled) {
                return $this->cancelled($lockedOrder);
            }

            if ($lockedOrder->status !== OrderStatus::Confirmed) {
                throw OrderConflictException::invalidState();
            }

            $this->lockOffer($lockedOrder);

            $lockedOrder->status = OrderStatus::Cancelled;
            $lockedOrder->save();

            $transition = $lockedOrder->transitions()->make([
                'actor_user_id' => $actorUserId,
                'from_status' => OrderStatus::Confirmed,
                'to_status' => OrderStatus::Cancelled,
            ]);

            $transition->created_at = $now;
            $transition->save();

            return $this->cancelled($lockedOrder);
        });
    }

    private function lockOffer(Order $order): void
    {
        ProductOffer::query()
            ->whereKey($order->product_offer_id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * @return array{
     *     reference: string,
     *     status: string,
     * }
     */
    private function cancelled(Order $order): array
    {
        return [
            'reference' => $order->public_id,
            'status' => $order->status->value,
        ];
    }
}
