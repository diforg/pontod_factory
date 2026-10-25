<?php

namespace App\Http\Controllers;

use App\Actions\CompletePlate;
use App\Actions\ReopenPlate;
use App\Enums\PlateStatus;
use App\Http\Requests\ReorderPlateRequest;
use App\Http\Requests\StorePlateRequest;
use App\Http\Requests\UpdatePlateStatusRequest;
use App\Models\Filament;
use App\Models\Plate;
use App\Services\PlateSnapshotService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PlateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Plates/Index', [
            'plates' => Plate::query()
                ->with(['filament:code,balance', 'parts:id,code,filament_g'])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
            'filament_balances' => Filament::query()
                ->orderBy('code')
                ->pluck('balance', 'code')
                ->toArray(),
        ]);
    }

    public function store(StorePlateRequest $request, PlateSnapshotService $snapshotService): RedirectResponse
    {
        $data = $request->validated();
        $parts = Arr::pull($data, 'parts', []);

        $plate = DB::transaction(function () use ($data, $parts): Plate {
            $nextSortOrder = ((int) Plate::query()->max('sort_order')) + 1;

            $plate = Plate::query()->create([
                'filament_code' => $data['filament_code'],
                'status' => PlateStatus::CREATED,
                'sort_order' => $nextSortOrder,
            ]);

            $plate->parts()->sync($this->formatPartsForSync($parts));

            return $plate;
        });

        $plate = $plate->fresh(['filament', 'parts']);
        $snapshot = $snapshotService->computeForPlate($plate);
        $estimated = (float) $snapshot['total'];
        $balance = (float) $plate->filament->balance;

        if ($estimated > $balance) {
            $estimatedFormatted = number_format($estimated, 2, '.', '');
            $balanceFormatted = number_format($balance, 2, '.', '');

            return redirect()
                ->route('plates.index')
                ->with('warning', "Consumo estimado {$estimatedFormatted} g excede saldo {$balanceFormatted} g");
        }

        return redirect()->route('plates.index');
    }

    public function update(StorePlateRequest $request, Plate $plate): RedirectResponse
    {
        if ($plate->status !== PlateStatus::CREATED) {
            abort(403, 'Somente placas em estado CREATED podem ser editadas.');
        }

        $data = $request->validated();
        $parts = Arr::pull($data, 'parts', []);

        DB::transaction(function () use ($plate, $data, $parts): void {
            $plate->update([
                'filament_code' => $data['filament_code'],
            ]);

            $plate->parts()->sync($this->formatPartsForSync($parts));
        });

        return redirect()->route('plates.index');
    }

    public function updateStatus(
        UpdatePlateStatusRequest $request,
        Plate $plate,
        CompletePlate $completePlate
    ): RedirectResponse {
        $newStatus = PlateStatus::from($request->validated('status'));

        try {
            match (true) {
                $plate->status === PlateStatus::CREATED
                    && $newStatus === PlateStatus::QUEUED
                    => $plate->update(['status' => PlateStatus::QUEUED]),
                $plate->status === PlateStatus::QUEUED
                    && $newStatus === PlateStatus::PRINTING
                    => $plate->update(['status' => PlateStatus::PRINTING]),
                $plate->status === PlateStatus::PRINTING
                    && $newStatus === PlateStatus::COMPLETED
                    => $completePlate->execute($plate),
                default => throw new DomainException(
                    "Transicao invalida: {$plate->status->value} -> {$newStatus->value}"
                ),
            };
        } catch (DomainException $exception) {
            return back()->withErrors(['status' => $exception->getMessage()]);
        }

        return redirect()->route('plates.index');
    }

    public function reopen(Plate $plate, ReopenPlate $reopenPlate): RedirectResponse
    {
        try {
            $reopenPlate->execute($plate);
        } catch (DomainException $exception) {
            return back()->withErrors(['status' => $exception->getMessage()]);
        }

        return redirect()->route('plates.index');
    }

    public function reorder(ReorderPlateRequest $request): JsonResponse
    {
        $order = $request->validated('order');

        DB::transaction(function () use ($order): void {
            foreach ($order as $index => $plateId) {
                Plate::query()
                    ->where('id', (int) $plateId)
                    ->update(['sort_order' => (int) $index]);
            }
        });

        return response()->json(['ok' => true]);
    }

    /**
     * @param array<int, array{id:int, quantity:int}> $parts
     * @return array<int, array{quantity:int}>
     */
    private function formatPartsForSync(array $parts): array
    {
        $sync = [];

        foreach ($parts as $part) {
            $sync[(int) $part['id']] = [
                'quantity' => (int) $part['quantity'],
            ];
        }

        return $sync;
    }
}
