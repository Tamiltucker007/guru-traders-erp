<?php

namespace App\Http\Requests\Procurement;

use App\Models\InwardEntryItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

class ApproveInwardEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inward-entry.approve');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status'                => ['required', 'in:approved,rejected'],
            'remarks'               => ['nullable', 'string', 'max:1000'],
            'items'                 => ['nullable', 'array'],
            'items.*.passed_qty'    => ['nullable', 'integer', 'min:0'],
            'items.*.rejected_qty'  => ['nullable', 'integer', 'min:0'],
            'items.*.qc_remarks'    => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ($this->input('items', []) as $itemId => $itemData) {
                $item = InwardEntryItem::find($itemId);

                if (! $item) {
                    continue;
                }

                $passed = (int) ($itemData['passed_qty'] ?? $item->received_qty);
                $rejected = (int) ($itemData['rejected_qty'] ?? 0);

                if ($passed + $rejected > $item->received_qty) {
                    $validator->errors()->add(
                        "items.{$itemId}.rejected_qty",
                        'Passed + rejected quantity cannot exceed the received quantity.'
                    );
                }
            }
        });
    }
}
