<?php

namespace App\Http\Requests\Operator;

use Illuminate\Foundation\Http\FormRequest;

class CreateProductOfferReplacementRequest extends FormRequest
{
    /**
     * Determine whether the user can replace this published offer.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('replace', $this->route('productOffer')) ?? false;
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
