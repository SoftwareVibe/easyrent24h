<?php

namespace Database\Seeders;

use App\Models\Extra;
use App\Models\ExtraPrice;
use App\Models\Feature;
use App\Models\Hub;
use App\Models\Location;
use App\Models\PriceCondition;
use App\Models\Vehicle;
use App\Models\VehiclePrice;
use App\Models\VehicleType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * ETL dal sito WordPress attuale: importa data/catalog-export.json
 * (estratto dal dump dbjkglnikefpaj.sql) in modo idempotente.
 *
 * Le coppie di traduzione WPML (IT/EN) vengono raggruppate per trid in un
 * unico record con colonna translations; tutti gli id legacy di entrambe le
 * lingue vengono mappati sul nuovo id per risolvere i riferimenti incrociati
 * (location CSV nelle condizioni, location_drop dei veicoli, renroll_price).
 */
class CatalogImportSeeder extends Seeder
{
    private const EXPORT_PATH = __DIR__.'/../../../data/catalog-export.json';

    /** Ex $globalAgerolaList / $globalPositanoList (per nome località). */
    private const HUB_LOCATIONS = [
        'Agerola' => ['Agerola', 'Amalfi', 'Atrani', 'C/mare di stabia', 'Conca dei Marini', 'Furore', 'Gragnano', 'Pimonte', 'Pompei', 'Maiori', 'Minori'],
        'Positano' => ['Positano', 'Praiano', 'Sorrento'],
    ];

    /** Località con ritiro/consegna solo a inizio/fine giornata (ex fascia*PerLuoghiSpecifici). */
    private const ENDPOINTS_ONLY = ['Amalfi', 'Atrani', 'C/mare di stabia', 'Conca dei Marini', 'Furore', 'Gragnano', 'Pimonte', 'Pompei'];

    /** Località con finestra dalle 09:00 (ex fascia*PerLuoghiSpecifici). */
    private const FROM_NINE = ['Positano', 'Praiano', 'Sorrento'];

    /** Ex $globalIdVeicoliNoDisponibiliGiornoStesso (post id legacy IT+EN). */
    private const NO_SAME_DAY_LEGACY_IDS = [3574, 3570, 3563, 11312, 11318, 4400, 4401, 4402, 11313, 11314];

    public function run(): void
    {
        $path = realpath(self::EXPORT_PATH) ?: self::EXPORT_PATH;
        if (! is_file($path)) {
            $this->command?->warn("Export non trovato ($path): salto l'import del catalogo.");

            return;
        }

        $data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        $termsByTaxonomy = collect($data['terms'])->groupBy('taxonomy');
        $termmeta = $data['termmeta'];
        $tridGroups = $this->tridGroups($data['translations']);

        $locationMap = $this->importLocations($termsByTaxonomy->get('location', collect()), $termmeta, $tridGroups['tax_location'] ?? []);
        $typeMap = $this->importTerms($termsByTaxonomy->get('vehicle_type', collect()), $tridGroups['tax_vehicle_type'] ?? [], VehicleType::class);
        $featureMap = $this->importTerms($termsByTaxonomy->get('feature', collect()), $tridGroups['tax_feature'] ?? [], Feature::class);
        $conditionMap = $this->importConditions($termsByTaxonomy->get('condition', collect()), $termmeta, $locationMap, $typeMap);
        $extraMap = $this->importExtras($termsByTaxonomy->get('extra_option', collect()), $termmeta, $tridGroups['tax_extra_option'] ?? [], $conditionMap);

        $vehicleMap = $this->importVehicles($data['vehicles'], $tridGroups['post_catalog'] ?? [], $locationMap, $typeMap, $featureMap, $extraMap);

        $this->importPrices($data['renroll_price'], $vehicleMap, $conditionMap);

        $this->command?->info(sprintf(
            'Import: %d località, %d tipi, %d feature, %d condizioni, %d extra, %d veicoli, %d righe listino.',
            Location::count(), VehicleType::count(), Feature::count(),
            PriceCondition::count(), Extra::count(), Vehicle::count(), VehiclePrice::count(),
        ));
    }

    /** @return array<string, array<int, array<int, array{element_id:int, lang:string}>>> element_type => trid => members */
    private function tridGroups(array $translations): array
    {
        $groups = [];
        foreach ($translations as $row) {
            $groups[$row['element_type']][$row['trid']][] = [
                'element_id' => (int) $row['element_id'],
                'lang' => $row['lang'],
            ];
        }

        return $groups;
    }

    /**
     * Raggruppa i termini per trid e crea un record per gruppo.
     * Ritorna la mappa legacy term_id (tutte le lingue) => nuovo id.
     */
    private function importTerms($terms, array $tridGroups, string $model): array
    {
        $byTtId = $terms->keyBy('term_taxonomy_id');
        $map = [];
        $grouped = [];

        foreach ($tridGroups as $members) {
            $present = collect($members)->filter(fn ($m) => $byTtId->has($m['element_id']));
            if ($present->isEmpty()) {
                continue;
            }
            $grouped[] = $present->map(fn ($m) => ['term' => $byTtId[$m['element_id']], 'lang' => $m['lang']])->values();
            foreach ($present as $m) {
                $byTtId->forget($m['element_id']);
            }
        }
        // termini fuori WPML: gruppo singolo
        foreach ($byTtId as $term) {
            $grouped[] = collect([['term' => $term, 'lang' => 'it']]);
        }

        foreach ($grouped as $group) {
            // EN è la lingua di default del sito; fallback sulla sorgente IT.
            $en = $group->firstWhere('lang', 'en') ?? $group->first();
            $primary = $en['term'];

            $attrs = [
                'name' => $primary['name'],
                'translations' => $group->mapWithKeys(fn ($m) => [$m['lang'] => ['name' => $m['term']['name']]])->all(),
            ];
            if ($model !== Feature::class) {
                $attrs['slug'] = Str::slug($primary['name']);
            }

            $record = $model::updateOrCreate(['legacy_term_id' => $primary['term_id']], $attrs);

            foreach ($group as $m) {
                $map[(int) $m['term']['term_id']] = $record->id;
                $map[(int) $m['term']['term_taxonomy_id']] = $record->id;
            }
        }

        return $map;
    }

    private function importLocations($terms, array $termmeta, array $tridGroups): array
    {
        $byTtId = $terms->keyBy('term_taxonomy_id');
        $map = [];

        $groups = [];
        foreach ($tridGroups as $members) {
            $present = collect($members)->filter(fn ($m) => $byTtId->has($m['element_id']));
            if ($present->isEmpty()) {
                continue;
            }
            $groups[] = $present->map(fn ($m) => ['term' => $byTtId[$m['element_id']], 'lang' => $m['lang']])->values();
            foreach ($present as $m) {
                $byTtId->forget($m['element_id']);
            }
        }
        foreach ($byTtId as $term) {
            $groups[] = collect([['term' => $term, 'lang' => 'it']]);
        }

        $hubs = [];
        foreach (array_keys(self::HUB_LOCATIONS) as $hubName) {
            $hubs[$hubName] = Hub::firstOrCreate(['name' => $hubName]);
        }

        foreach ($groups as $group) {
            $name = $group->first()['term']['name'];
            $primary = $group->first()['term'];
            $meta = $termmeta[(string) $primary['term_id']] ?? [];

            $hubId = null;
            foreach (self::HUB_LOCATIONS as $hubName => $names) {
                if (in_array($name, $names, true)) {
                    $hubId = $hubs[$hubName]->id;
                    break;
                }
            }

            $location = Location::updateOrCreate(
                ['legacy_term_id' => $primary['term_id']],
                [
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'hub_id' => $hubId,
                    'activate_shipping' => (bool) $this->metaVal($meta, 'activate_shipping', 0),
                    'endpoints_only' => in_array($name, self::ENDPOINTS_ONLY, true),
                    'window_start' => in_array($name, self::FROM_NINE, true) ? '09:00' : null,
                    'window_end' => null,
                    'translations' => $group->mapWithKeys(fn ($m) => [$m['lang'] => ['name' => $m['term']['name']]])->all(),
                ],
            );

            foreach ($group as $m) {
                $map[(int) $m['term']['term_id']] = $location->id;
                $map[(int) $m['term']['term_taxonomy_id']] = $location->id;
            }
        }

        return $map;
    }

    private function importConditions($terms, array $termmeta, array $locationMap, array $typeMap): array
    {
        $map = [];

        foreach ($terms as $term) {
            $meta = $termmeta[(string) $term['term_id']] ?? [];

            $condition = PriceCondition::updateOrCreate(
                ['legacy_term_id' => $term['term_id']],
                [
                    'name' => $term['name'],
                    'days_from' => (int) $this->metaVal($meta, 'days_from', 0),
                    'days_to' => $this->intOrNull($this->metaVal($meta, 'days_to')),
                    'days_first' => $this->intOrNull($this->metaVal($meta, 'days_first')),
                    'fixed_price' => (bool) $this->metaVal($meta, 'fixed_price', 0),
                    'weekdays' => $this->metaList($meta, 'weekdays'),
                    'month_days' => $this->metaList($meta, 'days'),
                    'months' => $this->metaList($meta, 'months'),
                    'years' => $this->metaList($meta, 'years'),
                    'date_from' => $this->metaVal($meta, 'from_date') ?: null,
                    'date_to' => $this->metaVal($meta, 'to_date') ?: null,
                    'pickup_location_ids' => $this->mapCsv($this->metaVal($meta, 'location'), $locationMap),
                    'dropoff_location_ids' => $this->mapCsv($this->metaVal($meta, 'location_dropoff'), $locationMap),
                    'vehicle_type_ids' => $this->mapCsv($this->metaVal($meta, 'type'), $typeMap),
                    'active' => true,
                ],
            );

            $map[(int) $term['term_id']] = $condition->id;
            $map[(int) $term['term_taxonomy_id']] = $condition->id;
        }

        return $map;
    }

    private function importExtras($terms, array $termmeta, array $tridGroups, array $conditionMap): array
    {
        $byTtId = $terms->keyBy('term_taxonomy_id');
        $map = [];

        $groups = [];
        foreach ($tridGroups as $members) {
            $present = collect($members)->filter(fn ($m) => $byTtId->has($m['element_id']));
            if ($present->isEmpty()) {
                continue;
            }
            $groups[] = $present->map(fn ($m) => ['term' => $byTtId[$m['element_id']], 'lang' => $m['lang']])->values();
            foreach ($present as $m) {
                $byTtId->forget($m['element_id']);
            }
        }
        foreach ($byTtId as $term) {
            $groups[] = collect([['term' => $term, 'lang' => 'it']]);
        }

        foreach ($groups as $group) {
            $primary = $group->first()['term'];
            $meta = $termmeta[(string) $primary['term_id']] ?? [];

            $extra = Extra::updateOrCreate(
                ['legacy_term_id' => $primary['term_id']],
                [
                    'name' => $primary['name'],
                    'price' => (float) $this->metaVal($meta, 'price', 0),
                    'type' => $this->metaVal($meta, 'type', 'total') === 'day' ? 'day' : 'total',
                    'max_qty' => max(1, (int) $this->metaVal($meta, 'max', 1)),
                    'always_included' => (bool) $this->metaVal($meta, 'always_included', 0),
                    'translations' => $group->mapWithKeys(fn ($m) => [$m['lang'] => ['name' => $m['term']['name']]])->all(),
                ],
            );

            $priceCond = $this->decoded($meta['price_cond'] ?? null);
            if (is_array($priceCond)) {
                foreach ($priceCond as $row) {
                    $legacyCondition = (int) ($row['condition'] ?? 0);
                    if (! isset($conditionMap[$legacyCondition])) {
                        continue;
                    }
                    ExtraPrice::updateOrCreate(
                        ['extra_id' => $extra->id, 'price_condition_id' => $conditionMap[$legacyCondition]],
                        ['price' => (float) ($row['price'] ?? 0)],
                    );
                }
            }

            foreach ($group as $m) {
                $map[(int) $m['term']['term_id']] = $extra->id;
                $map[(int) $m['term']['term_taxonomy_id']] = $extra->id;
            }
        }

        return $map;
    }

    private function importVehicles(array $vehicles, array $tridGroups, array $locationMap, array $typeMap, array $featureMap, array $extraMap): array
    {
        $byId = collect($vehicles)->keyBy('id');
        $map = [];

        $groups = [];
        foreach ($tridGroups as $members) {
            $present = collect($members)->filter(fn ($m) => $byId->has($m['element_id']));
            if ($present->isEmpty()) {
                continue;
            }
            $groups[] = $present->map(fn ($m) => ['post' => $byId[$m['element_id']], 'lang' => $m['lang']])->values();
            foreach ($present as $m) {
                $byId->forget($m['element_id']);
            }
        }
        foreach ($byId as $post) {
            $groups[] = collect([['post' => $post, 'lang' => 'it']]);
        }

        foreach ($groups as $group) {
            // EN è la lingua principale del sito; fallback sulla sorgente IT.
            $en = $group->firstWhere('lang', 'en') ?? $group->first();
            $primary = $en['post'];
            $meta = $primary['meta'] ?? [];

            $legacyIds = $group->pluck('post.id')->map(fn ($id) => (int) $id);

            $typeId = null;
            foreach ((array) ($primary['terms_by_taxonomy']['vehicle_type'] ?? []) as $legacyType) {
                $typeId = $typeMap[(int) $legacyType] ?? $typeId;
            }

            $vehicle = Vehicle::updateOrCreate(
                ['legacy_post_id' => $primary['id']],
                [
                    'name' => $primary['title'],
                    'slug' => $primary['slug'],
                    'subheader' => $this->metaVal($meta, 'subheader') ?: null,
                    'vehicle_type_id' => $typeId,
                    'stock' => max(1, (int) $this->metaVal($meta, 'stock', 1)),
                    'price_on_request' => (bool) $this->metaVal($meta, 'price_on_request', 0),
                    'custom_price_text' => $this->metaVal($meta, 'custom_price_text') ?: null,
                    'sale_badge' => $this->metaVal($meta, 'sale') ?: null,
                    'video_url' => $this->metaVal($meta, 'video_url') ?: null,
                    'no_same_day' => $legacyIds->intersect(self::NO_SAME_DAY_LEGACY_IDS)->isNotEmpty(),
                    'sort_order' => (int) ($primary['menu_order'] ?? 0),
                    'active' => $primary['status'] === 'publish',
                    'translations' => $group->mapWithKeys(fn ($m) => [$m['lang'] => [
                        'name' => $m['post']['title'],
                        'subheader' => $this->metaVal($m['post']['meta'] ?? [], 'subheader') ?: null,
                    ]])->all(),
                ],
            );

            $pickupIds = collect((array) ($primary['terms_by_taxonomy']['location'] ?? []))
                ->map(fn ($id) => $locationMap[(int) $id] ?? null)->filter()->unique()->values();
            $vehicle->pickupLocations()->sync($pickupIds);

            $dropIds = collect(explode(',', (string) $this->metaVal($meta, 'location_drop', '')))
                ->map(fn ($id) => $locationMap[(int) trim($id)] ?? null)->filter()->unique()->values();
            $vehicle->dropoffLocations()->sync($dropIds);

            $featureIds = collect((array) ($primary['terms_by_taxonomy']['feature'] ?? []))
                ->map(fn ($id) => $featureMap[(int) $id] ?? null)->filter()->unique()->values();
            $vehicle->features()->sync($featureIds);

            $extraIds = collect((array) ($primary['terms_by_taxonomy']['extra_option'] ?? []))
                ->map(fn ($id) => $extraMap[(int) $id] ?? null)->filter()->unique()->values();
            $vehicle->extras()->sync($extraIds);

            foreach ($legacyIds as $legacyId) {
                $map[$legacyId] = $vehicle->id;
            }
        }

        return $map;
    }

    private function importPrices(array $rows, array $vehicleMap, array $conditionMap): void
    {
        foreach ($rows as $row) {
            $vehicleId = $vehicleMap[(int) $row['vehicle_id']] ?? null;
            if (! $vehicleId) {
                continue;
            }
            $legacyCondition = (int) $row['condition_id'];
            $conditionId = $legacyCondition === 0 ? null : ($conditionMap[$legacyCondition] ?? null);
            if ($legacyCondition !== 0 && $conditionId === null) {
                continue;
            }
            VehiclePrice::updateOrCreate(
                ['vehicle_id' => $vehicleId, 'price_condition_id' => $conditionId],
                ['price' => (float) $row['price']],
            );
        }
    }

    private function metaVal(array $meta, string $key, mixed $default = ''): mixed
    {
        $value = $meta[$key] ?? $default;
        if (is_array($value)) {
            // chiavi duplicate => array; oggetti {__serialized} gestiti a parte
            if (array_key_exists('__serialized', $value)) {
                return $value['decoded'] ?? $default;
            }
            $value = $value[0] ?? $default;
        }

        return $value;
    }

    private function metaList(array $meta, string $key): ?array
    {
        $value = $meta[$key] ?? null;
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value) && array_key_exists('__serialized', $value)) {
            $value = $value['decoded'];
        }
        $list = is_array($value) ? array_values($value) : [$value];
        $list = array_values(array_filter($list, fn ($v) => $v !== '' && $v !== null));

        return $list ?: null;
    }

    private function mapCsv(mixed $csv, array $map): ?array
    {
        if (! is_string($csv) || trim($csv) === '') {
            return null;
        }
        $ids = collect(explode(',', $csv))
            ->map(fn ($id) => $map[(int) trim($id)] ?? null)
            ->filter()->unique()->values()->all();

        return $ids ?: null;
    }

    private function decoded(mixed $value): mixed
    {
        if (is_array($value) && array_key_exists('__serialized', $value)) {
            return $value['decoded'];
        }

        return $value;
    }

    private function intOrNull(mixed $value): ?int
    {
        return ($value === '' || $value === null || $value === false) ? null : (int) $value;
    }
}
