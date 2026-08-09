<?php

namespace App\Http\Requests\Operator;

class UpdateProductOfferRequest extends StoreProductOfferRequest
{
    /**
     * Determine whether the user is authorized to update this draft.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('productOffer')) ?? false;
    }
}
