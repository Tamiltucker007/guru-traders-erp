<?php

/**
 * Parse client Product List XLS → JSON for live import.
 *
 * Sheet "Data" columns (row 3 headers):
 *   B label (code/name), D name, E export name, F barcode, G group code,
 *   H additional information, I price band, J gst%, K/L units, M HSN, N drawback sr,
 *   O–R drawback, S–Z rosctl (two %), AA–AD rodtep, AE/AF fabric.
 * Column C is binary-corrupted — derive code from B instead.
 *
 * Run: php scripts/parse-product-xls.php
 */

require dirname(__DIR__).'/vendor/autoload.php';

$path = 'c:/Users/Lenovo/Downloads/Product List - 01092026 (1).xls';
$out = dirname(__DIR__).'/storage/app/product-import-01092026.json';

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
$ss = $reader->load($path);
$sheet = $ss->getSheetByName('Data') ?: $ss->getActiveSheet();
$rows = $sheet->toArray(null, true, true, true);

function clean(?string $v): string
{
    $v = trim((string) $v);
    // Drop binary garbage / non-printables from corrupted cells
    $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? $v;
    $v = preg_replace('/\s+/u', ' ', $v) ?? $v;

    return trim($v);
}

function isBlank($v): bool
{
    $s = clean((string) $v);

    return $s === '' || strtoupper($s) === '#N/A' || $s === '-' || strtoupper($s) === '#DIV/0!';
}

function parsePercent($v): ?float
{
    if (is_numeric($v) && ! is_string($v)) {
        $n = (float) $v;
        if ($n > 0 && $n <= 1) {
            $n *= 100;
        }

        return round($n, 3);
    }

    $raw = clean((string) $v);
    if (isBlank($raw)) {
        return null;
    }
    $hadPct = str_contains($raw, '%');
    $s = str_replace(['%', ','], '', $raw);
    if (! is_numeric($s)) {
        return null;
    }
    $n = (float) $s;
    if (! $hadPct && $n > 0 && $n <= 1) {
        $n *= 100;
    }

    return round($n, 3);
}

function parseMoney($v): ?float
{
    $v = clean((string) $v);
    if (isBlank($v)) {
        return null;
    }
    $v = preg_replace('/[^\d.]/', '', $v) ?? '';
    if ($v === '' || ! is_numeric($v)) {
        return null;
    }

    return round((float) $v, 4);
}

function parseBand($v): ?string
{
    $v = strtoupper(clean((string) $v));
    if ($v === '' || $v === '#N/A' || $v === 'N/A' || $v === 'NA') {
        return 'NA';
    }
    if (preg_match('/\b(AA|AB|NA)\b/', $v, $m)) {
        return $m[1];
    }

    return null;
}

function parseGst($v): ?float
{
    if (isBlank($v)) {
        return null;
    }
    if (is_numeric($v) && ! is_string($v)) {
        $n = (float) $v;
        if ($n > 0 && $n <= 1) {
            $n *= 100;
        }

        return round($n, 2);
    }
    $s = str_replace('%', '', clean((string) $v));
    if (! is_numeric($s)) {
        return null;
    }

    return round((float) $s, 2);
}

function parseBasis($v): ?string
{
    $v = clean((string) $v);
    if (isBlank($v)) {
        return null;
    }
    $lower = strtolower($v);
    if (str_contains($lower, 'fob')) {
        return 'FOB Value';
    }
    if ($lower === 'qty' || str_contains($lower, 'quantity') || str_contains($lower, 'qty')) {
        return 'Quantity';
    }
    if (str_contains($lower, 'net value')) {
        return 'Net Value';
    }
    if (str_contains($lower, 'net weight')) {
        return 'Net Weight';
    }
    if (str_contains($lower, 'square') || str_contains($lower, 'sq')) {
        return 'Square Metre';
    }

    return $v;
}

$products = [];
$currentCategory = 'Ladies Readymade Garments';
$skipped = 0;

foreach ($rows as $i => $r) {
    if ($i < 7) {
        continue;
    }

    $b = clean((string) ($r['B'] ?? ''));
    $d = clean((string) ($r['D'] ?? ''));
    $e = clean((string) ($r['E'] ?? ''));
    $a = clean((string) ($r['A'] ?? ''));

    // Category banner rows
    if ($a !== '' && ! preg_match('/^SR\s*NO/i', $a) && $b === '') {
        $currentCategory = $a;
        continue;
    }
    if ($a !== '' && ! preg_match('/^SR\s*NO/i', $a) && stripos($a, 'LADIES') !== false && strlen($a) > 20) {
        $currentCategory = $a;
    }

    if ($b === '' || stripos($b, 'PRODUCT NAME') !== false || stripos($b, 'Item Group') !== false) {
        $skipped++;
        continue;
    }

    $code = null;
    $bandFromB = null;
    $nameFromB = $d !== '' ? $d : $b;

    // 001-AA/ NAME  OR  370A-AA/ NAME
    if (preg_match('/^(\d+[A-Za-z]?)\s*[-–]\s*([A-Za-z]{2})\s*\/\s*(.+)$/u', $b, $m)) {
        $code = strtoupper($m[1].$m[2]);
        $bandFromB = strtoupper($m[2]);
        if ($d === '') {
            $nameFromB = clean($m[3]);
        }
    // 252D - NAME  OR  245- NAME  (no AA/AB band)
    } elseif (preg_match('/^(\d+[A-Za-z]?)\s*[-–]\s*(.+)$/u', $b, $m)) {
        $code = strtoupper($m[1]);
        $bandFromB = null;
        if ($d === '') {
            $nameFromB = clean($m[2]);
        }
    } elseif (preg_match('/^(\d+[A-Za-z]?)\s*([A-Za-z]{2})\b/u', $b, $m)) {
        $code = strtoupper($m[1].$m[2]);
        $bandFromB = strtoupper($m[2]);
    } else {
        $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $b) ?? 'X', 0, 20));
    }

    if ($code === '' || $code === null) {
        $skipped++;
        continue;
    }

    // Prefer sheet price-band column; if #N/A use AA/AB from item code when present
    $bandFromSheet = parseBand($r['I'] ?? null);
    $band = $bandFromSheet;
    if ($band === 'NA' && $bandFromB && in_array($bandFromB, ['AA', 'AB'], true)) {
        $band = $bandFromB;
    }
    $band = $band ?: 'NA';

    $unitPo = strtoupper(clean((string) ($r['K'] ?? ''))) ?: 'SET';
    $unitExport = strtoupper(clean((string) ($r['L'] ?? ''))) ?: $unitPo;
    $hsn = clean((string) ($r['M'] ?? ''));
    $drawbackSr = clean((string) ($r['N'] ?? ''));
    $barcode = clean((string) ($r['F'] ?? ''));
    $groupCode = clean((string) ($r['G'] ?? ''));
    $additional = clean((string) ($r['H'] ?? ''));
    $gst = parseGst($r['J'] ?? null);

    $drawbackPct = parsePercent($r['O'] ?? null);
    $drawbackBasis = parseBasis($r['P'] ?? null) ?: 'FOB Value';
    $drawbackCap = parseMoney($r['Q'] ?? null);
    $drawbackCapBasis = parseBasis($r['R'] ?? null);

    $rosctl1 = parsePercent($r['S'] ?? null);
    $rosctlBasis1 = parseBasis($r['T'] ?? null) ?: 'FOB Value';
    $rosctlCap1 = parseMoney($r['U'] ?? null);
    $rosctlCapBasis1 = parseBasis($r['V'] ?? null);
    $rosctl2 = parsePercent($r['W'] ?? null);
    $rosctlBasis2 = parseBasis($r['X'] ?? null);
    $rosctlCap2 = parseMoney($r['Y'] ?? null);
    $rosctlCapBasis2 = parseBasis($r['Z'] ?? null);

    $rodtepPct = parsePercent($r['AA'] ?? null);
    $rodtepBasis = parseBasis($r['AB'] ?? null) ?: 'FOB Value';
    $rodtepCap = parseMoney($r['AC'] ?? null);
    $rodtepCapBasis = parseBasis($r['AD'] ?? null);

    $fabricLen = parseMoney($r['AE'] ?? null);
    $fabricWid = parseMoney($r['AF'] ?? null);

    $remarkParts = [];
    if ($groupCode !== '') {
        $remarkParts[] = 'Group Code: '.$groupCode;
    }
    if ($drawbackCapBasis) {
        $remarkParts[] = 'Drawback cap on: '.$drawbackCapBasis;
    }
    if ($rosctlCapBasis1) {
        $remarkParts[] = 'RoSCTL cap1 on: '.$rosctlCapBasis1;
    }
    if ($rosctlCapBasis2) {
        $remarkParts[] = 'RoSCTL cap2 on: '.$rosctlCapBasis2;
    }
    if ($rodtepCapBasis) {
        $remarkParts[] = 'RoDTEP cap on: '.$rodtepCapBasis;
    }
    if ($rosctlBasis2 && $rosctlBasis2 !== $rosctlBasis1) {
        $remarkParts[] = 'RoSCTL %2 basis: '.$rosctlBasis2;
    }

    $commentParts = [];
    if ($additional !== '') {
        // Prefer putting H into description; keep copy in comments only if needed
    }
    $commentParts[] = 'Source: '.basename($path).' row '.$i;
    if ($b !== '') {
        $commentParts[] = 'Sheet label: '.$b;
    }

    $products[$code] = [
        'item_group_code'         => $code,
        'name'                    => $nameFromB,
        'name_on_export_document' => $e !== '' ? $e : $nameFromB,
        'barcode'                 => $barcode !== '' ? $barcode : null,
        'category_name'           => $currentCategory,
        'group_code'              => $groupCode !== '' ? $groupCode : null,
        'description'             => $additional !== '' ? $additional : null,
        'remarks'                 => $remarkParts !== [] ? implode(' | ', $remarkParts) : null,
        'comments'                => implode(' | ', $commentParts),
        'price_band_code'         => $band,
        'gst_rate'                => $gst,
        'unit_po'                 => $unitPo,
        'unit_export'             => $unitExport,
        'hsn_code'                => $hsn !== '' ? $hsn : null,
        'drawback_sr_no'          => $drawbackSr !== '' ? $drawbackSr : null,
        'fabric_length_mtr'       => $fabricLen,
        'fabric_width_inch'       => $fabricWid,
        'incentives'              => [
            'drawback' => [
                'percent_1' => $drawbackPct,
                'cap_value' => $drawbackCap,
                'basis'     => $drawbackBasis,
            ],
            'rosctl' => [
                'percent_1'   => $rosctl1,
                'percent_2'   => $rosctl2,
                'cap_value'   => $rosctlCap1,
                'cap_value_2' => $rosctlCap2,
                'basis'       => $rosctlBasis1,
            ],
            'rodtep' => [
                'percent_1' => $rodtepPct,
                'cap_value' => $rodtepCap,
                'basis'     => $rodtepBasis,
            ],
        ],
        'source_row'   => $i,
        'source_label' => $b,
    ];
}

$products = array_values($products);
file_put_contents($out, json_encode([
    'generated_at' => date('c'),
    'source'       => basename($path),
    'count'        => count($products),
    'products'     => $products,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo 'parsed='.count($products)." skipped_approx={$skipped}\n";
echo 'out='.$out."\n";

$withDesc = count(array_filter($products, fn ($p) => ! empty($p['description'])));
$withFabric = count(array_filter($products, fn ($p) => $p['fabric_length_mtr'] !== null || $p['fabric_width_inch'] !== null));
$withGst = count(array_filter($products, fn ($p) => $p['gst_rate'] !== null));
$withRodtep = count(array_filter($products, fn ($p) => ($p['incentives']['rodtep']['percent_1'] ?? null) !== null));
$bands = [];
foreach ($products as $p) {
    $bands[$p['price_band_code']] = ($bands[$p['price_band_code']] ?? 0) + 1;
}
echo "with_description={$withDesc} with_fabric={$withFabric} with_gst={$withGst} with_rodtep={$withRodtep}\n";
echo 'bands='.json_encode($bands)."\n";
echo 'sample='.json_encode($products[0], JSON_UNESCAPED_UNICODE)."\n";
