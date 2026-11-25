<?php

namespace Tests\Feature;

use App\Models\SchoolDocument;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Document module: file upload & replacement.
 *
 * Covers upload validation, replacement ordering (new file stored before the
 * old one is removed) and preservation of the existing file when validation
 * fails or no replacement is selected.
 */
class DocumentFileUploadTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        $user = User::factory()->create();
        UserRole::create(['user_id' => $user->id, 'role' => 'academic_coordinator']);

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Parent Handbook',
            'category' => 'general',
            'audience' => 'all',
            'is_published' => 1,
        ], $overrides);
    }

    private function storedDocument(string $oldPath): SchoolDocument
    {
        return SchoolDocument::create([
            'title' => 'Existing Handbook',
            'category' => 'general',
            'audience' => 'all',
            'is_published' => true,
            'uploaded_by' => $this->manager()->id,
            'version' => 1,
            'file_name' => 'old-handbook.pdf',
            'file_size' => 1234,
            'mime' => 'application/pdf',
            'file_path' => $oldPath,
        ]);
    }

    public function test_store_with_a_valid_file(): void
    {
        Storage::fake('local');
        $manager = $this->manager();

        $this->actingAs($manager)
            ->post(route('admin.documents.store'), $this->payload([
                'file' => UploadedFile::fake()->create('handbook.pdf', 120, 'application/pdf'),
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $document = SchoolDocument::firstWhere('title', 'Parent Handbook');
        $this->assertNotNull($document);
        Storage::disk('local')->assertExists($document->file_path);
        $this->assertSame(1, $document->version);
    }

    public function test_store_needs_a_file_or_a_link(): void
    {
        Storage::fake('local');
        $manager = $this->manager();

        $this->actingAs($manager)
            ->post(route('admin.documents.store'), $this->payload())
            ->assertRedirect()
            ->assertSessionHasErrors('file');

        $this->assertSame(0, SchoolDocument::count());
    }

    public function test_invalid_file_type_is_rejected(): void
    {
        Storage::fake('local');
        $manager = $this->manager();

        $this->actingAs($manager)
            ->post(route('admin.documents.store'), $this->payload([
                'file' => UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream'),
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('file');

        $this->assertSame(0, SchoolDocument::count());
    }

    public function test_oversized_file_is_rejected(): void
    {
        Storage::fake('local');
        $manager = $this->manager();

        $this->actingAs($manager)
            ->post(route('admin.documents.store'), $this->payload([
                'file' => UploadedFile::fake()->create('huge.pdf', 11000, 'application/pdf'), // max is 10240 KB
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('file');

        $this->assertSame(0, SchoolDocument::count());
    }

    public function test_update_without_a_new_file_preserves_the_existing_file(): void
    {
        Storage::fake('local');
        $oldPath = UploadedFile::fake()->create('old.pdf')->store('documents', 'local');
        $document = $this->storedDocument($oldPath);

        $this->actingAs($this->manager())
            ->put(route('admin.documents.update', $document), $this->payload(['title' => 'Renamed Handbook']))
            ->assertRedirect()
            ->assertSessionHas('success');

        $document->refresh();
        $this->assertSame($oldPath, $document->file_path, 'No replacement selected: keep the existing file.');
        $this->assertSame(1, $document->version, 'No replacement selected: keep the version.');
        Storage::disk('local')->assertExists($oldPath);
    }

    public function test_successful_replacement_stores_new_then_removes_old(): void
    {
        Storage::fake('local');
        $oldPath = UploadedFile::fake()->create('old.pdf')->store('documents', 'local');
        $document = $this->storedDocument($oldPath);

        $this->actingAs($this->manager())
            ->put(route('admin.documents.update', $document), $this->payload([
                'file' => UploadedFile::fake()->create('new.pdf', 200, 'application/pdf'),
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $document->refresh();
        $this->assertNotSame($oldPath, $document->file_path);
        $this->assertStringContainsString('documents/', $document->file_path);
        Storage::disk('local')->assertExists($document->file_path);
        Storage::disk('local')->assertMissing($oldPath);
        $this->assertSame(2, $document->version, 'A replaced file bumps the document version.');
    }

    public function test_failed_replacement_preserves_the_existing_file(): void
    {
        Storage::fake('local');
        $oldPath = UploadedFile::fake()->create('old.pdf')->store('documents', 'local');
        $document = $this->storedDocument($oldPath);

        $this->actingAs($this->manager())
            ->put(route('admin.documents.update', $document), $this->payload([
                'file' => UploadedFile::fake()->create('bad.xyz', 5, 'application/octet-stream'),
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('file');

        $document->refresh();
        $this->assertSame($oldPath, $document->file_path, 'An invalid replacement must never delete the existing file.');
        $this->assertSame(1, $document->version);
        Storage::disk('local')->assertExists($oldPath);
    }

    public function test_unauthorized_update_is_forbidden(): void
    {
        Storage::fake('local');
        $oldPath = UploadedFile::fake()->create('old.pdf')->store('documents', 'local');
        $document = $this->storedDocument($oldPath);

        $teacher = User::factory()->create();
        UserRole::create(['user_id' => $teacher->id, 'role' => 'teacher']);

        $this->actingAs($teacher)
            ->put(route('admin.documents.update', $document), $this->payload([
                'file' => UploadedFile::fake()->create('new.pdf', 10, 'application/pdf'),
            ]))
            ->assertForbidden();

        $document->refresh();
        $this->assertSame($oldPath, $document->file_path);
        Storage::disk('local')->assertExists($oldPath);
    }
}
