<?php

namespace App\Http\Requests\Operator;

use App\Enums\DeliveryZone;
use App\Enums\OrderChannel;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListOrdersRequest extends FormRequest
{
    /**
     * Determine whether the user is authorized to list orders.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Order::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Only the known filters are validated; any other query parameter is
     * ignored by `validated()` and never reaches the Action.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'channel' => ['sometimes', Rule::enum(OrderChannel::class)],
            'status' => ['sometimes', Rule::enum(OrderStatus::class)],
            'service_date' => ['sometimes', 'date_format:Y-m-d'],
            'offer' => ['sometimes', 'uuid'],
            'delivery_zone' => ['sometimes', Rule::enum(DeliveryZone::class)],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
