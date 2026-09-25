<?php

namespace App\Http\Controllers\Admin\Lessons;

use App\Enums\AudioSpeed;
use App\Enums\MediaKind;
use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\MediaAsset;
use App\Services\Audio\AudioLibrary;
use App\Services\Audio\PlayableTextCollector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Block audio (CTRL-05, TTS-01..03, TTS-06; spec 0003 B.4, B.10). A block
 * editor lists the playable sentences of a block and, for each, generates the
 * normal and slow clips once, or replaces one with an uploaded file. Audio is
 * never synthesised at play time; it is generated here and served from the
 * stored file.
 */
class AudioController extends Controller
{
    /**
     * Queue the normal and slow clips for every sentence still missing or
     * failed (TTS-01, TTS-02).
     */
    public function generate(Request $request, AudioLibrary $audio): RedirectResponse
    {
        Gate::authorize('create', MediaAsset::class);

        $validated = $request->validate([
            'texts' => ['required', 'array', 'min:1'],
            'texts.*' => ['required', 'string', 'max:500'],
        ]);

        /** @var list<string> $texts */
        $texts = $validated['texts'];

        foreach ($texts as $text) {
            if (trim($text) !== '') {
                $audio->ensureBoth($text);
            }
        }

        Inertia::flash('toast', ['type' => 'info', 'message' => __('Audio is being generated.')]);

        return back();
    }

    /**
     * Queue normal and slow audio for every playable text in every lesson.
     * Existing clips for the selected voice are reused; changing the saved
     * voice creates the new voice's stored clips (TTS-01..03, CMS-06).
     */
    public function generateAll(AudioLibrary $audio, PlayableTextCollector $playable): RedirectResponse
    {
        Gate::authorize('generateAllAudio', Lesson::class);

        $inventory = $playable->forAllLessons();

        foreach ($inventory['texts'] as $text) {
            $audio->ensureBoth($text);
        }

        Inertia::flash('toast', [
            'type' => 'info',
            'message' => __('Queued normal and slow audio for :texts texts across :lessons lessons.', [
                'texts' => count($inventory['texts']),
                'lessons' => $inventory['lessons'],
            ]),
        ]);

        return back();
    }

    /**
     * Replace one clip with an uploaded audio file (TTS-03).
     */
    public function replace(Request $request, AudioLibrary $audio): RedirectResponse
    {
        Gate::authorize('create', MediaAsset::class);

        $validated = $request->validate([
            'text' => ['required', 'string', 'max:500'],
            'speed' => ['required', Rule::enum(AudioSpeed::class)],
            'media_id' => ['required', 'integer', Rule::exists('media_assets', 'id')],
        ]);

        $media = MediaAsset::query()
            ->where('kind', MediaKind::Audio)
            ->findOrFail((int) $validated['media_id']);

        $audio->attachUpload(
            (string) $validated['text'],
            AudioSpeed::from((string) $validated['speed']),
            $media,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('The audio clip was replaced.')]);

        return back();
    }
}
