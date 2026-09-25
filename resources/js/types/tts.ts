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
};
