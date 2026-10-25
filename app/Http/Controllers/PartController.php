<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePartRequest;
use App\Http\Requests\UpdatePartRequest;
use App\Models\Part;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PartController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Parts/Index', [
            'parts' => Part::query()
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function store(StorePartRequest $request): RedirectResponse
    {
        Part::query()->create($request->validated());

        return redirect()->route('parts.index');
    }

    public function update(UpdatePartRequest $request, Part $part): RedirectResponse
    {
        $part->update($request->validated());

        return redirect()->route('parts.index');
    }

    public function destroy(Part $part): RedirectResponse
    {
        $part->update(['is_active' => false]);

        return redirect()->route('parts.index');
    }
}
