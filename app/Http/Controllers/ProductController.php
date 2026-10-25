<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Products/Index', [
            'products' => Product::query()
                ->with('parts:id,code')
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $parts = Arr::pull($data, 'parts', []);

        DB::transaction(function () use ($data, $parts): void {
            $product = Product::query()->create($data);

            if ($parts !== []) {
                $product->parts()->sync($this->formatPartsForSync($parts));
            }
        });

        return redirect()->route('products.index');
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();
        $partsProvided = array_key_exists('parts', $data);
        $parts = Arr::pull($data, 'parts', []);

        DB::transaction(function () use ($product, $data, $partsProvided, $parts): void {
            $product->update($data);

            if ($partsProvided) {
                $product->parts()->sync($this->formatPartsForSync($parts));
            }
        });

        return redirect()->route('products.index');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $product->update(['is_active' => false]);

        return redirect()->route('products.index');
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
