<?php

namespace Tests\Feature\Admin\Lessons;

use App\Enums\MediaKind;
use App\Enums\MediaLibrary;
use App\Models\AuditLog;
use App\Models\MediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Uploads: MIME + size + alt text validation, UUID names, GD resize and
 * the thumb variant (MED-01, MED-02, MED-07, SEC-04, PERF-01).
 */
class MediaUploadTest extends TestCase
{
    use BuildsContentFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildContent();
        Storage::fake('public');
    }

    public function test_a_large_jpeg_is_stored_under_a_uuid_resized_to_1600_with_a_thumb()
    {
        $this->actingAs($this->owner)
            ->post(route('media.store'), [
                'file' => UploadedFile::fake()->image('Front Desk.jpg', 2400, 1200),
                'alt_text' => 'The front desk at dawn',
                'category' => 'reception',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $asset = MediaAsset::query()->latest('id')->firstOrFail();

        $this->assertSame(MediaKind::Image, $asset->kind);
        $this->assertSame(MediaLibrary::MyImages, $asset->library);
        $this->assertSame('Front Desk.jpg', $asset->original_name);
        $this->assertSame('Front Desk', $asset->label);
        $this->assertSame('The front desk at dawn', $asset->alt_text);
        $this->assertSame(1600, $asset->width);
        $this->assertSame(800, $asset->height);
        $this->assertMatchesRegularExpression('#^content/image/\d{4}/\d{2}/[0-9a-f-]{36}\.jpg$#', $asset->path);
        $this->assertSame($this->owner->id, $asset->uploaded_by);
        Storage::disk('public')->assertExists($asset->path);
        Storage::disk('public')->assertExists($asset->variants['thumb'] ?? '');
        $this->assertSame(1, AuditLog::query()->where('action', 'media.uploaded')->count());
    }

    public function test_type_size_and_alt_text_are_validated()
    {
        $this->actingAs($this->owner)
            ->from(route('lessons-content'))
            ->post(route('media.store'), ['file' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf')])
            ->assertSessionHasErrors(['file', 'alt_text']);

        $this->actingAs($this->owner)
            ->from(route('lessons-content'))
            ->post(route('media.store'), [
                'file' => UploadedFile::fake()->image('huge.jpg')->size(6 * 1024),
                'alt_text' => 'Too big',
            ])
            ->assertSessionHasErrors(['file']);

        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_a_json_upload_returns_the_picker_row()
    {
        $this->actingAs($this->owner)
            ->postJson(route('media.store'), [
                'file' => UploadedFile::fake()->image('bell.png', 640, 480),
                'alt_text' => 'A brass bell',
                'library' => 'guesvia_library',
            ])
            ->assertCreated()
            ->assertJsonPath('image.alt', 'A brass bell')
            ->assertJsonPath('image.label', 'bell');
    }
}
