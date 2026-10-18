<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\SchoolClass;
use App\Models\Student;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Define exact class and section layout for Pragyan Pathashala
        $structure = [
            'EL1'      => ['A', 'B'],
            'EL2'      => ['A', 'B'],
            'EL3'      => ['A', 'B'],
            'Class 1'  => ['A', 'B', 'C'],
            'Class 2'  => ['A', 'B'],
            'Class 3'  => ['A', 'B'],
            'Class 4'  => ['A', 'B'],
            'Class 5'  => ['A', 'B'],
            'Class 6'  => ['A', 'B'],
            'Class 7'  => ['A'],
            'Class 8'  => ['A'],
            'Class 9'  => ['A'],
            'Class 10' => ['A'],
        ];

        $createdClasses = [];

        foreach ($structure as $className => $sections) {
            foreach ($sections as $section) {
                $classObj = SchoolClass::create([
                    'name'    => $className,
                    'section' => $section,
                ]);
                $createdClasses["{$className}-{$section}"] = $classObj;
            }
        }

        // 2. Create Admin Account
        User::create([
            'name'     => 'School Admin',
            'email'    => 'admin@pragyan.edu.np',
            'password' => Hash::make('password123'),
            'role'     => 'admin',
            'phone'    => '9800000000',
            'address'  => 'Surkhet, Nepal',
        ]);

        // 3. Create Teacher Account
        User::create([
            'name'     => 'Computer Teacher',
            'email'    => 'teacher@pragyan.edu.np',
            'password' => Hash::make('password123'),
            'role'     => 'teacher',
            'phone'    => '9800000001',
            'address'  => 'Surkhet, Nepal',
        ]);

        // 4. Create Student Account & Linked Student Profile in Class 10-A
        $studentUser = User::create([
            'name'     => 'Saurav Student',
            'email'    => 'student@pragyan.edu.np',
            'password' => Hash::make('password123'),
            'role'     => 'student',
            'phone'    => '9800000002',
            'address'  => 'Birendranagar, Surkhet',
        ]);

        Student::create([
            'user_id'         => $studentUser->id,
            'school_class_id' => $createdClasses['Class 10-A']->id,
            'roll_number'     => '1001',
            'guardian_name'   => 'Ram Acharya',
            'guardian_phone'  => '9800000003',
            'gender'          => 'male',
        ]);
    }
}