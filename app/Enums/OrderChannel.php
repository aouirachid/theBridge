<?php

namespace App\Enums;

enum OrderChannel: string
{
    case B2c = 'b2c';
    case B2b = 'b2b';

    /**
     * The public display label for this channel.
     */
    public function label(): string
    {
        return match ($this) {
            self::B2c => 'Individual',
            self::B2b => 'Business',
        };
    }
}
