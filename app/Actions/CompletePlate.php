<?php

namespace App\Actions;

use App\Enums\MovementSourceType;
use App\Enums\MovementType;
use App\Enums\PlateStatus;
use App\Models\Filament;
use App\Models\FilamentMovement;
use App\Models\Plate;
use App\Services\PlateSnapshotService;
use DomainException;
use Illuminate\Support\Facades\DB;

class CompletePlate
{
    public function __construct(
        private readonly PlateSnapshotService $snapshotService,
    ) {
    }

    public function execute(Plate $plate): Plate
    {
        return DB::transaction(function () use ($plate): Plate {
            $affected = DB::table('plates')
                ->where('id', $plate->id)
                ->where('status', PlateStatus::PRINTING->value)
                ->update(['status' => PlateStatus::COMPLETED->value]);

            if ($affected === 0) {
                return $plate->fresh();
            }

            $filament = Filament::query()
                ->where('code', $plate->filament_code)
                ->lockForUpdate()
                ->firstOrFail();

            $snapshot = $this->snapshotService->computeForPlate($plate);
            $total = (float) $snapshot['total'];

            $currentBalance = (float) $filament->balance;

            if ($currentBalance < $total) {
                throw new DomainException(
                    "Insufficient filament balance. Required: {$total}, available: {$currentBalance}."
                );
            }

            $plate->consumed_filament_g = $total;
            $plate->completed_at = now();
            $plate->status = PlateStatus::COMPLETED;
            $plate->save();

            foreach ($snapshot['per_part'] as $partId => $partData) {
                DB::table('part_plate')
                    ->where('plate_id', $plate->id)
                    ->where('part_id', (int) $partId)
                    ->update([
                        'consumed_filament_g' => $partData['consumed_g'],
                    ]);
            }

            $itemsToInsert = [];
            $createdAt = now();

            foreach ($snapshot['per_part'] as $partId => $partData) {
                for ($i = 0; $i < $partData['quantity']; $i++) {
                    $itemsToInsert[] = [
                        'part_id' => (int) $partId,
                        'plate_id' => $plate->id,
                        'created_at' => $createdAt,
                    ];
                }
            }

            if ($itemsToInsert !== []) {
                DB::table('items')->insert($itemsToInsert);
            }

            $previousBalance = $currentBalance;
            $newBalance = round($previousBalance - $total, 2);

            $filament->balance = $newBalance;
            $filament->save();

            FilamentMovement::query()->create([
                'filament_code' => $filament->code,
                'type' => MovementType::CONSUMPTION,
                'quantity_g' => -$total,
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
