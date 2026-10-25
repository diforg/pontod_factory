<?php

use App\Models\Part;
use App\Models\Product;
use App\Models\User;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function productPayload(array $overrides = []): array
{
    return array_merge([
        'code' => 'NUVP1',
        'name' => 'Produto P',
        'description' => 'Produto base',
        'size' => 'P',
        'version' => 'v1',
        'sale_price' => 89.90,
        'is_active' => true,
    ], $overrides);
}

function createProductForControllerTests(string $code = 'NUVP1'): Product
{
    return Product::query()->create([
        'code' => $code,
        'name' => 'Produto P',
        'description' => 'Produto base',
        'size' => 'P',
        'version' => 'v1',
        'sale_price' => 89.90,
        'is_active' => true,
    ]);
}

function createPartForProductTests(string $code): Part
{
    return Part::query()->create([
        'code' => $code,
        'description' => 'Peca '.$code,
        'size' => 'P',
        'filament_g' => 10,
        'production_time_min' => 20,
        'is_active' => true,
    ]);
}

test('authenticated user can view products index with inertia props', function () {
    $user = User::factory()->create();

    $this->withoutMiddleware(HandleInertiaRequests::class);
    $response = $this->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->get('/products');

    $response->assertOk();
    $response->assertJsonPath('component', 'Products/Index');
    $response->assertJsonStructure([
        'component',
        'props' => ['products'],
    ]);
});

test('guest is redirected to login when accessing products index', function () {
    $response = $this->get('/products');

    $response->assertRedirect('/login');
});

test('store creates product with parts association', function () {
    $user = User::factory()->create();

    $partA = createPartForProductTests('PART-A');
    $partB = createPartForProductTests('PART-B');

    $response = $this->actingAs($user)->post('/products', productPayload([
        'parts' => [
            ['id' => $partA->id, 'quantity' => 2],
            ['id' => $partB->id, 'quantity' => 1],
        ],
    ]));

    $response->assertRedirect(route('products.index', absolute: false));

    $product = Product::query()->where('code', 'NUVP1')->firstOrFail();

    $this->assertDatabaseHas('part_product', [
        'product_id' => $product->id,
        'part_id' => $partA->id,
        'quantity' => 2,
    ]);

    $this->assertDatabaseHas('part_product', [
        'product_id' => $product->id,
        'part_id' => $partB->id,
        'quantity' => 1,
    ]);
});

test('store creates product without parts', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/products', productPayload());

    $response->assertRedirect(route('products.index', absolute: false));

    $product = Product::query()->where('code', 'NUVP1')->firstOrFail();

    expect($product->parts()->count())->toBe(0);
});

test('store rejects parts quantity equal to zero', function () {
    $user = User::factory()->create();

    $part = createPartForProductTests('PART-A');

    $response = $this->from('/products')->actingAs($user)->post('/products', productPayload([
        'parts' => [
            ['id' => $part->id, 'quantity' => 0],
        ],
    ]));

    $response->assertRedirect('/products');
    $response->assertSessionHasErrors('parts.0.quantity');
});

test('update changes product name and sale price', function () {
    $user = User::factory()->create();

    $product = createProductForControllerTests();

    $response = $this->actingAs($user)->patch('/products/'.$product->id, [
        'name' => 'Produto P Atualizado',
        'sale_price' => 99.50,
    ]);

    $response->assertRedirect(route('products.index', absolute: false));

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'name' => 'Produto P Atualizado',
        'sale_price' => 99.50,
    ]);
});

test('update syncs pivot parts replacing previous associations', function () {
    $user = User::factory()->create();

    $product = createProductForControllerTests();
    $partA = createPartForProductTests('PART-A');
    $partB = createPartForProductTests('PART-B');
    $partC = createPartForProductTests('PART-C');

    $product->parts()->attach($partA->id, ['quantity' => 3]);
    $product->parts()->attach($partB->id, ['quantity' => 1]);

    $response = $this->actingAs($user)->patch('/products/'.$product->id, [
        'parts' => [
            ['id' => $partB->id, 'quantity' => 4],
            ['id' => $partC->id, 'quantity' => 2],
        ],
    ]);

    $response->assertRedirect(route('products.index', absolute: false));

    $this->assertDatabaseMissing('part_product', [
        'product_id' => $product->id,
        'part_id' => $partA->id,
    ]);

    $this->assertDatabaseHas('part_product', [
        'product_id' => $product->id,
        'part_id' => $partB->id,
        'quantity' => 4,
    ]);

    $this->assertDatabaseHas('part_product', [
        'product_id' => $product->id,
        'part_id' => $partC->id,
        'quantity' => 2,
    ]);
});

test('destroy performs soft delete by setting is_active false', function () {
    $user = User::factory()->create();

    $product = createProductForControllerTests();

    $response = $this->actingAs($user)->delete('/products/'.$product->id);

    $response->assertRedirect(route('products.index', absolute: false));

    $this->assertDatabaseHas('products', [
        'id' => $product->id,
        'is_active' => 0,
    ]);
});
