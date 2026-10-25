<?php

use App\Actions\CompletePlate;
use App\Actions\CreateFilament;
use App\Actions\ReopenPlate;
use App\Enums\MovementSourceType;
use App\Enums\MovementType;
use App\Enums\PlateStatus;
use App\Models\FilamentMovement;
use App\Models\Item;
use App\Models\Part;
use App\Models\Plate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function buildCompletedPlateForReopenScenario(): array
{
    $filament = app(CreateFilament::class)->execute('PLA', 'Multfila', 'Rosa', 1000, 42.50);

    $partA = Part::query()->create([
        'code' => 'PART-A-'.uniqid(),
        'description' => 'Part A',
        'size' => 'S',
        'filament_g' => 1.2,
        'production_time_min' => 10,
        'is_active' => true,
    ]);

    $partB = Part::query()->create([
        'code' => 'PART-B-'.uniqid(),
        'description' => 'Part B',
        'size' => 'M',
        'filament_g' => 0.8,
        'production_time_min' => 12,
        'is_active' => true,
    ]);

    $plate = Plate::query()->create([
        'filament_code' => $filament->code,
        'status' => PlateStatus::PRINTING,
        'sort_order' => 1,
        'consumed_filament_g' => null,
        'completed_at' => null,
    ]);

    $plate->parts()->attach($partA->id, ['quantity' => 2]);
    $plate->parts()->attach($partB->id, ['quantity' => 1]);

    app(CompletePlate::class)->execute($plate->fresh());

    $plate->refresh();
    $filament->refresh();

    return [$filament, $plate, $partA, $partB];
}

test('it reopens a completed plate back to printing', function () {
    [, $plate] = buildCompletedPlateForReopenScenario();

    $reopened = app(ReopenPlate::class)->execute($plate->fresh());

    expect($reopened->status)->toBe(PlateStatus::PRINTING);
});

test('it clears frozen total snapshot when reopening', function () {
    [, $plate] = buildCompletedPlateForReopenScenario();

    $reopened = app(ReopenPlate::class)->execute($plate->fresh());

    expect($reopened->consumed_filament_g)->toBeNull()
        ->and($reopened->completed_at)->toBeNull();
});

test('it clears per-part snapshot on pivot and preserves quantities', function () {
    [, $plate, $partA, $partB] = buildCompletedPlateForReopenScenario();

    app(ReopenPlate::class)->execute($plate->fresh());

    $pivotA = DB::table('part_plate')
        ->where('plate_id', $plate->id)
        ->where('part_id', $partA->id)
        ->first();

    $pivotB = DB::table('part_plate')
        ->where('plate_id', $plate->id)
        ->where('part_id', $partB->id)
        ->first();

    expect($pivotA->consumed_filament_g)->toBeNull()
        ->and((int) $pivotA->quantity)->toBe(2)
        ->and($pivotB->consumed_filament_g)->toBeNull()
        ->and((int) $pivotB->quantity)->toBe(1);
});

test('it removes only items from reopened plate and keeps other plate items', function () {
    [, $plate] = buildCompletedPlateForReopenScenario();
    [, $otherPlate] = buildCompletedPlateForReopenScenario();

    expect(Item::query()->where('plate_id', $plate->id)->count())->toBe(3)
        ->and(Item::query()->where('plate_id', $otherPlate->id)->count())->toBe(3);

    app(ReopenPlate::class)->execute($plate->fresh());

    expect(Item::query()->where('plate_id', $plate->id)->count())->toBe(0)
        ->and(Item::query()->where('plate_id', $otherPlate->id)->count())->toBe(3);
});

test('it writes one REVERSAL movement using frozen snapshot values', function () {
    [, $plate] = buildCompletedPlateForReopenScenario();

    app(ReopenPlate::class)->execute($plate->fresh());

    $movement = FilamentMovement::query()
        ->where('type', MovementType::REVERSAL)
        ->firstOrFail();

    expect((string) $movement->quantity_g)->toBe('3.20')
        ->and((string) $movement->previous_balance_g)->toBe('996.80')
        ->and((string) $movement->new_balance_g)->toBe('1000.00')
        ->and($movement->source_type)->toBe(MovementSourceType::PLATE)
        ->and($movement->source_id)->toBe($plate->id)
        ->and($movement->filament_code)->toBe($plate->filament_code);

    expect(FilamentMovement::query()->where('type', MovementType::CONSUMPTION)->count())->toBe(1)
        ->and(FilamentMovement::query()->where('type', MovementType::REVERSAL)->count())->toBe(1);
});

test('it increments filament balance back to original value', function () {
    [$filament, $plate] = buildCompletedPlateForReopenScenario();

    app(ReopenPlate::class)->execute($plate->fresh());
    $filament->refresh();

    expect((string) $filament->balance)->toBe('1000.00');
});

test('it uses frozen snapshot and does not recalculate after part change', function () {
    [$filament, $plate, $partA] = buildCompletedPlateForReopenScenario();

    $partA->update(['filament_g' => 99.0]);

    app(ReopenPlate::class)->execute($plate->fresh());

    $movement = FilamentMovement::query()
        ->where('type', MovementType::REVERSAL)
        ->firstOrFail();

    $filament->refresh();

    expect((string) $movement->quantity_g)->toBe('3.20')
        ->and((string) $filament->balance)->toBe('1000.00');
});

test('it is idempotent when reopen is called twice', function () {
    [$filament, $plate] = buildCompletedPlateForReopenScenario();

    $action = app(ReopenPlate::class);

    $action->execute($plate->fresh());
    $second = $action->execute($plate->fresh());

    $filament->refresh();

    expect($second->status)->toBe(PlateStatus::PRINTING)
        ->and(FilamentMovement::query()->where('type', MovementType::REVERSAL)->count())->toBe(1)
        ->and((string) $filament->balance)->toBe('1000.00');
});

test('it is a no-op when reopening a non-completed plate', function () {
    $filament = app(CreateFilament::class)->execute('PLA', 'Multfila', 'Rosa', 1000, 42.50);

    $plate = Plate::query()->create([
        'filament_code' => $filament->code,
        'status' => PlateStatus::PRINTING,
        'sort_order' => 1,
        'consumed_filament_g' => null,
        'completed_at' => null,
    ]);

    $result = app(ReopenPlate::class)->execute($plate->fresh());
    $filament->refresh();

    expect($result->status)->toBe(PlateStatus::PRINTING)
        ->and((string) $filament->balance)->toBe('1000.00')
        ->and(FilamentMovement::query()->where('type', MovementType::REVERSAL)->count())->toBe(0)
        ->and(Item::query()->where('plate_id', $plate->id)->count())->toBe(0);
});
