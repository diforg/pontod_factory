<?php

namespace App\Enums;

enum MovementType: string
{
    case ENTRY = 'ENTRY';
    case CONSUMPTION = 'CONSUMPTION';
    case REVERSAL = 'REVERSAL';
    case RECONCILIATION_ADJUSTMENT = 'RECONCILIATION_ADJUSTMENT';

    public function label(): string
    {
        return match ($this) {
            self::ENTRY => 'Entrada',
            self::CONSUMPTION => 'Consumo',
            self::REVERSAL => 'Reversao',
            self::RECONCILIATION_ADJUSTMENT => 'Ajuste de reconciliacao',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}