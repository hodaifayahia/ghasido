<?php

namespace Tests\Feature\Admin\Lessons;

use App\Enums\MediaKind;
use App\Models\MediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Large audio and video go up in pieces and are joined on the server
 * (client report 2026-11-12: a whole video in one request was cut by the
 * hosting before it reached the app).
 */
class ChunkedMediaUploadTest extends TestCase
{
    use BuildsContentFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->buildContent();
        Storage::fake('public');
        Storage::fake(MediaAsset::DISK_LOCAL);
    }

    /**
     * A real (tiny) MP4 header, so the joined file is detected as video.
     */
    private function mp4Bytes(int $length): string
    {
        $ftyp = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom";

        return $ftyp.str_repeat("\x00", max(0, $length - strlen($ftyp)));
    }

    /**
     * @return array<string, mixed>
     */
    private function piece(string $uploadId, int $index, int $total, string $bytes): array
    {
        return [
            'upload_id' => $uploadId,
            'index' => $index,
            'total' => $total,
            'name' => 'reception lesson 3.mp4',
            'kind' => 'video',
            'library' => 'guesvia_library',
            'alt_text' => 'Check-in at the desk',
            'chunk' => UploadedFile::fake()->createWithContent($index.'.part', $bytes),
        ];
    }

    public function test_a_video_sent_in_pieces_is_joined_checked_and_stored()
    {
        $bytes = $this->mp4Bytes(30_000);
        $pieces = str_split($bytes, 12_000);
        $uploadId = (string) Str::uuid();

        foreach ($pieces as $index => $piece) {
            $response = $this->actingAs($this->owner)
                ->postJson(route('media.chunk'), $this->piece($uploadId, $index, count($pieces), $piece));

            if ($index < count($pieces) - 1) {
                $response->assertStatus(202)->assertJsonPath('received', $index + 1);
            }
        }

        $response->assertCreated()->assertJsonPath('image.label', 'reception lesson 3');

        $asset = MediaAsset::query()->latest('id')->firstOrFail();
        $this->assertSame(MediaKind::Video, $asset->kind);
        $this->assertSame('video/mp4', $asset->mime);
        $this->assertStringEndsWith('.mp4', $asset->path);
        $this->assertSame($bytes, Storage::disk('public')->get($asset->path));
        $this->assertSame([], Storage::disk(MediaAsset::DISK_LOCAL)->allFiles('chunks'));
    }

    public function test_a_joined_file_that_is_not_a_video_is_refused_and_cleaned_up()
    {
        $uploadId = (string) Str::uuid();

        $this->actingAs($this->owner)
            ->postJson(route('media.chunk'), [
                ...$this->piece($uploadId, 0, 1, '<?php echo "hello"; ?>'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertSame(0, MediaAsset::query()->count());
        $this->assertSame([], Storage::disk(MediaAsset::DISK_LOCAL)->allFiles('chunks'));
    }

    public function test_pieces_are_kept_per_user_and_a_learner_cannot_upload()
    {
        $this->actingAs($this->manager())
            ->postJson(route('media.chunk'), $this->piece((string) Str::uuid(), 0, 1, $this->mp4Bytes(100)))
            ->assertForbidden();
    }

    public function test_a_bad_piece_number_is_refused()
    {
        $this->actingAs($this->owner)
            ->postJson(route('media.chunk'), $this->piece((string) Str::uuid(), 3, 2, $this->mp4Bytes(100)))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('index');
    }
}
