<?php

namespace App\Enums;

enum MovementSourceType: string
{
    case ENTRY = 'ENTRY';
    case PLATE = 'PLATE';
    case RECONCILIATION = 'RECONCILIATION';

    public function label(): string
    {
        return match ($this) {
            self::ENTRY => 'Entrada',
            self::PLATE => 'Placa',
            self::RECONCILIATION => 'Reconciliacao',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $source): string => $source->value, self::cases());
    }
}
