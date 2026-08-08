<?php

namespace App\Actions\ProductOffers;

use App\Models\ProductOffer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Withdraw a published offer one-way.
 *
 * The transition is transactional and idempotent: a second call for an already
 * withdrawn offer returns the record unchanged instead of failing.
 */
final class WithdrawProductOfferAction
{
    public function execute(User $operator, ProductOffer $offer): ProductOffer
    {
        return DB::transaction(function () use ($offer): ProductOffer {
            $locked = ProductOffer::query()->lockForUpdate()->findOrFail($offer->id);

            if ($locked->isDraft()) {
                throw new RuntimeException('Only a published offer can be withdrawn.');
            }

            if ($locked->withdrawn_at === null) {
                $locked->forceFill(['withdrawn_at' => now()])->save();
            }

            return $locked;
        });
    }
}
