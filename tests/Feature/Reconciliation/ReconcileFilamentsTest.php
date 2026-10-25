<?php

use App\Actions\CreateFilament;
use App\Actions\ReconcileFilaments;
use App\Enums\MovementSourceType;
use App\Enums\MovementType;
use App\Models\Filament;
use App\Models\FilamentMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function createLedgerBackedFilament(string $brand = 'Multfila', float $weight = 1000): Filament
{
    return app(CreateFilament::class)->execute('PLA', $brand, 'Rosa', $weight, 42.50);
}

test('it returns no divergences in dry-run when filament is consistent', function () {
    createLedgerBackedFilament();

    $result = app(ReconcileFilaments::class)->execute(true);

    expect($result['checked'])->toBe(1)
        ->and($result['dry_run'])->toBeTrue()
        ->and($result['divergences'])->toBe([])
        ->and(FilamentMovement::query()->where('type', MovementType::RECONCILIATION_ADJUSTMENT)->count())->toBe(0);
});

test('it does not create reconciliation movement on apply when filament is consistent', function () {
    createLedgerBackedFilament();

    $result = app(ReconcileFilaments::class)->execute(false);

    expect($result['checked'])->toBe(1)
        ->and($result['dry_run'])->toBeFalse()
        ->and($result['divergences'])->toBe([])
        ->and($result['corrected'])->toBe([])
        ->and(FilamentMovement::query()->where('type', MovementType::RECONCILIATION_ADJUSTMENT)->count())->toBe(0);
});

test('it lists divergence in dry-run without changing balance or movement', function () {
    $filament = createLedgerBackedFilament();

    DB::table('filaments')
        ->where('code', $filament->code)
        ->update(['balance' => 900]);

    $result = app(ReconcileFilaments::class)->execute(true);
    $filament->refresh();

    expect($result['divergences'])->toHaveCount(1)
        ->and($result['divergences'][0])->toMatchArray([
            'filament_code' => $filament->code,
            'current' => 900.0,
            'expected' => 1000.0,
            'diff' => 100.0,
        ])
        ->and((string) $filament->balance)->toBe('900.00')
        ->and(FilamentMovement::query()->where('type', MovementType::RECONCILIATION_ADJUSTMENT)->count())->toBe(0);
});

test('it corrects divergence on apply by creating RECONCILIATION_ADJUSTMENT movement', function () {
    $filament = createLedgerBackedFilament();

    DB::table('filaments')
        ->where('code', $filament->code)
        ->update(['balance' => 900]);

    $result = app(ReconcileFilaments::class)->execute(false);
    $filament->refresh();

    $movement = FilamentMovement::query()
        ->where('type', MovementType::RECONCILIATION_ADJUSTMENT)
        ->firstOrFail();

    expect((string) $movement->quantity_g)->toBe('100.00')
        ->and((string) $movement->previous_balance_g)->toBe('900.00')
        ->and((string) $movement->new_balance_g)->toBe('1000.00')
        ->and($movement->source_type)->toBe(MovementSourceType::RECONCILIATION)
        ->and($movement->source_id)->toBeNull()
        ->and((string) $filament->balance)->toBe('1000.00')
        ->and($result['corrected'])->toBe([$filament->code]);
});

test('it creates negative reconciliation movement when current balance is above ledger', function () {
    $filament = createLedgerBackedFilament();

    DB::table('filaments')
        ->where('code', $filament->code)
        ->update(['balance' => 1100]);

    app(ReconcileFilaments::class)->execute(false);

    $movement = FilamentMovement::query()
        ->where('type', MovementType::RECONCILIATION_ADJUSTMENT)
        ->firstOrFail();

    $filament->refresh();

    expect((string) $movement->quantity_g)->toBe('-100.00')
        ->and((string) $movement->previous_balance_g)->toBe('1100.00')
        ->and((string) $movement->new_balance_g)->toBe('1000.00')
        ->and((string) $filament->balance)->toBe('1000.00');
});

test('it corrects only divergent filaments on apply', function () {
    $filamentA = createLedgerBackedFilament('BrandA');
    $filamentB = createLedgerBackedFilament('BrandB');

    DB::table('filaments')
        ->where('code', $filamentB->code)
        ->update(['balance' => 900]);

    $result = app(ReconcileFilaments::class)->execute(false);

    expect(FilamentMovement::query()->where('type', MovementType::RECONCILIATION_ADJUSTMENT)->count())->toBe(1)
        ->and($result['corrected'])->toBe([$filamentB->code])
        ->and($result['consistent'])->toContain($filamentA->code);
});

test('it returns a clear execute result structure for dry-run and apply', function () {
    $filament = createLedgerBackedFilament();

    DB::table('filaments')
        ->where('code', $filament->code)
        ->update(['balance' => 900]);

    $dry = app(ReconcileFilaments::class)->execute(true);
    $apply = app(ReconcileFilaments::class)->execute(false);

    expect($dry)->toHaveKeys(['checked', 'consistent', 'divergences', 'corrected', 'dry_run'])
        ->and($apply)->toHaveKeys(['checked', 'consistent', 'divergences', 'corrected', 'dry_run'])
        ->and($dry['dry_run'])->toBeTrue()
        ->and($apply['dry_run'])->toBeFalse()
        ->and($dry['divergences'])->toHaveCount(1)
        ->and($apply['corrected'])->toBe([$filament->code]);
});

test('second apply finds no divergence after first correction', function () {
    $filament = createLedgerBackedFilament();

    DB::table('filaments')
        ->where('code', $filament->code)
        ->update(['balance' => 900]);

    $first = app(ReconcileFilaments::class)->execute(false);
    $second = app(ReconcileFilaments::class)->execute(false);
    $filament->refresh();

    expect($first['corrected'])->toBe([$filament->code])
        ->and($second['corrected'])->toBe([])
        ->and($second['divergences'])->toBe([])
        ->and(FilamentMovement::query()->where('type', MovementType::RECONCILIATION_ADJUSTMENT)->count())->toBe(1)
        ->and((string) $filament->balance)->toBe('1000.00');
});

test('adjustment preserves baseline across subsequent consumption', function () {
    $filament = createLedgerBackedFilament();

    FilamentMovement::query()->create([
        'filament_code' => $filament->code,
        'type' => MovementType::RECONCILIATION_ADJUSTMENT,
        'quantity_g' => 100.0,
        'previous_balance_g' => 1000.0,
        'new_balance_g' => 1100.0,
        'source_type' => MovementSourceType::RECONCILIATION,
        'source_id' => null,
        'occurred_at' => now(),
        'user_id' => auth()->id(),
    ]);

    FilamentMovement::query()->create([
        'filament_code' => $filament->code,
        'type' => MovementType::CONSUMPTION,
        'quantity_g' => -50.0,
        'previous_balance_g' => 1100.0,
        'new_balance_g' => 1050.0,
        'source_type' => MovementSourceType::PLATE,
        'source_id' => null,
        'occurred_at' => now(),
        'user_id' => auth()->id(),
    ]);

    DB::table('filaments')
        ->where('code', $filament->code)
        ->update(['balance' => 1050]);

    $dry = app(ReconcileFilaments::class)->execute(true);
    $apply = app(ReconcileFilaments::class)->execute(false);
    $filament->refresh();

    expect($dry['divergences'])->toBe([])
        ->and($dry['consistent'])->toContain($filament->code)
        ->and($apply['corrected'])->toBe([])
        ->and(FilamentMovement::query()->where('type', MovementType::RECONCILIATION_ADJUSTMENT)->count())->toBe(1)
        ->and((string) $filament->balance)->toBe('1050.00');
});

test('multiple adjustments preserve correct baseline', function () {
    $filament = createLedgerBackedFilament();

    FilamentMovement::query()->create([
        'filament_code' => $filament->code,
        'type' => MovementType::RECONCILIATION_ADJUSTMENT,
        'quantity_g' => 100.0,
        'previous_balance_g' => 1000.0,
        'new_balance_g' => 1100.0,
        'source_type' => MovementSourceType::RECONCILIATION,
        'source_id' => null,
        'occurred_at' => now(),
        'user_id' => auth()->id(),
    ]);

    FilamentMovement::query()->create([
        'filament_code' => $filament->code,
        'type' => MovementType::RECONCILIATION_ADJUSTMENT,
        'quantity_g' => -150.0,
        'previous_balance_g' => 1100.0,
        'new_balance_g' => 950.0,
        'source_type' => MovementSourceType::RECONCILIATION,
        'source_id' => null,
        'occurred_at' => now(),
        'user_id' => auth()->id(),
    ]);

    DB::table('filaments')
        ->where('code', $filament->code)
        ->update(['balance' => 950]);

    $result = app(ReconcileFilaments::class)->execute(false);
    $filament->refresh();

    expect($result['corrected'])->toBe([])
        ->and($result['divergences'])->toBe([])
        ->and(FilamentMovement::query()->where('type', MovementType::RECONCILIATION_ADJUSTMENT)->count())->toBe(2)
        ->and((string) $filament->balance)->toBe('950.00');
});
