<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Part;
use App\Models\Plate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    public function index(Request $request): Response
    {
        $plateId = $request->integer('plate_id');
        $partId = $request->integer('part_id');

        $itemsQuery = Item::query()
            ->with([
                'plate:id,status,sort_order',
                'part:id,code,description,size',
            ])
            ->orderByDesc('id');

        if ($plateId > 0) {
            $itemsQuery->where('plate_id', $plateId);
        }

        if ($partId > 0) {
            $itemsQuery->where('part_id', $partId);
        }

        return Inertia::render('Items/Index', [
            'items' => $itemsQuery->get(),
            'plates' => Plate::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['id', 'status', 'sort_order']),
            'parts' => Part::query()
                ->orderBy('code')
                ->get(['id', 'code', 'description']),
            'filters' => [
                'plate_id' => $plateId > 0 ? $plateId : null,
                'part_id' => $partId > 0 ? $partId : null,
            ],
        ]);
    }
}
