<?php

namespace App\Support\Validation;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Reject publicly displayed text that could disclose private contact or
 * address information.
 *
 * Blocks email addresses, phone numbers, exact street-address fragments, and
 * URLs that embed credentials. Rejected values are never logged.
 */
final class PublicDisplayText implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        if ($this->containsEmail($value)
            || $this->containsPhoneNumber($value)
            || $this->containsExactAddress($value)
            || $this->containsUrlCredentials($value)) {
            $fail('This text may not contain private contact or address details.');
        }
    }

    /**
     * Detect an email address anywhere in the text.
     */
    private function containsEmail(string $value): bool
    {
        return preg_match('/[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}/', $value) === 1;
    }

    /**
     * Detect a phone number, including international and national formats.
     */
    private function containsPhoneNumber(string $value): bool
    {
        $international = '/\+?\d{1,3}[\s.-]?\(?\d{2,4}\)?[\s.-]?\d{3,4}[\s.-]?\d{3,4}/';

        $national = '/(?<!\d)(?:0\d{1,3}[\s.-]?\d{2}[\s.-]?\d{2}[\s.-]?\d{2}|\d{3}[\s.-]?\d{2}[\s.-]?\d{2})(?!\d)/';

        return preg_match($international, $value) === 1
            || preg_match($national, $value) === 1;
    }

    /**
     * Detect an exact street-address fragment: a house number next to a road
     * keyword, or a road keyword followed by an address number.
     */
    private function containsExactAddress(string $value): bool
    {
        $roads = '(?:rue|avenue|av\.?|boulevard|boul\.?|bd|street|st\.?|road|rd\.?|lot|villa|ferme|route|chem(?:in)?|all(?:ée|ee))';

        $numberBeforeStreet = '/(?<!\d)(?:\d{1,4}\s*,\s*|n[°o]\s*\d{1,4}\s+|\d{1,4}\s+)'.$roads.'\b/i';

        $streetWithNumber = '/\b'.$roads.'(?:\s+[^\s,;]{2,}){0,3}\s+[n°o]?\s*\d{1,5}\b/i';

        return preg_match($numberBeforeStreet, $value) === 1
            || preg_match($streetWithNumber, $value) === 1;
    }

    /**
     * Detect a URL with an embedded user:password credential segment.
     */
    private function containsUrlCredentials(string $value): bool
    {
        return preg_match('#\b[a-z][a-z0-9+.-]*://[^\s/@]+:[^\s/@]+@#i', $value) === 1;
    }
}
