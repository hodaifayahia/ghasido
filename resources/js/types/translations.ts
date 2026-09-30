/**
 * Show Meaning translations as the admin manages them (TranslationsController,
 * user request 2026-09-26).
 */
export type TranslationState =
    | 'missing'
    | 'drafting'
    | 'failed'
    | 'ai'
    | 'manual';

export type TranslationRow = {
    id: number | null;
    text: string;
    /** The meaning in the language being edited. */
    translation: string | null;
    /** The same, for the builders' Arabic field button. */
    arabic: string | null;
    state: TranslationState;
    updatedAt: string | null;
};

export type TranslationScope = {
    kind: 'lesson' | 'test' | 'course';
    id: number;
    title: string;
};

export type TranslationFilters = {
    /** `all`, `missing`, `ai` or `manual`. */
    state: string;
    search: string;
    /** The helper language being edited (client request 2026-09-30). */
    language?: string;
};

export type TranslationLanguage = { code: string; name: string; dir: string };

export type TranslationOption = { value: string; label: string };

export type TranslationOptions = {
    lessons: TranslationOption[];
    tests: TranslationOption[];
    courses: TranslationOption[];
};
