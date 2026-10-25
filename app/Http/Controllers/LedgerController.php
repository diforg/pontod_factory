<?php

namespace App\Http\Controllers;

use App\Enums\MovementType;
use App\Models\Filament;
use App\Models\FilamentMovement;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LedgerController extends Controller
{
    public function index(Request $request): Response
    {
        $filamentCode = $request->string('filament_code')->toString();
        $type = $request->string('type')->toString();
        $fromDate = $request->string('from_date')->toString();
        $toDate = $request->string('to_date')->toString();

        $movementsQuery = FilamentMovement::query()
            ->with('filament:code,material,brand,color')
            ->orderByDesc('occurred_at')
            ->orderByDesc('id');

        if ($filamentCode !== '') {
            $movementsQuery->where('filament_code', $filamentCode);
        }

        if ($type !== '' && in_array($type, MovementType::values(), true)) {
            $movementsQuery->where('type', $type);
        }

        if ($fromDate !== '') {
            $movementsQuery->whereDate('occurred_at', '>=', $fromDate);
        }

        if ($toDate !== '') {
            $movementsQuery->whereDate('occurred_at', '<=', $toDate);
        }

        return Inertia::render('Ledger/Index', [
            'movements' => $movementsQuery->get(),
            'filaments' => Filament::query()
                ->orderBy('code')
                ->get(['code', 'material', 'brand', 'color', 'balance']),
            'filters' => [
                'filament_code' => $filamentCode !== '' ? $filamentCode : null,
                'type' => $type !== '' ? $type : null,
                'from_date' => $fromDate !== '' ? $fromDate : null,
                'to_date' => $toDate !== '' ? $toDate : null,
            ],
        ]);
    }

    public function show(string $filament_code): Response
    {
        $filament = Filament::query()->findOrFail($filament_code);

        return Inertia::render('Ledger/Show', [
            'filament' => $filament,
            'movements' => FilamentMovement::query()
                ->where('filament_code', $filament_code)
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->get(),
        ]);
    }
}
