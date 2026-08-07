<?php

namespace App\Services\Procurement;

use App\Models\InwardEntry;
use App\Models\InwardEntryItem;
use App\Models\NumberSeries;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderTimelineEntry;
use App\Services\NumberSeriesService;
use App\Support\FinancialYear;
use Illuminate\Support\Facades\DB;

class InwardEntryService
{
    public function __construct(private readonly NumberSeriesService $numbers)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): InwardEntry
    {
        return DB::transaction(function () use ($data) {
            $po = PurchaseOrder::findOrFail($data['purchase_order_id']);

            $financialYear = $data['financial_year'] ?? $po->financial_year ?? FinancialYear::current();

            $this->ensureNumberSeries($financialYear);
            $seq = $this->numbers->nextNumber('inward-entry', $financialYear);

            $inwardNo = "GT/INW/{$seq}/{$financialYear}";

            $inward = new InwardEntry([
                'financial_year'    => $financialYear,
                'inward_date'       => $data['inward_date'] ?? now()->toDateString(),
                'purchase_order_id' => $po->id,
                'supplier_id'       => $po->supplier_id,
                'challan_no'        => $data['challan_no'] ?? null,
                'challan_date'       => $data['challan_date'] ?? null,
                'remarks'           => $data['remarks'] ?? null,
                'status'            => 'pending',
            ]);
            $inward->inward_no = $inwardNo;
            $inward->save();

            $this->syncItems($inward, $data['items'] ?? []);
            $this->updatePurchaseOrderStatusAndTimeline($po, $inward);

            return $inward;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(InwardEntry $inward, array $data): InwardEntry
    {
        return DB::transaction(function () use ($inward, $data) {
            $inward->update([
                'inward_date'  => $data['inward_date'] ?? $inward->inward_date,
                'challan_no'   => $data['challan_no'] ?? null,
                'challan_date' => $data['challan_date'] ?? null,
                'remarks'      => $data['remarks'] ?? null,
            ]);

            $this->syncItems($inward, $data['items'] ?? []);

            $po = $inward->purchaseOrder;
            if ($po) {
                $this->updatePurchaseOrderStatusAndTimeline($po, $inward);
            }

            return $inward->refresh();
        });
    }

    /**
     * Record Quality Control inspection approval/rejection.
     *
     * @param  array<string, mixed>  $qcData
     */
    public function approve(InwardEntry $inward, array $qcData, int $userId): InwardEntry
    {
        return DB::transaction(function () use ($inward, $qcData, $userId) {
            $status = $qcData['status'] ?? 'approved';

            $inward->update([
                'status'          => $status,
                'qc_inspected_at' => now(),
                'qc_inspected_by' => $userId,
                'remarks'         => $qcData['remarks'] ?? $inward->remarks,
            ]);

            if (! empty($qcData['items'])) {
                foreach ($qcData['items'] as $itemId => $itemData) {
                    $item = InwardEntryItem::where('inward_entry_id', $inward->id)
                        ->where('id', $itemId)
                        ->first();

                    if ($item) {
                        $item->update([
                            'passed_qty'   => (int) ($itemData['passed_qty'] ?? $item->received_qty),
                            'rejected_qty' => (int) ($itemData['rejected_qty'] ?? 0),
                            'qc_remarks'   => $itemData['qc_remarks'] ?? null,
                        ]);
                    }
                }
            }

            $po = $inward->purchaseOrder;
            if ($po) {
                $this->updatePurchaseOrderStatusAndTimeline($po, $inward);
            }

            return $inward->refresh();
        });
    }

    /**
     * Delete an inward entry and recalculate PO status.
     */
    public function delete(InwardEntry $inward): void
    {
        DB::transaction(function () use ($inward) {
            $po = $inward->purchaseOrder;
            $inwardNo = $inward->inward_no;

            $inward->delete();

            if ($po) {
                // Remove timeline entry for this inward
                PurchaseOrderTimelineEntry::where('purchase_order_id', $po->id)
                    ->where('note', 'like', "%{$inwardNo}%")
                    ->delete();

                $this->recalculatePoStatus($po);
            }
        });
    }

    /**
     * Sync line items for the inward entry.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(InwardEntry $inward, array $items): void
    {
        $inward->items()->delete();

        foreach (array_values($items) as $index => $itemData) {
            $poItem = isset($itemData['purchase_order_item_id'])
                ? PurchaseOrderItem::find($itemData['purchase_order_item_id'])
                : null;

            $receivedQty = (int) ($itemData['received_qty'] ?? 0);
            $passedQty   = isset($itemData['passed_qty']) ? (int) $itemData['passed_qty'] : $receivedQty;
            $rejectedQty = isset($itemData['rejected_qty']) ? (int) $itemData['rejected_qty'] : 0;

            $inward->items()->create([
                'purchase_order_item_id' => $itemData['purchase_order_item_id'] ?? null,
                'product_id'             => $itemData['product_id'] ?? $poItem?->product_id,
                'sort_order'             => $index,
                'description'            => $itemData['description'] ?? $poItem?->description,
                'unit'                   => $itemData['unit'] ?? $poItem?->unit,
                'ordered_qty'            => (int) ($itemData['ordered_qty'] ?? $poItem?->qty ?? 0),
                'received_qty'           => $receivedQty,
                'passed_qty'             => $passedQty,
                'rejected_qty'           => $rejectedQty,
                'remarks'                => $itemData['remarks'] ?? null,
                'qc_remarks'             => $itemData['qc_remarks'] ?? null,
            ]);
        }
    }

    /**
     * Recalculate PO status and create/update PO delivery timeline entry.
     */
    public function updatePurchaseOrderStatusAndTimeline(PurchaseOrder $po, InwardEntry $inward): void
    {
        $this->recalculatePoStatus($po);

        $challanText = $inward->challan_no ? " (Challan: {$inward->challan_no})" : '';
        $note = "Inward Entry {$inward->inward_no}: {$inward->totalReceivedQty()} pcs received{$challanText}";

        $po->timelineEntries()->updateOrCreate(
            [
                'note' => $note,
            ],
            [
                'entry_date' => $inward->inward_date ?? now()->toDateString(),
                'qty'        => $inward->totalReceivedQty(),
                'sort_order' => $po->timelineEntries()->count(),
            ]
        );
    }

    public function recalculatePoStatus(PurchaseOrder $po): void
    {
        $totalOrdered = (int) $po->items()->sum('qty');

        $totalReceived = (int) InwardEntryItem::query()
            ->whereHas('inwardEntry', function ($q) use ($po) {
                $q->where('purchase_order_id', $po->id);
            })
            ->sum('received_qty');

        if ($totalReceived >= $totalOrdered && $totalOrdered > 0) {
            $po->update(['status' => 'received']);
        } elseif ($totalReceived > 0) {
            $po->update(['status' => 'partial']);
        } elseif ($po->status !== 'draft') {
            $po->update(['status' => 'raised']);
        }
    }

    private function ensureNumberSeries(string $financialYear): void
    {
        NumberSeries::firstOrCreate(
            ['module' => 'inward-entry', 'financial_year' => $financialYear],
            ['prefix' => 'GT/INW/', 'padding' => 3, 'current_number' => 0, 'reset_yearly' => true]
        );
    }
}
