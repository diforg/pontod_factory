<?php

namespace App\Http\Controllers;

use App\Enums\PlateStatus;
use App\Models\Filament;
use App\Models\Plate;
use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Dashboard/Index', [
            'active_filaments' => Filament::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['code', 'brand', 'material', 'color', 'balance']),
            'unfinished_plates' => Plate::query()
                ->where('status', '!=', PlateStatus::COMPLETED->value)
                ->with('parts:id,code,description,filament_g')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'products_with_parts' => Product::query()
                ->where('is_active', true)
                ->with(['parts' => function ($query): void {
                    $query->withCount('items');
                }])
                ->orderBy('code')
                ->get(),
        ]);
    }
}
