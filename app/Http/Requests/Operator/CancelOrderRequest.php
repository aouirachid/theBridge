<?php

namespace App\Http\Requests\Operator;

use App\Concerns\RejectsNonEmptyBody;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CancelOrderRequest extends FormRequest
{
    use RejectsNonEmptyBody;

    /**
     * Determine whether the user is authorized to cancel this order.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('view', $this->route('order')) ?? false;
    }

    /**
     * Reject any submitted body.
     */
    public function withValidator(Validator $validator): void
    {
        $this->rejectNonEmptyBody($validator);
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
