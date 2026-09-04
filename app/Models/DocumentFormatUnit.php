<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One unit an order format offers — PCS, SET, MTR, KGS, PAIR, DOZ.
 *
 * Not a lookup table with a screen of its own: the client cancelled the Unit
 * Master ("I don't see a need for the unit master"), and the prototype edits
 * these as chips on the format itself.
 */
class DocumentFormatUnit extends Model
{
    public $timestamps = false;

    protected $fillable = ['name', 'sort_order'];

    /**
     * What a new format starts with, matching the chips the prototype shows.
     *
     * @var array<int, string>
     */
    public const DEFAULTS = ['PCS', 'SET', 'MTR', 'SQ. MTR', 'KGS', 'PAIR', 'DOZ'];

    protected function casts(): array
    {
        return ['sort_order' => 'integer'];
    }

    public function format(): BelongsTo
    {
        return $this->belongsTo(DocumentFormat::class, 'document_format_id');
    }
}
