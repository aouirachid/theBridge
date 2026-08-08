<?php

namespace App\Http\Requests\Operator;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBenchmarkComparisonRequest extends FormRequest
{
    /**
     * Determine whether the user is authorized to record a benchmark.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('recordBenchmark', $this->route('productOffer')) ?? false;
    }

    /**
     * Store the observed timestamp as UTC before validation.
     */
    protected function prepareForValidation(): void
    {
        $observedAt = $this->input('observed_at');

        if ($observedAt !== null) {
            $this->merge([
                'observed_at' => CarbonImmutable::parse((string) $observedAt, 'Africa/Casablanca')
                    ->utc()
                    ->format('Y-m-d H:i:s'),
            ]);
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
            'benchmark_price_per_kg' => ['required', 'string', $money, 'not_in:0,0.0,0.00'],
            'market_name' => ['required', 'string', 'max:160'],
            'source_type' => ['required', Rule::in(['url', 'document', 'field_observation'])],
            'source_reference' => ['required', 'string', 'max:500'],
            'observed_at' => ['required', 'date', 'before_or_equal:now'],
            'is_demo' => ['required', 'boolean'],
        ];
    }

    /**
     * Require a valid URL when the source type is url.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('source_type') === 'url'
                && filter_var($this->input('source_reference'), FILTER_VALIDATE_URL) === false) {
                $validator->errors()->add(
                    'source_reference',
                    'The source reference must be a valid URL for this source type.',
                );
            }
        });
    }
}
