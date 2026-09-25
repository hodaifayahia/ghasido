<?php

namespace App\Services\Pronunciation;

use App\Models\Lesson;
use App\Services\Audio\AudioLibrary;
use App\Services\Audio\PlayableTextCollector;
use App\Services\Tts\TtsSettings;

/**
 * Prepares a lesson for listening and speaking in its accent (spec 0006
 * §4): normal and slow audio for every playable text in the accent's voice,
 * and a pronunciation guide for every text a learner can be asked to say.
 *
 * Idempotent and cheap to call again: clips and guides are found or created
 * by their keys and only new or failed ones are queued. Called when a
 * lesson's accent changes, from "Generate audio" in the builder, from the
 * all-lessons generation and after AI lesson generation.
 */
class LessonSpeech
{
    public function __construct(
        private readonly AudioLibrary $audio,
        private readonly PlayableTextCollector $playable,
        private readonly PronunciationTargets $targets,
        private readonly PronunciationGuides $guides,
    ) {}

    /**
     * @return array{audio: int, guides: int}
     */
    public function prepare(Lesson $lesson, ?TtsSettings $settings = null): array
    {
        $settings ??= app(TtsSettings::class);
        $texts = $this->playable->forLesson($lesson);

        // Audio in the lesson's accent voice (the platform voice when it has
        // none); guides in the accent the learner is judged in.
        foreach ($texts as $text) {
            $this->audio->ensureBoth($text, $settings, $lesson->accent);
        }

        $guides = $this->guides->ensureMany($this->targets->speakableTexts($lesson), $lesson->speakingAccent());

        return ['audio' => count($texts), 'guides' => $guides];
    }

    /**
     * Guides only, for the texts of one lesson (the builder's "Generate
     * audio" already queued the clips it was given).
     */
    public function prepareGuides(Lesson $lesson): int
    {
        return $this->guides->ensureMany($this->targets->speakableTexts($lesson), $lesson->speakingAccent());
    }
}
