<?php

namespace App\Services\Export;

use App\Models\DocumentChecklistType;
use App\Models\ExportDocument;
use App\Models\ExportDocumentChecklist;
use App\Models\Incoterm;
use App\Models\NumberSeries;
use App\Models\OrderConfirmation;
use App\Models\Port;
use App\Models\ShipmentMethod;
use App\Services\NumberSeriesService;
use App\Support\FinancialYear;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ExportDocumentService
{
    public function __construct(private readonly NumberSeriesService $numbers)
    {
    }

    /**
     * "Raise Export Document for Selected" — the OC-side counterpart of
     * OrderConfirmationService::raisePurchaseOrders(), except every selected
     * item goes onto one Export Document (one buyer per shipment) rather
     * than being grouped and split.
     *
     * @param  array<int, int>  $itemIds
     */
    public function raiseFromOrderConfirmation(OrderConfirmation $oc, array $itemIds): ExportDocument
    {
        return DB::transaction(function () use ($oc, $itemIds) {
            $items = $oc->items()
                ->whereIn('id', $itemIds)
                ->whereNull('export_document_id')
                ->with('colours.sizes')
                ->get();

            if ($items->isEmpty()) {
                throw new RuntimeException('Nothing to raise — the selected items are already on an Export Document.');
            }

            $doc = new ExportDocument([
                'order_confirmation_id' => $oc->id,
                'buyer_id'              => $oc->buyer_id,
                'currency_id'           => $oc->currency_id,
                'incoterm_id'           => $this->matchIncoterm($oc->incoterm),
                'port_of_loading_id'    => $this->matchPort($oc->pol),
                'port_of_discharge_id'  => $this->matchPort($oc->pod),
                'shipment_method_id'    => $this->matchShipmentMethod($oc->ship_method),
                'status'                => 'draft',
            ]);
            $this->assignNumber($doc);
            $doc->save();

            foreach ($items->values() as $index => $ocItem) {
                $docItem = $doc->items()->create([
                    'order_confirmation_item_id' => $ocItem->id,
                    'sort_order'                 => $index,
                    'design_no'                   => $ocItem->design_no,
                    'description'                 => $ocItem->description,
                    'product_id'                  => $ocItem->product_id,
                    'unit'                        => $ocItem->unit,
                    'price'                       => $ocItem->price,
                    'qty'                         => $ocItem->qty,
                    'amount'                      => $ocItem->amount,
                    'remarks'                     => $ocItem->remarks,
                    'custom_values'               => $ocItem->custom_values,
                ]);

                foreach ($ocItem->colours as $colour) {
                    $docColour = $docItem->colours()->create([
                        'colour'     => $colour->colour,
                        'sort_order' => $colour->sort_order,
                    ]);

                    foreach ($colour->sizes as $size) {
                        $docColour->sizes()->create([
                            'size'       => $size->size,
                            'qty'        => $size->qty,
                            'sort_order' => $size->sort_order,
                        ]);
                    }
                }

                // Direct property assignment, not update() — same reasoning as
                // OrderConfirmationService::raisePurchaseOrders(): both columns
                // are absent from OrderConfirmationItem::$fillable so a
                // mass-assignment update() would silently drop them.
                $ocItem->export_document_id = $doc->id;
                $ocItem->shipped_at = now();
                $ocItem->save();
            }

            $this->ensureChecklist($doc);

            return $doc;
        });
    }

    /**
     * Creates one pending ExportDocumentChecklist stub per active
     * DocumentChecklistType (one per variant label, for types that have
     * them). Safe to call again later if a new checklist type is added after
     * the Export Document already exists — existing rows are left alone.
     */
    public function ensureChecklist(ExportDocument $doc): void
    {
        $types = DocumentChecklistType::query()->where('status', 'active')->orderBy('sort_order')->get();

        foreach ($types as $type) {
            $variants = $type->hasVariants() ? $type->variant_labels : [null];

            foreach ($variants as $label) {
                $variantCode = $label !== null ? str($label)->slug()->toString() : null;

                ExportDocumentChecklist::query()->firstOrCreate([
                    'export_document_id'         => $doc->id,
                    'document_checklist_type_id' => $type->id,
                    'variant_code'                => $variantCode,
                ], [
                    'status' => 'pending',
                ]);
            }
        }
    }

    /**
     * @param  array{
     *     file?: ?UploadedFile,
     *     mark_done?: bool,
     *     reference_no?: ?string,
     *     amount?: ?float,
     *     remarks?: ?string,
     *     matched_buyer_id?: ?int,
     *     matched_supplier_id?: ?int,
     *     matched_order_confirmation_id?: ?int,
     *     insurance_action?: ?string,
     *     bl_number?: ?string,
     *     bl_date?: ?string
     * }  $data
     */
    public function recordChecklist(ExportDocumentChecklist $entry, array $data): ExportDocumentChecklist
    {
        return DB::transaction(function () use ($entry, $data) {
            $type = $entry->type;
            $file = $data['file'] ?? null;

            if ($type->code === 'insurance' && ($data['insurance_action'] ?? null) === 'cancel_draft') {
                if ($entry->hasFile()) {
                    Storage::disk('public')->delete($entry->file_path);
                }

                $entry->file_path = null;
                $entry->original_name = null;
                $entry->uploaded_at = null;
                $entry->generated_at = now();
                $entry->status = 'cancelled';
                $entry->reference_no = $data['reference_no'] ?? $entry->reference_no;
                $entry->remarks = filled($data['remarks'] ?? null)
                    ? $data['remarks']
                    : 'Insurance draft cancelled';
                $entry->save();

                $this->syncShipmentStatus($entry->exportDocument, $type);

                return $entry->refresh();
            }

            if ($type->code === 'insurance' && ($data['insurance_action'] ?? null) === 'upload_certificate') {
                $blNumber = trim((string) ($data['bl_number'] ?? ''));
                $blDate = trim((string) ($data['bl_date'] ?? ''));

                if (! $file instanceof UploadedFile) {
                    throw new RuntimeException('Upload the insurance certificate for option 2.');
                }
                if ($blNumber === '' || $blDate === '') {
                    throw new RuntimeException('B/L number and B/L date are required to create the insurance certificate.');
                }

                $data['remarks'] = $this->composeInsuranceRemarks(
                    $data['remarks'] ?? null,
                    $blNumber,
                    $blDate
                );

                if (blank($data['reference_no'] ?? null)) {
                    $data['reference_no'] = $blNumber;
                }
            }

            if ($file instanceof UploadedFile) {
                if ($entry->hasFile()) {
                    Storage::disk('public')->delete($entry->file_path);
                }

                $entry->file_path = $file->store('export-documents', 'public');
                $entry->original_name = $file->getClientOriginalName();
                $entry->uploaded_at = now();
                $entry->status = $type->category === 'generated' ? 'generated' : 'uploaded';
            } elseif (filled($data['mark_done'] ?? null) && $entry->status === 'pending') {
                if ($type->category === 'generated') {
                    $entry->status = 'generated';
                    $entry->generated_at = now();
                } else {
                    $entry->status = $type->category === 'manual' ? 'received' : 'uploaded';
                }
            }

            if (array_key_exists('reference_no', $data)) {
                $entry->reference_no = $data['reference_no'];
            }
            if (array_key_exists('amount', $data)) {
                $entry->amount = $data['amount'];
            }
            if (array_key_exists('remarks', $data)) {
                $entry->remarks = $data['remarks'];
            }
            if (array_key_exists('matched_buyer_id', $data)) {
                $entry->matched_buyer_id = $data['matched_buyer_id'] ?: null;
            }
            if (array_key_exists('matched_supplier_id', $data)) {
                $entry->matched_supplier_id = $data['matched_supplier_id'] ?: null;
            }
            if (array_key_exists('matched_order_confirmation_id', $data)) {
                $entry->matched_order_confirmation_id = $data['matched_order_confirmation_id'] ?: null;
            }
            if (array_key_exists('ocr_verification', $data)) {
                $entry->ocr_verification = $data['ocr_verification'];
            }

            $entry->save();

            $this->syncShipmentStatus($entry->exportDocument, $type);

            return $entry->refresh();
        });
    }

    /**
     * Marks a checklist row 'generated' and attaches the freshly rendered
     * PDF as its file — used by the PDF-generating controller actions
     * (Delivery Challan, E-Invoice, Export Invoice) so "Generate" leaves a
     * real, re-downloadable file against the row exactly like "Upload" does.
     */
    public function attachGeneratedFile(ExportDocument $document, string $typeCode, ?string $variantCode, string $storedPath, string $originalName): ExportDocumentChecklist
    {
        $entry = $document->checklist()
            ->whereHas('type', fn ($q) => $q->where('code', $typeCode))
            ->where('variant_code', $variantCode)
            ->firstOrFail();

        if ($entry->hasFile()) {
            Storage::disk('public')->delete($entry->file_path);
        }

        $entry->update([
            'status'        => 'generated',
            'file_path'     => $storedPath,
            'original_name' => $originalName,
            'generated_at'  => now(),
        ]);

        $this->syncShipmentStatus($document, $entry->type);

        return $entry->refresh();
    }

    /**
     * Rewrite an Export Document's carton breakdown from the submitted rows —
     * feeds Packing List Formats B and C. Deleted and re-inserted rather than
     * diffed, same call as Supplier::syncContacts() and Buyer's carton
     * markings: nothing references a carton or carton-line row by id.
     *
     * A carton with no carton number, or a line with no description, is
     * dropped — a row the user left blank, not one they meant to record.
     *
     * @param  array<int, array{carton_no?: ?string, net_weight?: mixed, gross_weight?: mixed, dimensions?: ?string, lines?: array<int, array{description?: ?string, unit?: ?string, qty?: mixed}>}>  $rows
     */
    public function syncCartons(ExportDocument $document, array $rows): void
    {
        DB::transaction(function () use ($document, $rows) {
            $document->cartons()->delete();

            $sortOrder = 0;

            foreach ($rows as $row) {
                $cartonNo = trim((string) ($row['carton_no'] ?? ''));

                if ($cartonNo === '') {
                    continue;
                }

                $carton = $document->cartons()->create([
                    'carton_no'    => $cartonNo,
                    'net_weight'   => $row['net_weight'] ?? null,
                    'gross_weight' => $row['gross_weight'] ?? null,
                    'dimensions'   => $row['dimensions'] ?? null,
                    'sort_order'   => $sortOrder++,
                ]);

                $lineOrder = 0;

                foreach ($row['lines'] ?? [] as $line) {
                    $description = trim((string) ($line['description'] ?? ''));

                    if ($description === '') {
                        continue;
                    }

                    $carton->lines()->create([
                        'description' => $description,
                        'unit'        => $line['unit'] ?: 'PCS',
                        'qty'         => $line['qty'] ?? 0,
                        'sort_order'  => $lineOrder++,
                    ]);
                }
            }
        });
    }

    /** Undo — clears a checklist row back to pending and removes its file. */
    public function resetChecklist(ExportDocumentChecklist $entry): ExportDocumentChecklist
    {
        if ($entry->hasFile()) {
            Storage::disk('public')->delete($entry->file_path);
        }

        $entry->update([
            'status'               => 'pending',
            'file_path'            => null,
            'original_name'        => null,
            'uploaded_at'          => null,
            'generated_at'         => null,
            'reference_no'         => null,
            'remarks'              => null,
            'matched_buyer_id'     => null,
            'matched_supplier_id'  => null,
            'matched_order_confirmation_id' => null,
        ]);

        if ($entry->exportDocument->status === 'closed') {
            $entry->exportDocument->update(['status' => 'in_progress']);
        }

        return $entry;
    }

    private function composeInsuranceRemarks(?string $remarks, string $blNumber, string $blDate): string
    {
        $parts = [];
        $parts[] = 'B/L: '.$blNumber;
        $parts[] = 'B/L date: '.$blDate;

        $existing = trim((string) $remarks);
        if ($existing !== '') {
            // Drop prior B/L lines so re-saves do not stack duplicates.
            $existing = preg_replace('/B\/L(?: date)?:\s*[^·]+(?:\s*·\s*)?/i', '', $existing) ?? $existing;
            $existing = trim($existing, " \t·");
            if ($existing !== '') {
                $parts[] = $existing;
            }
        }

        return implode(' · ', $parts);
    }

    /**
     * Flips the Export Document to 'in_progress' on its first completed row,
     * and to 'closed' once the row flagged closes_shipment (eBRC) is done —
     * "that is when the file for that shipment is closed."
     */
    private function syncShipmentStatus(ExportDocument $doc, DocumentChecklistType $type): void
    {
        if ($type->closes_shipment) {
            $doc->update(['status' => 'closed']);

            return;
        }

        if ($doc->status === 'draft') {
            $doc->update(['status' => 'in_progress']);
        }
    }

    private function matchIncoterm(?string $code): ?int
    {
        if (blank($code)) {
            return null;
        }

        return Incoterm::query()->whereRaw('LOWER(code) = ?', [strtolower(trim($code))])->value('id');
    }

    private function matchPort(?string $name): ?int
    {
        if (blank($name)) {
            return null;
        }

        return Port::query()->whereRaw('LOWER(name) = ?', [strtolower(trim($name))])->value('id');
    }

    private function matchShipmentMethod(?string $name): ?int
    {
        if (blank($name)) {
            return null;
        }

        return ShipmentMethod::query()->whereRaw('LOWER(name) = ?', [strtolower(trim($name))])->value('id');
    }

    /** `GT/EXP/{seq}/{FY}` — same recipe as PO's GT/PO/{seq}/{FY}. */
    private function assignNumber(ExportDocument $doc): void
    {
        $financialYear = FinancialYear::current();

        NumberSeries::firstOrCreate(
            ['module' => 'export-document', 'financial_year' => $financialYear],
            ['prefix' => '', 'padding' => 3, 'current_number' => 0, 'reset_yearly' => true]
        );

        $number = $this->numbers->nextNumber('export-document', $financialYear);

        $doc->doc_num = "GT/EXP/{$number}/{$financialYear}";
        $doc->financial_year = $financialYear;
    }
}
