<?php

namespace Database\Seeders;

use App\Actions\CreateFilament;
use App\Enums\PlateStatus;
use App\Models\Filament;
use App\Models\Part;
use App\Models\Plate;
use App\Models\Product;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(CreateFilament $createFilament): void
    {
        // Filaments must be created through the action to keep ledger entries consistent.
        $filaments = [
            ['material' => 'PLA', 'brand' => 'Multfila', 'color' => 'Rosa', 'weight' => 1000, 'price' => 129.90],
            ['material' => 'PLA', 'brand' => 'Multfila', 'color' => 'Preto', 'weight' => 1000, 'price' => 119.90],
        ];

        foreach ($filaments as $data) {
            $code = 'PLA-MULTFILA-'.strtoupper($data['color']).'-0001';

            if (! Filament::query()->where('code', $code)->exists()) {
                $createFilament->execute(
                    $data['material'],
                    $data['brand'],
                    $data['color'],
                    $data['weight'],
                    $data['price']
                );
            }
        }

        $parts = [
            [
                'code' => 'NUV1-DOMO_INFERIOR_A',
                'description' => 'Parte frontal inferior',
                'size' => 'P',
                'filament_g' => 1.2,
                'production_time_min' => 5,
            ],
            [
                'code' => 'NUV1-DOMO_INFERIOR_B',
                'description' => 'Parte que fica encima da tampa',
                'size' => 'P',
                'filament_g' => 0.8,
                'production_time_min' => 4,
            ],
            [
                'code' => 'NUV1-DOMO_INFERIOR_C',
                'description' => 'Parte traseira inferior',
                'size' => 'P',
                'filament_g' => 1.5,
                'production_time_min' => 6,
            ],
        ];

        foreach ($parts as $data) {
            Part::query()->firstOrCreate(['code' => $data['code']], $data + ['is_active' => true]);
        }

        $product1 = Product::query()->firstOrCreate(
            ['code' => 'NUVP1'],
            [
                'name' => 'NUV Pequeno',
                'description' => 'Nave ultimo voo vr. 1',
                'size' => 'P',
                'version' => '1',
                'sale_price' => 30.00,
                'is_active' => true,
            ]
        );

        $product2 = Product::query()->firstOrCreate(
            ['code' => 'NUVP2'],
            [
                'name' => 'NUV Medio',
                'description' => 'Nave ultimo voo vr. 1 tamanho M',
                'size' => 'M',
                'version' => '1',
                'sale_price' => 45.00,
                'is_active' => true,
            ]
        );

        $allParts = Part::query()->whereIn('code', array_column($parts, 'code'))->get();

        $product1->parts()->syncWithoutDetaching(
            $allParts->mapWithKeys(fn (Part $part) => [$part->id => ['quantity' => 1]])->all()
        );

        $product2->parts()->syncWithoutDetaching(
            $allParts->mapWithKeys(fn (Part $part) => [$part->id => ['quantity' => 2]])->all()
        );

        $filamentRosa = Filament::query()->where('code', 'PLA-MULTFILA-ROSA-0001')->first();

        if ($filamentRosa && ! Plate::query()->where('status', '!=', PlateStatus::COMPLETED->value)->exists()) {
            $plate = Plate::query()->create([
                'filament_code' => $filamentRosa->code,
                'status' => PlateStatus::PRINTING->value,
                'sort_order' => 1,
            ]);

            $plate->parts()->attach(
                $allParts->mapWithKeys(fn (Part $part) => [$part->id => ['quantity' => 1]])->all()
            );
        }
    }
}
