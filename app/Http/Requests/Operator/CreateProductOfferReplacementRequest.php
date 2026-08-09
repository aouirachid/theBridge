<?php

namespace App\Http\Requests\Operator;

use App\Concerns\RejectsNonEmptyBody;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CreateProductOfferReplacementRequest extends FormRequest
{
    use RejectsNonEmptyBody;

    /**
     * Determine whether the user can replace this published offer.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('replace', $this->route('productOffer')) ?? false;
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
