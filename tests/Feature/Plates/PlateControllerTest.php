<?php

use App\Actions\CompletePlate;
use App\Enums\MovementType;
use App\Enums\PlateStatus;
use App\Models\Filament;
use App\Models\FilamentMovement;
use App\Models\Item;
use App\Models\Part;
use App\Models\Plate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

function plateControllerCreateFilament(string $code, bool $active = true, float $balance = 1000): Filament
{
    return Filament::query()->create([
        'code' => $code,
        'brand' => 'MULTFILA',
        'material' => 'PLA',
        'color' => 'PRETO',
        'weight' => 1000,
        'price' => 50,
        'is_active' => $active,
        'balance' => $balance,
    ]);
}

function plateControllerCreatePart(string $code, float $filamentG = 10): Part
{
    return Part::query()->create([
        'code' => $code,
        'description' => 'Peca '.$code,
        'size' => 'P',
        'filament_g' => $filamentG,
        'production_time_min' => 40,
        'is_active' => true,
    ]);
}

function plateControllerPayload(string $filamentCode, array $parts): array
{
    return [
        'filament_code' => $filamentCode,
        'parts' => $parts,
    ];
}

test('authenticated user can view plates index with inertia props', function () {
    $user = User::factory()->create();
    $this->withoutVite();

    $filament = plateControllerCreateFilament('PLA-MULTFILA-PRETO-0001', active: true, balance: 850);
    $part = plateControllerCreatePart('PLATE-PART-A', filamentG: 15.5);

    $plate = Plate::query()->create([
        'filament_code' => $filament->code,
        'status' => PlateStatus::CREATED,
        'sort_order' => 0,
    ]);

    $plate->parts()->attach($part->id, ['quantity' => 2]);

    $response = $this->actingAs($user)->get('/plates');

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->component('Plates/Index', false)
        ->has('plates', 1)
        ->has('filament_balances')
        ->where('filament_balances.'.$filament->code, '850.00')
        ->has('auth.user')
        ->where('auth.user.id', $user->id)
    );
});

test('guest is redirected to login when accessing plates index', function () {
    $response = $this->get('/plates');

    $response->assertRedirect('/login');
});

test('store creates plate with created status and sort order at queue end', function () {
    $user = User::factory()->create();
    $filament = plateControllerCreateFilament('PLA-MULTFILA-PRETO-0001');
    $part = plateControllerCreatePart('PLATE-PART-A');

    Plate::query()->create([
        'filament_code' => $filament->code,
        'status' => PlateStatus::CREATED,
        'sort_order' => 4,
    ]);

    $response = $this->actingAs($user)->post('/plates', plateControllerPayload($filament->code, [
        ['id' => $part->id, 'quantity' => 1],
    ]));

    $response->assertRedirect(route('plates.index', absolute: false));

    $createdPlate = Plate::query()->latest('id')->firstOrFail();

    expect($createdPlate->status)->toBe(PlateStatus::CREATED)
        ->and($createdPlate->sort_order)->toBe(5);
});

test('store attaches parts correctly in pivot', function () {
    $user = User::factory()->create();
    $filament = plateControllerCreateFilament('PLA-MULTFILA-PRETO-0001');
    $partA = plateControllerCreatePart('PLATE-PART-A', filamentG: 2.5);
    $partB = plateControllerCreatePart('PLATE-PART-B', filamentG: 3.0);

    $this->actingAs($user)->post('/plates', plateControllerPayload($filament->code, [
        ['id' => $partA->id, 'quantity' => 2],
        ['id' => $partB->id, 'quantity' => 1],
    ]));

    $plate = Plate::query()->latest('id')->firstOrFail();

    $this->assertDatabaseHas('part_plate', [
        'plate_id' => $plate->id,
        'part_id' => $partA->id,
        'quantity' => 2,
    ]);

    $this->assertDatabaseHas('part_plate', [
        'plate_id' => $plate->id,
        'part_id' => $partB->id,
        'quantity' => 1,
    ]);
});

test('store rejects inactive filament', function () {
    $user = User::factory()->create();
    $filament = plateControllerCreateFilament('PLA-MULTFILA-PRETO-0001', active: false);
    $part = plateControllerCreatePart('PLATE-PART-A');

    $response = $this->from('/plates')->actingAs($user)->post('/plates', plateControllerPayload($filament->code, [
        ['id' => $part->id, 'quantity' => 1],
    ]));

    $response->assertRedirect('/plates');
    $response->assertSessionHasErrors('filament_code');
});

test('store persists plate but flashes warning when estimated consumption exceeds balance', function () {
    $user = User::factory()->create();
    $filament = plateControllerCreateFilament('PLA-MULTFILA-PRETO-0001', active: true, balance: 5);
    $part = plateControllerCreatePart('PLATE-PART-A', filamentG: 6.2);

    $response = $this->actingAs($user)->post('/plates', plateControllerPayload($filament->code, [
        ['id' => $part->id, 'quantity' => 1],
    ]));

    $response->assertRedirect(route('plates.index', absolute: false));
    $response->assertSessionHas('warning');

    $this->assertDatabaseHas('plates', [
        'filament_code' => $filament->code,
        'status' => PlateStatus::CREATED->value,
    ]);
});

test('update allows editing plate only when status is created', function () {
    $user = User::factory()->create();
    $filamentA = plateControllerCreateFilament('PLA-MULTFILA-PRETO-0001');
    $filamentB = plateControllerCreateFilament('PLA-MULTFILA-ROSA-0002');
    $partA = plateControllerCreatePart('PLATE-PART-A');
    $partB = plateControllerCreatePart('PLATE-PART-B', filamentG: 5.5);

    $plate = Plate::query()->create([
        'filament_code' => $filamentA->code,
        'status' => PlateStatus::CREATED,
        'sort_order' => 1,
    ]);

    $plate->parts()->attach($partA->id, ['quantity' => 1]);

    $response = $this->actingAs($user)->patch('/plates/'.$plate->id, plateControllerPayload($filamentB->code, [
        ['id' => $partB->id, 'quantity' => 3],
    ]));

    $response->assertRedirect(route('plates.index', absolute: false));

    $plate->refresh();

    expect($plate->filament_code)->toBe($filamentB->code);

    $this->assertDatabaseMissing('part_plate', [
        'plate_id' => $plate->id,
        'part_id' => $partA->id,
    ]);

    $this->assertDatabaseHas('part_plate', [
        'plate_id' => $plate->id,
        'part_id' => $partB->id,
        'quantity' => 3,
    ]);
});

test('update denies editing when plate is not in created status', function () {
    $user = User::factory()->create();
    $filament = plateControllerCreateFilament('PLA-MULTFILA-PRETO-0001');
    $part = plateControllerCreatePart('PLATE-PART-A');

    $plate = Plate::query()->create([
        'filament_code' => $filament->code,
        'status' => PlateStatus::PRINTING,
        'sort_order' => 1,
    ]);

    $response = $this->actingAs($user)->patch('/plates/'.$plate->id, plateControllerPayload($filament->code, [
        ['id' => $part->id, 'quantity' => 2],
    ]));

    $response->assertForbidden();
});

test('update status allows created to queued transition', function () {
    $user = User::factory()->create();
    $filament = plateControllerCreateFilament('PLA-MULTFILA-PRETO-0001');

    $plate = Plate::query()->create([
        'filament_code' => $filament->code,
        'status' => PlateStatus::CREATED,
        'sort_order' => 1,
    ]);

    $response = $this->actingAs($user)->post('/plates/'.$plate->id.'/status', [
        'status' => PlateStatus::QUEUED->value,
    ]);

    $response->assertRedirect(route('plates.index', absolute: false));

    $plate->refresh();
    expect($plate->status)->toBe(PlateStatus::QUEUED);
});

test('update status allows queued to printing transition', function () {
    $user = User::factory()->create();
    $filament = plateControllerCreateFilament('PLA-MULTFILA-PRETO-0001');

    $plate = Plate::query()->create([
        'filament_code' => $filament->code,
        'status' => PlateStatus::QUEUED,
        'sort_order' => 1,
    ]);

    $response = $this->actingAs($user)->post('/plates/'.$plate->id.'/status', [
        'status' => PlateStatus::PRINTING->value,
    ]);

    $response->assertRedirect(route('plates.index', absolute: false));

    $plate->refresh();
    expect($plate->status)->toBe(PlateStatus::PRINTING);
});

test('update status printing to completed runs completion flow and freezes snapshot', function () {
    $user = User::factory()->create();
    $filament = plateControllerCreateFilament('PLA-MULTFILA-PRETO-0001', active: true, balance: 1000);
    $part = plateControllerCreatePart('PLATE-PART-A', filamentG: 4.2);

    $plate = Plate::query()->create([
        'filament_code' => $filament->code,
        'status' => PlateStatus::PRINTING,
        'sort_order' => 1,
    ]);

    $plate->parts()->attach($part->id, ['quantity' => 2]);

    $response = $this->actingAs($user)->post('/plates/'.$plate->id.'/status', [
        'status' => PlateStatus::COMPLETED->value,
    ]);

    $response->assertRedirect(route('plates.index', absolute: false));

    $plate->refresh();

    expect($plate->status)->toBe(PlateStatus::COMPLETED)
        ->and((string) $plate->consumed_filament_g)->toBe('8.40');

    $pivot = DB::table('part_plate')
        ->where('plate_id', $plate->id)
        ->where('part_id', $part->id)
        ->first();

    expect((string) $pivot->consumed_filament_g)->toBe('8.40')
        ->and(Item::query()->where('plate_id', $plate->id)->count())->toBe(2)
        ->and(FilamentMovement::query()->where('type', MovementType::CONSUMPTION)->count())->toBe(1);
});

test('update status rejects invalid transition and keeps status unchanged', function () {
    $user = User::factory()->create();
    $filament = plateControllerCreateFilament('PLA-MULTFILA-PRETO-0001');

    $plate = Plate::query()->create([
        'filament_code' => $filament->code,
        'status' => PlateStatus::CREATED,
        'sort_order' => 1,
    ]);

    $response = $this->from('/plates')->actingAs($user)->post('/plates/'.$plate->id.'/status', [
        'status' => PlateStatus::COMPLETED->value,
    ]);

    $response->assertRedirect('/plates');
    $response->assertSessionHasErrors('status');

    $plate->refresh();
    expect($plate->status)->toBe(PlateStatus::CREATED);
});

test('update status to completed fails on insufficient balance and rolls back status', function () {
    $user = User::factory()->create();
    $filament = plateControllerCreateFilament('PLA-MULTFILA-PRETO-0001', active: true, balance: 2.5);
    $part = plateControllerCreatePart('PLATE-PART-A', filamentG: 5.0);

    $plate = Plate::query()->create([
        'filament_code' => $filament->code,
        'status' => PlateStatus::PRINTING,
        'sort_order' => 1,
    ]);

    $plate->parts()->attach($part->id, ['quantity' => 1]);

    $response = $this->from('/plates')->actingAs($user)->post('/plates/'.$plate->id.'/status', [
        'status' => PlateStatus::COMPLETED->value,
    ]);

    $response->assertRedirect('/plates');
    $response->assertSessionHasErrors('status');

    $plate->refresh();

    expect($plate->status)->toBe(PlateStatus::PRINTING)
        ->and($plate->consumed_filament_g)->toBeNull()
        ->and(Item::query()->where('plate_id', $plate->id)->count())->toBe(0)
        ->and(FilamentMovement::query()->where('type', MovementType::CONSUMPTION)->count())->toBe(0);
});

test('reorder persists new sort order and returns json ok', function () {
    $user = User::factory()->create();
    $filament = plateControllerCreateFilament('PLA-MULTFILA-PRETO-0001');

    $plateA = Plate::query()->create([
        'filament_code' => $filament->code,
        'status' => PlateStatus::CREATED,
        'sort_order' => 0,
    ]);

    $plateB = Plate::query()->create([
        'filament_code' => $filament->code,
        'status' => PlateStatus::QUEUED,
        'sort_order' => 1,
    ]);

    $plateC = Plate::query()->create([
        'filament_code' => $filament->code,
        'status' => PlateStatus::PRINTING,
        'sort_order' => 2,
    ]);

    $response = $this->actingAs($user)->post('/plates/reorder', [
        'order' => [$plateC->id, $plateA->id, $plateB->id],
    ]);

    $response->assertOk();
    $response->assertJson(['ok' => true]);

    $this->assertDatabaseHas('plates', ['id' => $plateC->id, 'sort_order' => 0]);
    $this->assertDatabaseHas('plates', ['id' => $plateA->id, 'sort_order' => 1]);
    $this->assertDatabaseHas('plates', ['id' => $plateB->id, 'sort_order' => 2]);
});

test('reopen endpoint moves completed plate back to printing and removes items', function () {
    $user = User::factory()->create();
    $filament = plateControllerCreateFilament('PLA-MULTFILA-PRETO-0001', active: true, balance: 1000);
    $part = plateControllerCreatePart('PLATE-PART-A', filamentG: 3.2);

    $plate = Plate::query()->create([
        'filament_code' => $filament->code,
        'status' => PlateStatus::PRINTING,
        'sort_order' => 1,
    ]);

    $plate->parts()->attach($part->id, ['quantity' => 2]);

    app(CompletePlate::class)->execute($plate->fresh());

    expect(Item::query()->where('plate_id', $plate->id)->count())->toBe(2);

    $response = $this->actingAs($user)->post('/plates/'.$plate->id.'/reopen');

    $response->assertRedirect(route('plates.index', absolute: false));

    $plate->refresh();

    expect($plate->status)->toBe(PlateStatus::PRINTING)
        ->and($plate->consumed_filament_g)->toBeNull()
        ->and(Item::query()->where('plate_id', $plate->id)->count())->toBe(0)
        ->and(FilamentMovement::query()->where('type', MovementType::REVERSAL)->count())->toBe(1);
});
