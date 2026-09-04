<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Buyer;
use App\Models\GstFiling;
use App\Models\User;

if (Buyer::count() === 0) {
    $buyer = new Buyer([
        'company_name' => 'Guru Test Buyer Ltd',
        'short_name'   => 'GTB',
        'contact_person' => 'Rajesh Kumar',
        'email'        => 'rajesh@gtb.com',
        'phone'        => '9876543210',
        'status'       => 'active',
    ]);
    $buyer->display_code = 'BUY01';
    $buyer->save();
    echo "Buyer created.\n";
}

if (GstFiling::count() === 0) {
    $months = ['2026-04','2026-05','2026-06','2026-07','2026-08','2026-09','2026-10','2026-11','2026-12','2027-01','2027-02','2027-03'];
    foreach ($months as $m) {
        GstFiling::create([
            'period'             => $m,
            'financial_year'     => '2026-27',
            'gstr1_status'       => 'pending',
            'gstr3b_status'      => 'pending',
            'total_sales_tax'    => 0,
            'total_purchase_tax' => 0,
            'net_gst_payable'    => 0,
        ]);
    }
    echo "GST filings created.\n";
}

// Make sure Super Admin monthly_salary is set for Payroll tests
$admin = User::where('email', 'admin@gurutraders.com')->first();
if ($admin) {
    $admin->update([
        'monthly_salary' => 50000.00,
        'employment_type' => 'Full-Time',
        'joining_date' => '2025-01-01',
    ]);
    echo "Admin salary updated.\n";
}

// Also create a non-admin employee for role testing
$staff = User::where('email', 'staff@gurutraders.com')->first();
if (!$staff) {
    $staff = User::create([
        'name' => 'Test Staff Member',
        'email' => 'staff@gurutraders.com',
        'password' => bcrypt('Staff@123'),
        'monthly_salary' => 30000.00,
        'employment_type' => 'Full-Time',
        'joining_date' => '2025-06-01',
        'is_active' => true,
    ]);
    echo "Staff user created.\n";
}

echo "Setup complete.\n";
