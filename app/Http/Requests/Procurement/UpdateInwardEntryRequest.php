<?php

namespace App\Http\Requests\Procurement;

use App\Models\InwardEntryItem;
use App\Models\PurchaseOrderItem;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInwardEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inward-entry.edit');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'inward_date'                  => ['required', 'date'],
            'challan_no'                   => ['nullable', 'string', 'max:100'],
            'challan_date'                 => ['nullable', 'date'],
            'remarks'                      => ['nullable', 'string', 'max:1000'],
            'items'                        => ['required', 'array', 'min:1'],
            'items.*.purchase_order_item_id' => ['required', 'integer', 'exists:purchase_order_items,id'],
            'items.*.received_qty'          => ['required', 'integer', 'min:0'],
            'items.*.passed_qty'            => ['nullable', 'integer', 'min:0'],
            'items.*.rejected_qty'          => ['nullable', 'integer', 'min:0'],
            'items.*.remarks'               => ['nullable', 'string', 'max:500'],
            'items.*.qc_remarks'            => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $currentInwardId = $this->route('inwardEntry')?->id;

            foreach ($this->input('items', []) as $index => $itemData) {
                $poItem = isset($itemData['purchase_order_item_id'])
                    ? PurchaseOrderItem::find($itemData['purchase_order_item_id'])
                    : null;

                $received = (int) ($itemData['received_qty'] ?? 0);
                $passed = (int) ($itemData['passed_qty'] ?? $received);
                $rejected = (int) ($itemData['rejected_qty'] ?? 0);

                if ($passed + $rejected > $received) {
                    $validator->errors()->add(
                        "items.{$index}.rejected_qty",
                        'Passed + rejected quantity cannot exceed the received quantity.'
                    );
                }

                if (! $poItem) {
                    continue;
                }

                $alreadyReceived = (int) InwardEntryItem::where('purchase_order_item_id', $poItem->id)
                    ->where('inward_entry_id', '!=', $currentInwardId)
                    ->sum('received_qty');
                $balance = max(0, (int) $poItem->qty - $alreadyReceived);

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
