<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function locations(): JsonResponse
    {
        return response()->json(
            Location::with('hub')->orderBy('name')->get()->map(fn (Location $l) => [
                'id' => $l->id,
                'name' => $l->name,
                'slug' => $l->slug,
                'hub' => $l->hub?->name,
                'translations' => $l->translations,
            ]),
        );
    }

    public function vehicleTypes(): JsonResponse
    {
        return response()->json(
            VehicleType::orderBy('name')->get(['id', 'name', 'slug', 'translations']),
        );
    }

    /**
     * Catalogo veicoli. Come nel sito attuale la disponibilità per date NON
     * filtra l'elenco: si filtra per località di ritiro, tipo, feature, prezzo.
     */
    public function vehicles(Request $request): JsonResponse
    {
        $query = Vehicle::query()
            ->where('active', true)
            ->with(['type', 'features', 'pickupLocations:id,name', 'dropoffLocations:id,name', 'prices', 'extras']);

        if ($pickup = $request->integer('pickup')) {
            $query->whereHas('pickupLocations', fn ($q) => $q->whereKey($pickup));
        }
        if ($type = $request->input('type')) {
            $query->whereHas('type', fn ($q) => $q->where('slug', $type)->orWhere('id', (int) $type));
        }
        if ($features = $request->input('features')) {
            foreach ((array) $features as $featureId) {
                $query->whereHas('features', fn ($q) => $q->whereKey((int) $featureId));
            }
        }

        $vehicles = $query->get()->map(fn (Vehicle $v) => $this->presentVehicle($v));

        if ($request->input('sort') === 'low-price') {
            $vehicles = $vehicles->sortBy(fn ($v) => $v['base_price'] ?? PHP_FLOAT_MAX)->values();
        } elseif ($request->input('sort') === 'high-price') {
            $vehicles = $vehicles->sortByDesc(fn ($v) => $v['base_price'] ?? 0)->values();
        } else {
            $vehicles = $vehicles->sortBy('sort_order')->values();
        }

        return response()->json($vehicles);
    }

    public function vehicle(Vehicle $vehicle): JsonResponse
    {
        $vehicle->load(['type', 'features', 'pickupLocations:id,name', 'dropoffLocations:id,name', 'prices', 'extras']);

        return response()->json($this->presentVehicle($vehicle));
    }

    private function presentVehicle(Vehicle $v): array
    {
        return [
            'id' => $v->id,
            'name' => $v->name,
            'slug' => $v->slug,
            'subheader' => $v->subheader,
            'type' => $v->type?->only(['id', 'name', 'slug']),
            'stock' => $v->stock,
            'price_on_request' => $v->price_on_request,
            'custom_price_text' => $v->custom_price_text,
            'sale_badge' => $v->sale_badge,
            'base_price' => $v->basePrice(),
            'image' => $v->gallery[0] ?? null,
            'no_same_day' => $v->no_same_day,
            'sort_order' => $v->sort_order,
            'features' => $v->features->map->only(['id', 'name'])->all(),
            'pickup_locations' => $v->pickupLocations->map->only(['id', 'name'])->all(),
            'dropoff_locations' => $v->dropoffLocations->map->only(['id', 'name'])->all(),
            'extras' => $v->extras->map->only(['id', 'name', 'price', 'type', 'max_qty', 'always_included'])->all(),
            'translations' => $v->translations,
        ];
    }
}
