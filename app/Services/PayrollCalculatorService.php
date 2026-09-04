<?php

namespace App\Services;

use App\Models\PayrollRecord;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PayrollCalculatorService
{
    /**
     * Calculate payroll metrics for a given employee and period.
     */
    public function calculateForUser(User $user, string $period, int $workingDays = 26, int $daysPresent = 26, int $normalLop = 0, int $doubleLop = 0, float $overtimeHours = 0): array
    {
        $monthlySalary = (float) $user->monthly_salary;
        $dailyRate = $workingDays > 0 ? ($monthlySalary / $workingDays) : 0;
        $hourlyRate = $dailyRate / 8.0;

        // LOP Math: 1x for Normal LOP, 2x for Unannounced LOP
        $totalLopUnits = ($normalLop * 1) + ($doubleLop * 2);
        $totalDeductions = round($totalLopUnits * $dailyRate, 2);

        $overtimeAmount = round($overtimeHours * $hourlyRate, 2);
        $grossSalary = $monthlySalary;
        $netPay = max(0, round($grossSalary - $totalDeductions + $overtimeAmount, 2));

        return [
            'period'           => $period,
            'user_id'          => $user->id,
            'employment_type'  => $user->employment_type ?? 'Full-Time',
            'joining_date'     => $user->joining_date,
            'monthly_salary'   => $monthlySalary,
            'gross_salary'     => $grossSalary,
            'working_days'     => $workingDays,
            'days_present'     => $daysPresent,
            'normal_lop_days'  => $normalLop,
            'double_lop_days'  => $doubleLop,
            'total_deductions' => $totalDeductions,
            'overtime_hours'   => $overtimeHours,
            'overtime_amount'   => $overtimeAmount,
            'net_pay'          => $netPay,
            'status'           => 'processed',
        ];
    }

    /**
     * Process payroll records for all active users for a given period (YYYY-MM).
     */
    public function processPeriod(string $period, ?int $processedById = null): Collection
    {
        $users = User::active()->get();
        $records = collect();

        foreach ($users as $user) {
            $data = $this->calculateForUser($user, $period);
            $data['processed_by'] = $processedById;

            $record = PayrollRecord::updateOrCreate(
                ['period' => $period, 'user_id' => $user->id],
                $data
            );

            $records->push($record);
        }

        return $records;
    }
}
