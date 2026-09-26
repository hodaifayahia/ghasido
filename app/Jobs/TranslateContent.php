<?php

namespace App\Jobs;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\Test;
use App\Models\User;
use App\Services\Meaning\MeaningTexts;
use App\Services\Meaning\MeaningTranslations;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * After an admin saves a lesson, test or course: queue one AI draft for each
 * of its English texts that has no translation yet (user request
 * 2026-09-26). Unique per piece of content, so a burst of saves while the
 * admin edits collapses into one pass over the latest text.
 */
class TranslateContent implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 120;

    /**
     * @param  'lesson'|'test'|'course'  $kind
     */
    public function __construct(
        public readonly string $kind,
        public readonly int $contentId,
        public readonly ?int $userId = null,
    ) {}

    public function uniqueId(): string
    {
        return $this->kind.':'.$this->contentId;
    }

    public function handle(MeaningTranslations $translations): void
    {
        $texts = match ($this->kind) {
            'lesson' => ($lesson = Lesson::query()->find($this->contentId)) !== null ? MeaningTexts::forLesson($lesson) : [],
            'test' => ($test = Test::query()->withoutGlobalScopes()->find($this->contentId)) !== null ? MeaningTexts::forTest($test) : [],
            'course' => ($course = Course::query()->find($this->contentId)) !== null ? MeaningTexts::forCourse($course) : [],
        };

        $translations->queue($texts, $this->userId !== null ? User::query()->find($this->userId) : null);
    }

    /**
     * Queue a pass for this content when automatic drafts are on.
     *
     * @param  'lesson'|'test'|'course'  $kind
     */
    public static function afterSave(string $kind, ?int $id): void
    {
        if ($id === null || ! MeaningTranslations::autoTranslate()) {
            return;
        }

        $user = auth('web')->user();

        self::dispatch($kind, $id, $user instanceof User ? $user->id : null)->delay(now()->addSeconds(15));
    }
}
