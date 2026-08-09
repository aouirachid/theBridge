<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The exact B2C/B2B order-review allowlist.
 *
 * Only channel, quantity, and slot selection are accepted; every other field is
 * ignored by validated() and never reaches the Action.
 */
class ReviewOrderRequest extends FormRequest
{
    /**
     * Public reviews are always allowed.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * The slot is validated for format only; ownership and timing are checked by
     * the Action so a valid-but-unavailable slot resolves to a safe 409 conflict.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'channel' => ['required', Rule::in(['b2c', 'b2b'])],
            'quantity_kg' => ['required', 'regex:/^\d+(?:\.\d{1,2})?$/', 'gt:0'],
            'delivery_slot_public_id' => ['required', 'uuid'],
        ];
    }

    /**
     * Normalize the quantity string before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'quantity_kg' => Str::of((string) $this->input('quantity_kg'))->squish()->toString(),
        ]);
    }
}
