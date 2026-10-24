<?php

namespace App\Services;

use App\Models\Plate;

class PlateSnapshotService
{
    /**
     * Compute the plate consumption snapshot from the current part filament usage.
     *
     * Details:
     * - loads the plate's parts with quantity from the pivot
     * - calculates consumed filament as part.filament_g * quantity
     * - returns a stateless snapshot for later persistence by business actions
     * - rounds values to two decimal places so the output is stable for storage and tests
     *
     * @return array{
     *   total: float,
     *   per_part: array<int, array{quantity: int, consumed_g: float}>
     * }
     */
    public function computeForPlate(Plate $plate): array
    {
        $plate->load(['parts' => function ($query) {
            $query->withPivot('quantity');
        }]);

        $total = 0.0;
        $perPart = [];

        foreach ($plate->parts as $part) {
            $quantity = (int) $part->pivot->quantity;
            $consumed = round((float) $part->filament_g * $quantity, 2);

            $total += $consumed;
            $perPart[(int) $part->id] = [
                'quantity' => $quantity,
                'consumed_g' => $consumed,
            ];
        }

        return [
            'total' => round($total, 2),
            'per_part' => $perPart,
        ];
    }
}
