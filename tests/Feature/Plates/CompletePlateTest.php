<?php

use App\Actions\CompletePlate;
use App\Actions\CreateFilament;
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

function buildPrintingPlateScenario(): array
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

    return [$filament, $plate, $partA, $partB];
}

test('it completes plate and freezes total consumption snapshot', function () {
    [, $plate] = buildPrintingPlateScenario();

    $completed = app(CompletePlate::class)->execute($plate);

    expect($completed->status)->toBe(PlateStatus::COMPLETED)
        ->and((string) $completed->consumed_filament_g)->toBe('3.20')
        ->and($completed->completed_at)->not->toBeNull();
});

test('it freezes per-part snapshot in pivot rows', function () {
    [, $plate, $partA, $partB] = buildPrintingPlateScenario();

    app(CompletePlate::class)->execute($plate);

    $pivotA = DB::table('part_plate')
        ->where('plate_id', $plate->id)
        ->where('part_id', $partA->id)
        ->first();

    $pivotB = DB::table('part_plate')
        ->where('plate_id', $plate->id)
        ->where('part_id', $partB->id)
        ->first();

    expect((string) $pivotA->consumed_filament_g)->toBe('2.40')
        ->and((int) $pivotA->quantity)->toBe(2)
        ->and((string) $pivotB->consumed_filament_g)->toBe('0.80')
        ->and((int) $pivotB->quantity)->toBe(1);
});

test('it creates one item per unit per part', function () {
    [, $plate, $partA, $partB] = buildPrintingPlateScenario();

    app(CompletePlate::class)->execute($plate);

    expect(Item::query()->where('plate_id', $plate->id)->count())->toBe(3)
        ->and(Item::query()->where('plate_id', $plate->id)->where('part_id', $partA->id)->count())->toBe(2)
        ->and(Item::query()->where('plate_id', $plate->id)->where('part_id', $partB->id)->count())->toBe(1);
});

test('it decrements filament balance by frozen consumption', function () {
    [$filament, $plate] = buildPrintingPlateScenario();

    app(CompletePlate::class)->execute($plate);

    $filament->refresh();

    expect((string) $filament->balance)->toBe('996.80');
});

test('it writes one CONSUMPTION movement with expected ledger values', function () {
    [, $plate] = buildPrintingPlateScenario();

    app(CompletePlate::class)->execute($plate);

    $movement = FilamentMovement::query()
        ->where('type', MovementType::CONSUMPTION)
        ->firstOrFail();

    expect((string) $movement->quantity_g)->toBe('-3.20')
        ->and((string) $movement->previous_balance_g)->toBe('1000.00')
        ->and((string) $movement->new_balance_g)->toBe('996.80')
        ->and($movement->source_type)->toBe(MovementSourceType::PLATE)
        ->and($movement->source_id)->toBe($plate->id)
        ->and($movement->filament_code)->toBe($plate->filament_code);

    expect(FilamentMovement::query()->where('type', MovementType::CONSUMPTION)->count())->toBe(1);
});

test('it is idempotent when execute is called twice and returns early without side effects', function () {
    [$filament, $plate] = buildPrintingPlateScenario();

    $action = app(CompletePlate::class);

    $action->execute($plate->fresh());
    $second = $action->execute($plate->fresh());

    $filament->refresh();

    expect($second->status)->toBe(PlateStatus::COMPLETED)
        ->and(Item::query()->where('plate_id', $plate->id)->count())->toBe(3)
        ->and((string) $filament->balance)->toBe('996.80')
        ->and(FilamentMovement::query()->where('type', MovementType::CONSUMPTION)->count())->toBe(1);
});

test('it is a no-op when plate is already completed from another flow', function () {
    [$filament, $plate] = buildPrintingPlateScenario();

    $plate->update([
        'status' => PlateStatus::COMPLETED,
        'completed_at' => now(),
    ]);

    $result = app(CompletePlate::class)->execute($plate->fresh());
    $filament->refresh();

    expect($result->status)->toBe(PlateStatus::COMPLETED)
        ->and(Item::query()->where('plate_id', $plate->id)->count())->toBe(0)
        ->and((string) $filament->balance)->toBe('1000.00')
        ->and(FilamentMovement::query()->where('type', MovementType::CONSUMPTION)->count())->toBe(0);
});
