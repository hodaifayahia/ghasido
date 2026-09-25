<?php

namespace App\Services\Learning;

use App\Enums\MediaKind;
use App\Enums\MediaLibrary;
use App\Models\AuditLog;
use App\Models\MediaAsset;
use App\Models\User;
use App\Models\VoiceRecording;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Stores a learner's voice recording (TEST-07, DATA-02, RESP-05, PRIV-04,
 * SEC-04; spec 0003 Part E, spec 0006 §7).
 *
 * The file goes on the private disk under a UUID name, never a public URL;
 * the `media_assets` row makes it playable and downloadable from the admin
 * panel through `media.show`, and the `voice_recordings` row says what it
 * was recorded for. Shared by the plain recording upload and the
 * pronunciation check.
 */
class RecordingStore
{
    public function store(User $user, UploadedFile $file, Model $recordable, ?int $durationMs): VoiceRecording
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: ($file->guessExtension() ?? 'webm'));
        $name = Str::uuid().'.'.$extension;

        $path = Storage::disk(MediaAsset::DISK_LOCAL)->putFileAs('recordings/'.$user->id, $file, $name);

        if ($path === false) {
            throw new RuntimeException('The recording could not be stored.');
        }

        return DB::transaction(function () use ($user, $file, $path, $recordable, $durationMs): VoiceRecording {
            $asset = MediaAsset::query()->create([
                'disk' => MediaAsset::DISK_LOCAL,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType() ?? $file->getClientMimeType(),
                'kind' => MediaKind::Audio,
                'alt_text' => null,
                'duration_ms' => $durationMs,
                'size_bytes' => $file->getSize(),
                'uploaded_by' => $user->id,
                'hotel_id' => $user->hotel_id,
                'library' => MediaLibrary::Recordings,
            ]);

            $recording = VoiceRecording::query()->create([
                'user_id' => $user->id,
                'recordable_type' => $recordable->getMorphClass(),
                'recordable_id' => $recordable->getKey(),
                'media_asset_id' => $asset->id,
                'duration_ms' => $durationMs,
            ]);

            AuditLog::record($recording, 'recording.uploaded', ['media_asset_id' => $asset->id]);

            return $recording;
        });
    }
}
