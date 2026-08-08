<?php

namespace App\Http\Requests\Operator;

use Illuminate\Foundation\Http\FormRequest;

class PublishBenchmarkComparisonRequest extends FormRequest
{
    /**
     * Determine whether the user can publish a benchmark refresh for this offer.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('publishBenchmark', $this->route('productOffer')) ?? false;
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
