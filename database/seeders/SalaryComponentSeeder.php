<?php

namespace Database\Seeders;

use App\Models\SalaryComponent;
use Illuminate\Database\Seeder;

class SalaryComponentSeeder extends Seeder
{
    public function run(): void
    {
        // [name, type, calc, default]. Rates are placeholders: confirm them with your accountant.
        $rows = [
            ['Dearness Allowance', 'earning', 'fixed', 0],
            ['Transport Allowance', 'earning', 'fixed', 0],
            ['Class Teacher Allowance', 'earning', 'fixed', 0],
            ['Overtime / Bonus', 'earning', 'fixed', 0],
            ['Provident Fund', 'deduction', 'percent', 10],
            ['Social Security Tax', 'deduction', 'percent', 1],
            ['Income Tax', 'deduction', 'fixed', 0],
            ['Advance Recovery', 'deduction', 'fixed', 0],
        ];

        foreach ($rows as [$name, $type, $calc, $default]) {
            SalaryComponent::firstOrCreate(['name' => $name], ['type' => $type, 'calc' => $calc, 'default_value' => $default]);
        }
    }
}
