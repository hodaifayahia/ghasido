<?php

namespace App\Http\Controllers\Learn;

use App\Http\Controllers\Controller;
use App\Http\Requests\Learn\StoreRecordingRequest;
use App\Models\PronunciationAttempt;
use App\Models\User;
use App\Models\VoiceRecording;
use App\Services\Learning\RecordingStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Date;

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

        // Every upload stays on the private disk for good (DATA-10), so one
        // learner's uploads are capped per day, beside the route throttle.
        // Pronunciation checks have their own cap and are not counted here
        // (SEC-04; security audit 2026-11-08).
        $cap = (int) config('guesvia.recordings.daily_uploads_per_employee', 200);
        $today = VoiceRecording::query()
            ->where('user_id', $user->id)
            ->where('created_at', '>=', Date::now()->startOfDay())
            ->whereNotIn('id', PronunciationAttempt::query()
                ->where('user_id', $user->id)
                ->whereNotNull('voice_recording_id')
                ->select('voice_recording_id'))
            ->count();

        if ($today >= $cap) {
            return response()->json([
                'message' => __('You have recorded a lot today. Recording opens again tomorrow.'),
            ], 429);
        }

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
