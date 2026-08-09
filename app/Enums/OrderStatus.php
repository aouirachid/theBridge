<?php

namespace App\Enums;

/**
 * The order lifecycle.
 *
 * The full allowed graph is pending -> confirmed -> grouped -> allocated ->
 * dispatched -> delivered, plus cancellation from pending or confirmed. Phase
 * 2 only writes the initial pending record, the pending -> confirmed
 * confirmation, and the confirmed -> cancelled operator cancellation; later
 * phases add their own forward transition Actions.
 */
enum OrderStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Grouped = 'grouped';
    case Allocated = 'allocated';
    case Dispatched = 'dispatched';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    /**
     * Whether a transition to the given status is allowed.
     */
    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /**
     * The statuses reachable from this status.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Grouped, self::Cancelled],
            self::Grouped => [self::Allocated],
            self::Allocated => [self::Dispatched],
            self::Dispatched => [self::Delivered],
            self::Delivered, self::Cancelled => [],
        };
    }
}
