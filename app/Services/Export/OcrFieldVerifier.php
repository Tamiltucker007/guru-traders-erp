<?php

namespace App\Services\Export;

use App\Models\ExportDocument;
use Illuminate\Support\Str;

/**
 * Compare Gemini-extracted document fields against ERP data for the selected
 * Export Document — Match / Error / Warning (spelling), like the verification
 * table Hiral described.
 */
class OcrFieldVerifier
{
    /**
     * @param  array<string, mixed>  $extracted  Gemini mapToChecklistFields result (+ fields)
     * @return array{
     *     status: string,
     *     status_label: string,
     *     match_count: int,
     *     error_count: int,
     *     warning_count: int,
     *     rows: list<array{field:string,erp:string,document:string,result:string,result_label:string}>,
     *     summary: string
     * }
     */
    public function compare(?ExportDocument $document, array $extracted): array
    {
        if (! $document) {
            return [
                'status'         => 'pending',
                'status_label'   => 'No Export Document selected',
                'match_count'    => 0,
                'error_count'    => 0,
                'warning_count'  => 0,
                'rows'           => [],
                'summary'        => 'Select an Export Document to verify against ERP.',
            ];
        }

        $document->loadMissing([
            'buyer',
            'currency',
            'orderConfirmation.buyer',
            'items.product',
            'orderConfirmation.items.product',
        ]);

        $fields = is_array($extracted['fields'] ?? null) ? $extracted['fields'] : [];
        $docBuyer = $this->str($extracted['buyer_name'] ?? ($fields['buyer_or_consignee_name'] ?? null));
        $docInvoice = $this->str($fields['invoice_no'] ?? null);
        $docCurrency = $this->str($fields['currency'] ?? null);
        $docAmount = $this->money($fields['total_amount'] ?? ($fields['amount'] ?? null));
        $docQty = $this->number($fields['quantity'] ?? ($fields['total_qty'] ?? null));
        $docUnitPrice = $this->money($fields['unit_price'] ?? null);
        $docHs = $this->str($fields['hs_code'] ?? ($fields['hsn_code'] ?? null));
        $docAddress = $this->str($fields['buyer_address'] ?? ($fields['consignee_address'] ?? null));

        $erpBuyer = $document->buyer?->company_name
            ?? $document->consignee_name
            ?? $document->orderConfirmation?->buyer?->company_name;
        $erpInvoice = $document->invoice_no;
        $erpCurrency = $document->currency?->iso_code;
        $erpAmount = round((float) $document->totalAmount(), 3);
        $erpQty = (float) ($document->items->sum('qty')
            ?: $document->orderConfirmation?->items->sum('qty')
            ?: 0);
        $erpUnitPrice = (float) (
            $document->items->first()?->price
            ?? $document->orderConfirmation?->items->first()?->price
            ?? 0
        );
        $erpHs = $document->items->first()?->product?->hsn_code
            ?? $document->orderConfirmation?->items->first()?->product?->hsn_code;
        $erpAddress = $document->consignee_address
            ?? $document->buyer?->address;

        $currencyLabel = $docCurrency ?: $erpCurrency;

        $rows = [
            $this->row('Buyer Name', $erpBuyer, $docBuyer, 'name'),
            $this->row('Invoice No.', $erpInvoice, $docInvoice, 'code'),
            $this->row('Quantity', $erpQty > 0 ? $this->fmtNum($erpQty) : null, $docQty !== null ? $this->fmtNum($docQty) : null, 'number'),
            $this->row(
                'Unit Price',
                $erpUnitPrice > 0 ? $this->fmtMoney($erpUnitPrice, $currencyLabel) : null,
                $docUnitPrice !== null ? $this->fmtMoney($docUnitPrice, $currencyLabel) : null,
                'money'
            ),
            $this->row(
                'Total Amount',
                $erpAmount > 0 ? $this->fmtMoney($erpAmount, $currencyLabel) : null,
                $docAmount !== null ? $this->fmtMoney($docAmount, $currencyLabel) : null,
                'money'
            ),
            $this->row('Currency', $erpCurrency, $docCurrency, 'code'),
            $this->row('HS Code', $erpHs, $docHs, 'code'),
            $this->row('Buyer Address', $erpAddress, $docAddress, 'address'),
        ];

        // Drop rows where both sides empty (nothing to verify).
        $rows = array_values(array_filter($rows, fn ($r) => $r['erp'] !== '—' || $r['document'] !== '—'));

        $matchCount = count(array_filter($rows, fn ($r) => $r['result'] === 'match'));
        $errorCount = count(array_filter($rows, fn ($r) => $r['result'] === 'error'));
        $warningCount = count(array_filter($rows, fn ($r) => $r['result'] === 'warning'));
        $compared = count(array_filter($rows, fn ($r) => $r['result'] !== 'missing'));

        if ($compared === 0) {
            $status = 'review';
            $label = 'Review Required';
        } elseif ($errorCount > 0) {
            $status = 'errors';
            $label = $errorCount.' Error'.($errorCount === 1 ? '' : 's');
        } elseif ($warningCount > 0) {
            $status = 'review';
            $label = 'Review Required';
        } else {
            $status = 'verified';
            $label = '100% Verified';
        }

        return [
            'status'        => $status,
            'status_label'  => $label,
            'match_count'   => $matchCount,
            'error_count'   => $errorCount,
            'warning_count' => $warningCount,
            'rows'          => $rows,
            'summary'       => $label.' · '.$matchCount.' match'
                .($errorCount ? ', '.$errorCount.' error' : '')
                .($warningCount ? ', '.$warningCount.' warning' : ''),
        ];
    }

    /**
     * Aggregate checklist OCR verification for the Export Documents list.
     *
     * @return array{
     *     status: string,
     *     status_label: string,
     *     invoice: string,
     *     packing_list: string,
     *     shipping_bill: string,
     *     action: string
     * }
     */
    public function documentDashboard(ExportDocument $document): array
    {
        $document->loadMissing(['checklist.type']);

        $dot = function (?array $verification, string $fallbackStatus): string {
            if (! $verification) {
                return $fallbackStatus === 'uploaded' || $fallbackStatus === 'received' || $fallbackStatus === 'generated'
                    ? 'uploaded'
                    : 'pending';
            }

            return match ($verification['status'] ?? 'pending') {
                'verified' => 'verified',
                'errors'   => 'errors',
                'review'   => 'review',
                default    => 'pending',
            };
        };

        $pick = function (array $codes) use ($document, $dot): string {
            $rows = $document->checklist->filter(
                fn ($c) => in_array($c->type?->code, $codes, true)
            );
            if ($rows->isEmpty()) {
                return 'pending';
            }

            $worst = 'verified';
            $rank = ['pending' => 0, 'uploaded' => 1, 'verified' => 2, 'review' => 3, 'errors' => 4];
            foreach ($rows as $row) {
                $state = $dot($row->ocr_verification, $row->status);
                if (($rank[$state] ?? 0) >= ($rank[$worst] ?? 0)) {
                    $worst = $state;
                }
                // Prefer explicit verification when present.
                if (is_array($row->ocr_verification) && ($row->ocr_verification['status'] ?? null)) {
                    $state = $dot($row->ocr_verification, $row->status);
                    if (($rank[$state] ?? 0) > ($rank[$worst] ?? 0)) {
                        $worst = $state;
                    }
                }
            }

            return $worst;
        };

        $invoice = $pick(['e_sanchit_docs', 'export_invoice', 'e_invoice', 'cha_checklist']);
        $packing = $pick(['packing_list', 'measurement_copy', 'clp']);
        $shipping = $pick(['assessed_copy', 'leo_copy', 'bl_final', 'bl_draft']);

        $verifiedRows = $document->checklist
            ->filter(fn ($c) => is_array($c->ocr_verification))
            ->values();

        $errorCount = $verifiedRows->sum(fn ($c) => (int) ($c->ocr_verification['error_count'] ?? 0));
        $warningCount = $verifiedRows->sum(fn ($c) => (int) ($c->ocr_verification['warning_count'] ?? 0));

        if ($verifiedRows->isEmpty()) {
            $status = 'pending';
            $label = 'Not scanned';
            $action = 'Scan';
        } elseif ($errorCount > 0) {
            $status = 'errors';
            $label = $errorCount.' Error'.($errorCount === 1 ? '' : 's');
            $action = 'Fix';
        } elseif ($warningCount > 0 || in_array('review', [$invoice, $packing, $shipping], true)) {
            $status = 'review';
            $label = 'Review Required';
            $action = 'Review';
        } else {
            $status = 'verified';
            $label = '100% Verified';
            $action = 'View';
        }

        return [
            'status'         => $status,
            'status_label'   => $label,
            'invoice'        => $invoice,
            'packing_list'   => $packing,
            'shipping_bill'  => $shipping,
            'action'         => $action,
        ];
    }

    /**
     * @return array{field:string,erp:string,document:string,result:string,result_label:string}
     */
    private function row(string $field, mixed $erp, mixed $doc, string $mode): array
    {
        $erpStr = $this->display($erp);
        $docStr = $this->display($doc);

        if ($erpStr === '—' && $docStr === '—') {
            return [
                'field'        => $field,
                'erp'          => '—',
                'document'     => '—',
                'result'       => 'missing',
                'result_label' => 'No data',
            ];
        }

        if ($docStr === '—') {
            return [
                'field'        => $field,
                'erp'          => $erpStr,
                'document'     => '—',
                'result'       => 'missing',
                'result_label' => 'Not on document',
            ];
        }

        if ($erpStr === '—') {
            return [
                'field'        => $field,
                'erp'          => '—',
                'document'     => $docStr,
                'result'       => 'warning',
                'result_label' => 'Only on document',
            ];
        }

        [$result, $label] = $this->judge($erpStr, $docStr, $mode);

        return [
            'field'        => $field,
            'erp'          => $erpStr,
            'document'     => $docStr,
            'result'       => $result,
            'result_label' => $label,
        ];
    }

    /**
     * @return array{0:string,1:string}
     */
    private function judge(string $erp, string $doc, string $mode): array
    {
        $a = $this->normalize($erp, $mode);
        $b = $this->normalize($doc, $mode);

        if ($a === $b) {
            return ['match', 'Match'];
        }

        if ($mode === 'number' || $mode === 'money') {
            $na = $this->number($erp);
            $nb = $this->number($doc);
            if ($na !== null && $nb !== null && $this->amountsEqual($na, $nb)) {
                return ['match', 'Match'];
            }

            // Any decimal / paisa / cent difference is an Error (USD, AED, INR/Rs, etc.).
            return ['error', $mode === 'money' ? 'Price error' : 'Error'];
        }

        if ($mode === 'address' || $mode === 'name') {
            similar_text($a, $b, $pct);
            if ($pct >= 92) {
                return ['warning', 'Possible spelling error'];
            }
            if ($pct >= 75) {
                return ['warning', 'Possible spelling error'];
            }

            return ['error', 'Error'];
        }

        // codes / invoice
        similar_text($a, $b, $pct);
        if ($pct >= 90) {
            return ['warning', 'Possible spelling error'];
        }

        return ['error', 'Error'];
    }

    private function normalize(string $value, string $mode): string
    {
        $v = Str::lower(trim($value));
        $v = preg_replace('/\s+/', ' ', $v) ?? $v;

        if ($mode === 'code' || $mode === 'number' || $mode === 'money') {
            $v = preg_replace('/[^a-z0-9.]/', '', $v) ?? $v;
        }

        if ($mode === 'name' || $mode === 'address') {
            $v = preg_replace('/[^a-z0-9 ]/', '', $v) ?? $v;
        }

        return $v;
    }

    private function str(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }

    private function number(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $s = trim((string) $value);
        // Strip currency words/symbols so Rs / ₹ / INR / USD / $ all compare as amounts.
        $s = preg_replace('/\b(rs\.?|inr|usd|aed|eur|gbp|jpy)\b/iu', '', $s) ?? $s;
        $s = str_replace(['₹', '$', '€', '£'], '', $s);
        $s = trim($s);

        // Indian / US grouping: 10,678.678 → 10678.678
        if (preg_match('/^-?\d{1,3}(,\d{2,3})+(\.\d+)?$/', $s) || preg_match('/^-?\d+\.\d+$/', $s) || preg_match('/^-?\d+$/', $s)) {
            $s = str_replace(',', '', $s);
        } else {
            $s = preg_replace('/[^0-9.\-]/', '', $s) ?? '';
        }

        if ($s === '' || ! is_numeric($s)) {
            return null;
        }

        return (float) $s;
    }

    /**
     * Exact to 3 decimal places — 10678.678 vs 10678 is a Price error.
     */
    private function amountsEqual(float $a, float $b): bool
    {
        return (int) round($a * 1000) === (int) round($b * 1000);
    }

    private function money(mixed $value): ?float
    {
        return $this->number($value);
    }

    private function display(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return is_string($value) ? trim($value) : (string) $value;
    }

    private function fmtNum(float $n): string
    {
        return number_format($n, $n == floor($n) ? 0 : 3, '.', ',');
    }

    private function fmtMoney(float $n, ?string $currency = null): string
    {
        $decimals = (abs($n - round($n)) < 0.0000001) ? 2 : 3;
        $amount = number_format($n, $decimals, '.', ',');
        $code = $currency ? strtoupper(trim($currency)) : null;

        if ($code === 'INR' || $code === 'RS' || $code === 'RS.') {
            return 'Rs '.$amount;
        }

        return $code ? $amount.' '.$code : $amount;
    }
}
