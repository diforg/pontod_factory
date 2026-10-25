<?php

use App\Actions\CreateFilament;
use App\Enums\MovementType;
use App\Models\Filament;
use App\Models\FilamentMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use function Pest\Laravel\artisan;
use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

function createCommandTestFilament(string $brand = 'Multfila', float $weight = 1000): Filament
{
    return app(CreateFilament::class)->execute('PLA', $brand, 'Rosa', $weight, 42.50);
}

test('dry-run with no divergences returns success and reports consistent ledger', function () {
    createCommandTestFilament();

    artisan('filaments:reconcile')
        ->expectsOutputToContain('All filaments are consistent')
        ->assertExitCode(0);
});

test('dry-run with divergence does not change database', function () {
    $filament = createCommandTestFilament();

    DB::table('filaments')
        ->where('code', $filament->code)
        ->update(['balance' => 900]);

    artisan('filaments:reconcile')
        ->expectsOutputToContain('Dry-run')
        ->assertExitCode(0);

    assertDatabaseHas('filaments', [
        'code' => $filament->code,
        'balance' => 900,
    ]);

    expect(FilamentMovement::query()
        ->where('type', MovementType::RECONCILIATION_ADJUSTMENT)
        ->count())->toBe(0);
});

test('apply mode fixes divergence', function () {
    $filament = createCommandTestFilament();

    DB::table('filaments')
        ->where('code', $filament->code)
        ->update(['balance' => 900]);

    artisan('filaments:reconcile --apply')
        ->expectsOutputToContain('Corrected: 1')
        ->assertExitCode(0);

    assertDatabaseHas('filaments', [
        'code' => $filament->code,
        'balance' => 1000,
    ]);

    expect(FilamentMovement::query()
        ->where('type', MovementType::RECONCILIATION_ADJUSTMENT)
        ->count())->toBe(1);
});

test('apply mode is idempotent on second run', function () {
    $filament = createCommandTestFilament();

    DB::table('filaments')
        ->where('code', $filament->code)
        ->update(['balance' => 900]);

    artisan('filaments:reconcile --apply')
        ->assertExitCode(0);

    artisan('filaments:reconcile --apply')
        ->expectsOutputToContain('All filaments are consistent')
        ->assertExitCode(0);

    expect(FilamentMovement::query()
        ->where('type', MovementType::RECONCILIATION_ADJUSTMENT)
        ->count())->toBe(1);
});