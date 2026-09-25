<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Http\Requests\Learn\StoreRecordingRequest;
use App\Models\User;
use App\Services\Learning\RecordingStore;
use Illuminate\Http\JsonResponse;

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
    public function store(StoreRecordingRequest $request, RecordingStore $recordings): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $recordable = $request->recordableClass()::query()->findOrFail($request->recordableId());

        $recording = $recordings->store($user, $request->audio(), $recordable, $request->durationMs());

        $asset = $recording->media()->firstOrFail();

        return response()->json([
            'id' => $asset->id,
            'recording_id' => $recording->id,
            'url' => $asset->url(),
            'duration_ms' => $recording->duration_ms,
        ], 201);
    }
}
