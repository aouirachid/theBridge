<?php

namespace App\Enums;

/**
 * The complete Phase 2 Casablanca delivery service map.
 */
enum DeliveryZone: string
{
    case CasablancaCentre = 'casablanca_centre';
    case CasablancaEast = 'casablanca_east';
    case CasablancaWest = 'casablanca_west';

    /**
     * The public display label for this zone.
     */
    public function label(): string
    {
        return match ($this) {
            self::CasablancaCentre => 'Casablanca Centre',
            self::CasablancaEast => 'Casablanca East',
            self::CasablancaWest => 'Casablanca West',
        };
    }
}
