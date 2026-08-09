<?php

namespace App\Exceptions\Ordering;

use RuntimeException;

/**
 * A safe public conflict during order capture.
 *
 * Exposes only one fixed conflict code and a fixed user-facing message. It must
 * never carry submitted values, PII, raw submission tokens, SQL, or model dumps.
 */
final class OrderConflictException extends RuntimeException
{
    public const OFFER_UNAVAILABLE = 'offer_unavailable';

    public const SLOT_UNAVAILABLE = 'slot_unavailable';

    public const QUANTITY_UNAVAILABLE = 'quantity_unavailable';

    public const SUBMISSION_MISMATCH = 'submission_mismatch';

    /**
     * @param  string  $code  one of the fixed public conflict codes
     */
    public function __construct(
        public $code,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function offerUnavailable(): self
    {
        return new self(self::OFFER_UNAVAILABLE, 'This offer is no longer available for ordering.');
    }

    public static function slotUnavailable(): self
    {
        return new self(self::SLOT_UNAVAILABLE, 'This delivery slot is no longer available.');
    }

    public static function quantityUnavailable(): self
    {
        return new self(self::QUANTITY_UNAVAILABLE, 'The requested quantity exceeds the remaining availability.');
    }

    public static function submissionMismatch(): self
    {
        return new self(self::SUBMISSION_MISMATCH, 'This submission token was already used with different details.');
    }
}
