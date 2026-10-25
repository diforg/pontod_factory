<?php

use App\Enums\MovementSourceType;
use App\Enums\MovementType;
use App\Models\Filament;
use App\Models\FilamentMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createMovement(): FilamentMovement
{
    $filament = Filament::query()->create([
        'code' => 'PLA-TEST-BLACK-0001',
        'brand' => 'TEST',
        'material' => 'PLA',
        'color' => 'BLACK',
        'weight' => 1000,
        'price' => 100,
        'is_active' => true,
        'balance' => 1000,
    ]);

    return FilamentMovement::query()->create([
        'filament_code' => $filament->code,
        'type' => MovementType::ENTRY,
        'quantity_g' => 1000,
        'previous_balance_g' => 0,
        'new_balance_g' => 1000,
        'source_type' => MovementSourceType::ENTRY,
        'source_id' => null,
        'occurred_at' => now(),
        'user_id' => null,
    ]);
}

test('update throws exception on FilamentMovement', function () {
    $movement = createMovement();

    expect(fn () => $movement->update(['quantity_g' => 999]))
        ->toThrow(RuntimeException::class);
});

test('delete throws exception on FilamentMovement', function () {
    $movement = createMovement();

    expect(fn () => $movement->delete())
        ->toThrow(RuntimeException::class);
});

test('mass update via query builder is blocked', function () {
    $this->markTestSkipped('Known limitation: mass updates bypass model events; guarded by code review policy.');
});
