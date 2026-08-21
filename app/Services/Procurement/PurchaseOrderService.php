<?php

namespace App\Services\Procurement;

use App\Models\NumberSeries;
use App\Models\PurchaseOrder;
use App\Services\NumberSeriesService;
use App\Support\FinancialYear;
use Illuminate\Support\Facades\DB;

class PurchaseOrderService
{
    public function __construct(private readonly NumberSeriesService $numbers)
    {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {
            // `po_num` is not fillable — assign like OC-raised POs (GT/PO/{seq}/{FY}).
            $po = new PurchaseOrder($this->headerData($data));
            $this->assignPoNumber($po);
            $po->save();

            $this->syncItems($po, $data['items'] ?? []);
            $this->syncTimeline($po, $data['timeline'] ?? []);

            return $po->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(PurchaseOrder $po, array $data): PurchaseOrder
    {
        return DB::transaction(function () use ($po, $data) {
            $po->update($this->headerData($data));

            $this->syncItems($po, $data['items'] ?? []);
            $this->syncTimeline($po, $data['timeline'] ?? []);

            return $po->refresh();
        });
    }

    /**
     * `GT/PO/{seq}/{FY}` — same layout OrderConfirmationService uses when
     * raising POs from an OC.
     */
    private function assignPoNumber(PurchaseOrder $po): void
    {
        $financialYear = FinancialYear::current();

        NumberSeries::firstOrCreate(
            ['module' => 'po', 'financial_year' => $financialYear],
            ['prefix' => '', 'padding' => 3, 'current_number' => 0, 'reset_yearly' => true]
        );

        $number = $this->numbers->nextNumber('po', $financialYear);

        $po->po_num = "GT/PO/{$number}/{$financialYear}";
        $po->financial_year = $financialYear;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function headerData(array $data): array
    {
        return array_diff_key($data, array_flip(['items', 'timeline']));
    }

    /**
     * Delete-then-recreate, same call every other item grid in this app
     * makes. `order_confirmation_item_id` — the traceability link back to
     * the OC line this was raised from — is carried through as a hidden
     * field on the form rather than typed, so re-saving an OC-raised PO
     * doesn't sever the link the same way it was never allowed to be edited
     * away in the first place.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(PurchaseOrder $po, array $items): void
    {
        $po->items()->delete();

        foreach (array_values($items) as $index => $itemData) {
            $item = $po->items()->create([
                'order_confirmation_item_id' => $itemData['order_confirmation_item_id'] ?? null,
                'sort_order'                 => $index,
                'design_no'                   => $itemData['design_no'] ?? null,
                'description'                 => $itemData['description'] ?? null,
                'product_id'                  => $itemData['product_id'] ?? null,
                'unit'                        => $itemData['unit'] ?? null,
                'cost_price'                  => $itemData['cost_price'] ?? null,
                'remarks'                     => $itemData['remarks'] ?? null,
                'custom_values'               => array_filter((array) ($itemData['custom'] ?? []), fn ($v) => filled($v)) ?: null,
            ]);

            $qty = 0;
            $colours = $itemData['colours'] ?? [['colour' => null, 'sizes' => []]];

            foreach (array_values($colours) as $colourIndex => $colourData) {
                $colour = $item->colours()->create([
                    'colour'     => $colourData['colour'] ?? null,
                    'sort_order' => $colourIndex,
                ]);

                foreach (array_values($colourData['sizes'] ?? []) as $sizeIndex => $sizeData) {
                    $sizeQty = (int) ($sizeData['qty'] ?? 0);

                    if (blank($sizeData['size'] ?? null) && $sizeQty === 0) {
                        continue;
                    }

                    $colour->sizes()->create([
                        'size'       => $sizeData['size'] ?? '',
                        'qty'        => $sizeQty,
                        'sort_order' => $sizeIndex,
                    ]);

                    $qty += $sizeQty;
                }
            }

            $item->update([
                'qty'    => $qty,
                'amount' => round($qty * (float) ($itemData['cost_price'] ?? 0), 2),
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $timeline
     */
    private function syncTimeline(PurchaseOrder $po, array $timeline): void
    {
        $po->timelineEntries()->delete();

        foreach (array_values($timeline) as $index => $row) {
            if (blank($row['date'] ?? null) && blank($row['note'] ?? null)) {
                continue;
            }

            $po->timelineEntries()->create([
                'entry_date' => $row['date'] ?? now()->toDateString(),
                'note'       => $row['note'] ?? '',
                'qty'        => filled($row['qty'] ?? null) ? (int) $row['qty'] : null,
                'sort_order' => $index,
            ]);
        }
    }
}
