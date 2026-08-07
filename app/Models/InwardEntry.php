<?php

namespace App\Models;

use App\Models\Concerns\Filterable;
use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InwardEntry extends Model
{
    use Filterable, HasAuditColumns, SoftDeletes;

    public const STATUSES = [
        'pending'  => 'Pending Inspection',
        'approved' => 'Approved (QC Passed)',
        'rejected' => 'Rejected',
    ];

    public const STATUS_COLORS = [
        'pending'  => 'warning',
        'approved' => 'success',
        'rejected' => 'danger',
    ];

    protected $fillable = [
        'financial_year',
        'inward_date',
        'purchase_order_id',
        'supplier_id',
        'challan_no',
        'challan_date',
        'remarks',
        'status',
        'qc_inspected_at',
        'qc_inspected_by',
    ];

    protected function casts(): array
    {
        return [
            'inward_date'     => 'date',
            'challan_date'    => 'date',
            'qc_inspected_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InwardEntryItem::class)->orderBy('sort_order');
    }

    public function qcInspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'qc_inspected_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Derived
    |--------------------------------------------------------------------------
    */

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'secondary';
    }

    public function totalReceivedQty(): int
    {
        return (int) $this->items->sum('received_qty');
    }

    public function totalPassedQty(): int
    {
        return (int) $this->items->sum('passed_qty');
    }

    public function totalRejectedQty(): int
    {
        return (int) $this->items->sum('rejected_qty');
    }

    /*
    |--------------------------------------------------------------------------
    | Filtering
    |--------------------------------------------------------------------------
    */

    public function searchable(): array
    {
        return ['inward_no', 'challan_no', 'remarks'];
    }

    public function sortable(): array
    {
        return ['id', 'inward_no', 'inward_date', 'status', 'created_at'];
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            foreach ($this->searchable() as $column) {
                $q->orWhere($column, 'like', "%{$term}%");
            }

            $q->orWhereHas('supplier', fn (Builder $s) => $s
                ->where('company_name', 'like', "%{$term}%")
                ->orWhere('display_code', 'like', "%{$term}%"));

            $q->orWhereHas('purchaseOrder', fn (Builder $p) => $p
                ->where('po_num', 'like', "%{$term}%"));
        });
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        if (blank($status) || ! array_key_exists($status, self::STATUSES)) {
            return $query;
        }

        return $query->where('status', $status);
    }
}
