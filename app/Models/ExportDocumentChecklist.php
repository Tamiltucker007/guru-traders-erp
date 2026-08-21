<?php

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One checklist row's live status against one Export Document — a stub
 * ('pending') from the moment the Export Document is raised, filled in as
 * the document is generated or a scan is uploaded. See
 * ExportDocumentService::ensureChecklist().
 */
class ExportDocumentChecklist extends Model
{
    use HasAuditColumns;

    /**
     * @var array<string, string>
     */
    public const STATUSES = [
        'pending'   => 'Pending',
        'generated' => 'Generated',
        'uploaded'  => 'Uploaded',
        'received'  => 'Received',
        'cancelled' => 'Draft cancelled',
    ];

    /**
     * @var array<string, string>
     */
    public const STATUS_COLORS = [
        'pending'   => 'secondary',
        'generated' => 'info',
        'uploaded'  => 'success',
        'received'  => 'success',
        'cancelled' => 'warning',
    ];

    protected $fillable = [
        'export_document_id',
        'document_checklist_type_id',
        'variant_code',
        'status',
        'file_path',
        'original_name',
        'uploaded_at',
        'generated_at',
        'reference_no',
        'amount',
        'remarks',
        'matched_buyer_id',
        'matched_supplier_id',
        'matched_order_confirmation_id',
        'ocr_verification',
    ];

    protected function casts(): array
    {
        return [
            'uploaded_at'      => 'datetime',
            'generated_at'     => 'datetime',
            'amount'           => 'decimal:2',
            'ocr_verification' => 'array',
        ];
    }

    public function exportDocument(): BelongsTo
    {
        return $this->belongsTo(ExportDocument::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(DocumentChecklistType::class, 'document_checklist_type_id');
    }

    public function matchedBuyer(): BelongsTo
    {
        return $this->belongsTo(Buyer::class, 'matched_buyer_id');
    }

    public function matchedSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'matched_supplier_id');
    }

    public function matchedOrderConfirmation(): BelongsTo
    {
        return $this->belongsTo(OrderConfirmation::class, 'matched_order_confirmation_id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUS_COLORS[$this->status] ?? 'secondary';
    }

    /** The variant's display label, e.g. "For Customs" — null when the type has no variants. */
    public function variantLabel(): ?string
    {
        if (blank($this->variant_code)) {
            return null;
        }

        $labels = $this->relationLoaded('type') ? $this->type?->variant_labels : $this->type()->value('variant_labels');

        return collect($labels)->first(fn ($label) => str($label)->slug()->toString() === $this->variant_code) ?? $this->variant_code;
    }

    public function hasFile(): bool
    {
        return filled($this->file_path);
    }

    public function fileUrl(): ?string
    {
        if (! $this->hasFile()) {
            return null;
        }

        return route('export.documents.checklist.file', [$this->export_document_id, $this]);
    }

    /** B/L number stored in insurance remarks as "B/L: …". */
    public function insuranceBlNumber(): ?string
    {
        if (! preg_match('/B\/L:\s*([^·]+)/i', (string) $this->remarks, $m)) {
            return null;
        }

        return trim($m[1]) ?: null;
    }

    /** B/L date stored in insurance remarks as "B/L date: …". */
    public function insuranceBlDate(): ?string
    {
        if (! preg_match('/B\/L date:\s*([^·]+)/i', (string) $this->remarks, $m)) {
            return null;
        }

        return trim($m[1]) ?: null;
    }
}
