<?php

use App\Actions\CompletePlate;
use App\Actions\CreateFilament;
use App\Actions\ReconcileFilaments;
use App\Actions\ReopenPlate;
use App\Enums\MovementType;
use App\Enums\PlateStatus;
use App\Models\FilamentMovement;
use App\Models\Item;
use App\Models\Part;
use App\Models\Plate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('full production cycle from creation to reconciliation', function () {
    $filament = app(CreateFilament::class)->execute('PLA', 'Multfila', 'Rosa', 1000, 42.50);

    $partA = Part::query()->create([
        'code' => 'INT-PART-A-'.uniqid(),
        'description' => 'Integration Part A',
        'size' => 'M',
        'filament_g' => 1.20,
        'production_time_min' => 15,
        'is_active' => true,
    ]);

    $partB = Part::query()->create([
        'code' => 'INT-PART-B-'.uniqid(),
        'description' => 'Integration Part B',
        'size' => 'S',
        'filament_g' => 1.10,
        'production_time_min' => 10,
        'is_active' => true,
    ]);

    $plate = Plate::query()->create([
        'filament_code' => $filament->code,
        'status' => PlateStatus::CREATED,
        'sort_order' => 1,
        'consumed_filament_g' => null,
        'completed_at' => null,
    ]);

    $plate->parts()->attach($partA->id, ['quantity' => 2]);
    $plate->parts()->attach($partB->id, ['quantity' => 1]);

    $plate->update(['status' => PlateStatus::QUEUED]);
    $plate->update(['status' => PlateStatus::PRINTING]);

    app(CompletePlate::class)->execute($plate->fresh());

    $plate->refresh();
    $filament->refresh();

    expect($plate->status)->toBe(PlateStatus::COMPLETED)
        ->and((string) $plate->consumed_filament_g)->toBe('3.50')
        ->and(Item::query()->where('plate_id', $plate->id)->count())->toBe(3)
        ->and((string) $filament->balance)->toBe('996.50')
        ->and(FilamentMovement::query()->where('type', MovementType::CONSUMPTION)->count())->toBe(1);

    app(ReopenPlate::class)->execute($plate->fresh());

    $plate->refresh();
    $filament->refresh();

    expect($plate->status)->toBe(PlateStatus::PRINTING)
        ->and($plate->consumed_filament_g)->toBeNull()
        ->and(Item::query()->where('plate_id', $plate->id)->count())->toBe(0)
        ->and((string) $filament->balance)->toBe('1000.00')
        ->and(FilamentMovement::query()->where('type', MovementType::REVERSAL)->count())->toBe(1);

    app(CompletePlate::class)->execute($plate->fresh());

    $plate->refresh();
    $filament->refresh();

    expect($plate->status)->toBe(PlateStatus::COMPLETED)
        ->and((string) $plate->consumed_filament_g)->toBe('3.50')
        ->and(Item::query()->where('plate_id', $plate->id)->count())->toBe(3)
        ->and((string) $filament->balance)->toBe('996.50')
        ->and(FilamentMovement::query()->where('type', MovementType::CONSUMPTION)->count())->toBe(2)
        ->and(FilamentMovement::query()->where('type', MovementType::REVERSAL)->count())->toBe(1)
        ->and(FilamentMovement::query()->count())->toBe(4);

    $reconcileResult = app(ReconcileFilaments::class)->execute(true);

    expect($reconcileResult['divergences'])->toBe([]);

    $movements = FilamentMovement::query()
        ->where('filament_code', $filament->code)
        ->orderBy('id')
        ->get();

    expect($movements)->toHaveCount(4)
        ->and($movements->pluck('type')->map(fn ($type) => $type->value)->all())->toBe([
            MovementType::ENTRY->value,
            MovementType::CONSUMPTION->value,
            MovementType::REVERSAL->value,
            MovementType::CONSUMPTION->value,
        ])
        ->and((string) $movements[0]->previous_balance_g)->toBe('0.00')
        ->and((string) $movements[0]->new_balance_g)->toBe('1000.00')
        ->and((string) $movements[1]->previous_balance_g)->toBe('1000.00')
        ->and((string) $movements[1]->new_balance_g)->toBe('996.50')
        ->and((string) $movements[2]->previous_balance_g)->toBe('996.50')
        ->and((string) $movements[2]->new_balance_g)->toBe('1000.00')
        ->and((string) $movements[3]->previous_balance_g)->toBe('1000.00')
        ->and((string) $movements[3]->new_balance_g)->toBe('996.50');

    foreach ($movements as $movement) {
        $expected = round((float) $movement->previous_balance_g + (float) $movement->quantity_g, 2);
        $actual = round((float) $movement->new_balance_g, 2);

        expect(abs($expected - $actual) < 0.01)->toBeTrue();
    }
});
