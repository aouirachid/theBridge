<?php

namespace App\Actions\ProductOffers;

use App\Models\BenchmarkComparison;
use App\Models\ProductOffer;
use App\Models\User;
use App\Support\Pricing\OfferPriceCalculator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Record one market benchmark observation without touching public state.
 *
 * Recording only creates a retained observation with staff attribution and an
 * explicit demo flag. Publication requires a separate, explicit operator step.
 */
final class RecordBenchmarkComparisonAction
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function execute(User $operator, ProductOffer $offer, array $input): BenchmarkComparison
    {
        $validated = Validator::make($input, [
            'benchmark_price_per_kg' => ['required', 'string', 'regex:/^\d+(\.\d{1,2})?$/', 'not_in:0,0.0,0.00'],
            'market_name' => ['required', 'string', 'max:160'],
            'source_type' => ['required', Rule::in(['url', 'document', 'field_observation'])],
            'source_reference' => ['required', 'string', 'max:500'],
            'observed_at' => ['required', 'date', 'before_or_equal:now'],
            'is_demo' => ['required', 'boolean'],
        ])->validate();

        if ($validated['source_type'] === 'url' && ! filter_var($validated['source_reference'], FILTER_VALIDATE_URL)) {
            $validator = Validator::make([], []);
            $validator->errors()->add('source_reference', 'The source reference must be a valid URL for this source type.');
            throw new ValidationException($validator);
        }

        return BenchmarkComparison::create([
            'product_offer_id' => $offer->id,
            'recorded_by_user_id' => $operator->id,
            'benchmark_price_minor' => OfferPriceCalculator::parseMinor($validated['benchmark_price_per_kg']),
            'market_name' => $validated['market_name'],
            'source_type' => $validated['source_type'],
            'source_reference' => $validated['source_reference'],
            'observed_at' => $validated['observed_at'],
            'is_demo' => $validated['is_demo'],
        ]);
    }
}
