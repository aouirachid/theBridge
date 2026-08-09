<?php

namespace App\Http\Requests\Operator;

use App\Models\OfferCostComponent;
use App\Models\ProductOffer;
use App\Support\Validation\PublicDisplayText;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

class StoreProductOfferRequest extends FormRequest
{
    /**
     * Determine whether the user is authorized to create a product offer.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', ProductOffer::class) ?? false;
    }

    /**
     * Normalize whitespace and local Casablanca datetimes before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'crop' => Str::of((string) $this->input('crop'))->squish()->toString(),
            'origin' => Str::of((string) $this->input('origin'))->squish()->toString(),
            'availability_starts_at' => $this->normalizeDateTime('availability_starts_at'),
            'availability_ends_at' => $this->normalizeDateTime('availability_ends_at'),
        ]);

        foreach ((array) $this->input('custom_costs', []) as $index => $custom) {
            if (! is_array($custom)) {
                continue;
            }

            $this->merge([
                "custom_costs.$index.name" => Str::of((string) ($custom['name'] ?? ''))->squish()->toString(),
            ]);
        }
    }

    /**
     * Parse a local Casablanca datetime and store the UTC equivalent.
     */
    protected function normalizeDateTime(string $key): ?string
    {
        $value = $this->input($key);

        if ($value === null) {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value, 'Africa/Casablanca')
                ->utc()
                ->format('Y-m-d H:i:s');
        } catch (InvalidFormatException) {
            return (string) $value;
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $money = 'regex:/^\d+(\.\d{1,2})?$/';

        return [
            'crop' => ['required', 'string', 'max:120', new PublicDisplayText],
            'origin' => ['required', 'string', 'max:255', new PublicDisplayText],
            'available_quantity_kg' => ['required', 'numeric', 'gt:0', $money],
            'availability_starts_at' => ['required', 'date'],
            'availability_ends_at' => ['required', 'date', 'after:availability_starts_at'],
            'farmer_payment_per_kg' => ['required', 'string', $money],
            'platform_margin_per_kg' => ['required', 'string', $money, 'not_in:0,0.0,0.00'],
            'standard_costs' => ['required', 'array'],
            'standard_costs.collection' => ['required', 'string', $money],
            'standard_costs.quality_control' => ['required', 'string', $money],
            'standard_costs.hub_handling_storage' => ['required', 'string', $money],
            'standard_costs.delivery_allocation' => ['required', 'string', $money],
            'custom_costs' => ['array', 'max:10'],
            'custom_costs.*.name' => ['required', 'string', 'max:120', new PublicDisplayText],
            'custom_costs.*.amount_per_kg' => ['required', 'string', $money],
        ];
    }

    /**
     * Reject custom cost names that collide with a standard category or each
     * other after normalization.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $standardCosts = $this->input('standard_costs', []);
            $knownStandardKeys = ['collection', 'quality_control', 'hub_handling_storage', 'delivery_allocation'];

            if (is_array($standardCosts)) {
                foreach (array_keys($standardCosts) as $key) {
                    if (! in_array($key, $knownStandardKeys, true)) {
                        $validator->errors()->add("standard_costs.$key", 'This is not a recognized standard cost category.');
                    }
                }
            }

            $seen = [];

            $customCosts = $this->input('custom_costs', []);

            if (! is_array($customCosts)) {
                return;
            }

            foreach ($customCosts as $index => $custom) {
                $name = Str::of((string) ($custom['name'] ?? ''))->squish()->toString();

                if ($name === '') {
                    continue;
                }

                $normalized = OfferCostComponent::normalizeName($name);

                if (OfferCostComponent::isStandardName($normalized)) {
                    $validator->errors()->add("custom_costs.$index.name", 'This name matches a standard cost category.');
                }

                if (in_array($normalized, $seen, true)) {
                    $validator->errors()->add("custom_costs.$index.name", 'Custom cost names must be unique after normalization.');
                }

                $seen[] = $normalized;
            }
        });
    }
}
