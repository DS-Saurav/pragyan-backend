<?php

namespace Database\Seeders;

use App\Models\FeeHead;
use Illuminate\Database\Seeder;

class FeeHeadSeeder extends Seeder
{
    public function run(): void
    {
        $heads = [
            ['Admission Fee', 'one_time'],
            ['Tuition Fee', 'monthly'],
            ['Exam Fee', 'termly'],
            ['Transport Fee', 'monthly'],
            ['Computer Lab Fee', 'monthly'],
            ['Library Fee', 'yearly'],
            ['Annual / Development Fee', 'yearly'],
            ['Uniform / Books', 'one_time'],
            ['Other', 'one_time'],
        ];

        foreach ($heads as [$name, $frequency]) {
            FeeHead::firstOrCreate(['name' => $name], ['frequency' => $frequency]);
        }
    }
}
