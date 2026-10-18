<?php

namespace App\Enums;

/**
 * Which part of My Lessons a course belongs to (client request 2026-10-18).
 *
 * `department` is the training of the learner's own department: it is the
 * "Training" stage of the journey, counts toward the Post-test and the
 * certificate (JOURNEY-04, JOURNEY-05). `essentials` is English Essentials:
 * beginner topics (letters, numbers, time, greetings, clarification) any
 * learner may open when they need them. Its progress is saved like any
 * lesson (PROG-01) but never counts toward the journey or the certificate.
 */
enum CourseTrack: string
{
    case Department = 'department';
    case Essentials = 'essentials';

    public function label(): string
    {
        return match ($this) {
            self::Department => __('Department training'),
            self::Essentials => __('English Essentials'),
        };
    }
}
