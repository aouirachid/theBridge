<?php

namespace App\Http\Requests\Operator;

use App\Concerns\RejectsNonEmptyBody;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class WithdrawProductOfferRequest extends FormRequest
{
    use RejectsNonEmptyBody;

    /**
     * Determine whether the user is authorized to withdraw this offer.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('withdraw', $this->route('productOffer')) ?? false;
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
