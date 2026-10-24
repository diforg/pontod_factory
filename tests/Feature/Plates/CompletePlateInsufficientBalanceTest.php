<?php

use App\Actions\CompletePlate;
use App\Actions\CreateFilament;
use App\Enums\MovementType;
use App\Enums\PlateStatus;
use App\Models\Filament;
use App\Models\FilamentMovement;
use App\Models\Item;
use App\Models\Part;
use App\Models\Plate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function buildInsufficientBalanceScenario(): array
{
    $filament = app(CreateFilament::class)->execute('PLA', 'Multfila', 'Rosa', 1000, 42.50);

    $filament->balance = 2.0;
    $filament->save();

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

    return [$filament, $plate];
}

test('it throws domain exception when filament balance is insufficient', function () {
    [, $plate] = buildInsufficientBalanceScenario();

    expect(fn () => app(CompletePlate::class)->execute($plate))
        ->toThrow(DomainException::class, 'Insufficient');
});

test('it rolls back every side effect when balance is insufficient', function () {
    [$filament, $plate] = buildInsufficientBalanceScenario();

    try {
        app(CompletePlate::class)->execute($plate);
    } catch (DomainException) {
        // Expected path for insufficient balance scenario.
    }

    $plate->refresh();
    $filament->refresh();

    expect($plate->status)->toBe(PlateStatus::PRINTING)
        ->and($plate->consumed_filament_g)->toBeNull()
        ->and((string) $filament->balance)->toBe('2.00')
        ->and(Item::query()->where('plate_id', $plate->id)->count())->toBe(0)
        ->and(FilamentMovement::query()->where('type', MovementType::CONSUMPTION)->count())->toBe(0);
});
