<?php

use App\Enums\PlateStatus;
use App\Models\Filament;
use App\Models\Item;
use App\Models\Part;
use App\Models\Plate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function itemControllerFilament(string $code): Filament
{
    return Filament::query()->create([
        'code' => $code,
        'brand' => 'MULTFILA',
        'material' => 'PLA',
        'color' => 'PRETO',
        'weight' => 1000,
        'price' => 50,
        'is_active' => true,
        'balance' => 900,
    ]);
}

function itemControllerPart(string $code): Part
{
    return Part::query()->create([
        'code' => $code,
        'description' => 'Peca '.$code,
        'size' => 'P',
        'filament_g' => 8,
        'production_time_min' => 30,
        'is_active' => true,
    ]);
}

function itemControllerPlate(string $filamentCode, int $sort = 0): Plate
{
    return Plate::query()->create([
        'filament_code' => $filamentCode,
        'status' => PlateStatus::COMPLETED,
        'sort_order' => $sort,
    ]);
}

test('authenticated user can view items index with inertia props', function () {
    $user = User::factory()->create();
    $this->withoutVite();

    $filament = itemControllerFilament('PLA-MULTFILA-PRETO-0001');
    $part = itemControllerPart('ITEM-PART-A');
    $plate = itemControllerPlate($filament->code, 1);

    Item::query()->create([
        'part_id' => $part->id,
        'plate_id' => $plate->id,
    ]);

    $response = $this->actingAs($user)->get('/items');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('Items/Index', false)
        ->has('items', 1)
        ->has('plates')
        ->has('parts')
        ->has('auth.user')
        ->where('auth.user.id', $user->id)
    );
});

test('items index filters by plate id', function () {
    $user = User::factory()->create();
    $this->withoutVite();

    $filament = itemControllerFilament('PLA-MULTFILA-PRETO-0001');
    $part = itemControllerPart('ITEM-PART-A');

    $plateA = itemControllerPlate($filament->code, 1);
    $plateB = itemControllerPlate($filament->code, 2);

    Item::query()->create(['part_id' => $part->id, 'plate_id' => $plateA->id]);
    Item::query()->create(['part_id' => $part->id, 'plate_id' => $plateB->id]);

    $response = $this->actingAs($user)->get('/items?plate_id='.$plateA->id);

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('Items/Index', false)
        ->has('items', 1)
        ->where('items.0.plate_id', $plateA->id)
    );
});

test('items index filters by part id', function () {
    $user = User::factory()->create();
    $this->withoutVite();

    $filament = itemControllerFilament('PLA-MULTFILA-PRETO-0001');
    $partA = itemControllerPart('ITEM-PART-A');
    $partB = itemControllerPart('ITEM-PART-B');
    $plate = itemControllerPlate($filament->code, 1);

    Item::query()->create(['part_id' => $partA->id, 'plate_id' => $plate->id]);
    Item::query()->create(['part_id' => $partB->id, 'plate_id' => $plate->id]);

    $response = $this->actingAs($user)->get('/items?part_id='.$partB->id);

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('Items/Index', false)
        ->has('items', 1)
        ->where('items.0.part_id', $partB->id)
    );
});

test('guest is redirected to login when accessing items index', function () {
    $response = $this->get('/items');

    $response->assertRedirect('/login');
});
