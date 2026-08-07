<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One dated line of the PO's delivery timeline. Rows come from two sources:
 * hand-entered via PurchaseOrderService::syncTimeline() (full delete/rebuild
 * on every PO save) and auto-appended by InwardEntryService on each goods
 * receipt (idempotent updateOrCreate keyed by note text). Saving the PO's own
 * timeline grid after a Goods Inward receipt will wipe the auto-appended rows
 * since syncTimeline() doesn't distinguish the two sources.
 */
class PurchaseOrderTimelineEntry extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'entry_date',
        'note',
        'qty',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'qty'        => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }
}
