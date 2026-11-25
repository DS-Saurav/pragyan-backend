<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\Student;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AssetLibrarySeparationTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => $role]);

        return $user;
    }

    public function test_assets_page_no_longer_shows_library_books_tab(): void
    {
        $admin = $this->userWithRole('admin');

        $this->actingAs($admin)
            ->get('/admin/assets')
            ->assertOk()
            ->assertDontSee('Library Books')
            ->assertDontSee('Quick barcode return');
    }

    public function test_barcode_return_route_is_removed(): void
    {
        $this->assertFalse(Route::has('admin.assets.barcode-return'));
    }

    public function test_assets_index_does_not_default_to_library_category(): void
    {
        $admin = $this->userWithRole('admin');

        Asset::create([
            'asset_tag' => 'LIB-00001',
            'name' => 'Harry Potter',
            'category' => 'library',
            'location' => 'Shelf A',
            'quantity' => 1,
            'available_quantity' => 1,
            'status' => 'in_use',
        ]);

        Asset::create([
            'asset_tag' => 'ROB-00001',
            'name' => 'ESP32 NodeMCU',
            'category' => 'robotics',
            'location' => 'Lab 1',
            'quantity' => 1,
            'available_quantity' => 1,
            'status' => 'in_use',
        ]);

        $this->actingAs($admin)
            ->get('/admin/assets')
            ->assertOk()
            ->assertSee('ESP32 NodeMCU')
            ->assertDontSee('Harry Potter');
    }

    public function test_general_asset_issue_and_return_still_work(): void
    {
        $admin = $this->userWithRole('admin');

        $student = Student::create([
            'first_name' => 'Test',
            'last_name' => 'Student',
            'gender' => 'male',
            'dob' => '2015-01-01',
            'class_applied' => 'Grade 5',
            'adm_number' => 'ADM-TEST-1',
            'status' => 'active',
        ]);

        $asset = Asset::create([
            'asset_tag' => 'ROB-00002',
            'name' => 'Robotics Kit',
            'category' => 'robotics',
            'location' => 'Lab 2',
            'quantity' => 1,
            'available_quantity' => 1,
            'status' => 'in_use',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.assets.issue', $asset), [
                'student_id' => $student->id,
                'due_date' => now()->addDays(7)->format('Y-m-d'),
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('asset_loans', [
            'asset_id' => $asset->id,
            'student_id' => $student->id,
            'status' => 'issued',
        ]);

        $this->assertSame(0, $asset->fresh()->available_quantity);

        $loanId = $asset->loans()->first()->id;

        $this->actingAs($admin)
            ->post(route('admin.assets.return', $asset), ['loan_id' => $loanId])
            ->assertRedirect();

        $this->assertDatabaseHas('asset_loans', [
            'id' => $loanId,
            'status' => 'returned',
        ]);

        $this->assertSame(1, $asset->fresh()->available_quantity);
    }

    public function test_library_catalog_and_circulation_routes_remain_registered(): void
    {
        $this->assertTrue(Route::has('admin.library.books.index'));
        $this->assertTrue(Route::has('admin.library.books.store'));
        $this->assertTrue(Route::has('admin.library.circulation.index'));
        $this->assertTrue(Route::has('admin.library.circulation.issue'));
        $this->assertTrue(Route::has('admin.library.circulation.return'));
    }
}
