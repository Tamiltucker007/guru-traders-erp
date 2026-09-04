<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GstFiling extends Model
{
    use HasFactory;

    protected $fillable = [
        'period',
        'financial_year',
        'gstr1_status',
        'gstr1_filed_at',
        'gstr3b_status',
        'gstr3b_filed_at',
        'total_sales_tax',
        'total_purchase_tax',
        'net_gst_payable',
        'notes',
    ];

    protected $casts = [
        'gstr1_filed_at'     => 'date',
        'gstr3b_filed_at'    => 'date',
        'total_sales_tax'    => 'decimal:2',
        'total_purchase_tax' => 'decimal:2',
        'net_gst_payable'    => 'decimal:2',
    ];
}
