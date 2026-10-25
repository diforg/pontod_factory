<?php

use App\Enums\MovementType;
use App\Models\Filament;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function filamentPayload(array $overrides = []): array
{
    return array_merge([
        'material' => 'PLA',
        'brand' => 'Multfila',
        'color' => 'Rosa',
        'weight' => 1000,
        'price' => 42.50,
    ], $overrides);
}

test('authenticated user can view filaments index with inertia props', function () {
    $user = User::factory()->create();
    $this->withoutVite();
    $this->actingAs($user);

    $response = $this->get('/filaments');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('Filaments/Index', false)
        ->has('filaments')
        ->has('auth.user')
        ->where('auth.user.id', $user->id)
    );
});

test('guest is redirected to login when accessing filaments index', function () {
    $response = $this->get('/filaments');

    $response->assertRedirect('/login');
});

test('store creates filament and entry movement', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/filaments', filamentPayload());

    $response->assertRedirect(route('filaments.index', absolute: false));

    $this->assertDatabaseHas('filaments', [
        'code' => 'PLA-MULTFILA-ROSA-0001',
        'brand' => 'MULTFILA',
        'material' => 'PLA',
        'color' => 'ROSA',
    ]);

    $this->assertDatabaseHas('filament_movements', [
        'filament_code' => 'PLA-MULTFILA-ROSA-0001',
        'type' => MovementType::ENTRY->value,
    ]);
});

test('store rejects invalid payload when weight is zero', function () {
    $user = User::factory()->create();

    $response = $this->from('/filaments')->actingAs($user)->post('/filaments', filamentPayload([
        'weight' => 0,
    ]));

    $response->assertRedirect('/filaments');
    $response->assertSessionHasErrors('weight');
});

test('update changes allowed fields and keeps invariants unchanged', function () {
    $user = User::factory()->create();

    $filament = Filament::query()->create([
        'code' => 'PLA-MULTFILA-ROSA-0001',
        'brand' => 'MULTFILA',
        'material' => 'PLA',
        'color' => 'ROSA',
        'weight' => 1000,
        'price' => 42.50,
        'is_active' => true,
        'balance' => 990,
    ]);

    $response = $this->actingAs($user)->patch('/filaments/'.$filament->code, [
        'brand' => 'Voolt3d',
        'price' => 39.90,
    ]);

    $response->assertRedirect(route('filaments.index', absolute: false));

    $filament->refresh();

    expect($filament->brand)->toBe('Voolt3d')
        ->and((float) $filament->price)->toBe(39.9)
        ->and((float) $filament->balance)->toBe(990.0)
        ->and($filament->code)->toBe('PLA-MULTFILA-ROSA-0001');
});

test('update ignores forbidden fields code weight and material', function () {
    $user = User::factory()->create();

    $filament = Filament::query()->create([
        'code' => 'PLA-MULTFILA-ROSA-0001',
        'brand' => 'MULTFILA',
        'material' => 'PLA',
        'color' => 'ROSA',
        'weight' => 1000,
        'price' => 42.50,
        'is_active' => true,
        'balance' => 1000,
    ]);

    $response = $this->actingAs($user)->patch('/filaments/'.$filament->code, [
        'code' => 'PETG-BRAND-COLOR-0001',
        'material' => 'PETG',
        'weight' => 700,
        'brand' => 'NovaMarca',
    ]);

    $response->assertRedirect(route('filaments.index', absolute: false));

    $filament->refresh();

    expect($filament->code)->toBe('PLA-MULTFILA-ROSA-0001')
        ->and($filament->material)->toBe('PLA')
        ->and((float) $filament->weight)->toBe(1000.0)
        ->and($filament->brand)->toBe('NovaMarca');
});

test('activate endpoint sets filament as active', function () {
    $user = User::factory()->create();

    $filament = Filament::query()->create([
        'code' => 'PLA-MULTFILA-ROSA-0001',
        'brand' => 'MULTFILA',
        'material' => 'PLA',
        'color' => 'ROSA',
        'weight' => 1000,
        'price' => 42.50,
        'is_active' => false,
        'balance' => 1000,
    ]);

    $response = $this->actingAs($user)->post('/filaments/'.$filament->code.'/activate');

    $response->assertRedirect(route('filaments.index', absolute: false));
    $this->assertDatabaseHas('filaments', [
        'code' => $filament->code,
        'is_active' => 1,
    ]);
});

test('deactivate endpoint sets filament as inactive', function () {
    $user = User::factory()->create();

    $filament = Filament::query()->create([
        'code' => 'PLA-MULTFILA-ROSA-0001',
        'brand' => 'MULTFILA',
        'material' => 'PLA',
        'color' => 'ROSA',
        'weight' => 1000,
        'price' => 42.50,
        'is_active' => true,
        'balance' => 1000,
    ]);

    $response = $this->actingAs($user)->post('/filaments/'.$filament->code.'/deactivate');

    $response->assertRedirect(route('filaments.index', absolute: false));
    $this->assertDatabaseHas('filaments', [
        'code' => $filament->code,
        'is_active' => 0,
    ]);
});

test('activate is idempotent when filament is already active', function () {
    $user = User::factory()->create();

    $filament = Filament::query()->create([
        'code' => 'PLA-MULTFILA-ROSA-0001',
        'brand' => 'MULTFILA',
        'material' => 'PLA',
        'color' => 'ROSA',
        'weight' => 1000,
        'price' => 42.50,
        'is_active' => true,
        'balance' => 1000,
    ]);

    $response = $this->actingAs($user)->post('/filaments/'.$filament->code.'/activate');

    $response->assertRedirect(route('filaments.index', absolute: false));
    $this->assertDatabaseHas('filaments', [
        'code' => $filament->code,
        'is_active' => 1,
    ]);
});

test('activate on missing filament by code returns not found', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/filaments/INEXISTENTE-0001/activate');

    $response->assertNotFound();
});
