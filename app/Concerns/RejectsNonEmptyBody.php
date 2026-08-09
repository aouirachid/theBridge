<?php

namespace App\Concerns;

use Illuminate\Validation\Validator;

/**
 * Reject any request that carries a body for endpoints that must be invoked
 * without input (state transitions triggered by intent alone).
 */
trait RejectsNonEmptyBody
{
    /**
     * Fail validation when the request contains any input.
     */
    public function rejectNonEmptyBody(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->all() !== []) {
                $validator->errors()->add(
                    'request',
                    'This request does not accept a body.',
                );
            }
        });
    }
}
