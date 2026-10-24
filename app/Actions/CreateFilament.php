<?php

namespace App\Actions;

use App\Enums\MovementSourceType;
use App\Enums\MovementType;
use App\Models\Filament;
use App\Models\FilamentMovement;
use App\Support\FilamentCodeGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CreateFilament
{
    public function __construct(
        private readonly FilamentCodeGenerator $codeGenerator,
    ) {
    }

    public function execute(
        string $material,
        string $brand,
        string $color,
        float $weight,
        float $price
    ): Filament {
        $maxAttempts = 3;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return DB::transaction(function () use ($material, $brand, $color, $weight, $price): Filament {
                    $code = $this->codeGenerator->generate($material, $brand, $color);

                    $filament = Filament::query()->create([
                        'code' => $code,
                        'brand' => strtoupper(trim($brand)),
                        'material' => strtoupper(trim($material)),
                        'color' => strtoupper(trim($color)),
                        'weight' => $weight,
                        'price' => $price,
                        'is_active' => true,
                        'balance' => $weight,
                    ]);

                    FilamentMovement::query()->create([
                        'filament_code' => $code,
                        'type' => MovementType::ENTRY,
                        'quantity_g' => $weight,
                        'previous_balance_g' => 0,
                        'new_balance_g' => $weight,
                        'source_type' => MovementSourceType::ENTRY,
                        'source_id' => null,
                        'occurred_at' => now(),
                        'user_id' => auth()->id(),
                    ]);

                    return $filament;
                });
            } catch (QueryException $exception) {
                if (! $this->isUniqueViolation($exception) || $attempt === $maxAttempts) {
                    throw $exception;
                }
            }
        }

        throw new \RuntimeException('Unreachable: CreateFilament attempts exhausted without result.');
    }

    private function isUniqueViolation(QueryException $exception): bool
    {
        $sqlState = $exception->errorInfo[0] ?? '';

        return in_array($sqlState, ['23000', '23505'], true);
    }
}
