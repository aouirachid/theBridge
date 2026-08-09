<?php

namespace App\Http\Requests\Public;

use App\Enums\DeliveryZone;
use App\Enums\OrderChannel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * The exact B2C/B2B order-confirmation allowlist.
 *
 * Client-supplied price, total, status, snapshot, and internal-ID fields are
 * never accepted: they are ignored by validated() and never reach the Action.
 */
class StoreOrderRequest extends FormRequest
{
    /**
     * Public order confirmation is always allowed.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize the defining payload before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'submission_token' => Str::of((string) $this->input('submission_token'))->squish()->toString(),
            'quantity_kg' => Str::of((string) $this->input('quantity_kg'))->squish()->toString(),
            'customer_name' => Str::of((string) $this->input('customer_name'))->squish()->toString(),
            'phone' => $this->normalizePhone((string) $this->input('phone')),
            'email' => $this->normalizeOptional('email'),
            'business_name' => $this->normalizeOptional('business_name'),
            'delivery_address' => $this->normalizeOptional('delivery_address'),
            'delivery_note' => $this->normalizeOptional('delivery_note'),
        ]);
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
            'submission_token' => ['required', 'uuid'],
            'channel' => ['required', Rule::enum(OrderChannel::class)],
            'quantity_kg' => ['required', 'regex:/^\d+(?:\.\d{1,2})?$/', 'gt:0'],
            'delivery_slot_public_id' => ['required', 'uuid'],
            'delivery_zone' => ['required', Rule::enum(DeliveryZone::class)],
            'customer_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', 'max:32'],
            'email' => ['nullable', 'email', 'max:255'],
            'business_name' => [
                'nullable',
                'string',
                'max:160',
                'required_if:channel,b2b',
                'prohibited_if:channel,b2c',
            ],
            'delivery_address' => ['nullable', 'string', 'max:500', 'prohibited_if:channel,b2b'],
            'delivery_note' => ['nullable', 'string', 'max:500', 'prohibited_if:channel,b2b'],
        ];
    }

    /**
     * Store the national-format phone number, converting the leading +212
     * country code into the local leading zero.
     */
    protected function normalizePhone(string $value): string
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (str_starts_with($digits, '212') && strlen($digits) === 12) {
            return '0'.substr($digits, 3);
        }

        return $digits;
    }

    protected function normalizeOptional(string $key): ?string
    {
        $value = $this->input($key);

        if ($value === null || Str::of((string) $value)->squish()->toString() === '') {
            return null;
        }

        return Str::of((string) $value)->squish()->toString();
    }
}
