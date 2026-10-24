<?php

use App\Models\Filament;
use App\Support\FilamentCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('generate emits correct code for the first roll', function () {
    $generator = new FilamentCodeGenerator();

    expect($generator->generate('PLA', 'Multfila', 'Rosa'))
        ->toBe('PLA-MULTFILA-ROSA-0001');
});

test('generate increments after an existing code in the same prefix', function () {
    Filament::query()->create([
        'code' => 'PLA-MULTFILA-ROSA-0001',
        'brand' => 'MULTFILA',
        'material' => 'PLA',
        'color' => 'ROSA',
        'weight' => 1000,
        'price' => 42.50,
        'is_active' => true,
        'balance' => 1000,
    ]);

    $generator = new FilamentCodeGenerator();

    expect($generator->generate('PLA', 'Multfila', 'Rosa'))
        ->toBe('PLA-MULTFILA-ROSA-0002');
});

test('generate does not collide across different prefixes', function () {
    Filament::query()->create([
        'code' => 'PLA-MULTFILA-ROSA-0001',
        'brand' => 'MULTFILA',
        'material' => 'PLA',
        'color' => 'ROSA',
        'weight' => 1000,
        'price' => 42.50,
        'is_active' => true,
        'balance' => 1000,
    ]);

    $generator = new FilamentCodeGenerator();

    expect($generator->generate('PLA', 'Multfila', 'Preto'))
        ->toBe('PLA-MULTFILA-PRETO-0001');
});

test('generate normalizes spaces and hyphens and uppercases values', function () {
    $generator = new FilamentCodeGenerator();

    expect($generator->generate('PLA', 'Mult Filament', 'Azul Céu'))
        ->toBe('PLA-MULT-FILAMENT-AZUL-CEU-0001');
});

test('generate grows to five digits after 9999', function () {
    $rows = [];

    foreach (range(1, 9999) as $number) {
        $rows[] = [
            'code' => 'PLA-MULTFILA-ROSA-'.str_pad((string) $number, 4, '0', STR_PAD_LEFT),
            'brand' => 'MULTFILA',
            'material' => 'PLA',
            'color' => 'ROSA',
            'weight' => 1000,
            'price' => 42.50,
            'is_active' => true,
            'balance' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (count($rows) >= 1000) {
            DB::table('filaments')->insert($rows);
            $rows = [];
        }
    }

    if ($rows !== []) {
        DB::table('filaments')->insert($rows);
    }

    $generator = new FilamentCodeGenerator();

    expect($generator->generate('PLA', 'Multfila', 'Rosa'))
        ->toBe('PLA-MULTFILA-ROSA-10000');
});
