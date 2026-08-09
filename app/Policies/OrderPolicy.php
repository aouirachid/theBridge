<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OrderPolicy
{
    /**
     * Determine whether the user can list and filter orders.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_operations_operator;
    }

    /**
     * Determine whether the user can view the private order detail.
     */
    public function view(User $user, Order $order): bool
    {
        return $user->is_operations_operator;
    }

    /**
     * Determine whether the user can cancel the order.
     *
     * Only the operations permission applies; the sourcing permission is never
     * reused. The current confirmed-status requirement is enforced by the
     * Action so a non-confirmed cancellation surfaces as an order-level
     * conflict that returns safely to the same page rather than a 403.
     */
    public function cancel(User $user, Order $order): bool
    {
        return $user->is_operations_operator;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Order $order): Response
    {
        return Response::deny('Orders cannot be deleted.');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Order $order): Response
    {
        return Response::deny('Orders cannot be deleted.');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Order $order): Response
    {
        return Response::deny('Orders cannot be deleted.');
    }
}
