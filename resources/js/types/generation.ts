/**
 * "Generate with AI" on Lessons & Content (GEN-01, GEN-03, GEN-04, PERF-04;
 * spec 0004). The shape App\Services\Content\LessonGenerator::present()
 * returns, polled until `state` is `done` or `failed`.
 */
export type ContentGenerationType = 'lesson' | 'course' | 'image';

export type ContentGenerationState =
    | 'queued'
    | 'outline'
    | 'writing'
    | 'images'
    | 'audio'
    | 'done'
    | 'failed';

export type ContentGenerationMedia = {
    id: number;
    url: string;
    thumbUrl: string;
    alt: string;
    label: string;
};

export type ContentGeneration = {
    id: number;
    type: ContentGenerationType;
    status: 'pending' | 'running' | 'done' | 'failed';
    state: ContentGenerationState;
    prompt: string;
    failedReason: string | null;
    lessons: { done: number; total: number };
    images: { done: number; failed: number; total: number };
    audio: { done: number; failed: number; total: number };
    courseId: number | null;
    lessonIds: number[];
    openUrl: string | null;
    media: ContentGenerationMedia | null;
};

export type ContentGenerationResponse = {
    generation: ContentGeneration;
};

export type ContentGenerationCourseOption = {
    value: string;
    label: string;
    status: 'draft' | 'published';
};

export type ContentGenerationLevel = 'beginner' | 'intermediate' | 'advanced';
