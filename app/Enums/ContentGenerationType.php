<?php

namespace App\Enums;

/**
 * What one "Generate with AI" request produces (GEN-01; spec 0004).
 */
enum ContentGenerationType: string
{
    /** One lesson from one prompt. */
    case Lesson = 'lesson';

    /** An outline, then one lesson per outline row. */
    case Course = 'course';

    /** One image for a slot in the lesson editor (GEN-04 regenerate). */
    case Image = 'image';
}
