<?php

use App\Enums\PlateStatus;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Part;
use App\Models\Plate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function partPayload(array $overrides = []): array
{
    return array_merge([
        'code' => 'NUV1-DOMO_INFERIOR_A',
        'description' => 'Domo inferior A',
        'size' => 'P',
        'filament_g' => 12.40,
        'production_time_min' => 55,
        'is_active' => true,
    ], $overrides);
}

function createPartForControllerTests(string $code = 'NUV1-DOMO_INFERIOR_A'): Part
{
    return Part::query()->create([
        'code' => $code,
        'description' => 'Domo inferior A',
        'size' => 'P',
        'filament_g' => 12.40,
        'production_time_min' => 55,
        'is_active' => true,
    ]);
}

test('authenticated user can view parts index with inertia props', function () {
    $user = User::factory()->create();

    $this->withoutMiddleware(HandleInertiaRequests::class);
    $response = $this->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->get('/parts');

    $response->assertOk();
    $response->assertJsonPath('component', 'Parts/Index');
    $response->assertJsonStructure([
        'component',
        'props' => ['parts'],
    ]);
});

test('guest is redirected to login when accessing parts index', function () {
    $response = $this->get('/parts');

    $response->assertRedirect('/login');
});

test('store creates part and redirects', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/parts', partPayload());

    $response->assertRedirect(route('parts.index', absolute: false));

    $this->assertDatabaseHas('parts', [
        'code' => 'NUV1-DOMO_INFERIOR_A',
        'description' => 'Domo inferior A',
        'size' => 'P',
    ]);
});

test('store rejects duplicated part code', function () {
    $user = User::factory()->create();

    createPartForControllerTests();

    $response = $this->from('/parts')->actingAs($user)->post('/parts', partPayload());

    $response->assertRedirect('/parts');
    $response->assertSessionHasErrors('code');
});

test('update changes filament consumption', function () {
    $user = User::factory()->create();

    $part = createPartForControllerTests();

    $response = $this->actingAs($user)->patch('/parts/'.$part->id, [
        'filament_g' => 25.50,
    ]);

    $response->assertRedirect(route('parts.index', absolute: false));

    $this->assertDatabaseHas('parts', [
        'id' => $part->id,
        'filament_g' => 25.50,
    ]);
});

test('destroy performs soft delete by setting is_active false when no relations', function () {
    $user = User::factory()->create();

    $part = createPartForControllerTests();

    $response = $this->actingAs($user)->delete('/parts/'.$part->id);

    $response->assertRedirect(route('parts.index', absolute: false));

    $this->assertDatabaseHas('parts', [
        'id' => $part->id,
        'is_active' => 0,
    ]);
});

test('destroy keeps soft delete behavior even when part has plates and items', function () {
    $user = User::factory()->create();

    $part = createPartForControllerTests();

    $filamentCode = app(App\Actions\CreateFilament::class)->execute('PLA', 'Multfila', 'Preto', 1000, 42.50)->code;

    $plate = Plate::query()->create([
        'filament_code' => $filamentCode,
        'status' => PlateStatus::PRINTING,
        'sort_order' => 1,
        'consumed_filament_g' => null,
        'completed_at' => null,
    ]);

    $plate->parts()->attach($part->id, ['quantity' => 1]);

    App\Models\Item::query()->create([
        'part_id' => $part->id,
        'plate_id' => $plate->id,
    ]);

    $response = $this->actingAs($user)->delete('/parts/'.$part->id);

    $response->assertRedirect(route('parts.index', absolute: false));

    $this->assertDatabaseHas('parts', [
        'id' => $part->id,
        'is_active' => 0,
    ]);
});

test('store rejects filament consumption equal to zero', function () {
    $user = User::factory()->create();

    $response = $this->from('/parts')->actingAs($user)->post('/parts', partPayload([
        'filament_g' => 0,
    ]));

    $response->assertRedirect('/parts');
    $response->assertSessionHasErrors('filament_g');
});
