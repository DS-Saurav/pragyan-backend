<?php

namespace Database\Seeders;

use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Models\UserRole;
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
            'EL1' => ['A', 'B'],
            'EL2' => ['A', 'B'],
            'EL3' => ['A', 'B'],
            'Class 1' => ['A', 'B', 'C'],
            'Class 2' => ['A', 'B'],
            'Class 3' => ['A', 'B'],
            'Class 4' => ['A', 'B'],
            'Class 5' => ['A', 'B'],
            'Class 6' => ['A', 'B'],
            'Class 7' => ['A'],
            'Class 8' => ['A'],
            'Class 9' => ['A'],
            'Class 10' => ['A'],
        ];

        $createdClasses = [];

        foreach ($structure as $className => $sections) {
            foreach ($sections as $section) {
                $createdClasses["{$className}-{$section}"] = SchoolClass::firstOrCreate([
                    'name' => $className,
                    'section' => $section,
                ]);
            }
        }

        // 2. Controlled administrator account (intentionally privileged).
        $this->createUser('School Admin', 'admin@pragyan.edu.np', '9800000000', 'admin');

        // 3. Demo staff and student login accounts (no privileged roles).
        $this->createUser('Computer Teacher', 'teacher@pragyan.edu.np', '9800000001', 'teacher');
        $this->createUser('Saurav Student', 'student@pragyan.edu.np', '9800000002', 'student');

        // 4. Demo student profile in Class 10-A.
        Student::firstOrCreate(
            ['adm_number' => 'ADM-1001'],
            [
                'first_name' => 'Saurav',
                'last_name' => 'Student',
                'gender' => 'male',
                'dob' => '2010-05-15',
                'class_applied' => 'Class 10 A',
                'school_class_id' => $createdClasses['Class 10-A']->id,
                'roll_no' => 1001,
                'status' => 'active',
            ]
        );

        // 5. Reference data for fees and payroll (idempotent).
        $this->call([
            FeeHeadSeeder::class,
            SalaryComponentSeeder::class,
        ]);
    }

    /**
     * Create a demo login and assign its canonical role in user_roles.
     * Both the user lookup and the role assignment are idempotent.
     */
    private function createUser(string $name, string $email, string $phone, string $role): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('password123'),
                'phone' => $phone,
                'status' => 'active',
                'email_verified_at' => now(),
            ]
        );

        // `role` and `address` exist on the users table but are not mass-assignable.
        $user->forceFill(['role' => $role])->saveQuietly();

        UserRole::firstOrCreate(['user_id' => $user->id, 'role' => $role]);

        return $user;
    }
}
