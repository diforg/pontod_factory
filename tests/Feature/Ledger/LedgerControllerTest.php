<?php

use App\Enums\MovementSourceType;
use App\Enums\MovementType;
use App\Models\Filament;
use App\Models\FilamentMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function ledgerControllerFilament(string $code, float $balance = 1000): Filament
{
    return Filament::query()->create([
        'code' => $code,
        'brand' => 'MULTFILA',
        'material' => 'PLA',
        'color' => 'PRETO',
        'weight' => 1000,
        'price' => 50,
        'is_active' => true,
        'balance' => $balance,
    ]);
}

function ledgerControllerMovement(string $filamentCode, MovementType $type, string $occurredAt): FilamentMovement
{
    return FilamentMovement::query()->create([
        'filament_code' => $filamentCode,
        'type' => $type,
        'quantity_g' => $type === MovementType::CONSUMPTION ? -20 : 20,
        'previous_balance_g' => 1000,
        'new_balance_g' => $type === MovementType::CONSUMPTION ? 980 : 1020,
        'source_type' => MovementSourceType::PLATE,
        'source_id' => 1,
        'occurred_at' => $occurredAt,
        'user_id' => null,
    ]);
}

test('authenticated user can view ledger index with inertia props', function () {
    $user = User::factory()->create();
    $this->withoutVite();

    $filament = ledgerControllerFilament('PLA-MULTFILA-PRETO-0001');
    ledgerControllerMovement($filament->code, MovementType::ENTRY, '2026-10-01 10:00:00');

    $response = $this->actingAs($user)->get('/ledger');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) =>
        $page->component('Ledger/Index', false)
            ->has('movements', 1)
            ->has('filaments')
            ->has('filters')
            ->has('auth.user')
            ->where('auth.user.id', $user->id)
    );
});

test('ledger index filters by movement type', function () {
    $user = User::factory()->create();
    $this->withoutVite();

    $filament = ledgerControllerFilament('PLA-MULTFILA-PRETO-0001');
    ledgerControllerMovement($filament->code, MovementType::ENTRY, '2026-10-01 10:00:00');
    ledgerControllerMovement($filament->code, MovementType::CONSUMPTION, '2026-10-02 10:00:00');

    $response = $this->actingAs($user)->get('/ledger?type=CONSUMPTION');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) =>
        $page->component('Ledger/Index', false)
            ->has('movements', 1)
            ->where('movements.0.type', MovementType::CONSUMPTION->value)
    );
});

test('ledger index filters by filament code', function () {
    $user = User::factory()->create();
    $this->withoutVite();

    $filamentA = ledgerControllerFilament('PLA-MULTFILA-PRETO-0001');
    $filamentB = ledgerControllerFilament('PLA-MULTFILA-ROSA-0002');

    ledgerControllerMovement($filamentA->code, MovementType::ENTRY, '2026-10-01 10:00:00');
    ledgerControllerMovement($filamentB->code, MovementType::ENTRY, '2026-10-02 10:00:00');

    $response = $this->actingAs($user)->get('/ledger?filament_code='.$filamentB->code);

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) =>
        $page->component('Ledger/Index', false)
            ->has('movements', 1)
            ->where('movements.0.filament_code', $filamentB->code)
    );
});

test('ledger index filters by occurred at date period', function () {
    $user = User::factory()->create();
    $this->withoutVite();

    $filament = ledgerControllerFilament('PLA-MULTFILA-PRETO-0001');

    ledgerControllerMovement($filament->code, MovementType::ENTRY, '2026-10-01 10:00:00');
    ledgerControllerMovement($filament->code, MovementType::CONSUMPTION, '2026-10-15 12:00:00');
    ledgerControllerMovement($filament->code, MovementType::REVERSAL, '2026-10-25 12:00:00');

    $response = $this->actingAs($user)->get('/ledger?from_date=2026-10-10&to_date=2026-10-20');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) =>
        $page->component('Ledger/Index', false)
            ->has('movements', 1)
            ->where('movements.0.type', MovementType::CONSUMPTION->value)
    );
});

test('guest is redirected to login when accessing ledger index', function () {
    $response = $this->get('/ledger');

    $response->assertRedirect('/login');
});

test('ledger show returns only movements for the requested filament', function () {
    $user = User::factory()->create();
    $this->withoutVite();

    $filamentA = ledgerControllerFilament('PLA-MULTFILA-PRETO-0001');
    $filamentB = ledgerControllerFilament('PLA-MULTFILA-ROSA-0002');

    ledgerControllerMovement($filamentA->code, MovementType::ENTRY, '2026-10-01 10:00:00');
    ledgerControllerMovement($filamentA->code, MovementType::CONSUMPTION, '2026-10-02 10:00:00');
    ledgerControllerMovement($filamentB->code, MovementType::ENTRY, '2026-10-03 10:00:00');

    $response = $this->actingAs($user)->get('/ledger/'.$filamentA->code);

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) =>
        $page->component('Ledger/Show', false)
            ->where('filament.code', $filamentA->code)
            ->has('movements', 2)
            ->where('movements.0.filament_code', $filamentA->code)
            ->where('movements.1.filament_code', $filamentA->code)
    );
});

test('ledger show with unknown filament code returns not found', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/ledger/INEXISTENTE-0001');

    $response->assertNotFound();
});
