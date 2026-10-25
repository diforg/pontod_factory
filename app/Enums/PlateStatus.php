<?php

namespace App\Enums;

enum PlateStatus: string
{
    case CREATED = 'CREATED';
    case QUEUED = 'QUEUED';
    case PRINTING = 'PRINTING';
    case COMPLETED = 'COMPLETED';

    public function label(): string
    {
        return match ($this) {
            self::CREATED => 'Criado',
            self::QUEUED => 'Na fila',
            self::PRINTING => 'Imprimindo',
            self::COMPLETED => 'Concluido',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status): string => $status->value, self::cases());
    }
}
