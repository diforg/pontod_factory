<?php

use App\Actions\ReconcileFilaments;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('demo seeder creates 2 filaments and 2 entry movements with expected initial balance', function () {
    $this->seed(DemoDataSeeder::class);

    $this->assertDatabaseCount('filaments', 2);
    $this->assertDatabaseCount('filament_movements', 2);

    $this->assertDatabaseHas('filaments', [
        'code' => 'PLA-MULTFILA-ROSA-0001',
        'balance' => 1000,
    ]);
});

test('demo seeder is idempotent when executed twice', function () {
    $this->seed(DemoDataSeeder::class);
    $this->seed(DemoDataSeeder::class);

    $this->assertDatabaseCount('filaments', 2);
    $this->assertDatabaseCount('filament_movements', 2);
});

test('reconciliation is consistent after demo seeder', function () {
    $this->seed(DemoDataSeeder::class);

    $result = app(ReconcileFilaments::class)->execute(true);

    expect($result['divergences'])->toBe([])
        ->and($result['checked'])->toBe(2);
});