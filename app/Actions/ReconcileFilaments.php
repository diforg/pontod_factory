<?php

namespace App\Actions;

use App\Enums\MovementSourceType;
use App\Enums\MovementType;
use App\Models\Filament;
use App\Models\FilamentMovement;
use Illuminate\Support\Facades\DB;

class ReconcileFilaments
{
    /**
     * Expected balance = baseline from the last reconciliation adjustment (if any)
     * + sum of operational movements after that baseline.
     *
     * RECONCILIATION_ADJUSTMENT movements are NOT summed as if they were
     * operational — they establish a new baseline. This preserves idempotency
     * (running apply twice is a no-op the second time) AND correctness for the
     * legitimate "adjust then continue operating" scenario.
     *
     * @return array{
     *   checked: int,
     *   consistent: array<int, string>,
     *   divergences: array<int, array{filament_code: string, current: float, expected: float, diff: float}>,
     *   corrected: array<int, string>,
     *   dry_run: bool
     * }
     */
    public function execute(bool $dryRun = true): array
    {
        $result = [
            'checked' => 0,
            'consistent' => [],
            'divergences' => [],
            'corrected' => [],
            'dry_run' => $dryRun,
        ];

        $filaments = Filament::query()->orderBy('code')->get();

        foreach ($filaments as $filament) {
            $result['checked']++;

            $expected = $this->computeExpectedBalance($filament->code);
            $current = (float) $filament->balance;
            $diff = round($expected - $current, 2);

            if (abs($diff) < 0.01) {
                $result['consistent'][] = $filament->code;

                continue;
            }

            $result['divergences'][] = [
                'filament_code' => $filament->code,
                'current' => $current,
                'expected' => $expected,
                'diff' => $diff,
            ];

            if ($dryRun) {
                continue;
            }

            DB::transaction(function () use ($filament, &$result): void {
                $locked = Filament::query()
                    ->where('code', $filament->code)
                    ->lockForUpdate()
                    ->firstOrFail();

                $expected = $this->computeExpectedBalance($locked->code);
                $previous = (float) $locked->balance;
                $diff = round($expected - $previous, 2);

                if (abs($diff) < 0.01) {
                    return;
                }

                $newBalance = round($previous + $diff, 2);

                $locked->balance = $newBalance;
                $locked->save();

                FilamentMovement::query()->create([
                    'filament_code' => $locked->code,
                    'type' => MovementType::RECONCILIATION_ADJUSTMENT,
                    'quantity_g' => $diff,
                    'previous_balance_g' => $previous,
                    'new_balance_g' => $newBalance,
                    'source_type' => MovementSourceType::RECONCILIATION,
                    'source_id' => null,
                    'occurred_at' => now(),
                    'user_id' => auth()->id(),
                ]);

                $result['corrected'][] = $locked->code;
            });
        }

        return $result;
    }

    /**
     * Expected balance = baseline from the last reconciliation adjustment, if any,
     * plus the sum of operational movements recorded afterward.
     */
    private function computeExpectedBalance(string $filamentCode): float
    {
        $lastAdjustment = FilamentMovement::query()
            ->where('filament_code', $filamentCode)
            ->where('type', MovementType::RECONCILIATION_ADJUSTMENT->value)
            ->orderByDesc('id')
            ->first();

        if ($lastAdjustment !== null) {
            $baseline = (float) $lastAdjustment->new_balance_g;

            $postAdjustmentOperationalSum = (float) FilamentMovement::query()
                ->where('filament_code', $filamentCode)
                ->whereIn('type', [
                    MovementType::ENTRY->value,
                    MovementType::CONSUMPTION->value,
                    MovementType::REVERSAL->value,
                ])
                ->where('id', '>', $lastAdjustment->id)
                ->sum('quantity_g');

            return round($baseline + $postAdjustmentOperationalSum, 2);
        }

        return round((float) FilamentMovement::query()
            ->where('filament_code', $filamentCode)
            ->whereIn('type', [
                MovementType::ENTRY->value,
                MovementType::CONSUMPTION->value,
                MovementType::REVERSAL->value,
            ])
            ->sum('quantity_g'), 2);
    }
}
