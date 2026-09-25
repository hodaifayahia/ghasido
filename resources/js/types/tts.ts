import type { Accent } from './pronunciation';

export type TtsVoice = {
    model: string;
    name: string;
    gender: string;
    accent: string;
    age: string;
    characteristics: string[];
    use_cases: string[];
    featured: boolean;
};

export type TtsFilters = {
    genders: string[];
    accents: string[];
    ages: string[];
    use_cases: string[];
    characteristics: string[];
};

export type TtsSettings = {
    provider: string;
    model: string;
    voice: string;
    expressivity: number;
    apiConfigured: boolean;
    voices: TtsVoice[];
    filters: TtsFilters;
    /** The lesson voice of each accent (spec 0006 §3). */
    britishVoice: string;
    americanVoice: string;
    /** The accent of a lesson that has none: the platform voice's. */
    defaultAccent: Accent;
};
