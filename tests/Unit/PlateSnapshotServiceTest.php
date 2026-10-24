<?php

use App\Models\Filament;
use App\Models\Part;
use App\Models\Plate;
use App\Services\PlateSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makePlateWithPart(float $filamentG, int $quantity): array
{
    $filament = Filament::query()->create([
        'code' => 'PLA-TEST-BLACK-0001',
        'brand' => 'TEST',
        'material' => 'PLA',
        'color' => 'BLACK',
        'weight' => 1000,
        'price' => 25,
        'is_active' => true,
        'balance' => 1000,
    ]);

    $plate = Plate::query()->create([
        'filament_code' => $filament->code,
        'status' => 'CREATED',
        'sort_order' => 1,
        'consumed_filament_g' => null,
    ]);

    $part = Part::query()->create([
        'code' => 'PART-'.uniqid(),
        'description' => 'Test part',
        'size' => 'S',
        'filament_g' => $filamentG,
        'production_time_min' => 10,
        'is_active' => true,
    ]);

    $plate->parts()->attach($part->id, ['quantity' => $quantity]);

    return [$plate, $part];
}

test('computeForPlate calculates total and per-part consumption for one piece', function () {
    [$plate, $part] = makePlateWithPart(1.2, 1);

    expect((new PlateSnapshotService())->computeForPlate($plate))->toBe([
        'total' => 1.2,
        'per_part' => [
            $part->id => ['quantity' => 1, 'consumed_g' => 1.2],
        ],
    ]);
});

test('computeForPlate calculates total and per-part consumption for multiple quantity of same piece', function () {
    [$plate, $part] = makePlateWithPart(1.2, 3);

    expect((new PlateSnapshotService())->computeForPlate($plate))->toBe([
        'total' => 3.6,
        'per_part' => [
            $part->id => ['quantity' => 3, 'consumed_g' => 3.6],
        ],
    ]);
});

test('computeForPlate calculates total and per-part consumption for multiple different pieces', function () {
    $filament = Filament::query()->create([
        'code' => 'PLA-TEST-BLACK-0001',
        'brand' => 'TEST',
        'material' => 'PLA',
        'color' => 'BLACK',
        'weight' => 1000,
        'price' => 25,
        'is_active' => true,
        'balance' => 1000,
    ]);

    $plate = Plate::query()->create([
        'filament_code' => $filament->code,
        'status' => 'CREATED',
        'sort_order' => 1,
        'consumed_filament_g' => null,
    ]);

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
        'production_time_min' => 11,
        'is_active' => true,
    ]);

    $plate->parts()->attach($partA->id, ['quantity' => 2]);
    $plate->parts()->attach($partB->id, ['quantity' => 1]);

    expect((new PlateSnapshotService())->computeForPlate($plate))->toBe([
        'total' => 3.2,
        'per_part' => [
            $partA->id => ['quantity' => 2, 'consumed_g' => 2.4],
            $partB->id => ['quantity' => 1, 'consumed_g' => 0.8],
        ],
    ]);
});

test('computeForPlate returns zero for a plate without parts', function () {
    $filament = Filament::query()->create([
        'code' => 'PLA-TEST-BLACK-0001',
        'brand' => 'TEST',
        'material' => 'PLA',
        'color' => 'BLACK',
        'weight' => 1000,
        'price' => 25,
        'is_active' => true,
        'balance' => 1000,
    ]);

    $plate = Plate::query()->create([
        'filament_code' => $filament->code,
        'status' => 'CREATED',
        'sort_order' => 1,
        'consumed_filament_g' => null,
    ]);

    expect((new PlateSnapshotService())->computeForPlate($plate))->toBe([
        'total' => 0.0,
        'per_part' => [],
    ]);
});
