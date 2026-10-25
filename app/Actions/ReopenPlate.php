<?php

namespace App\Actions;

use App\Enums\MovementSourceType;
use App\Enums\MovementType;
use App\Enums\PlateStatus;
use App\Models\Filament;
use App\Models\FilamentMovement;
use App\Models\Plate;
use Illuminate\Support\Facades\DB;

class ReopenPlate
{
    public function execute(Plate $plate): Plate
    {
        return DB::transaction(function () use ($plate): Plate {
            // Read the frozen snapshot first; this guarantees we reverse exactly what was consumed.
            $frozenRaw = DB::table('plates')
                ->where('id', $plate->id)
                ->where('status', PlateStatus::COMPLETED->value)
                ->value('consumed_filament_g');

            if ($frozenRaw === null) {
                return $plate->fresh();
            }

            $frozenTotal = (float) $frozenRaw;

            $affected = DB::table('plates')
                ->where('id', $plate->id)
                ->where('status', PlateStatus::COMPLETED->value)
                ->update([
                    'status' => PlateStatus::PRINTING->value,
                    'consumed_filament_g' => null,
                    'completed_at' => null,
                ]);

            if ($affected === 0) {
                return $plate->fresh();
            }

            $filament = Filament::query()
                ->where('code', $plate->filament_code)
                ->lockForUpdate()
                ->firstOrFail();

            DB::table('part_plate')
                ->where('plate_id', $plate->id)
                ->update(['consumed_filament_g' => null]);

            DB::table('items')
                ->where('plate_id', $plate->id)
                ->delete();

            $previousBalance = (float) $filament->balance;
            $newBalance = round($previousBalance + $frozenTotal, 2);

            $filament->balance = $newBalance;
            $filament->save();

            FilamentMovement::query()->create([
                'filament_code' => $filament->code,
                'type' => MovementType::REVERSAL,
                'quantity_g' => $frozenTotal,
                'previous_balance_g' => $previousBalance,
                'new_balance_g' => $newBalance,
                'source_type' => MovementSourceType::PLATE,
                'source_id' => $plate->id,
                'occurred_at' => now(),
                'user_id' => auth()->id(),
            ]);

            return $plate->fresh();
        });
    }
}
