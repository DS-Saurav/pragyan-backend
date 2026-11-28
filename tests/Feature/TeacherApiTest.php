<?php

namespace Tests\Feature;

use App\Models\Teacher;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TeacherApiTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create();
        UserRole::create([
            'user_id' => $user->id,
            'role' => 'admin',
        ]);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_teachers_table_matches_simplified_schema(): void
    {
        $columns = Schema::getColumnListing('teachers');

        $expected = [
            'id',
            'slug',
            'first_name',
            'middle_name',
            'last_name',
            'gender',
            'phone',
            'email',
            'address',
            'qualification',
            'specialization',
            'joining_date',
            'profile_image',
            'bio',
            'is_visible',
            'status',
            'created_at',
            'updated_at',
        ];

        foreach ($expected as $col) {
            $this->assertContains($col, $columns);
        }

        foreach (['department', 'class_teacher_of', 'username', 'assignments', 'user_id'] as $removed) {
            $this->assertNotContains($removed, $columns);
        }
    }

    public function test_admin_can_create_update_list_and_delete_teacher(): void
    {
        Storage::fake('r2');
        $this->actingAsAdmin();

        $create = $this->post('/api/admin/teachers', [
            'first_name' => 'Ramesh',
            'middle_name' => 'Kumar',
            'last_name' => 'Adhikari',
            'gender' => 'male',
            'phone' => '9841000000',
            'email' => 'ramesh@pragyan.edu.np',
            'address' => 'Kathmandu',
            'qualification' => 'M.Sc. Mathematics',
            'specialization' => 'Mathematics',
            'joining_date' => '2024-04-15',
            'profile_image' => UploadedFile::fake()->image('ramesh.jpg'),
            'bio' => 'Senior math teacher.',
            'is_visible' => true,
            'status' => 'active',
        ], ['Accept' => 'application/json']);

        $create->assertCreated()
            ->assertJsonPath('teacher.slug', 'ramesh-kumar-adhikari')
            ->assertJsonPath('teacher.fullName', 'Ramesh Kumar Adhikari')
            ->assertJsonPath('teacher.qualification', 'M.Sc. Mathematics')
            ->assertJsonPath('teacher.isVisible', true);

        $storedPath = Teacher::firstOrFail()->profile_image;
        $this->assertNotNull($storedPath);
        Storage::disk('r2')->assertExists($storedPath);

        $id = $create->json('teacher.id');

        $this->getJson('/api/admin/teachers')
            ->assertOk()
            ->assertJsonCount(1, 'teachers')
            ->assertJsonPath('counts.active', 1)
            ->assertJsonPath('counts.visible', 1);

        $this->putJson("/api/admin/teachers/{$id}", [
            'first_name' => 'Ramesh',
            'middle_name' => null,
            'last_name' => 'Adhikari',
            'is_visible' => false,
            'status' => 'inactive',
        ])
            ->assertOk()
            ->assertJsonPath('teacher.fullName', 'Ramesh Adhikari')
            ->assertJsonPath('teacher.isVisible', false)
            ->assertJsonPath('teacher.status', 'inactive');

        $this->deleteJson("/api/admin/teachers/{$id}")
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->assertSame(0, Teacher::count());
    }

    public function test_public_teachers_endpoint_only_returns_visible_active_teachers(): void
    {
        Teacher::create([
            'slug' => 'sita-sharma',
            'first_name' => 'Sita',
            'last_name' => 'Sharma',
            'qualification' => 'M.Ed.',
            'specialization' => 'Science',
            'is_visible' => true,
            'status' => 'active',
        ]);

        Teacher::create([
            'slug' => 'hidden-teacher',
            'first_name' => 'Hidden',
            'last_name' => 'Teacher',
            'specialization' => 'Mathematics',
            'is_visible' => false,
            'status' => 'active',
        ]);

        Teacher::create([
            'slug' => 'inactive-teacher',
            'first_name' => 'Inactive',
            'last_name' => 'Teacher',
            'specialization' => 'English',
            'is_visible' => true,
            'status' => 'inactive',
        ]);

        $this->getJson('/api/public/teachers')
            ->assertOk()
            ->assertJsonCount(1, 'teachers')
            ->assertJsonPath('teachers.0.slug', 'sita-sharma')
            ->assertJsonPath('subjects', ['Science']);

        $this->getJson('/api/public/teachers/sita-sharma')
            ->assertOk()
            ->assertJsonPath('teacher.fullName', 'Sita Sharma');

        $this->getJson('/api/public/teachers/hidden-teacher')->assertNotFound();
        $this->getJson('/api/public/teachers/inactive-teacher')->assertNotFound();
    }
}
