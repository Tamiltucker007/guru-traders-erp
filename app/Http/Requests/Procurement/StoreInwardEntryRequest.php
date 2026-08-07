<?php

namespace App\Http\Requests\Procurement;

use App\Models\InwardEntryItem;
use App\Models\PurchaseOrderItem;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreInwardEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inward-entry.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'purchase_order_id'            => ['required', 'integer', 'exists:purchase_orders,id'],
            'inward_date'                  => ['required', 'date'],
            'challan_no'                   => ['nullable', 'string', 'max:100'],
            'challan_date'                 => ['nullable', 'date'],
            'remarks'                      => ['nullable', 'string', 'max:1000'],
            'items'                        => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'integer', 'exists:purchase_order_items,id'],
            'items.*.received_qty'          => ['required', 'integer', 'min:0'],
            'items.*.remarks'               => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ($this->input('items', []) as $index => $itemData) {
                $poItem = isset($itemData['purchase_order_item_id'])
                    ? PurchaseOrderItem::find($itemData['purchase_order_item_id'])
                    : null;

                if (! $poItem) {
                    continue;
                }

                $alreadyReceived = (int) InwardEntryItem::where('purchase_order_item_id', $poItem->id)->sum('received_qty');
                $balance = max(0, (int) $poItem->qty - $alreadyReceived);
                $received = (int) ($itemData['received_qty'] ?? 0);

                if ($received > $balance) {
                    $validator->errors()->add(
                        "items.{$index}.received_qty",
                        "Received quantity exceeds the remaining PO balance ({$balance} pcs)."
                    );
                }
            }
        });
    }
}
