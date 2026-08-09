<?php

namespace App\Http\Requests\Operator;

use App\Models\ProductOffer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use RuntimeException;

class PublishProductOfferRequest extends FormRequest
{
    /**
     * Determine whether the user is authorized to publish this draft.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('publish', $this->route('productOffer')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'benchmark_comparison_id' => [
                'required',
                'integer',
                Rule::exists('benchmark_comparisons', 'id')->where(function ($query): void {
                    $query->where('product_offer_id', $this->offer()->id)
                        ->whereNull('published_at');
                }),
            ],
        ];
    }

    /**
     * The product offer bound to the route.
     */
    private function offer(): ProductOffer
    {
        $offer = $this->route('productOffer');

        if (! $offer instanceof ProductOffer) {
            throw new RuntimeException('Expected a bound ProductOffer route model.');
        }

        return $offer;
    }
}
