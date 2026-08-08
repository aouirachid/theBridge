<?php

namespace App\Http\Requests\Operator;

use Illuminate\Foundation\Http\FormRequest;

class WithdrawProductOfferRequest extends FormRequest
{
    /**
     * Determine whether the user is authorized to withdraw this offer.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('withdraw', $this->route('productOffer')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
