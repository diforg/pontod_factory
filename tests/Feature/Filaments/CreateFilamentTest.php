<?php

use App\Actions\CreateFilament;
use App\Enums\MovementSourceType;
use App\Enums\MovementType;
use App\Models\Filament;
use App\Models\FilamentMovement;
use App\Support\FilamentCodeGenerator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createExistingFilament(string $code = 'PLA-MULTFILA-ROSA-0001'): Filament
{
    return Filament::query()->create([
        'code' => $code,
        'brand' => 'MULTFILA',
        'material' => 'PLA',
        'color' => 'ROSA',
        'weight' => 1000,
        'price' => 42.50,
        'is_active' => true,
        'balance' => 1000,
    ]);
}

test('it creates filament with generated code, initial balance and active flag', function () {
    $filament = app(CreateFilament::class)->execute('PLA', 'Multfila', 'Rosa', 1000, 42.50);

    expect($filament->code)->toBe('PLA-MULTFILA-ROSA-0001')
        ->and((string) $filament->balance)->toBe('1000.00')
        ->and($filament->is_active)->toBeTrue();

    $this->assertDatabaseHas('filaments', [
        'code' => 'PLA-MULTFILA-ROSA-0001',
        'brand' => 'MULTFILA',
        'material' => 'PLA',
        'color' => 'ROSA',
        'is_active' => 1,
    ]);
});

test('it writes exactly one ENTRY movement with expected values', function () {
    $filament = app(CreateFilament::class)->execute('PLA', 'Multfila', 'Rosa', 1000, 42.50);

    expect(FilamentMovement::query()->count())->toBe(1);

    $movement = FilamentMovement::query()->firstOrFail();

    expect($movement->type)->toBe(MovementType::ENTRY)
        ->and((string) $movement->quantity_g)->toBe('1000.00')
        ->and((string) $movement->previous_balance_g)->toBe('0.00')
        ->and((string) $movement->new_balance_g)->toBe('1000.00')
        ->and($movement->source_type)->toBe(MovementSourceType::ENTRY)
        ->and($movement->source_id)->toBeNull()
        ->and($movement->filament_code)->toBe($filament->code)
        ->and($movement->occurred_at)->not->toBeNull();
});

test('it is atomic when filament insert fails and does not persist movement', function () {
    $longCode = str_repeat('A', 65);

    $generator = Mockery::mock(FilamentCodeGenerator::class);
    $generator->shouldReceive('generate')->once()->andReturn($longCode);

    $this->app->instance(FilamentCodeGenerator::class, $generator);

    expect(fn () => app(CreateFilament::class)->execute('PLA', 'Multfila', 'Rosa', 1000, 42.50))
        ->toThrow(QueryException::class);

    expect(Filament::query()->count())->toBe(0)
        ->and(FilamentMovement::query()->count())->toBe(0);
});

test('it succeeds with next sequence when first code already exists', function () {
    createExistingFilament('PLA-MULTFILA-ROSA-0001');

    $filament = app(CreateFilament::class)->execute('PLA', 'Multfila', 'Rosa', 1000, 42.50);

    expect($filament->code)->toBe('PLA-MULTFILA-ROSA-0002');

    $this->assertDatabaseHas('filament_movements', [
        'filament_code' => 'PLA-MULTFILA-ROSA-0002',
        'type' => MovementType::ENTRY->value,
    ]);
});

test('it retries unique violations up to three attempts and then throws', function () {
    createExistingFilament('PLA-MULTFILA-ROSA-0001');

    $generator = Mockery::mock(FilamentCodeGenerator::class);
    $generator->shouldReceive('generate')->times(3)->andReturn('PLA-MULTFILA-ROSA-0001');

    $this->app->instance(FilamentCodeGenerator::class, $generator);

    expect(fn () => app(CreateFilament::class)->execute('PLA', 'Multfila', 'Rosa', 1000, 42.50))
        ->toThrow(QueryException::class);

    expect(Filament::query()->count())->toBe(1)
        ->and(FilamentMovement::query()->count())->toBe(0);
});
