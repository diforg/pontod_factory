<?php

use App\Enums\PlateStatus;
use App\Models\Filament;
use App\Models\Item;
use App\Models\Part;
use App\Models\Plate;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function dashboardFilament(string $code, bool $active): Filament
{
    return Filament::query()->create([
        'code' => $code,
        'brand' => 'MULTFILA',
        'material' => 'PLA',
        'color' => 'PRETO',
        'weight' => 1000,
        'price' => 50,
        'is_active' => $active,
        'balance' => 800,
    ]);
}

function dashboardPart(string $code): Part
{
    return Part::query()->create([
        'code' => $code,
        'description' => 'Peca '.$code,
        'size' => 'P',
        'filament_g' => 7,
        'production_time_min' => 25,
        'is_active' => true,
    ]);
}

test('authenticated user can view dashboard with required inertia props', function () {
    $user = User::factory()->create();
    $this->withoutVite();

    $activeFilament = dashboardFilament('PLA-MULTFILA-PRETO-0001', true);
    $part = dashboardPart('DASH-PART-A');

    $plate = Plate::query()->create([
        'filament_code' => $activeFilament->code,
        'status' => PlateStatus::QUEUED,
        'sort_order' => 2,
    ]);
    $plate->parts()->attach($part->id, ['quantity' => 2]);

    $product = Product::query()->create([
        'code' => 'DASH-PROD-1',
        'name' => 'Produto 1',
        'description' => 'Desc',
        'size' => 'P',
        'sale_price' => 10,
        'is_active' => true,
    ]);
    $product->parts()->attach($part->id, ['quantity' => 1]);

    Item::query()->create(['part_id' => $part->id, 'plate_id' => $plate->id]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('Dashboard/Index', false)
        ->has('active_filaments')
        ->has('unfinished_plates')
        ->has('products_with_parts')
        ->has('auth.user')
        ->where('auth.user.id', $user->id)
    );
});

test('dashboard active filaments does not include inactive ones', function () {
    $user = User::factory()->create();
    $this->withoutVite();

    $active = dashboardFilament('PLA-MULTFILA-PRETO-0001', true);
    dashboardFilament('PLA-MULTFILA-ROSA-0002', false);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('Dashboard/Index', false)
        ->has('active_filaments', 1)
        ->where('active_filaments.0.code', $active->code)
    );
});

test('dashboard unfinished plates excludes completed and keeps sort order', function () {
    $user = User::factory()->create();
    $this->withoutVite();

    $filament = dashboardFilament('PLA-MULTFILA-PRETO-0001', true);

    $plateA = Plate::query()->create([
        'filament_code' => $filament->code,
        'status' => PlateStatus::QUEUED,
        'sort_order' => 1,
    ]);

    Plate::query()->create([
        'filament_code' => $filament->code,
        'status' => PlateStatus::COMPLETED,
        'sort_order' => 0,
    ]);

    $plateB = Plate::query()->create([
        'filament_code' => $filament->code,
        'status' => PlateStatus::PRINTING,
        'sort_order' => 3,
    ]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('Dashboard/Index', false)
        ->has('unfinished_plates', 2)
        ->where('unfinished_plates.0.id', $plateA->id)
        ->where('unfinished_plates.1.id', $plateB->id)
    );
});

test('dashboard products with parts includes items count per part', function () {
    $user = User::factory()->create();
    $this->withoutVite();

    $filament = dashboardFilament('PLA-MULTFILA-PRETO-0001', true);
    $plate = Plate::query()->create([
        'filament_code' => $filament->code,
        'status' => PlateStatus::PRINTING,
        'sort_order' => 1,
    ]);

    $partA = dashboardPart('DASH-PART-A');
    $partB = dashboardPart('DASH-PART-B');

    $product = Product::query()->create([
        'code' => 'DASH-PROD-1',
        'name' => 'Produto 1',
        'description' => 'Desc',
        'size' => 'P',
        'sale_price' => 10,
        'is_active' => true,
    ]);
    $product->parts()->attach($partA->id, ['quantity' => 1]);
    $product->parts()->attach($partB->id, ['quantity' => 2]);

    Item::query()->create(['part_id' => $partA->id, 'plate_id' => $plate->id]);
    Item::query()->create(['part_id' => $partA->id, 'plate_id' => $plate->id]);
    Item::query()->create(['part_id' => $partB->id, 'plate_id' => $plate->id]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('Dashboard/Index', false)
        ->where('products_with_parts.0.parts.0.items_count', 2)
        ->where('products_with_parts.0.parts.1.items_count', 1)
    );
});

test('dashboard query count remains controlled to avoid n plus one', function () {
    $user = User::factory()->create();
    $this->withoutVite();

    $filament = dashboardFilament('PLA-MULTFILA-PRETO-0001', true);

    $partA = dashboardPart('DASH-PART-A');
    $partB = dashboardPart('DASH-PART-B');

    $plateA = Plate::query()->create([
        'filament_code' => $filament->code,
        'status' => PlateStatus::QUEUED,
        'sort_order' => 1,
    ]);

    $plateB = Plate::query()->create([
        'filament_code' => $filament->code,
        'status' => PlateStatus::PRINTING,
        'sort_order' => 2,
    ]);

    $plateA->parts()->attach($partA->id, ['quantity' => 1]);
    $plateB->parts()->attach($partB->id, ['quantity' => 2]);

    $productA = Product::query()->create([
        'code' => 'DASH-PROD-1',
        'name' => 'Produto 1',
        'description' => 'Desc',
        'size' => 'P',
        'sale_price' => 10,
        'is_active' => true,
    ]);

    $productB = Product::query()->create([
        'code' => 'DASH-PROD-2',
        'name' => 'Produto 2',
        'description' => 'Desc',
        'size' => 'M',
        'sale_price' => 20,
        'is_active' => true,
    ]);

    $productA->parts()->attach($partA->id, ['quantity' => 1]);
    $productB->parts()->attach($partB->id, ['quantity' => 1]);

    Item::query()->create(['part_id' => $partA->id, 'plate_id' => $plateA->id]);
    Item::query()->create(['part_id' => $partB->id, 'plate_id' => $plateB->id]);

    DB::flushQueryLog();
    DB::enableQueryLog();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk();

    $queryCount = count(DB::getQueryLog());

    expect($queryCount)->toBeLessThanOrEqual(5);
});
