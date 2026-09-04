<?php

namespace App\Console\Commands;

use App\Models\CalculationBasis;
use App\Models\Category;
use App\Models\DocumentFormat;
use App\Models\GstRate;
use App\Models\PriceBand;
use App\Models\Product;
use App\Services\Masters\CategoryService;
use App\Services\Masters\ProductService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportProductsJson extends Command
{
    protected $signature = 'products:import-json
                            {path : Absolute path to product-import JSON}
                            {--dry-run : Parse and report only, no DB writes}';

    protected $description = 'Upsert products (and categories/lookups) from a parsed Product List JSON';

    public function handle(CategoryService $categories, ProductService $products): int
    {
        $path = $this->argument('path');
        if (! is_readable($path)) {
            $this->error("Cannot read: {$path}");

            return self::FAILURE;
        }

        $payload = json_decode((string) file_get_contents($path), true);
        if (! is_array($payload) || ! isset($payload['products']) || ! is_array($payload['products'])) {
            $this->error('JSON must contain a products array.');

            return self::FAILURE;
        }

        $rows = $payload['products'];
        $this->info('Rows in file: '.count($rows));

        if ($this->option('dry-run')) {
            $this->warn('Dry run — no changes written.');

            return self::SUCCESS;
        }

        foreach (['FOB Value', 'Net Value', 'Quantity', 'Net Weight', 'Square Metre'] as $basisName) {
            CalculationBasis::firstOrCreate(['name' => $basisName], ['status' => 'active']);
        }

        foreach ([['AA', 'Band AA'], ['AB', 'Band AB'], ['NA', 'N/A']] as [$code, $name]) {
            PriceBand::firstOrCreate(['code' => $code], ['name' => $name, 'status' => 'active']);
        }

        $this->ensureUnitsAvailable();

        $created = 0;
        $updated = 0;
        $failed = 0;

        DB::transaction(function () use ($rows, $categories, $products, &$created, &$updated, &$failed) {
            $categoryCache = [];
            $bandCache = PriceBand::query()->pluck('id', 'code')->all();
            $gstCache = [];
            $basisCache = CalculationBasis::query()->pluck('id', 'name')->all();

            foreach ($rows as $index => $row) {
                try {
                    $code = trim((string) ($row['item_group_code'] ?? ''));
                    if ($code === '') {
                        $failed++;

                        continue;
                    }

                    $catName = trim((string) ($row['category_name'] ?? 'Ladies Readymade Garments'));
                    if ($catName === '') {
                        $catName = 'Ladies Readymade Garments';
                    }

                    if (! isset($categoryCache[$catName])) {
                        $existing = Category::withTrashed()
                            ->where('name', $catName)
                            ->first();

                        if ($existing) {
                            if ($existing->trashed()) {
                                $existing->restore();
                            }
                            if ($existing->status !== 'active') {
                                $existing->update(['status' => 'active']);
                            }
                            $categoryCache[$catName] = $existing->id;
                        } else {
                            $categoryCache[$catName] = $categories->create([
                                'name'   => $catName,
                                'status' => 'active',
                            ])->id;
                        }
                    }

                    $bandCode = strtoupper(trim((string) ($row['price_band_code'] ?? 'NA'))) ?: 'NA';
                    if (! isset($bandCache[$bandCode])) {
                        $band = PriceBand::firstOrCreate(
                            ['code' => $bandCode],
                            ['name' => $bandCode === 'NA' ? 'N/A' : "Band {$bandCode}", 'status' => 'active']
                        );
                        $bandCache[$bandCode] = $band->id;
                    }

                    $gstRateId = null;
                    if (isset($row['gst_rate']) && $row['gst_rate'] !== null && $row['gst_rate'] !== '') {
                        $rateKey = (string) round((float) $row['gst_rate'], 2);
                        if (! isset($gstCache[$rateKey])) {
                            $gst = GstRate::firstOrCreate(
                                ['rate' => $rateKey],
                                ['status' => 'active']
                            );
                            $gstCache[$rateKey] = $gst->id;
                        }
                        $gstRateId = $gstCache[$rateKey];
                    }

                    $incentives = [];
                    foreach (['drawback', 'rosctl', 'rodtep'] as $scheme) {
                        $inc = $row['incentives'][$scheme] ?? [];
                        $p1 = $inc['percent_1'] ?? null;
                        if ($p1 === null || $p1 === '') {
                            continue;
                        }

                        $basisName = trim((string) ($inc['basis'] ?? 'FOB Value')) ?: 'FOB Value';
                        if (! isset($basisCache[$basisName])) {
                            $basis = CalculationBasis::firstOrCreate(
                                ['name' => $basisName],
                                ['status' => 'active']
                            );
                            $basisCache[$basisName] = $basis->id;
                        }

                        $incentives[$scheme] = [
                            'percent_1'            => $p1,
                            'percent_2'            => $inc['percent_2'] ?? null,
                            'cap_value'            => $inc['cap_value'] ?? null,
                            'cap_value_2'          => $inc['cap_value_2'] ?? null,
                            'calculation_basis_id' => $basisCache[$basisName],
                        ];
                    }

                    // Sheet often reuses the same display name for AA/AB bands;
                    // DB enforces unique products.name, so keep code in the label.
                    $baseName = trim((string) ($row['name'] ?? $code));
                    if ($baseName === '') {
                        $baseName = $code;
                    }
                    $uniqueName = $baseName;
                    if (! preg_match('/\b'.preg_quote($code, '/').'\b/i', $uniqueName)) {
                        $uniqueName = $baseName.' ('.$code.')';
                    }

                    $payload = [
                        'category_id'             => $categoryCache[$catName],
                        'item_group_code'         => $code,
                        'name'                    => $uniqueName,
                        'name_on_export_document' => (string) ($row['name_on_export_document'] ?? $row['name'] ?? $code),
                        'barcode'                 => $row['barcode'] ?? null,
                        'unit_po'                 => $row['unit_po'] ?? 'SET',
                        'unit_export'             => $row['unit_export'] ?? ($row['unit_po'] ?? 'SET'),
                        'hsn_code'                => $row['hsn_code'] ?? null,
                        'drawback_sr_no'          => $row['drawback_sr_no'] ?? null,
                        'price_band_id'           => $bandCache[$bandCode],
                        'gst_rate_id'             => $gstRateId,
                        'fabric_length_mtr'       => $row['fabric_length_mtr'] ?? null,
                        'fabric_width_inch'       => $row['fabric_width_inch'] ?? null,
                        'description'             => $row['description'] ?? null,
                        'remarks'                 => $row['remarks'] ?? null,
                        'comments'                => $row['comments'] ?? null,
                        'status'                  => 'active',
                        'incentives'              => $incentives,
                    ];

                    $product = Product::withTrashed()
                        ->where('item_group_code', $code)
                        ->first();

                    if ($product) {
                        if ($product->trashed()) {
                            $product->restore();
                        }
                        $products->update($product, $payload);
                        $updated++;
                    } else {
                        $products->create($payload);
                        $created++;
                    }
                } catch (\Throwable $e) {
                    $failed++;
                    $this->error('Row '.($index + 1).' ('.($row['item_group_code'] ?? '?').'): '.$e->getMessage());
                }
            }
        });

        $this->info("Created: {$created}");
        $this->info("Updated: {$updated}");
        $this->info("Failed:  {$failed}");
        $this->info('Products in DB: '.Product::query()->count());

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function ensureUnitsAvailable(): void
    {
        $needed = ['SET', 'PCS', 'MTR', 'KGS', 'PAIR', 'DOZ'];
        $format = DocumentFormat::query()->orderBy('id')->first();
        if (! $format) {
            return;
        }

        $existing = $format->units()->pluck('name')->map(fn ($n) => strtoupper((string) $n))->all();
        $sort = (int) ($format->units()->max('sort_order') ?? 0);

        foreach ($needed as $unit) {
            if (in_array($unit, $existing, true)) {
                continue;
            }
            $sort++;
            $format->units()->create([
                'name'       => $unit,
                'sort_order' => $sort,
            ]);
        }
    }
}
