<?php

namespace App\Policies;

use App\Models\ProductOffer;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProductOfferPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_sourcing_operator;
    }

    /**
     * Determine whether the user can view the model (staff record access).
     */
    public function view(User $user, ProductOffer $productOffer): bool
    {
        return $user->is_sourcing_operator;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->is_sourcing_operator;
    }

    /**
     * Determine whether the user can update the draft.
     */
    public function update(User $user, ProductOffer $productOffer): bool
    {
        return $user->is_sourcing_operator && $productOffer->isDraft();
    }

    /**
     * Determine whether the user can publish the offer.
     */
    public function publish(User $user, ProductOffer $productOffer): bool
    {
        return $user->is_sourcing_operator && $productOffer->isDraft();
    }

    /**
     * Determine whether the user can create a replacement for the offer.
     */
    public function replace(User $user, ProductOffer $productOffer): bool
    {
        return $user->is_sourcing_operator
            && $productOffer->isPublished()
            && ! $productOffer->isSuperseded()
            && ! $productOffer->replacement()->exists();
    }

    /**
     * Determine whether the user can withdraw the published offer.
     *
     * Withdrawal is repeatable for already-withdrawn offers so an operator can
     * safely resubmit the same transition without an authorization error.
     */
    public function withdraw(User $user, ProductOffer $productOffer): bool
    {
        return $user->is_sourcing_operator
            && ! $productOffer->isDraft()
            && ! $productOffer->isSuperseded();
    }

    /**
     * Determine whether the user can record a benchmark observation.
     */
    public function recordBenchmark(User $user, ProductOffer $productOffer): bool
    {
        return $user->is_sourcing_operator && ! $productOffer->isWithdrawn();
    }

    /**
     * Determine whether the user can publish a reviewed benchmark comparison.
     */
    public function publishBenchmark(User $user, ProductOffer $productOffer): bool
    {
        return $user->is_sourcing_operator
            && $productOffer->isPublished()
            && ! $productOffer->isWithdrawn()
            && ! $productOffer->isSuperseded();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ProductOffer $productOffer): Response
    {
        return Response::deny('Product offers cannot be deleted.');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ProductOffer $productOffer): Response
    {
        return Response::deny('Product offers cannot be deleted.');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ProductOffer $productOffer): Response
    {
        return Response::deny('Product offers cannot be deleted.');
    }
}
