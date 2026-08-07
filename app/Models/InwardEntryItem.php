<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InwardEntryItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'inward_entry_id',
        'purchase_order_item_id',
        'product_id',
        'sort_order',
        'description',
        'unit',
        'ordered_qty',
        'received_qty',
        'passed_qty',
        'rejected_qty',
        'remarks',
        'qc_remarks',
    ];

    protected function casts(): array
    {
        return [
            'ordered_qty'  => 'integer',
            'received_qty' => 'integer',
            'passed_qty'   => 'integer',
            'rejected_qty' => 'integer',
            'sort_order'   => 'integer',
        ];
    }

    public function inwardEntry(): BelongsTo
    {
        return $this->belongsTo(InwardEntry::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
