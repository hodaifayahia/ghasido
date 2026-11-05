import type { CourseTone, EssentialsIcon } from './learning';

/*
 * The English Essentials studio (client request 2026-11-05): props of
 * admin/EnglishEssentials, built by App\Services\Essentials\EssentialsStudio.
 */
export type EssentialsStudioStats = {
    topics: number;
    visibleTopics: number;
    lessons: number;
    publishedLessons: number;
    learners: number;
    helpLinks: number;
};

export type EssentialsStudioTopic = {
    id: number;
    title: string;
    description: string | null;
    icon: EssentialsIcon;
    tone: CourseTone;
    visible: boolean;
    /** Department ids that see it; empty = every department. */
    audience: number[];
    audienceLabels: string[];
    lessonsTotal: number;
    lessonsPublished: number;
    learners: number;
    shared: boolean;
    updateUrl: string;
    destroyUrl: string;
    addLessonUrl: string;
    reorderUrl: string;
};

export type EssentialsStudioStep = {
    type: string;
    label: string;
};

export type EssentialsStudioLesson = {
    id: number;
    title: string;
    published: boolean;
    estimatedMinutes: number | null;
    steps: EssentialsStudioStep[];
    /** Department lesson steps whose "Learn it now" link opens it. */
    helpLinks: number;
    learners: number;
    editUrl: string;
    previewUrl: string;
    publishUrl: string;
    destroyUrl: string;
};

export type EssentialsStudioTemplate = {
    value: string;
    label: string;
    description: string;
    minutes: number;
    steps: EssentialsStudioStep[];
};

export type EssentialsStudioOption = {
    value: number;
    label: string;
};

export type EssentialsStudioIconOption = {
    value: EssentialsIcon;
    label: string;
};
