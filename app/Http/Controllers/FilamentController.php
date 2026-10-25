<?php

namespace App\Http\Controllers;

use App\Actions\CreateFilament;
use App\Http\Requests\StoreFilamentRequest;
use App\Http\Requests\UpdateFilamentRequest;
use App\Models\Filament;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class FilamentController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Filaments/Index', [
            'filaments' => Filament::query()
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function store(StoreFilamentRequest $request, CreateFilament $createFilament): RedirectResponse
    {
        $data = $request->validated();

        $createFilament->execute(
            $data['material'],
            $data['brand'],
            $data['color'],
            (float) $data['weight'],
            (float) $data['price']
        );

        return redirect()->route('filaments.index');
    }

    public function update(UpdateFilamentRequest $request, Filament $filament): RedirectResponse
    {
        $filament->update($request->validated());

        return redirect()->route('filaments.index');
    }

    public function activate(Filament $filament): RedirectResponse
    {
        if (! $filament->is_active) {
            $filament->update(['is_active' => true]);
        }

        return redirect()->route('filaments.index');
    }

    public function deactivate(Filament $filament): RedirectResponse
    {
        if ($filament->is_active) {
            $filament->update(['is_active' => false]);
        }

        return redirect()->route('filaments.index');
    }
}
