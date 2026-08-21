<?php

namespace App\Services\Export;

use App\Models\Buyer;
use App\Models\CompanyProfile;
use App\Models\ExportDocument;
use App\Models\OrderConfirmation;
use App\Models\Supplier;
use Illuminate\Support\Collection;

/**
 * Maps Gemini-extracted party / invoice text onto Buyer, Supplier and
 * Order Confirmation masters, then suggests the Export Document that
 * belongs to that OC / buyer.
 */
class OcrPartyMatcher
{
    private const MIN_SCORE = 72;

    /**
     * @return array{
     *     buyer_name: ?string,
     *     supplier_name: ?string,
     *     invoice_no: ?string,
     *     buyer: ?array{id:int,display_code:?string,company_name:string,score:int,source?:string},
     *     supplier: ?array{id:int,display_code:?string,company_name:string,score:int,source?:string},
     *     order_confirmation: ?array{id:int,oc_num:string,buyer_ref:?string,buyer_name:?string,score:int,source?:string},
     *     suggested_export_documents: list<array{id:int,doc_num:string,buyer_name:?string,oc_num:?string}>,
     *     selected_buyer_matches: ?bool,
     *     selected_oc_matches: ?bool,
     *     scan_notes: list<string>
     * }
     */
    public function resolve(
        ?string $buyerName,
        ?string $supplierName,
        ?ExportDocument $selected = null,
        ?string $invoiceNo = null,
    ): array {
        $scanNotes = [];
        $buyerName = $this->clean($buyerName);
        $supplierName = $this->clean($supplierName);
        $invoiceNo = $this->clean($invoiceNo);

        if ($selected) {
            $selected->loadMissing([
                'buyer:id,display_code,company_name',
                'orderConfirmation.buyer:id,company_name',
                'items.sourceItem.supplier:id,display_code,company_name,discount_percent,name_on_bill',
            ]);
        }

        $buyer = $this->matchBuyer($buyerName);
        if (! $buyer && $buyerName) {
            $scanNotes[] = 'Scan buyer "'.$buyerName.'" is not in Buyer master.';
        }

        // Exporter on customs docs is our company — not a supplier master.
        if ($supplierName && $this->looksLikeOurCompany($supplierName)) {
            $scanNotes[] = 'Scan shipper/exporter "'.$supplierName.'" is our company profile — skipped as supplier.';
            $supplierName = null;
        }

        $supplier = $this->matchSupplier($supplierName, $selected);
        if (! $supplier && $this->clean($supplierName)) {
            $scanNotes[] = 'Scan supplier "'.$supplierName.'" is not in Supplier master.';
        }

        $orderConfirmation = $this->matchOrderConfirmation($invoiceNo, $buyer, $selected);
        if (! $orderConfirmation && $invoiceNo) {
            $scanNotes[] = 'Scan invoice "'.$invoiceNo.'" did not match an OC / export invoice.';
        }

        // Particular order already chosen on the OCR desk: link Save to that
        // shipment's buyer / OC / suppliers even when the PDF names differ
        // (demo scans often use a different consignee than the live OC).
        if ($selected) {
            if (! $buyer && $selected->buyer) {
                $buyer = [
                    'id'           => (int) $selected->buyer->id,
                    'display_code' => $selected->buyer->display_code,
                    'company_name' => $selected->buyer->company_name,
                    'score'        => 100,
                    'source'       => 'shipment',
                ];
                $scanNotes[] = 'Linked Buyer from selected Export Document '.$selected->doc_num.'.';
            }

            if (! $orderConfirmation && $selected->orderConfirmation) {
                $oc = $selected->orderConfirmation;
                $orderConfirmation = [
                    'id'         => (int) $oc->id,
                    'oc_num'     => (string) $oc->oc_num,
                    'buyer_ref'  => $oc->buyer_ref,
                    'buyer_name' => $oc->buyer?->company_name,
                    'score'      => 100,
                    'source'     => 'shipment',
                ];
                $scanNotes[] = 'Linked Order Confirmation '.$oc->oc_num.' from selected shipment.';
            }

            if (! $supplier) {
                $fromOrder = $this->firstSupplierOnShipment($selected);
                if ($fromOrder) {
                    $supplier = $fromOrder + ['source' => 'shipment'];
                    $scanNotes[] = 'Linked Supplier from this order\'s OC / export lines.';
                }
            }
        }

        $suggested = $this->suggestExportDocuments($buyer, $orderConfirmation);
        if ($selected) {
            // Keep the desk on the order the user already opened.
            array_unshift($suggested, [
                'id'         => $selected->id,
                'doc_num'    => $selected->doc_num,
                'buyer_name' => $selected->buyer?->company_name,
                'oc_num'     => $selected->orderConfirmation?->oc_num,
            ]);
            $suggested = collect($suggested)->unique('id')->values()->take(8)->all();
        }

        $selectedBuyerMatches = null;
        if ($selected && $buyer) {
            $selectedBuyerMatches = (int) $selected->buyer_id === (int) $buyer['id'];
        }

        $selectedOcMatches = null;
        if ($selected && $orderConfirmation) {
            $selectedOcMatches = (int) $selected->order_confirmation_id === (int) $orderConfirmation['id'];
        }

        return [
            'buyer_name'                 => $buyerName,
            'supplier_name'              => $this->clean($supplierName),
            'invoice_no'                 => $invoiceNo,
            'buyer'                      => $buyer,
            'supplier'                   => $supplier,
            'order_confirmation'         => $orderConfirmation,
            'suggested_export_documents' => $suggested,
            'selected_buyer_matches'     => $selectedBuyerMatches,
            'selected_oc_matches'        => $selectedOcMatches,
            'scan_notes'                 => $scanNotes,
        ];
    }

    private function looksLikeOurCompany(string $name): bool
    {
        $company = CompanyProfile::current()->company_name;
        if (blank($company)) {
            return false;
        }

        $score = $this->score($this->normalize($name), $this->normalize($company));

        return $score >= self::MIN_SCORE;
    }

    /**
     * @return array{id:int,display_code:?string,company_name:string,score:int}|null
     */
    private function firstSupplierOnShipment(ExportDocument $document): ?array
    {
        $document->loadMissing([
            'items.sourceItem.supplier:id,display_code,company_name',
            'orderConfirmation.items.supplier:id,display_code,company_name',
        ]);

        foreach ($document->items as $item) {
            $supplier = $item->sourceItem?->supplier;
            if ($supplier) {
                return [
                    'id'           => (int) $supplier->id,
                    'display_code' => $supplier->display_code,
                    'company_name' => $supplier->company_name,
                    'score'        => 100,
                ];
            }
        }

        foreach ($document->orderConfirmation?->items ?? [] as $item) {
            if ($item->supplier) {
                return [
                    'id'           => (int) $item->supplier->id,
                    'display_code' => $item->supplier->display_code,
                    'company_name' => $item->supplier->company_name,
                    'score'        => 100,
                ];
            }
        }

        return null;
    }

    /**
     * @return array{id:int,display_code:?string,company_name:string,score:int}|null
     */
    public function matchBuyer(?string $name): ?array
    {
        $needle = $this->normalize($name);
        if ($needle === '') {
            return null;
        }

        $candidates = Buyer::query()
            ->where('status', 'active')
            ->get(['id', 'display_code', 'company_name', 'name_on_export_invoice']);

        return $this->bestMatch($needle, $candidates, fn (Buyer $b) => array_filter([
            $b->company_name,
            $b->name_on_export_invoice,
            $b->display_code,
        ]));
    }

    /**
     * Prefer suppliers already on the shipment (via OC item), then all active suppliers.
     *
     * @return array{id:int,display_code:?string,company_name:string,score:int}|null
     */
    public function matchSupplier(?string $name, ?ExportDocument $document = null): ?array
    {
        $needle = $this->normalize($name);
        if ($needle === '') {
            return null;
        }

        $shipmentSupplierIds = collect();
        if ($document) {
            $document->loadMissing('items.sourceItem:id,supplier_id');
            $shipmentSupplierIds = $document->items
                ->pluck('sourceItem.supplier_id')
                ->filter()
                ->unique()
                ->values();
        }

        if ($shipmentSupplierIds->isNotEmpty()) {
            $preferred = Supplier::query()
                ->whereIn('id', $shipmentSupplierIds)
                ->get(['id', 'display_code', 'company_name', 'name_on_bill']);

            $match = $this->bestMatch($needle, $preferred, fn (Supplier $s) => array_filter([
                $s->company_name,
                $s->name_on_bill,
                $s->display_code,
            ]));

            if ($match) {
                return $match;
            }
        }

        $all = Supplier::query()
            ->where('status', 'active')
            ->get(['id', 'display_code', 'company_name', 'name_on_bill']);

        return $this->bestMatch($needle, $all, fn (Supplier $s) => array_filter([
            $s->company_name,
            $s->name_on_bill,
            $s->display_code,
        ]));
    }

    /**
     * Resolve OC from invoice / buyer-ref text, preferring the selected shipment's OC
     * when it already matches the buyer.
     *
     * @param  array{id:int,display_code:?string,company_name:string,score:int}|null  $buyer
     * @return array{id:int,oc_num:string,buyer_ref:?string,buyer_name:?string,score:int}|null
     */
    public function matchOrderConfirmation(
        ?string $invoiceNo,
        ?array $buyer = null,
        ?ExportDocument $selected = null,
    ): ?array {
        $invoice = $this->clean($invoiceNo);

        if ($selected?->order_confirmation_id) {
            $selected->loadMissing('orderConfirmation.buyer:id,company_name');
            $oc = $selected->orderConfirmation;

            if ($oc) {
                $buyerOk = ! $buyer || (int) $oc->buyer_id === (int) $buyer['id'];
                $invoiceOk = ! $invoice || $this->invoiceTouchesOcOrExport($invoice, $oc, $selected);

                if ($buyerOk && ($invoiceOk || blank($invoice))) {
                    return [
                        'id'         => (int) $oc->id,
                        'oc_num'     => (string) $oc->oc_num,
                        'buyer_ref'  => $oc->buyer_ref,
                        'buyer_name' => $oc->buyer?->company_name,
                        'score'      => $invoiceOk && $invoice ? 100 : 90,
                    ];
                }
            }
        }

        if ($invoice) {
            $byExportInvoice = ExportDocument::query()
                ->with(['orderConfirmation.buyer:id,company_name', 'buyer:id,company_name'])
                ->whereNotNull('order_confirmation_id')
                ->where(function ($q) use ($invoice) {
                    $q->where('invoice_no', $invoice)
                        ->orWhere('buyer_ref_no', $invoice)
                        ->orWhere('invoice_no', 'like', '%'.$invoice.'%')
                        ->orWhere('buyer_ref_no', 'like', '%'.$invoice.'%');
                })
                ->when($buyer, fn ($q) => $q->where('buyer_id', $buyer['id']))
                ->orderByDesc('id')
                ->first();

            if ($byExportInvoice?->orderConfirmation) {
                $oc = $byExportInvoice->orderConfirmation;

                return [
                    'id'         => (int) $oc->id,
                    'oc_num'     => (string) $oc->oc_num,
                    'buyer_ref'  => $oc->buyer_ref,
                    'buyer_name' => $oc->buyer?->company_name,
                    'score'      => 96,
                ];
            }

            $byOc = OrderConfirmation::query()
                ->with('buyer:id,company_name')
                ->when($buyer, fn ($q) => $q->where('buyer_id', $buyer['id']))
                ->where(function ($q) use ($invoice) {
                    $q->where('buyer_ref', $invoice)
                        ->orWhere('oc_num', $invoice)
                        ->orWhere('buyer_ref', 'like', '%'.$invoice.'%')
                        ->orWhere('oc_num', 'like', '%'.$invoice.'%');
                })
                ->orderByDesc('id')
                ->first();

            if ($byOc) {
                return [
                    'id'         => (int) $byOc->id,
                    'oc_num'     => (string) $byOc->oc_num,
                    'buyer_ref'  => $byOc->buyer_ref,
                    'buyer_name' => $byOc->buyer?->company_name,
                    'score'      => 92,
                ];
            }
        }

        if ($buyer) {
            $latest = OrderConfirmation::query()
                ->with('buyer:id,company_name')
                ->where('buyer_id', $buyer['id'])
                ->whereIn('status', ['confirmed', 'sent'])
                ->orderByDesc('id')
                ->first()
                ?? OrderConfirmation::query()
                    ->with('buyer:id,company_name')
                    ->where('buyer_id', $buyer['id'])
                    ->orderByDesc('id')
                    ->first();

            if ($latest) {
                return [
                    'id'         => (int) $latest->id,
                    'oc_num'     => (string) $latest->oc_num,
                    'buyer_ref'  => $latest->buyer_ref,
                    'buyer_name' => $latest->buyer?->company_name,
                    'score'      => 80,
                ];
            }
        }

        return null;
    }

    /**
     * @param  array{id:int}|null  $buyer
     * @param  array{id:int}|null  $orderConfirmation
     * @return list<array{id:int,doc_num:string,buyer_name:?string,oc_num:?string}>
     */
    private function suggestExportDocuments(?array $buyer, ?array $orderConfirmation): array
    {
        $query = ExportDocument::query()
            ->with(['buyer:id,company_name', 'orderConfirmation:id,oc_num'])
            ->orderByDesc('id')
            ->limit(8);

        if ($orderConfirmation) {
            $query->where('order_confirmation_id', $orderConfirmation['id']);
        } elseif ($buyer) {
            $query->where('buyer_id', $buyer['id']);
        } else {
            return [];
        }

        return $query->get()->map(fn (ExportDocument $doc) => [
            'id'         => $doc->id,
            'doc_num'    => $doc->doc_num,
            'buyer_name' => $doc->buyer?->company_name,
            'oc_num'     => $doc->orderConfirmation?->oc_num,
        ])->all();
    }

    private function invoiceTouchesOcOrExport(string $invoice, OrderConfirmation $oc, ExportDocument $document): bool
    {
        $needle = $this->normalize($invoice);
        $haystacks = [
            $this->normalize($document->invoice_no),
            $this->normalize($document->buyer_ref_no),
            $this->normalize($oc->buyer_ref),
            $this->normalize($oc->oc_num),
        ];

        foreach ($haystacks as $hay) {
            if ($hay !== '' && ($hay === $needle || str_contains($hay, $needle) || str_contains($needle, $hay))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, Buyer>|Collection<int, Supplier>  $candidates
     * @param  callable(Buyer|Supplier): list<string>  $labels
     * @return array{id:int,display_code:?string,company_name:string,score:int}|null
     */
    private function bestMatch(string $needle, Collection $candidates, callable $labels): ?array
    {
        $best = null;
        $bestScore = 0;

        foreach ($candidates as $row) {
            foreach ($labels($row) as $label) {
                $score = $this->score($needle, $this->normalize($label));
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $best = [
                        'id'           => (int) $row->id,
                        'display_code' => $row->display_code,
                        'company_name' => $row->company_name,
                        'score'        => $score,
                    ];
                }
            }
        }

        return ($best && $bestScore >= self::MIN_SCORE) ? $best : null;
    }

    private function score(string $needle, string $haystack): int
    {
        if ($haystack === '') {
            return 0;
        }

        if ($needle === $haystack) {
            return 100;
        }

        if (str_contains($haystack, $needle) || str_contains($needle, $haystack)) {
            $shorter = min(strlen($needle), strlen($haystack));
            $longer = max(strlen($needle), strlen($haystack));

            return (int) max(85, round(($shorter / $longer) * 100));
        }

        similar_text($needle, $haystack, $percent);

        return (int) round($percent);
    }

    private function normalize(?string $value): string
    {
        $value = strtolower(trim((string) $value));
        $value = preg_replace('/[^a-z0-9\s]/', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
