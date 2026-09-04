<?php

namespace App\Models;

use App\Models\Concerns\Filterable;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillingInvoice extends Model
{
    use Filterable, HasAuditColumns, HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_num',
        'buyer_id',
        'export_document_id',
        'invoice_date',
        'due_date',
        'subtotal',
        'tax_amount',
        'tds_percentage',
        'tds_amount',
        'total_amount',
        'paid_amount',
        'balance_amount',
        'status',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'invoice_date'   => 'date',
        'due_date'       => 'date',
        'subtotal'       => 'decimal:2',
        'tax_amount'     => 'decimal:2',
        'tds_percentage' => 'decimal:2',
        'tds_amount'     => 'decimal:2',
        'total_amount'   => 'decimal:2',
        'paid_amount'    => 'decimal:2',
        'balance_amount' => 'decimal:2',
    ];

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class);
    }

    public function exportDocument(): BelongsTo
    {
        return $this->belongsTo(ExportDocument::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillingInvoiceItem::class, 'billing_invoice_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recalculateTotals(): void
    {
        if ($this->items()->exists()) {
            $this->subtotal = $this->items()->sum('amount');
            $this->total_amount = $this->subtotal + $this->tax_amount;
        }
        
        $this->tds_amount = round(($this->subtotal * $this->tds_percentage) / 100, 2);
        $netPayable = max(0, $this->total_amount - $this->tds_amount);
        $this->balance_amount = max(0, $netPayable - $this->paid_amount);

        if ($this->paid_amount >= $netPayable && ($netPayable > 0 || $this->paid_amount > 0)) {
            $this->status = 'paid';
        } elseif ($this->paid_amount > 0) {
            $this->status = 'partial';
        } else {
            $this->status = ($this->due_date && $this->due_date->isPast()) ? 'overdue' : 'unpaid';
        }

        $this->save();
    }
}
