<?php

namespace App\Contracts;

/**
 * An AI-drafted course plan: a title, a description and one topic per lesson
 * (GEN-01, GEN-03; spec 0004). Each lesson topic is then written by its own
 * generateLesson() call, so one slow lesson never holds the others.
 */
final readonly class CourseOutline
{
    /**
     * @param  list<array{title: string, topic: string}>  $lessons
     */
    public function __construct(
        public string $title,
        public string $description,
        public array $lessons,
        public AiUsageInfo $usage,
    ) {}

    /**
     * @return array{title: string, description: string, lessons: list<array{title: string, topic: string}>}
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'lessons' => $this->lessons,
        ];
    }
}
