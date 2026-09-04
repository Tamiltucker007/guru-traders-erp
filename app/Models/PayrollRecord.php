<?php

namespace App\Models;

use App\Models\Concerns\Filterable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollRecord extends Model
{
    use Filterable, HasFactory;

    protected $fillable = [
        'period',
        'user_id',
        'employment_type',
        'joining_date',
        'monthly_salary',
        'gross_salary',
        'working_days',
        'days_present',
        'normal_lop_days',
        'double_lop_days',
        'total_deductions',
        'overtime_hours',
        'overtime_amount',
        'net_pay',
        'status',
        'processed_by',
    ];

    protected $casts = [
        'joining_date'     => 'date',
        'monthly_salary'   => 'decimal:2',
        'gross_salary'     => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'overtime_hours'   => 'decimal:2',
        'overtime_amount'   => 'decimal:2',
        'net_pay'          => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
