<?php

namespace App\Http\Controllers\Learn;

use App\Enums\MediaKind;
use App\Enums\MediaLibrary;
use App\Http\Controllers\Controller;
use App\Http\Requests\Learn\StoreRecordingRequest;
use App\Models\AuditLog;
use App\Models\MediaAsset;
use App\Models\User;
use App\Models\VoiceRecording;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores a learner's voice recording (TEST-07, DATA-02, RESP-05, PRIV-04,
 * SEC-04; spec 0003 Part E).
 *
 * The file goes on the private disk under a UUID name, never a public URL;
 * the `media_assets` row makes it playable and downloadable from the admin
 * panel through `media.show`, and the `voice_recordings` row says what it
 * was recorded for. The speaking answer then references the media id.
 */
class RecordingController extends Controller
{
    public function store(StoreRecordingRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $file = $request->audio();
        $extension = strtolower($file->getClientOriginalExtension() ?: ($file->guessExtension() ?? 'webm'));
        $directory = 'recordings/'.$user->id;
        $name = Str::uuid().'.'.$extension;

        $path = Storage::disk(MediaAsset::DISK_LOCAL)->putFileAs($directory, $file, $name);

        abort_if($path === false, 500, __('The recording could not be stored.'));

        $recording = DB::transaction(function () use ($user, $request, $file, $path): VoiceRecording {
            $asset = MediaAsset::query()->create([
                'disk' => MediaAsset::DISK_LOCAL,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType() ?? $file->getClientMimeType(),
                'kind' => MediaKind::Audio,
                'alt_text' => null,
                'duration_ms' => $request->durationMs(),
                'size_bytes' => $file->getSize(),
                'uploaded_by' => $user->id,
                'hotel_id' => $user->hotel_id,
                'library' => MediaLibrary::Recordings,
            ]);

            $recording = VoiceRecording::query()->create([
                'user_id' => $user->id,
                'recordable_type' => (new ($request->recordableClass()))->getMorphClass(),
                'recordable_id' => $request->recordableId(),
                'media_asset_id' => $asset->id,
                'duration_ms' => $request->durationMs(),
            ]);

            AuditLog::record($recording, 'recording.uploaded', ['media_asset_id' => $asset->id]);

            return $recording;
        });

        $asset = $recording->media()->firstOrFail();

        return response()->json([
            'id' => $asset->id,
            'recording_id' => $recording->id,
            'url' => $asset->url(),
            'duration_ms' => $recording->duration_ms,
        ], 201);
    }
}
